<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Application\App;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Security\Csrf;




abstract class BaseController
{
	public function __construct(protected App $app) {}

	protected function requireAdmin(): void
	{
		$this->requireAdminIp();
		if (empty($_SESSION['admin'])) {
			throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
		}
		// Admin IMAP (session = user_id) : revalide le compte à chaque appel -
		// un utilisateur rétrogradé, bloqué ou supprimé perd le panel aussitôt.
		// Avant session_write_close() pour que unset() persiste.
		if (is_int($_SESSION['admin'])) {
			$u = $this->app->users()->findById($_SESSION['admin']);
			if (!$u || !(int) $u['is_admin'] || (int) ($u['disabled'] ?? 0)) {
				unset($_SESSION['admin']);
				throw new \RuntimeException($this->t('errors.unauthenticated'), 401);
			}
		}
		// Libère le verrou du fichier de session : aucun handler admin
		// n'écrit $_SESSION après l'auth (login/logout n'en passent pas par ici).
		session_write_close();
	}

	protected function requireAdminIp(): void
	{
		$list = trim((string) $this->app->config()->get('admin_ip_allowlist', ''));
		if ($list !== ''
			&& !\MXWebMail\Security\IpMatch::inList($this->app->clientIp(), $list)) {
			throw new \RuntimeException($this->t('errors.admin_ip'), 403);
		}
	}

	protected function requireCsrf(): void
	{
		if (!Csrf::verifyRequest()) {
			throw new \RuntimeException($this->t('errors.csrf'), 403);
		}
	}

