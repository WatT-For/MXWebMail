<?php
declare(strict_types=1);

namespace MXWebMail\Controller\Admin;
use MXWebMail\Config\ConfigWriter;
use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\UserFacingException;
use MXWebMail\Validation\Rule;




abstract class BaseController extends \MXWebMail\Controller\BaseController
{
	protected function requireAdmin(): void
	{
		$this->requireAdminIp();
		if (!$this->app->isAdminSession()) {
			throw new UserFacingException($this->t('errors.unauthenticated'), 401, jsonCode: 'auth');
		}
		// Admin IMAP (session = user_id) : revalide le compte à chaque appel -
		// un utilisateur rétrogradé, bloqué ou supprimé perd le panel aussitôt.
		// Avant session_write_close() pour que unset() persiste.
		if (is_int($_SESSION['admin'])) {
			$u = $this->app->users()->findById($_SESSION['admin']);
			if (!$u || !(int) $u['is_admin'] || (int) ($u['disabled'] ?? 0)) {
				unset($_SESSION['admin']);
				throw new UserFacingException($this->t('errors.unauthenticated'), 401, jsonCode: 'auth');
			}
		}
		// Libère le verrou du fichier de session : aucun handler admin
		// n'écrit $_SESSION après l'auth (login/logout n'en passent pas par ici).
		session_write_close();
	}

	/** Garde commune des endpoints d'ecriture : admin + CSRF en une ligne. */
	protected function requireAdminWrite(): void
	{
		$this->requireAdmin();
		$this->requireCsrf();
	}

	protected function requireAdminIp(): void
	{
		$list = trim((string) $this->app->config()->get('admin_ip_allowlist', ''));
		if ($list !== ''
			&& !\MXWebMail\Security\IpMatch::inList($this->app->clientIp(), $list)) {
			throw new UserFacingException($this->t('errors.admin_ip'), 403, jsonCode: 'forbidden');
		}
	}

	/**
	 * Nom de plugin du paramètre de route {name} : whitelist stricte,
	 * sinon UserFacingException 'validation'.
	 */
	protected function requirePluginName(Request $request): string
	{
		$name = (string) ($request->params['name'] ?? '');
		if (!preg_match('/^[a-z0-9\-]+$/i', $name)) {
			throw new UserFacingException(
				$this->t('errors.invalid_value', ['key' => 'name']), 400, jsonCode: 'validation');
		}
		return $name;
	}

	/** Nom de thème du paramètre de route {name}, ou UserFacingException 'validation'. */
	protected function requireThemeName(Request $request): string
	{
		$name = (string) ($request->params['name'] ?? '');
		if (!$this->app->themes()->exists($name)) {
			throw new UserFacingException(
				$this->t('errors.invalid_value', ['key' => 'name']), 400, jsonCode: 'validation');
		}
		return $name;
	}

	/**
	 * Écriture config.php factorisée : retourne le ConfigWriter utilisé
	 * (writable() utile à settingsSet), ou jette 'config_write' 500.
	 */
	protected function writeConfig(array $values): ConfigWriter
	{
		$writer = new ConfigWriter($this->app->rootDir());
		try {
			$writer->write($values, $this->app->config());
		} catch (\Throwable $e) {
			throw new UserFacingException(
				$this->t('errors.config_write_failed'), 500, $e, 'config_write');
		}
		return $writer;
	}

	/** Enveloppe commune : RuntimeException du service > errors.failed + code HTTP. */
	protected function serviceApi(callable $fn): JsonResponse
	{
		try {
			return JsonResponse::ok($fn());
		} catch (\RuntimeException $e) {
			$s = $e->getCode();
			throw new UserFacingException(
				$this->t('errors.failed', ['msg' => $e->getMessage()]),
				($s >= 400 && $s < 600) ? $s : 400, $e, 'failed');
		}
	}

	protected function validate(string $key, mixed $value, string $rule): mixed
	{
		[$ok, $v] = Rule::check($rule, $value);
		if (!$ok) {
			throw new UserFacingException(
				$this->t('errors.invalid_value', ['key' => $key]), 400, jsonCode: 'validation');
		}
		return $v;
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
		$schema = [];
		foreach ($this->app->userSettingDefs() as $key => $d) {
			$schema[$key] = [
				'label' => $d['label'],
				'type' => match (true) {
					str_starts_with($d['rule'], 'int') => 'integer',
					$d['rule'] === 'bool' => 'bool',
					default => 'enum',
				},
			] + array_intersect_key($d, ['options' => 1, 'help' => 1]);
		}
		$schema['theme']['options'] = $this->app->themes()->options();
		return $schema;
	}

}
