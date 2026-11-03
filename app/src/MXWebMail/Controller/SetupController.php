<?php
declare(strict_types=1);

namespace MXWebMail\Controller;

use MXWebMail\Application\App;
use MXWebMail\Http\Request;
use MXWebMail\Http\Response;

/**
 * Installeur web MXWebMail - wizard multi-étapes (état en session
 * "mxwm_setup"). Servi par App::run() tant que storage/INSTALLED est
 * absent ; une fois installé, /setup retombe sur le routing normal > 404.
 *
 * Autonome par conception : ne s'appuie ni sur config.php (peut être absent)
 * ni sur les services applicatifs - la config et la base sont créées par
 * doInstall() qui instancie un App frais après écriture de config.php.
 */
final class SetupController
{
	// Champs persistés en session à chaque étape (token/action exclus)
	private const STEP_FIELDS = [
		2 => ['app_name', 'admin_path', 'attachments_max_total'],
		3 => ['db_driver', 'db_host', 'db_port', 'db_name', 'db_user', 'db_pass'],
		4 => ['admin_user', 'admin_pass'], // admin_pass2 : vérifié, jamais stocké
	];
	private const STEP_TITLES = [1 => 'setup.prereq', 'setup.app', 'setup.db',
		'setup.admin', 'setup.review'];
	private const STEP_LAST = 5;

	// Limite de taille SMTP usuelle : au-delà, beaucoup de serveurs rejettent
	// le message (le base64 des PJ ajoute ~33 % au volume réel).
	private const RECOMMENDED_TOTAL_MB = 25;

	private string $root;
	private string $installDir;
	private string $lockFile;
	private string $configFile;
	private ?Request $request = null;
	private string $nonce = '';
	private array $example = [];
	private array $exDb = [];

	public function __construct(private App $app)
	{
		$this->root = $app->rootDir();
		$this->installDir = $app->installDir();
		$this->lockFile = $this->root . '/storage/INSTALLED';
		$this->configFile = $this->root . '/config/config.php';
	}

	public function handle(Request $request): Response
	{
		// Garde défensive : le dispatch a déjà exclu les instances installées,
		// mais le verrou a pu être posé entre-temps - avant tout session_start.
		if (is_file($this->lockFile)) {
			return Response::notFound();
		}

		// Slash final manquant : mod_dir ne joue plus (plus de dossier réel).
		// $request->path normalise le '/' final - il faut l'URI brute pour
		// distinguer /setup de /setup/ (une détection sur le path bouclerait).
		$uriPath = (string) parse_url(
			(string) ($request->server['REQUEST_URI'] ?? ''), PHP_URL_PATH);
		if (preg_match('~/setup$~', $uriPath)) {
			$q = (string) ($request->server['QUERY_STRING'] ?? '');
			return Response::redirect($uriPath . '/' . ($q !== '' ? "?$q" : ''));
		}

		$this->request = $request;
		$this->nonce = base64_encode(random_bytes(16));

		session_name('mxwm_setup');
		// Mêmes flags que la session applicative (App::startSession) -
		// Lax suffit : le wizard n'a pas de retour cross-site.
		session_set_cookie_params([
			'httponly' => true,
			'samesite' => 'Lax',
			'secure'   => $this->app->isHttps(),
			'path'     => '/',
		]);
		session_start();
		if (empty($_SESSION['token'])) {
			$_SESSION['token'] = bin2hex(random_bytes(16));
		}
		$token = (string) $_SESSION['token'];

		// ─── Valeurs par défaut issues de config.example.php ───
		$this->example = is_file($this->root . '/config/config.example.php')
			? (array) require $this->root . '/config/config.example.php'
			: [];

		// DSN d'exemple > champs "Base de données" du formulaire
		$this->exDb = ['driver' => 'sqlite', 'host' => 'localhost',
			'port' => '3306', 'name' => 'mxwebmail'];
		$exDsn = $this->ex('db_dsn', 'sqlite');
		if (str_starts_with($exDsn, 'mysql:')) {
			$this->exDb['driver'] = 'mysql';
			foreach (['host', 'port', 'dbname'] as $p) {
				if (preg_match('/\b' . $p . '=([^;]*)/', $exDsn, $m) && $m[1] !== '') {
					$this->exDb[$p === 'dbname' ? 'name' : $p] = $m[1];
				}
			}
		}

		$checks = $this->requirements();
		$blocking = (bool) array_filter($checks, fn($c) => !$c[1] && empty($c[3]));

		$_SESSION['data'] ??= [];
		$_SESSION['step'] ??= 1;
		$_SESSION['max_step'] ??= 1;

		// Navigation GET : uniquement vers une étape déjà atteinte
		if ($request->method !== 'POST'
			&& isset($request->query['step']) && is_numeric($request->query['step'])) {
			$_SESSION['step'] = max(1,
				min((int) $request->query['step'], $_SESSION['max_step']));
		}

		$errors = [];
		$done = false;
		$doneInfo = [];
		$manualConfig = null;
		$flash = $_SESSION['flash'] ?? null;
		unset($_SESSION['flash']);

		ob_start();

		// ─── Traitement du formulaire ───
		if ($request->method === 'POST') {
			if (!hash_equals($token, $this->post('token'))) {
				$errors[] = $this->t('setup.err_token');
			} else {
				$step = $_SESSION['step'];
				$action = $this->post('action');

				if ($action === 'prev' && $step > 1) {
					$this->mergeStep($step);
					$step--;
				} elseif ($action === 'next' && $step < self::STEP_LAST) {
					$errors = $this->validateStep($step, $blocking);
					if (!$errors) {
						$this->mergeStep($step);
						$step++;
					}
				} elseif ($action === 'install' && $step === self::STEP_LAST) {
					if (!$_SESSION['data']) {
						$errors[] = $this->t('setup.err_session');
						$step = 1;
					} elseif ($blocking) {
						$errors[] = $this->t('setup.err_prereq');
					} else {
						$r = $this->doInstall($_SESSION['data']);
						$errors = $r['errors'];
						$manualConfig = $r['manual'];
						if ($r['done']) {
							$done = true;
							$doneInfo = [$r['path'], $r['user'], $r['replaced'],
								$r['domain'], $r['presets']];
							session_destroy();
						}
					}
				}

				$_SESSION['step'] = max(1, min($step, self::STEP_LAST));
				$_SESSION['max_step'] = max($_SESSION['max_step'], $_SESSION['step']);
				// POST-Redirect-GET : pas de resoumission au rechargement
				if (!$errors && !$done && $manualConfig === null) {
					$target = '?step=' . $_SESSION['step'];
					ob_end_clean();
					return Response::redirect($target);
				}
			}
		}
		$step = $_SESSION['step'];

		// ─── Affichage ───
		if ($done) {
			$this->renderDone($doneInfo);
			return $this->page();
		}

		$this->pageTop($this->t('setup.installation'),
			$manualConfig === null ? $step : 0, $_SESSION['max_step']);

		foreach ($errors as $e) {
			echo '<div class="alert alert-err">', $this->h($e), '</div>';
		}
		if ($flash !== null) {
			echo '<div class="alert alert-info">', $this->h($flash), '</div>';
		}

		if ($manualConfig !== null) {
			echo '<div class="alert alert-err">',
				$this->h($this->t('setup.manual_config')), '</div>',
				'<pre>', $this->h($manualConfig), '</pre>',
				'<a class="btn" href="./">', $this->h($this->t('setup.next')), '</a>';
			$this->pageBottom();
			return $this->page();
		}

		echo '<form method="post">',
			'<input type="hidden" name="token" value="', $this->h($token), '">';
		switch ($step) {
			case 1: $this->stepChecks($checks, $blocking); break;
			case 2: $this->stepApp(); break;
			case 3: $this->stepDb(); break;
			case 4: $this->stepAdmin(); break;
			case 5: $this->stepReview(); break;
		}
		echo '</form>';
		$this->pageBottom();
		return $this->page();
	}