	protected function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}

	/** Enveloppe commune : RuntimeException du service > errors.failed + code HTTP. */
	protected function serviceApi(callable $fn): JsonResponse
	{
		try {
			return JsonResponse::ok($fn());
		} catch (\RuntimeException $e) {
			$s = $e->getCode();
			return JsonResponse::error(
				$this->t('errors.failed', ['msg' => $e->getMessage()]),
				'failed', ($s >= 400 && $s < 600) ? $s : 400);
		}
	}

	protected static function validate(string $key, mixed $value, string $rule): mixed
	{
		$fail = fn() => throw new \RuntimeException("Valeur invalide pour $key", 400);
		return match (true) {
			$rule === 'bool' => (bool) $value,
			$rule === 'str' => self::str($value, 255) ?? $fail(),
			$rule === 'key' => preg_match('/^[a-z0-9_\-]{1,32}$/', (string) $value) ? (string) $value : $fail(),
			$rule === 'port' => self::intRange($value, 1, 65535) ?? $fail(),
			$rule === 'enc' => in_array($value, ['ssl', 'tls', 'none'], true) ? $value : $fail(),
			$rule === 'csv_ip' => self::csvIp($value) ?? $fail(),
			$rule === 'csv_domain' => self::csvDomain($value) ?? $fail(),
			$rule === 'filename' => self::filename($value) ?? $fail(),
			$rule === 'tz' => self::timeZone($value) ?? $fail(),
			str_starts_with($rule, 'enum:') => in_array($value, explode(',', substr($rule, 5)), true) ? $value : $fail(),
			str_starts_with($rule, 'int:') => self::intRange($value, ...array_map('intval', array_slice(explode(':', $rule), 1))) ?? $fail(),
			default => $fail(),
		};
	}

	protected static function str(mixed $v, int $max): ?string
	{
		$s = trim((string) $v);
		return (strlen($s) <= $max && !preg_match('/[\r\n\x00-\x1F]/', $s)) ? $s : null;
	}

	protected static function intRange(mixed $v, int $min, int $max): ?int
	{
		$i = filter_var($v, FILTER_VALIDATE_INT);
		return ($i !== false && $i >= $min && $i <= $max) ? $i : null;
	}

	protected static function csvIp(mixed $v): ?string
	{
		$s = trim((string) $v);
		if ($s === '') return '';
		foreach (explode(',', $s) as $entry) {
			$entry = trim($entry);
			[$ip, $bits] = array_pad(explode('/', $entry, 2), 2, null);
			if (!filter_var($ip, FILTER_VALIDATE_IP)) return null;
			// Borne par famille : un /64 IPv4 serait validé mais jamais
			// appliqué par IpMatch::inCidr (bits > 32 > false systématique).
			$maxBits = str_contains($ip, ':') ? 128 : 32;
			if ($bits !== null && (!ctype_digit($bits) || (int) $bits > $maxBits)) return null;
		}
		return $s;
	}

	protected static function csvDomain(mixed $v): ?string
	{
		$s = trim((string) $v);
		if ($s === '') return '';
		foreach (explode(',', $s) as $d) {
			if (!preg_match('/^(\*\.)?[a-z0-9]([a-z0-9.\-]*[a-z0-9])?$/i', trim($d))) return null;
		}
		return $s;
	}

	protected static function filename(mixed $v): ?string
	{
		$s = trim((string) $v);
		return ($s !== '' && preg_match('/^[a-zA-Z0-9._\-]{1,64}$/', $s)) ? $s : null;
	}

	protected static function timeZone(mixed $v): ?string
	{
		$s = trim((string) $v);
		return ($s === '' || in_array($s, timezone_identifiers_list(), true)) ? $s : null;
	}

	protected const EDITABLE = [
		'app_name' => 'str', 'default_domain' => 'str', 'admin_path' => 'key',
		'language' => 'str',
		'maintenance' => 'bool', 'session_lifetime' => 'int:0:720',
		'remember_days' => 'int:1:365',
		'restrict_domains' => 'bool',
		'admin_user' => 'str',
		'login_max_attempts' => 'int:1:50', 'login_lock_seconds' => 'int:30:86400',
		'admin_ip_allowlist' => 'csv_ip', 'trusted_proxies' => 'csv_ip',
		'force_https' => 'bool', 'hsts' => 'bool',
		'cookie_samesite' => 'enum:Strict,Lax,None',
		'csp_extra' => 'str', 'secfetch_allow' => 'str',
		'image_proxy' => 'bool', 'image_proxy_allowlist' => 'csv_domain',
		'attachment_max_size' => 'int:1:100',
		'attachments_max_total' => 'int:1:1024',
		'contacts_enable' => 'bool', 'contacts_suggestions_limit' => 'int:1:100',
		'allow_additional_accounts' => 'bool', 'allow_additional_identities' => 'bool',
		'popup_identity' => 'bool', 'dangerous_actions' => 'bool',
		'auth_logging' => 'bool', 'auth_logging_filename' => 'filename',
		'auth_logging_format' => 'str',
		'debug' => 'bool',
	];

	protected const SECTIONS = [
		'ssl' => [
			'verify_certificate' => 'bool', 'allow_self_signed' => 'bool',
			'cafile' => 'str', 'capath' => 'str', 'local_cert' => 'str',
			'disable_compression' => 'bool', 'security_level' => 'int:0:5',
		],
		'logs' => ['enabled' => 'bool', 'level' => 'int:0:7',
			'syslog' => 'bool', 'time_zone' => 'tz'],
		'labs' => ['smtp_show_server_errors' => 'bool'],
		// repo (fallback dev), token et manifest_url volontairement absents :
		// config.php uniquement, jamais modifiables via l'API admin
		'update' => ['channel' => 'enum:stable,beta'],
		'autodetect' => ['enabled' => 'bool', 'use_ispdb' => 'bool', 'probe' => 'bool'],
		'http' => [
			'gzip' => 'bool', 'gzip_level' => 'int:1:9',
			'gzip_min_size' => 'int:0:1048576', 'etag' => 'bool',
			'assets_ttl' => 'int:0:31536000', 'favicon_ttl' => 'int:0:31536000',
			'proxy_ttl' => 'int:0:604800',
		],
		'cache' => [
			'enabled' => 'bool', 'domain_detect_ttl' => 'int:60:604800',
			'update_ttl' => 'int:60:86400', 'gc_hours' => 'int:0:720',
		],
		// 'user_defaults' n'est pas ici : ses règles vivent dans
		// App::userSettingRules() (partagées avec les écritures par
		// utilisateur) et sont injectées dans settingsSet().
	];
	/**
	 * Schéma des réglages utilisateur (table user_settings), clés plates.
	 * Source unique : la section "Valeurs par défaut" du panneau global
	 * préfixe ces champs en user_defaults.* ; la modale d'édition d'un
	 * compte les applique directement.
	 */
	protected function userSettingsSchema(): array
	{
		return [
			'messages_per_page' => ['label' => 'admin.f.user_defaults.messages_per_page', 'type' => 'integer'],
			'list_sort' => ['label' => 'admin.f.user_defaults.list_sort', 'type' => 'enum',
				'options' => [
					'REVERSE DATE' => 'app.sort.date_desc', 'DATE' => 'app.sort.date_asc',
					'FROM' => 'app.sort.from', 'TO' => 'app.sort.to',
					'SUBJECT' => 'app.sort.subject',
					'REVERSE SIZE' => 'app.sort.size_desc', 'SIZE' => 'app.sort.size_asc',
				]],
			'view_images' => ['label' => 'admin.f.user_defaults.view_images', 'type' => 'enum',
				'options' => ['ask' => 'settings.view_images.ask', 'match' => 'settings.view_images.match', 'always' => 'settings.view_images.always'],
				'help' => 'admin.f.user_defaults.view_images_help'],
			'msg_default_action' => ['label' => 'admin.f.user_defaults.msg_default_action', 'type' => 'enum',
				'options' => ['reply' => 'app.reply', 'reply_all' => 'app.reply_all']],
			'mark_read_delay' => ['label' => 'admin.f.user_defaults.mark_read_delay', 'type' => 'integer'],
			'autologout' => ['label' => 'admin.f.user_defaults.autologout', 'type' => 'integer'],
			'list_refresh' => ['label' => 'admin.f.user_defaults.list_refresh', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.list_refresh_help'],
			'notify_new_mail' => ['label' => 'admin.f.user_defaults.notify_new_mail', 'type' => 'bool',
				'help' => 'admin.f.user_defaults.notify_new_mail_help'],
			'contacts_autosave' => ['label' => 'admin.f.user_defaults.contacts_autosave', 'type' => 'bool',
				'help' => 'admin.f.user_defaults.contacts_autosave_help'],
			'folder_pane_width' => ['label' => 'admin.f.user_defaults.folder_pane_width', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.folder_pane_width_help'],
			'message_pane_width' => ['label' => 'admin.f.user_defaults.message_pane_width', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.message_pane_width_help'],
			'layout' => ['label' => 'admin.f.user_defaults.layout', 'type' => 'enum',
				'options' => ['horizontal' => 'settings.layout.horizontal', 'vertical' => 'settings.layout.vertical']],
			'message_pane_height' => ['label' => 'admin.f.user_defaults.message_pane_height', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.message_pane_height_help'],
			'compose_modal_width' => ['label' => 'admin.f.user_defaults.compose_modal_width', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.compose_modal_width_help'],
			'empty_trash_on_logout' => ['label' => 'admin.f.user_defaults.empty_trash_on_logout', 'type' => 'bool'],
			'empty_junk_on_logout' => ['label' => 'admin.f.user_defaults.empty_junk_on_logout', 'type' => 'bool'],
			'list_threads' => ['label' => 'admin.f.user_defaults.list_threads', 'type' => 'bool'],
			'after_delete' => ['label' => 'admin.f.user_defaults.after_delete', 'type' => 'enum',
				'options' => ['next' => 'settings.after_delete.next', 'prev' => 'settings.after_delete.prev',
					'close' => 'settings.after_delete.close'],
				'help' => 'admin.f.user_defaults.after_delete_help'],
			'keyboard_shortcuts' => ['label' => 'admin.f.user_defaults.keyboard_shortcuts', 'type' => 'bool',
				'help' => 'admin.f.user_defaults.keyboard_shortcuts_help'],
			'compose_modal_height' => ['label' => 'admin.f.user_defaults.compose_modal_height', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.compose_modal_height_help'],
			'identity_modal_width' => ['label' => 'admin.f.user_defaults.identity_modal_width', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.identity_modal_width_help'],
			'identity_modal_height' => ['label' => 'admin.f.user_defaults.identity_modal_height', 'type' => 'integer',
				'help' => 'admin.f.user_defaults.identity_modal_height_help'],
			'theme' => ['label' => 'settings.theme', 'type' => 'enum',
				'options' => $this->app->themes()->options()],
		];
	}
}
