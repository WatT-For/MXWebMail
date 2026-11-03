<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\Request;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Security\Wildcard;

/**
 * Étiquettes couleur sur les mails.
 * - app.data     : onglet Paramètres + slot dans la barre d'actions groupées
 * - message.meta : injecte colorTag/colorTagId/colorTagLabel par message
 * API : /api/plugin/color-tags/{state|save|tag}
 */
final class ColorTags extends PluginBase
{
	private const KEY_PALETTE = 'plugin.color-tags.palette';
	private const KEY_RULES   = 'plugin.color-tags.rules';
	private const KW_PREFIX   = 'mx-colortag-';
	private const FIELDS      = ['from', 'domain', 'subject'];
	private const ID_RE       = '/^[a-z0-9][a-z0-9-]{0,31}$/';
	private const COLOR_RE    = '/^#[0-9a-fA-F]{6}$/';
	private const MAX_PALETTE = 16;
	private const MAX_RULES   = 100;

	/** Palette par défaut - labels localisés via langs/defaults.<id>. */
	private const DEFAULTS = [
		'urgent'    => '#e53935',
		'important' => '#fb8c00',
		'work'      => '#1e88e5',
		'personal'  => '#43a047',
		'follow-up' => '#8e24aa',
	];

	private ?array $palette = null; // cache requête (resolve appelé par message)
	private ?array $rules   = null;

	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.data', function (array $data) {
			$data['pluginTabs'][] = [
				'id'     => 'color-tags',
				'label'  => 'plugin.color-tags.tab',
				'icon'   => 'fa-tag',
				'js'     => 'color-tags.js',
				'plugin' => 'color-tags',
			];
			$data['messageToolbar'][] = ['plugin' => 'color-tags', 'js' => 'color-tags-toolbar.js'];
			return $data;
		});

		$hooks->on('message.meta', function (array $meta, \MailSo\Mail\Message $msg) {
			$user = $this->app->currentUser();
			if ($user) {
				[$meta['colorTag'], $meta['colorTagId'], $meta['colorTagLabel']]
					= $this->resolve((int) $user['id'], $msg);
			}
			return $meta;
		});
	}

	// ─── API (POST /api/plugin/color-tags/<action>) ───

	public function apiState(Request $request, ?array $user): array
	{
		$uid = (int) $user['id'];
		return [
			'palette' => $this->palette($uid),
			'rules'   => $this->rules($uid),
			'manual'  => $this->app->mail()->supportsKeyword('INBOX', self::KW_PREFIX . 'x'),
		];
	}

	public function apiSave(Request $request, ?array $user): array
	{
		$uid = (int) $user['id'];
		$palette = $this->validatePalette((array) $request->input('palette', []));
		$rules   = $this->validateRules((array) $request->input('rules', []), $palette);
		// set() encode en JSON en interne
		$this->app->settings()->set($uid, self::KEY_PALETTE, $palette);
		$this->app->settings()->set($uid, self::KEY_RULES, $rules);
		$this->palette = $palette;
		$this->rules   = $rules;
		return ['palette' => $palette, 'rules' => $rules];
	}

	public function apiTag(Request $request, ?array $user): array
	{
		$uid    = (int) $user['id'];
		$folder = trim((string) $request->input('folder', ''));
		$uids   = array_values(array_filter(array_map('intval', (array) $request->input('uids', []))));
		$tag    = $request->input('tag');
		$tag    = $tag === null ? null : (string) $tag;

		if ($folder === '' || !$uids || ($tag !== null && !preg_match(self::ID_RE, $tag))) {
			throw new \RuntimeException($this->t('plugin.color-tags.error_params'), 400);
		}
		$mail = $this->app->mail();
		if ($tag !== null && !$mail->supportsKeyword($folder, self::KW_PREFIX . $tag)) {
			throw new \RuntimeException($this->t('plugin.color-tags.manual_unsupported'), 409);
		}
		// Retire les anciens tags : 1 STORE par id de palette, sans fetch IMAP.
		foreach ($this->palette($uid) as $entry) {
			$mail->flagsMany($folder, $uids, self::KW_PREFIX . $entry['id'], false);
		}
		if ($tag !== null) {
			$mail->flagsMany($folder, $uids, self::KW_PREFIX . $tag, true);
		}
		$entry = $tag !== null ? $this->paletteEntry($uid, $tag) : null;
		return ['tag' => $tag, 'color' => $entry['color'] ?? null, 'label' => $entry['label'] ?? null];
	}

	// ─── Résolution de la couleur d'un message ───

	/** @return array{0:?string,1:?string,2:?string} [hex, id, label] */
	private function resolve(int $uid, \MailSo\Mail\Message $msg): array
	{
		$d       = $msg->jsonSerialize();
		$flags   = $d['flags'] ?? [];          // déjà en minuscules (aFlagsLowerCase)
		$palette = $this->palette($uid);
		$id      = null;

		// 1. Tag manuel (keyword IMAP) - prioritaire sur les règles
		foreach ($palette as $entry) {
			if (in_array(self::KW_PREFIX . $entry['id'], $flags, true)) {
				$id = $entry['id'];
				break;
			}
		}

		// 2. Règles automatiques - ordre = priorité, premier match gagne
		if ($id === null) {
			$email = self::firstEmail($msg->From())?->GetEmail() ?? '';
			foreach ($this->rules($uid) as $rule) {
				$value = match ($rule['field']) {
					'from'    => $email,
					'domain'  => $email !== ''
						? \MailSo\Base\Utils::getEmailAddressDomain($email) : '',
					'subject' => (string) ($d['subject'] ?? ''),
					default   => '',
				};
				if ($value !== '' && Wildcard::match($value, (string) $rule['pattern'])) {
					$id = $rule['tag'];
					break;
				}
			}
		}

		$entry = $id !== null ? $this->paletteEntry($uid, $id) : null;
		return [$entry['color'] ?? null, $entry['id'] ?? null, $entry['label'] ?? null];
	}

	private function paletteEntry(int $uid, string $id): ?array
	{
		foreach ($this->palette($uid) as $entry) {
			if ($entry['id'] === $id) {
				return $entry;
			}
		}
		return null;
	}

	private function palette(int $uid): array
	{
		if ($this->palette === null) {
			$stored = $this->app->settings()->get($uid, self::KEY_PALETTE);
			if (is_array($stored) && $stored) {
				$this->palette = $stored;
			} else {
				$this->palette = [];
				foreach (self::DEFAULTS as $id => $color) {
					$this->palette[] = [
						'id'    => $id,
						'label' => $this->t('plugin.color-tags.defaults.' . $id),
						'color' => $color,
					];
				}
			}
		}
		return $this->palette;
	}

	private function rules(int $uid): array
	{
		if ($this->rules === null) {
			$stored = $this->app->settings()->get($uid, self::KEY_RULES);
			$this->rules = is_array($stored) ? $stored : [];
		}
		return $this->rules;
	}

	// ─── Validation ───

	private function validatePalette(array $in): array
	{
		if (count($in) > self::MAX_PALETTE) {
			throw new \RuntimeException($this->t('plugin.color-tags.error_save'), 400);
		}
		$out = [];
		$seen = [];
		foreach ($in as $row) {
			$id    = trim((string) ($row['id'] ?? ''));
			$label = trim((string) ($row['label'] ?? ''));
			$color = trim((string) ($row['color'] ?? ''));
			if (!preg_match(self::ID_RE, $id) || isset($seen[$id])
				|| $label === '' || mb_strlen($label) > 60
				|| !preg_match(self::COLOR_RE, $color)) {
				throw new \RuntimeException($this->t('plugin.color-tags.error_save'), 400);
			}
			$seen[$id] = true;
			$out[] = ['id' => $id, 'label' => $label, 'color' => strtolower($color)];
		}
		return $out;
	}

	private function validateRules(array $in, array $palette): array
	{
		if (count($in) > self::MAX_RULES) {
			throw new \RuntimeException($this->t('plugin.color-tags.error_save'), 400);
		}
		$ids = array_column($palette, 'id');
		$out = [];
		foreach ($in as $row) {
			$field   = (string) ($row['field'] ?? '');
			$pattern = trim((string) ($row['pattern'] ?? ''));
			$tag     = (string) ($row['tag'] ?? '');
			if (!in_array($field, self::FIELDS, true)
				|| $pattern === '' || mb_strlen($pattern) > 200
				|| !in_array($tag, $ids, true)) {
				throw new \RuntimeException($this->t('plugin.color-tags.error_save'), 400);
			}
			$out[] = ['field' => $field, 'pattern' => $pattern, 'tag' => $tag];
		}
		return $out;
	}

	private static function firstEmail(?\MailSo\Mime\EmailCollection $col): ?\MailSo\Mime\Email
	{
		if ($col && count($col)) {
			foreach ($col as $email) {
				return $email;
			}
		}
		return null;
	}
}