	/**
	 * Page de réponse : le HTML bufférisé + les en-têtes propres du wizard
	 * (CSP à nonce pour le script inline, sans passer par Headers::send -
	 * le dispatch en amont court-circuite les en-têtes applicatifs).
	 */
	private function page(): Response
	{
		return new Response(ob_get_clean(), 200, [
			'Content-Type' => 'text/html; charset=utf-8',
			'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline';"
				. " script-src 'nonce-{$this->nonce}'",
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options' => 'DENY',
			'Referrer-Policy' => 'no-referrer',
		]);
	}

	// ─── Helpers ───

	private function h(string $s): string
	{
		return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
	}

	private function t(string $key, array $params = []): string
	{
		return $this->app->locale()->t($key, $params);
	}

	private function post(string $k, string $d = ''): string
	{
		return trim((string) ($this->request->post[$k] ?? $d));
	}

	// Valeur affichée : POST (erreur de validation) > session > défaut exemple
	private function val(string $k, string $d = ''): string
	{
		return $this->h($this->post($k, (string) ($_SESSION['data'][$k] ?? $d)));
	}

	private function ex(string $k, string $d = ''): string
	{
		$val = $this->example[$k] ?? $d;
		return is_scalar($val) ? (string) $val : $d;
	}

	private function validDbHost(string $host): bool
	{
		return $host !== '' && preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host) === 1;
	}

	// ini en Mio ; illimité (post_max_size=0/-1) ou illisible > -1 (ignoré) ;
	// upload_max_filesize ≤ 0 = uploads impossibles > 0 (le champ planchera à 1)
	private function iniToMb(string $key): int
	{
		$raw = ini_get($key);
		if ($raw === false) {
			return -1;
		}
		$bytes = ini_parse_quantity($raw);
		if ($bytes > 0) {
			return intdiv((int) $bytes, 1048576);
		}
		return $key === 'upload_max_filesize' ? 0 : -1;
	}

	// Défaut du champ : plafond d'upload PHP le plus restrictif, borné à la
	// recommandation (min 1 - upload_max_filesize < 1 Mio donne 0).
	private function defaultAttachTotal(): int
	{
		$default = self::RECOMMENDED_TOTAL_MB;
		foreach (['upload_max_filesize', 'post_max_size'] as $key) {
			$mb = $this->iniToMb($key);
			if ($mb >= 0) {
				$default = min($default, $mb);
			}
		}
		return max(1, $default);
	}

	// Valeur courante du champ : POST > session > défaut calculé
	private function currentAttachTotal(): int
	{
		$v = $this->post('attachments_max_total');
		return $v !== '' ? (int) $v
			: (int) ($_SESSION['data']['attachments_max_total'] ?? $this->defaultAttachTotal());
	}

	// ─── Vérifications des prérequis ───
	// 4e élément : soft = avertissement non bloquant (ldap optionnel,
	// config/ non inscriptible > le config.php peut être créé à la main).
	private function requirements(): array
	{
		$checks = [];
		$root = $this->root;

		$checks[] = ['PHP >= 8.4', version_compare(PHP_VERSION, '8.4.0', '>='),
			$this->t('setup.req.php_detected', ['version' => PHP_VERSION]), false];

		foreach (['sodium', 'mbstring', 'intl', 'openssl', 'ctype', 'json',
			'iconv', 'zlib', 'xml', 'dom'] as $ext) {
			$checks[] = ["extension $ext", extension_loaded($ext), '', false];
		}
		$checks[] = [$this->t('setup.req.pdo'),
			extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'),
			$this->t('setup.req.pdo_note'), false];
		$checks[] = [$this->t('setup.req.ldap'), extension_loaded('ldap'),
			$this->t('setup.req.ldap_note'), true];
		$checks[] = ['app/src/bootstrap.php', is_file("$root/src/bootstrap.php"),
			$this->t('setup.req.missing'), false];
		$checks[] = ['app/src/Sabre (vCard)', is_dir("$root/src/Sabre/VObject"),
			$this->t('setup.req.missing'), false];
		$checks[] = ['assets vendor (Bootstrap…)',
			is_dir($this->installDir . '/public/assets/vendor/bootstrap'),
			$this->t('setup.req.missing'), false];
		$checks[] = [$this->t('setup.req.config_w'), is_writable("$root/config"),
			$this->t('setup.req.config_note'), true];
		$checks[] = [$this->t('setup.req.storage_w'), is_writable("$root/storage"),
			$this->t('setup.req.storage_note'), false];

		return $checks;
	}

	// ─── Rendus ───

	private function pageTop(string $title, int $step = 0, int $max = 0): void
	{
		$lang = $this->app->locale()->lang();
		echo '<!doctype html><html lang="', $this->h($lang), '"><head><meta charset="utf-8">',
			'<meta name="viewport" content="width=device-width, initial-scale=1">',
			"<title>MXWebMail - " . $this->h($title) . "</title>",
			'<style>',
			'body{font-family:system-ui,sans-serif;background:#f0f2f5;margin:0;padding:2rem}',
			'.card{max-width:760px;margin:0 auto;background:#fff;border-radius:.5rem;padding:2rem;box-shadow:0 2px 8px rgba(0,0,0,.08)}',
			'h1{font-size:1.4rem;margin-bottom:1.5rem}h2{font-size:1.05rem;margin:1.5rem 0 .75rem;border-bottom:1px solid #e5e5e5;padding-bottom:.4rem}',
			'label{display:block;font-size:.85rem;margin:.6rem 0 .2rem;color:#444}',
			'input,select{width:100%;padding:.45rem .6rem;border:1px solid #ccc;border-radius:.35rem;box-sizing:border-box}',
			'.row{display:flex;gap:1rem}.row>div{flex:1}',
			'.btn{display:inline-block;padding:.6rem 1.6rem;background:#0d6efd;color:#fff;border:0;border-radius:.35rem;font-size:1rem;cursor:pointer;text-decoration:none}',
			'.btn-secondary{background:#6c757d;order:-1;margin-right:auto}',
			'.ok{color:#1a7f37}.ko{color:#cf222e}.warn{color:#9a6700}',
			'li{margin:.2rem 0}.alert{padding:.7rem 1rem;border-radius:.35rem;margin:.8rem 0}',
			'.alert-err{background:#ffebe9;color:#82071e}.alert-info{background:#ddf4ff;color:#0550ae}',
			'.alert-warn{background:#fff8e6;color:#9a6700}',
			'code{background:#f6f8fa;padding:.1rem .35rem;border-radius:.25rem}',
			'pre{background:#f6f8fa;padding:1rem;border-radius:.35rem;overflow-x:auto;font-size:.8rem}',
			'.steps{display:flex;gap:.5rem;list-style:none;padding:0;margin:0 0 1.5rem}',
			'.steps li{flex:1;font-size:.75rem;color:#999;text-align:center;padding-top:.45rem;border-top:3px solid #e0e0e0}',
			'.steps li.current{color:#0d6efd;border-color:#0d6efd;font-weight:600}',
			'.steps li.done{color:#1a7f37;border-color:#1a7f37}',
			'.steps a{color:inherit;text-decoration:none}.steps a:hover{text-decoration:underline}',
			'.nav{display:flex;justify-content:flex-end;gap:1rem;margin-top:1.5rem}',
			'table.review{width:100%;border-collapse:collapse;font-size:.9rem;margin-top:.8rem}',
			'table.review td{padding:.4rem .5rem;border-bottom:1px solid #eee;vertical-align:top}',
			'table.review td:first-child{color:#666;width:38%}',
			'table.review td:last-child{text-align:right;white-space:nowrap}',
			'.btn[disabled]{opacity:.65;cursor:default}',
			'.busy a{pointer-events:none}',
			'.spin{display:inline-block;width:.9em;height:.9em;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;vertical-align:-.12em;margin-right:.4em;animation:spin .7s linear infinite}',
			// Grande variante du spinner pour le voile : le .spin bouton est pensé
			// pour fond bleu (bord blanc) - sur la boîte blanche il faut du bleu.
			'.spin-lg{width:2.2em;height:2.2em;border-width:3px;border-color:rgba(13,110,253,.25);border-top-color:#0d6efd;display:block;vertical-align:baseline;margin:0 auto .8rem}',
			'@keyframes spin{to{transform:rotate(360deg)}}',
			'@media (prefers-reduced-motion:reduce){.spin{animation-duration:2s}}',
			// Voile pleine page de l'install : la détection de domaine peut durer
			// ~2 min, un spinner confiné au bouton donne une page qui semble figée.
			'.veil{position:fixed;inset:0;background:rgba(240,242,245,.85);display:flex;align-items:center;justify-content:center;z-index:10}',
			'.veil-box{background:#fff;border-radius:.5rem;padding:2rem 3rem;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.15);max-width:min(90vw,26rem)}',
			'.veil-box p{margin:.3rem 0}.veil-hint{color:#666;font-size:.85rem}',
			'</style></head><body><div class="card">',
			"<h1>MXWebMail - " . $this->h($title) . "</h1>";

		if ($step > 0) {
			echo '<ol class="steps">';
			for ($i = 1; $i <= self::STEP_LAST; $i++) {
				$cls = $i === $step ? 'current' : ($i <= $max ? 'done' : '');
				$label = $this->h($this->t(self::STEP_TITLES[$i]));
				echo '<li class="', $cls, '">',
					$i !== $step && $i <= $max ? "<a href=\"?step=$i\">$label</a>" : $label,
					'</li>';
			}
			echo '</ol>';
		}
	}

	private function pageBottom(): void
	{
		echo '</div><script nonce="', $this->h($this->nonce), '">', <<<'JS'
document.addEventListener('submit', (e) => {
	const form = e.target;
	if (form.dataset.sent) {
		e.preventDefault();
		return;
	}
	form.dataset.sent = '1';
	const btn = e.submitter || form.querySelector('button[name="action"]');
	// Un bouton disabled est exclu de l'entry list : action=… est copié
	// en champ caché avant de désactiver, sinon le POST arrive sans.
	if (btn && btn.name) {
		const hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = btn.name;
		hidden.value = btn.value;
		form.append(hidden);
	}
	form.querySelectorAll('button').forEach((b) => { b.disabled = true; });
	document.querySelector('.card').classList.add('busy');
	if (btn) {
		const label = btn.dataset.busy || btn.textContent.trim();
		btn.textContent = '';
		const spin = document.createElement('span');
		spin.className = 'spin';
		btn.append(spin, ' ', label);
	}
	// data-busy n'existe que sur "Installer" : en plus du spinner bouton,
	// un voile bloquant - l'install peut durer ~2 min (détection de domaine).
	if (btn && btn.dataset.busy) {
		const veil = document.createElement('div');
		veil.className = 'veil';
		const box = document.createElement('div');
		box.className = 'veil-box';
		const big = document.createElement('span');
		big.className = 'spin spin-lg';
		const msg = document.createElement('p');
		msg.textContent = btn.dataset.busy;
		const hint = document.createElement('p');
		hint.className = 'veil-hint';
		hint.textContent = btn.dataset.busyHint || '';
		box.append(big, msg, hint);
		veil.append(box);
		document.body.append(veil);
	}
});
JS
			, '</script></body></html>';
	}

	// "Suivant"/"Installer" en premier dans le DOM (Entrée = avancer) ;
	// "Précédent" est replacé à gauche en CSS (.btn-secondary{order:-1}).
	private function stepNav(int $step): void
	{
		echo '<div class="nav">',
			'<button class="btn" name="action" value="',
			$step < self::STEP_LAST ? 'next">' . $this->h($this->t('setup.next'))
				: 'install" data-busy="' . $this->h($this->t('setup.installing'))
					. '" data-busy-hint="' . $this->h($this->t('setup.installing_hint')) . '">'
					. $this->h($this->t('setup.install')),
			'</button>';
		if ($step > 1) {
			echo '<button class="btn btn-secondary" name="action" value="prev" formnovalidate>',
				$this->h($this->t('setup.prev')), '</button>';
		}
		echo '</div>';
	}

	// Merge des champs de l'étape dans $_SESSION['data']
	private function mergeStep(int $step): void
	{
		foreach (self::STEP_FIELDS[$step] ?? [] as $f) {
			$v = $this->post($f);
			// admin_pass : vide > conserver la valeur en session ;
			// trop court > ignorer (la session ne contient que du validé)
			if ($f === 'admin_pass' && strlen($v) < 8) {
				continue;
			}
			$_SESSION['data'][$f] = $v;
		}
		if ($step === 2) {
			$_SESSION['data']['admin_path'] =
				preg_replace('/[^a-z0-9\-_]/i', '', $_SESSION['data']['admin_path'] ?? '') ?: 'admin';
		}
	}

	// Erreurs de l'étape (tableau vide = OK)
	private function validateStep(int $step, bool $blocking): array
	{
		$e = [];

		if ($step === 1) {
			if ($blocking) {
				$e[] = $this->t('setup.blocking');
			}
		} elseif ($step === 2) {
			$v = $this->post('attachments_max_total');
			if ($v === '' || !preg_match('/^\d+$/', $v) || (int) $v < 1) {
				$e[] = $this->t('setup.err_attach_total');
			}
		} elseif ($step === 3 && $this->post('db_driver') === 'mysql') {
			// Test de connexion seule - CREATE DATABASE uniquement à l'installation
			$host = $this->post('db_host', $this->exDb['host']);
			if (!$this->validDbHost($host)) {
				$e[] = $this->t('errors.invalid_value', ['key' => 'db_host']);
			} else {
				try {
					new \PDO('mysql:host=' . $host
						. ';port=' . (int) $this->post('db_port', $this->exDb['port']) . ';charset=utf8mb4',
						$this->post('db_user'), $this->post('db_pass'),
						[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
					$_SESSION['flash'] = $this->t('setup.db_ok');
				} catch (\Throwable $x) {
					$e[] = $this->t('setup.err_db', ['msg' => $x->getMessage()]);
				}
			}
		} elseif ($step === 4) {
			if ($this->post('admin_user') === '') {
				$e[] = $this->t('setup.err_admin_user');
			}
			$p = $this->post('admin_pass');
			if ($p !== '') {
				if (strlen($p) < 8) {
					$e[] = $this->t('setup.err_admin_pass');
				} elseif (!hash_equals($p, $this->post('admin_pass2'))) {
					$e[] = $this->t('setup.err_pass_match');
				}
			} elseif (strlen((string) ($_SESSION['data']['admin_pass'] ?? '')) < 8) {
				$e[] = $this->t('setup.err_admin_pass');
			}
		}

		return $e;
	}

	// Installation : construit la config depuis la session (pas $_POST),
	// écrit config.php, migre la base et pose le verrou.
	private function doInstall(array $d): array
	{
		$root = $this->root;
		$configFile = $this->configFile;
		$lockFile = $this->lockFile;
		// Un client qui part en cours de route ne doit pas laisser une
		// installation tronquée (config.php écrit sans verrou INSTALLED).
		ignore_user_abort(true);
		$errors = [];
		$manual = null;
		$replaced = false;
		$detectedDomain = null;
		$presetOk = [];
		$attachTotal = max(1,
			(int) ($d['attachments_max_total'] ?? $this->defaultAttachTotal()));

		// Aucun paramètre serveur en config : les domaines vivent en table
		// (admin > Domaines). Les défauts de résolution sont ceux de
		// config.example.php - source unique ('auto', restriction, détection).
		$config = [
			'app_name' => $d['app_name'] ?? $this->ex('app_name', 'MXWebMail'),
			'admin_path' => preg_replace('/[^a-z0-9\-_]/i', '', $d['admin_path'] ?? '') ?: 'admin',
			'admin_user' => $d['admin_user'] ?? '',
			'admin_password_hash' => password_hash($d['admin_pass'] ?? '', PASSWORD_DEFAULT),
			// Par fichier : borné au total choisi (au-delà, la limite serait
			// sans effet - l'upload applique déjà le min des deux).
			'attachment_max_size' => min(
				(int) $this->ex('attachment_max_size', '25'), $attachTotal),
			'attachments_max_total' => $attachTotal,
			'default_domain'   => $this->ex('default_domain', 'auto'),
			'restrict_domains' => (bool) ($this->example['restrict_domains'] ?? true),
			'autodetect'       => (array) ($this->example['autodetect']
				?? ['enabled' => false, 'use_ispdb' => false, 'probe' => false]),
		];

		if (($d['admin_user'] ?? '') === '' || ($d['admin_pass'] ?? '') === '') {
			$errors[] = $this->t('setup.err_admin_pass');
		}

		// Base de données
		if (!$errors && ($d['db_driver'] ?? 'sqlite') === 'mysql') {
			$host = (string) ($d['db_host'] ?? $this->exDb['host']);
			$port = (int) ($d['db_port'] ?? $this->exDb['port']);
			$dbname = preg_replace('/[^a-zA-Z0-9_]/', '', $d['db_name'] ?? $this->exDb['name']);
			if (!$this->validDbHost($host)) {
				$errors[] = $this->t('errors.invalid_value', ['key' => 'db_host']);
			} else {
				try {
					// Connexion sans base pour pouvoir la créer si besoin
					$pdo = new \PDO("mysql:host=$host;port=$port;charset=utf8mb4",
						$d['db_user'] ?? '', $d['db_pass'] ?? '',
						[\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
					$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
				} catch (\Throwable $e) {
					$errors[] = $this->t('setup.err_db', ['msg' => $e->getMessage()]);
				}
				$config['db_dsn'] = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
				$config['db_user'] = $d['db_user'] ?? '';
				$config['db_pass'] = $d['db_pass'] ?? '';
			}
		} else {
			$config['db_dsn'] = 'sqlite';
		}

		if (!$errors) {
			// Écriture du config.php. Un fichier existant est archivé en .bak
			// horodaté puis remplacé (relancer l'installeur = intention claire,
			// cf. notice "config_exists" du récapitulatif) - sauf si config/
			// n'est pas inscriptible : le fichier a alors été créé à la main et
			// est conservé tel quel.
			$php = "<?php\n// Généré par l'installeur le " . date('Y-m-d H:i:s') . "\nreturn "
				. var_export($config, true) . ";\n";
			if (is_writable("$root/config")) {
				$replaced = is_file($configFile);
				if ($replaced
					&& !@rename($configFile, "$root/config/config." . date('Ymd-His') . '.bak')) {
					$errors[] = $this->t('setup.err_write');
				}
				if (!$errors && !file_put_contents($configFile, $php)) {
					$errors[] = $this->t('setup.err_write');
				}
			} elseif (!is_file($configFile)) {
				$manual = $php;
			}
		}

		if (!$errors && $manual === null) {
			try {
				// Migrations sur la base configurée - Config/App frais :
				// $this->app est figé sur les défauts de l'exemple (config.php
				// n'existait pas encore à sa construction).
				$cfg = new \MXWebMail\Config\Config($root);
				$db = \MXWebMail\Repository\Database::connect($cfg, "$root/storage");
				$db->migrate();

				$app = null;
				try {
					$app = new App($root, $this->installDir);
				} catch (\Throwable) {}

				if ($app) {
					// Domaines publics pré-autorisés en comptes secondaires
					// (options.secondary_only + oauth) : hors réseau, avant la
					// détection - la partie déterministe d'abord. Une ligne
					// existante n'est jamais écrasée (l'admin a pu l'ajuster) ;
					// non bloquant, comme la détection.
					try {
						foreach (\MXWebMail\Service\OAuthService::PRESET_DOMAINS
								as $name => [$provider, $imap, $smtp, $sport, $senc]) {
							if (!$app->domains()->exists($name)) {
								$app->domains()->save([
									'name' => $name,
									'imap_host' => $imap, 'imap_port' => 993,
									'imap_encryption' => 'ssl',
									'smtp_host' => $smtp, 'smtp_port' => $sport,
									'smtp_encryption' => $senc,
									'active' => 1,
									'options' => ['secondary_only' => true,
										'oauth' => $provider],
								]);
								$app->log('info',
									"domaine secondaire \"$name\" pré-autorisé ($provider)");
							}
							$presetOk[] = $name;
						}
					} catch (\Throwable) {}

					// Domaine de l'hébergement : le vhost peut être un sous-domaine
					// du domaine mail - hôte puis parents (w.mxrelay.fr >
					// mxrelay.fr). Détection forcée (ISPDB + sondage, comme le
					// bouton "Détecter" de l'admin ; ignore le cache négatif).
					// Une preuve SRV/autoconfig/MX arrête au premier candidat ;
					// un succès par sondage seul n'est qu'un repli - un vhost
					// colocalisé avec l'IMAP n'est pas forcément le domaine mail,
					// le parent le plus haut qui répond est alors préféré. Un
					// échec ou un hôte non-domaine n'ajoute rien, non bloquant.
					set_time_limit(120); // plusieurs candidats × sondes ~3 s
					try {
						$dom = null;
						foreach (\MXWebMail\Service\DomainDetector::hostCandidates(
								(string) ($this->request->server['HTTP_HOST'] ?? '')) as $candidate) {
							if (!($r = $app->domainDetector()->detect($candidate, true, $source))) {
								continue;
							}
							$dom = $r;
							if ($source !== 'probe') {
								break;
							}
						}
						// Garde exists() : le vhost peut être un nom de preset
						// (hébergement sur gmail.com…) - l'INSERT violerait
						// l'UNIQUE et le domaine détecté serait perdu ici.
						if ($dom && !$app->domains()->exists($dom['name'])) {
							$app->domains()->save($dom);
							$detectedDomain = $dom['name'];
						}
					} catch (\Throwable) {}
				}

				file_put_contents($lockFile, date('c') . "\n");
			} catch (\Throwable $e) {
				$errors[] = $this->t('setup.err_init', ['msg' => $e->getMessage()]);
			}
		}

		return ['errors' => $errors, 'manual' => $manual, 'replaced' => $replaced,
			'path' => $config['admin_path'], 'user' => $config['admin_user'],
			'domain' => $detectedDomain, 'presets' => $presetOk,
			'done' => !$errors && $manual === null];
	}

	private function stepChecks(array $checks, bool $blocking): void
	{
		echo '<h2>', $this->h($this->t('setup.prereq')), '</h2><ul>';
		foreach ($checks as [$label, $ok, $note, $soft]) {
			$cls = $ok ? 'ok' : ($soft ? 'warn' : 'ko');
			echo '<li class="', $cls, '">', $ok ? '✓' : '✗', ' ', $this->h($label),
				$note ? ' <small>- ' . $this->h($note) . '</small>' : '', '</li>';
		}
		echo '</ul>';
		if (is_file($this->configFile)) {
			echo '<div class="alert alert-info">',
				$this->h($this->t('setup.config_exists')), '</div>';
		}
		if ($blocking) {
			echo '<div class="alert alert-err">', $this->h($this->t('setup.blocking')), '</div>';
		} else {
			$this->stepNav(1);
		}
	}

	private function stepApp(): void
	{
		echo '<h2>', $this->h($this->t('setup.app')), '</h2>',
			'<div class="row">',
			'<div><label>', $this->h($this->t('setup.app_name')), '</label><input name="app_name" value="', $this->val('app_name', $this->ex('app_name', 'MXWebMail')), '"></div>',
			'<div><label>', $this->h($this->t('setup.admin_path')), '</label><input name="admin_path" value="', $this->val('admin_path', $this->ex('admin_path', 'admin')), '"></div>',
			'</div>',
			'<label>', $this->h($this->t('setup.attach_total')), '</label>',
			'<input name="attachments_max_total" type="number" min="1" value="',
				$this->val('attachments_max_total', (string) $this->defaultAttachTotal()), '">',
			'<small>', $this->h($this->t('setup.attach_total_help', [
				'upload' => (string) ini_get('upload_max_filesize'),
				'post'   => (string) ini_get('post_max_size'),
				'mb'     => self::RECOMMENDED_TOTAL_MB,
			])), '</small>';
		if ($this->currentAttachTotal() > self::RECOMMENDED_TOTAL_MB) {
			echo '<div class="alert alert-warn">',
				$this->h($this->t('setup.attach_total_warn', ['mb' => self::RECOMMENDED_TOTAL_MB])), '</div>';
		}
		$this->stepNav(2);
	}

	private function stepDb(): void
	{
		$exDb = $this->exDb;
		$drv = $this->post('db_driver', (string) ($_SESSION['data']['db_driver'] ?? $exDb['driver']));
		echo '<h2>', $this->h($this->t('setup.db')), '</h2>',
			'<label>', $this->h($this->t('setup.db_driver')), '</label>',
			'<select name="db_driver">',
			'<option value="sqlite"', $drv === 'sqlite' ? ' selected' : '', '>', $this->h($this->t('setup.sqlite_default')), '</option>',
			'<option value="mysql"', $drv === 'mysql' ? ' selected' : '', '>MariaDB / MySQL</option>',
			'</select>',
			'<div><small>', $this->h($this->t('setup.db_ignored')), '</small>',
			'<div class="row">',
			'<div><label>', $this->h($this->t('setup.host')), '</label><input name="db_host" value="', $this->val('db_host', $exDb['host']), '"></div>',
			'<div><label>', $this->h($this->t('setup.port')), '</label><input name="db_port" value="', $this->val('db_port', $exDb['port']), '"></div>',
			'</div><div class="row">',
			'<div><label>', $this->h($this->t('setup.db_name')), '</label><input name="db_name" value="', $this->val('db_name', $exDb['name']), '"></div>',
			'<div><label>', $this->h($this->t('setup.db_user')), '</label><input name="db_user" value="', $this->val('db_user', $this->ex('db_user')), '"></div>',
			'<div><label>', $this->h($this->t('setup.db_pass')), '</label><input name="db_pass" type="password" value="', $this->val('db_pass', $this->ex('db_pass')), '"></div>',
			'</div></div>';
		$this->stepNav(3);
	}

	private function stepAdmin(): void
	{
		echo '<h2>', $this->h($this->t('setup.admin')), '</h2>',
			'<small>', $this->h($this->t('setup.admin_note')), '</small>',
			'<div class="row">',
			'<div><label>', $this->h($this->t('setup.admin_user')), '</label><input name="admin_user" value="', $this->val('admin_user', $this->ex('admin_user', 'admin')), '" required></div>',
			'<div><label>', $this->h($this->t('setup.admin_pass')), '</label><input name="admin_pass" type="password" minlength="8" autocomplete="new-password"></div>',
			'<div><label>', $this->h($this->t('setup.admin_pass2')), '</label><input name="admin_pass2" type="password" minlength="8" autocomplete="new-password"></div>',
			'</div>';
		if (!empty($_SESSION['data']['admin_pass'])) {
			echo '<small>', $this->h($this->t('setup.pass_keep')), '</small>';
		}
		$this->stepNav(4);
	}

	private function stepReview(): void
	{
		$d = $_SESSION['data'];
		$mysql = ($d['db_driver'] ?? $this->exDb['driver']) === 'mysql';
		$rows = [
			[$this->t('setup.app_name'), $d['app_name'] ?? $this->ex('app_name', 'MXWebMail'), 2],
			[$this->t('setup.admin_path'), '/' . ($d['admin_path'] ?? $this->ex('admin_path', 'admin')), 2],
			[$this->t('setup.db_driver'), $mysql ? 'MariaDB / MySQL' : 'SQLite', 3],
		];
		if ($mysql) {
			$rows[] = [$this->t('setup.host'), ($d['db_host'] ?? '') . ':' . ($d['db_port'] ?? ''), 3];
			$rows[] = [$this->t('setup.db_name'), $d['db_name'] ?? '', 3];
			$rows[] = [$this->t('setup.db_user'), $d['db_user'] ?? '', 3];
			$rows[] = [$this->t('setup.db_pass'), '••••••••', 3];
		}
		$rows[] = [$this->t('setup.admin_user'), $d['admin_user'] ?? '', 4];
		$rows[] = [$this->t('setup.admin_pass'), '••••••••', 4];
		$attachTotal = (int) ($d['attachments_max_total'] ?? $this->defaultAttachTotal());
		$rows[] = [$this->t('setup.attach_total'), $attachTotal . ' Mo', 2];
		// Valeur dérivée écrite à l'install : non modifiable ici (d'où le
		// libellé distinct), bornée par le défaut de config.example.php.
		$rows[] = [$this->t('setup.attach_per_file'),
			min((int) $this->ex('attachment_max_size', '25'), $attachTotal) . ' Mo', 2];

		echo '<h2>', $this->h($this->t('setup.review')), '</h2>',
			'<div class="alert alert-info">', $this->h($this->t('setup.review_hint')), '</div>';
		if (is_file($this->configFile)) {
			echo '<div class="alert alert-info">',
				$this->h($this->t('setup.config_exists')), '</div>';
		}
		echo '<table class="review">';
		foreach ($rows as [$label, $v, $s]) {
			echo '<tr><td>', $this->h($label), '</td><td>', $this->h((string) $v), '</td>',
				'<td><a href="?step=', $s, '">', $this->h($this->t('setup.edit')), '</a></td></tr>';
		}
		echo '</table>',
			'<div class="alert alert-info">', $this->h($this->t('setup.review_providers')), '</div>';
		if ($this->currentAttachTotal() > self::RECOMMENDED_TOTAL_MB) {
			echo '<div class="alert alert-warn">',
				$this->h($this->t('setup.attach_total_warn', ['mb' => self::RECOMMENDED_TOTAL_MB])), '</div>';
		}
		$this->stepNav(self::STEP_LAST);
	}

	// Écran "installation terminée" : $doneInfo = [admin_path, admin_user,
	// config remplacé, domaine détecté, presets]. Base publique via
	// Request::basePath() (sous-dossier supporté - le fichier servi devait
	// déduire sa propre base de l'URI).
	private function renderDone(array $doneInfo): void
	{
		$appBase = Request::basePath();
		$adminPath = $doneInfo[0];
		$this->pageTop($this->t('setup.done_title'));
		echo '<div class="alert alert-info">', $this->h($this->t('setup.done_body')), '</div>';
		if ($doneInfo[2] ?? false) {
			echo '<div class="alert alert-info">',
				$this->h($this->t('setup.config_replaced')), '</div>';
		}
		echo '<h2>', $this->h($this->t('setup.next_steps')), '</h2><ul>',
			'<li>', $this->h($this->t('setup.done_admin', ['path' => ltrim("$appBase/$adminPath", '/'),
				'user' => $doneInfo[1]])), '</li>',
			'<li>', $this->h($doneInfo[3] !== null
				? $this->t('setup.done_domain_added', ['domain' => $doneInfo[3]])
				: $this->t('setup.done_domains')), '</li>';
		if (!empty($doneInfo[4])) {
			echo '<li>', $this->h($this->t('setup.done_providers',
				['domains' => implode(', ', $doneInfo[4])])), '</li>';
		}
		echo '<li>', $this->h($this->t('setup.done_webmail')), '</li>',
			'<li>', $this->h($this->t('setup.done_cleanup')), '</li>',
			'</ul><div class="nav">',
			'<a class="btn btn-secondary" href="', $this->h("$appBase/$adminPath"), '">',
				$this->h($this->t('setup.open_admin')), '</a>',
			'<a class="btn" href="', $this->h("$appBase/"), '">',
				$this->h($this->t('setup.open_webmail')), '</a>',
			'</div>';
		$this->pageBottom();
	}
}
