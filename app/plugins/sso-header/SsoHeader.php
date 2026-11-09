<?php
declare(strict_types=1);

namespace Plugin;

use MXWebMail\Http\JsonResponse;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;
use MXWebMail\Plugin\HookRegistry;
use MXWebMail\Plugin\PluginBase;
use MXWebMail\Security\IpMatch;
use MXWebMail\Security\Wildcard;

/**
 * SSO via proxy d'authentification (Authelia, authentik, oauth2-proxy…).
 * Le proxy pose un header de confiance (X-Forwarded-User, Remote-User) ;
 * quiami -> ticket a usage unique en session -> POST /api/login avec le
 * mot de passe sentinelle "sso-t:<ticket>" -> auth.provider echange le
 * ticket contre les credentials IMAP (master password).
 * FAIL-CLOSED : trusted_ips vide = plugin inerte ; le peer direct
 * (REMOTE_ADDR) est verifie, jamais l'IP forwardnee.
 * Modes master : "same" (passdb master=yes, le mdp maitre vaut mdp de
 * chaque user) ; "separate" (login user*master_user via before-connect).
 * Ne couvre pas /{admin_path} (compte admin local = login classique).
 */
final class SsoHeader extends PluginBase
{
	public function register(HookRegistry $hooks): void
	{
		$hooks->on('app.routes', function (\MXWebMail\Http\Router $router) {
			$router->get('/sso-header/whoami', [$this, 'whoami']);
		});

		$hooks->on('app.head', function (string $nonce, string $base): string {
			if ($this->app->isLoggedIn() || !$this->configured()) {
				return '';
			}
			$b = htmlspecialchars($base, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			$payload = json_encode([
				'hide_form' => !empty($this->config['hide_form']),
				'manual_label' => $this->t('plugin.sso-header.manual_login'),
			], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
			return "\n\t" . '<script nonce="' . $nonce . '">'
					. 'window.MXWM_SSOHEAD=' . $payload . ';</script>'
				. "\n\t" . '<script src="' . $b
				. '/assets/plugin/sso-header/sso-login.js?v='
				. rawurlencode($this->version()) . '" defer></script>';
		});

		$hooks->on('auth.provider', function ($email, $password, $domain) {
			$provided = is_string($password) && str_starts_with($password, 'sso-t:');
			if (!$provided) {
				unset($_SESSION['sso_header']);
				return null;
			}
			if (!$this->ssoAllowed($email)
				|| !hash_equals((string) ($_SESSION['sso_ticket'] ?? '~'),
					hash('sha256', substr($password, 6)))
				|| ($_SESSION['sso_email'] ?? '') !== $email) {
				throw new \RuntimeException(
					$this->t('plugin.sso-header.denied'), 403);
			}
			unset($_SESSION['sso_ticket'], $_SESSION['sso_email']);
			$_SESSION['sso_header'] = 1;
			return ['email' => $email,
				'password' => (string) ($this->config['master_pass'] ?? '')];
		});

		// Mode separate : login IMAP/SMTP/Sieve "user*master" pour la session
		// SSO primaire uniquement (jamais les comptes secondaires). Trois
		// on() litteraux : le check statique associe hooks declares/ecoutes.
		$master = function ($client, $settings) {
			if (!empty($_SESSION['sso_header']) && empty($_SESSION['acct_secondary'])
				&& ($mu = trim((string) ($this->config['master_user'] ?? ''))) !== '') {
				$settings->username .= '*' . $mu;
			}
		};
		$hooks->on('imap.before-connect', $master);
		$hooks->on('smtp.before-connect', $master);
		$hooks->on('sieve.before-connect', $master);
	}

	private function configured(): bool
	{
		return trim((string) ($this->config['trusted_ips'] ?? '')) !== ''
			&& trim((string) ($this->config['master_pass'] ?? '')) !== '';
	}

	/** Peer de confiance + header present et valide pour cet email. */
	private function ssoAllowed(string $email): bool
	{
		if (!$this->configured()
			|| !IpMatch::inList((string) ($_SERVER['REMOTE_ADDR'] ?? ''),
				(string) $this->config['trusted_ips'])
			|| $this->headerUser() !== $email) {
			return false;
		}
		$dom = trim((string) ($this->config['allowed_domains'] ?? ''));
		return $dom === '' || Wildcard::match(strtolower($email), $dom);
	}

	private function headerUser(): string
	{
		$h = trim((string) ($this->config['header'] ?? 'X-Forwarded-User')) ?: 'X-Forwarded-User';
		if (strcasecmp($h, 'REMOTE_USER') === 0) {
			return trim((string) ($_SERVER['REMOTE_USER'] ?? ''));
		}
		return trim((string) ($_SERVER['HTTP_' . strtoupper(strtr($h, '-', '_'))] ?? ''));
	}

	/** Route publique GET /sso-header/whoami : {email, ticket} ou {enabled:false}. */
	public function whoami(Request $request): Response
	{
		if (!$this->configured()
			|| !IpMatch::inList((string) ($_SERVER['REMOTE_ADDR'] ?? ''),
				(string) $this->config['trusted_ips'])) {
			return JsonResponse::ok(['enabled' => false]);
		}
		$email = $this->headerUser();
		$dom = trim((string) ($this->config['allowed_domains'] ?? ''));
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)
			|| ($dom !== '' && !Wildcard::match(strtolower($email), $dom))) {
			return JsonResponse::ok(['enabled' => false]);
		}
		$t = bin2hex(random_bytes(16));
		$_SESSION['sso_ticket'] = hash('sha256', $t);
		$_SESSION['sso_email'] = $email;
		session_write_close();
		return JsonResponse::ok(['enabled' => true, 'email' => $email, 'ticket' => $t]);
	}
}
