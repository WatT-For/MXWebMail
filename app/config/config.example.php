<?php
/**
 * Configuration MXWebMail — copier en config.php et adapter.
 * Seules les clés définies ici écrasent les défauts de Config.php.
 */
return [
	// ─── Stockage ───
	// 'sqlite' (par défaut) ou DSN PDO complet (ex. 'mysql:host=localhost;dbname=mxwebmail;charset=utf8mb4')
	'db_dsn'     => 'sqlite',
	'db_user'    => '',
	'db_pass'    => '',

	// ─── Application ───
	'app_name'   => 'MXWebMail',
	// Nom du fichier favicon uploadé dans app/storage/branding (admin → Général) ;
	// vide = icône MXWebMail par défaut. Servi via /favicon.ico.
	'favicon'    => '',
	'debug'      => false,          // affiche les erreurs internes aux clients API (jamais en production)
	// 'auto' = Accept-Language du navigateur ; sinon code d'un fichier
	// app/src/MXWebMail/Locale/<lang>.php (en, fr…). Repli final : 'en'.
	'language'   => 'auto',

	// ─── Domaines ───
	// Les serveurs IMAP/SMTP/Sieve se configurent par domaine dans l'admin
	// (page « Domaines » ; le domaine '*' sert de paramètres catch-all).
	// Suffixe d'un login saisi sans @domaine : 'auto' = hôte HTTP
	// (sans www. ni :port), '<domaine>' = suffixe fixe, '' = aucun.
	'default_domain'   => 'auto',
	// true = seuls les domaines de la table domains sont acceptés au login
	// (ni '*' ni la détection ne s'appliquent).
	'restrict_domains' => true,
	// Détection automatique de la config d'un domaine inconnu au login
	// (SRV → autoconfig Mozilla → MX → sondage). Désactivée par défaut.
	// Quelle que soit la source, ManageSieve (4190) est aussi sondé sur
	// l'hôte IMAP détecté (et sieve.<domaine> quand probe est actif).
	'autodetect' => [
		'enabled'   => false,
		'use_ispdb' => false,  // repli base Mozilla (autoconfig.thunderbird.net)
		'probe'     => false,  // sondage de ports imap./mail./smtp./sieve.<domaine>
	],
	// Applications OAuth2 des comptes secondaires : identifiants saisis et
	// stockés PAR UTILISATEUR (table oauth_apps ; Réglages → Applications
	// OAuth2). L'admin n'autorise que le provider par domaine (options.oauth).

	// ─── Sécurité ───
	'admin_path'      => 'admin',   // chemin d'accès au panneau admin (/admin_path)
	// Compte admin local (indépendant d'IMAP) — à renseigner manuellement :
	// 'admin_password_hash' => password_hash('motdepasse', PASSWORD_DEFAULT)
	// Sans admin_user + admin_password_hash, le login admin retombe sur un
	// utilisateur is_admin vérifié via IMAP.
	'admin_user'          => '',    // utilisateur admin local ; vide = fallback IMAP
	'admin_password_hash' => '',    // hash password_hash() ; vide = fallback IMAP
	'admin_totp_secret'   => '',    // secret TOTP (base32) du compte admin local ; provisionné
	                              // via le plugin two-factor-auth (QR). Plugin désactivé = non exigé.
	'login_max_attempts' => 5,      // tentatives avant blocage temporaire
	'login_lock_seconds' => 300,    // durée de blocage
	'admin_ip_allowlist' => '',     // IP/CIDR séparées par virgule pour l'admin ; vide = toutes
	'session_lifetime'   => 0,      // durée session en heures ; 0 = cookie de session
	'maintenance'        => false,  // page maintenance 503 sur / + /api/* utilisateur en 503
	                                // (admin + /api/ping restent actifs ; les connectés basculent
	                                // sur la page maintenance à leur prochaine requête API)
	'force_https'        => false,  // rediriger HTTP → HTTPS (301) dès l'entrée
	'hsts'               => false,  // émettre Strict-Transport-Security quand la requête est HTTPS
	'cookie_samesite'    => 'Lax',    // Lax | Strict | None (None exige HTTPS ;
	                                // Strict casse le retour OAuth2 cross-site)
	'csp_extra'          => '',     // directives CSP additionnelles (ex. "img-src https:")
	// IP/CIDR de reverse proxies de confiance (CSV). Vide = REMOTE_ADDR utilisé
	// tel quel et X-Forwarded-* ignorés. Obligatoire pour fiabiliser
	// rate-limit, allowlist admin et logs derrière un proxy.
	'trusted_proxies'    => '',
	// Règles Sec-Fetch-* additionnelles (format MXWebMailLib\HTTP\SecFetch :
	// "site=same-origin;mode=cors"). Vide = requêtes cross-site rejetées
	// sauf navigation GET vers un document (entrée sur le site — anti-CSRF
	// de base).
	'secfetch_allow'     => '',

	// ─── Mises à jour (admin → À propos, GitHub Releases) ───
	// Le dépôt officiel est verrouillé dans GitHubClient::REPO — partagé par
	// l'updater cœur et le catalogue plugins (même release : mxwebmail-*.zip
	// + plugin-<name>-*.zip + plugins.index.json). Cette section n'est pas
	// modifiable via le panel admin (sauf channel) — édition manuelle de
	// config.php uniquement.
	'update' => [
		'repo'    => '',        // fallback dev si GitHubClient::REPO vide ('owner/mxwebmail')
		'channel' => 'stable',  // stable | beta (le seul réglage exposé dans l'admin)
		'token'   => '',        // PAT GitHub optionnel (rate-limit / dépôt privé)
		'manifest_url' => '',   // dev : manifeste JSON format release, bypass l'API
	],

	// ─── Fonctionnalités ───
	'attachment_max_size' => 25,    // Mo maximum par fichier — doit être ≤ attachments_max_total
	'attachments_max_total' => 25,  // Mo maximum cumulé des pièces jointes par message
	'allow_additional_accounts'  => true,  // l'utilisateur peut ajouter des comptes secondaires
	'allow_additional_identities' => true, // l'utilisateur peut créer des identités additionnelles
	'popup_identity'      => false, // ouvrir le réglage d'identité au 1er login si nom vide
	'dangerous_actions'   => true,  // actions destructrices : vider/supprimer dossier, supprimer messages
	'contacts_enable'     => true,  // carnet d'adresses + autocomplétion
	'contacts_suggestions_limit' => 20, // nb max de suggestions d'autocomplétion (1-100)

	// ─── Défauts des réglages utilisateur (écrasables par utilisateur) ───
	// Les clés autorisées en écriture via /api/settings sont celles de cette
	// liste + celles déclarées par les plugins via le hook « settings.allowed ».
	'user_defaults' => [
		'messages_per_page' => 50,       // messages par page (5-200)
		'list_sort'         => 'REVERSE DATE', // tri liste messages (critère IMAP SORT whitelisté API)
		'view_images'       => 'ask',    // images externes : always | ask | match (expéditeur connu)
		'msg_default_action' => 'reply', // bouton répondre par défaut : reply | reply_all
		'mark_read_delay'   => 5,        // secondes avant de marquer lu un message ouvert (0 = immédiat)
		'autologout'        => 30,       // minutes d'inactivité avant déconnexion (0 = jamais)
		'list_refresh'      => 5,        // minutes entre réactualisations auto de la liste (0 = jamais)
		'notify_new_mail'   => false,    // notification navigateur à l'arrivée d'un mail (onglet ouvert)
		'contacts_autosave' => true,     // auto-enregistrer le destinataire dans les contacts
		'folder_pane_width'  => 240,     // largeur colonne dossiers (px, 140-600)
		'message_pane_width' => 340,     // largeur colonne liste des messages (px, 200-800)
		'layout'             => 'horizontal', // disposition lecture : horizontal | vertical
		'message_pane_height' => 320,    // hauteur liste des messages en mode vertical (px, 120-1200)
		'compose_modal_width'  => 0,     // largeur modale composition (px, 0 = défaut Bootstrap)
		'compose_modal_height' => 0,     // hauteur modale composition (px, 0 = auto)
		'theme'             => 'default', // template d'interface (dossier app/templates/<name>)
	],

	// ─── TLS (connexions IMAP/SMTP/Sieve sortantes) ───
	// Lu par MailSo\Net\SSLContext via la façade de compatibilité API.
	'ssl' => [
		'verify_certificate'  => true,   // false = ne pas vérifier le certificat (ex. cert auto-signé)
		'allow_self_signed'   => false,
		'cafile'              => '',     // CA custom (chemin absolu)
		'capath'              => '',
		'disable_compression' => true,
		'security_level'      => 1,      // OpenSSL security level (0-5)
		'local_cert'          => '',     // certificat client éventuel
	],

	// ─── Proxy images externes ───
	'image_proxy' => true,          // servir les images distantes via /api/proxy
	'image_proxy_allowlist' => '',  // domaines autorisés (CSV, *.domaine ok) ; vide = tous — IP privées toujours bloquées

	// ─── Logs MailSo (protocoles IMAP/SMTP/Sieve) ───
	'logs' => [
		'enabled' => true,              // app/storage/logs/mail.log — secrets masqués
		'level'   => LOG_WARNING,       // LOG_DEBUG pour tracer le protocole
		'syslog'  => false,             // doublon vers syslog() quand le log fichier est inactif
		'time_zone' => '',              // fuseau des horodatages de log (ex. 'Europe/Paris') ; vide = serveur
	],

	// ─── Journal d'authentification (type fail2ban) ───
	'auth_logging'          => false,
	'auth_logging_filename' => 'auth.log', // fichier dans app/storage/logs
	// Placeholders : {date:FORMAT} {request:ip} {imap:login} {result}
	'auth_logging_format'   => '{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}',

	// ─── HTTP : compression & cache navigateur ───
	'http' => [
		'gzip'          => true,   // compresser les réponses compressibles (texte/json…)
		'gzip_level'    => 6,      // niveau gzip 1-9
		'gzip_min_size' => 1024,   // corps en dessous duquel on ne compresse pas (octets)
		'etag'          => true,   // ETag + 304 sur les assets servis par PHP (thème/plugin/favicon)
		'assets_ttl'    => 86400,  // max-age des assets thème/plugin (s ; 0 = revalidation systématique)
		'favicon_ttl'   => 3600,   // max-age de /favicon.ico (s)
		'proxy_ttl'     => 86400,  // max-age des images servies via /api/proxy (s)
	],

	// ─── Cache applicatif (fichiers dans app/storage/cache) ───
	// Ne couvre ni classmap.php (autoloader) ni login_attempts.json (sécurité).
	'cache' => [
		'enabled'           => true,   // cache fichier (DNS, plugins, détection domaine, update)
		'domain_detect_ttl' => 86400,  // TTL du cache négatif de détection de domaine (s)
		'update_ttl'        => 3600,   // TTL du cache de vérification de mise à jour (s)
		'gc_hours'          => 168,    // purge des entrées plus vieilles que N heures (0 = jamais)
	],

	// ─── Labs / compatibilité MailSo ───
	'labs' => [
		// Afficher les messages d'erreur bruts du serveur SMTP (utile au
		// diagnostic ; peut révéler des détails internes à l'utilisateur).
		'smtp_show_server_errors' => false,
	],
];
