<?php
declare(strict_types=1);

namespace MXWebMail\Controller;
use MXWebMail\Application\App;
use MXWebMail\Http\Request;
use MXWebMail\Locale\Translates;
use MXWebMail\Security\Csrf;
use MXWebMail\UserFacingException;




/**
 * Socle commun des contrôleurs HTTP : ctor injectant l'application,
 * vérification CSRF et raccourci de traduction. Les contrôleurs de pages
 * (PageController, SetupController) ont des contrats propres et n'en
 * héritent pas.
 */
abstract class BaseController
{
	use Translates;

	public function __construct(protected App $app) {}

	/** Réglages utilisateur + défauts globaux (config user_defaults) pour les clés absentes. */
	protected function userSettings(int $userId): array
	{
		$settings = $this->app->settings()->allFor($userId);
		foreach ($this->app->config()->section('user_defaults') as $key => $value) {
			$settings[$key] ??= $value;
		}
		// Template stocké devenu non utilisable (désactivé/supprimé) > défaut global
		if (!is_string($settings['theme'] ?? null)
			|| !$this->app->themes()->isSelectable($settings['theme'])) {
			$settings['theme'] = $this->app->themes()->defaultTheme();
		}
		// Les clés "plugin.*" sont internes (secrets chiffrés, état) -
		// jamais exposées au frontend via settings/appdata.
		return array_filter($settings,
			fn(string $k) => !str_starts_with($k, 'plugin.'),
			ARRAY_FILTER_USE_KEY);
	}

	protected function requireCsrf(): void
	{
		if (!Csrf::verifyRequest()) {
			throw new UserFacingException($this->t('errors.csrf'), 403, jsonCode: 'csrf');
		}
	}

	/**
	 * Fichier uploadé validé (multipart $key) : upload() obligatoire +
	 * taille bornée si $maxBytes, sinon UserFacingException. Retourne
	 * l'entrée $_FILES - partagé côté API et admin (branding, imports).
	 */
	protected function requireUpload(Request $request, string $key,
		?int $maxBytes = null): array
	{
		$f = $request->upload($key);
		if ($f === null) {
			throw new UserFacingException($this->t('errors.bad_upload'), jsonCode: 'bad_upload');
		}
		if ($maxBytes !== null && (int) ($f['size'] ?? 0) > $maxBytes) {
			throw new UserFacingException(
				$this->t('errors.file_too_big',
					['mb' => (int) ($maxBytes / 1048576)]), jsonCode: 'too_big');
		}
		return $f;
	}

	/**
	 * Échec de login - TOTP demandé ou refusé (partagé user/admin).
	 * Code stable 'totp_required' : le client affiche le champ TOTP sans
	 * analyser le message (localisé). Un code saisi mais faux compte comme
	 * tentative ratée ; la demande initiale (champ vide) non.
	 */
	protected function loginTotpError(Request $request, string $rateKey,
		string $logIdent): never
	{
		if (trim((string) $request->input('totp', '')) !== '') {
			$this->app->auth()->recordFailedAttempt($rateKey);
			$this->app->logAuth($logIdent, 'AUTH FAILED (totp)');
		}
		throw new UserFacingException($this->t('errors.totp_required'), 401, jsonCode: 'totp_required');
	}

	/**
	 * Échec de login générique (partagé user/admin) : enregistre la
	 * tentative, journalise et renvoie la réponse JSON. Un 429 = tentative
	 * déjà bloquée : ne pas l'enregistrer, sinon un attaquant prolonge le
	 * verrou indéfiniment (lockout glissant). Les erreurs métier gardent
	 * leur message : 401 (credentials), 403 (white-list), 429 (rate-limit).
	 */
	protected function loginError(\Throwable $e, string $rateKey,
		string $logIdent, string $fallbackKey): never
	{
		if ($e->getCode() !== 429) {
			$this->app->auth()->recordFailedAttempt($rateKey);
		}
		$this->app->logAuth($logIdent, 'AUTH FAILED (' . $e->getCode() . ')');
		$this->app->log('warning', 'Login échoué (' . $logIdent . '): ' . $e->getMessage());
		if (in_array($e->getCode(), [401, 403, 429], true)) {
			throw new UserFacingException($e->getMessage(), $e->getCode(), $e, 'auth');
		}
		throw new UserFacingException($this->t($fallbackKey), 401, $e, 'auth');
	}
}
