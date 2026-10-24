# Installation

## 1. Prérequis

- **PHP ≥ 8.4** avec les extensions : `sodium`, `mbstring`, `intl`, `openssl`, `ctype`,
  `json`, `iconv`, `zlib`, `xml`, `dom`, et `pdo_sqlite` **ou** `pdo_mysql`.
  `ldap` est optionnel (plugins LDAP uniquement).
- Un serveur **IMAP** accessible (et SMTP, ManageSieve en option) pour le courrier.
- **Aucune dépendance à installer** : le dépôt est autosuffisant (autoloader maison,
  MailSo, Sabre, assets et librairies JS vendorés).
- Le document root du serveur web doit être **`public/`**, jamais la racine du projet.

## 2. Structure des dossiers

```
public/            racine web — index.php, define.php, assets/, setup/, .htaccess
app/               tout le non-public (peut être déplacé hors racine web)
app/src/           code applicatif + bibliothèques vendorées
app/config/        config.php (non versionné) + config.example.php
app/plugins/       extensions (vide à l'installation — plugins du catalogue GitHub)
app/templates/     thèmes/pages
app/storage/       runtime : db, cache, logs, tmp, app.key, INSTALLED — jamais en webroot
```

`public/define.php` définit `MXWM_APP_DIR` (chemin du dossier `app/`). Pour sortir `app/`
de la racine web, déplacez le dossier puis adaptez ce chemin (absolu ou relatif).

## 3. Installation via l'assistant `/setup/` (recommandée)

1. Pointez le vhost sur `public/`.
2. Ouvrez `https://webmail.example.com/`. Tant que le verrou `app/storage/INSTALLED`
   n'existe pas, toute URL est redirigée vers `/setup/`.
3. L'assistant se déroule en 5 étapes :
   - **Prérequis** : vérifie PHP, les extensions, la présence des fichiers vendorés et
     l'écriture de `app/storage/` (bloquant) et `app/config/` (non bloquant — le
     `config.php` peut être créé à la main).
   - **Application** : nom affiché (`app_name`), chemin d'admin (`admin_path`), taille
     cumulée maximale des pièces jointes (`attachments_max_total`, plafonnée à 25 Mo par
     défaut et bornée par `upload_max_filesize`/`post_max_size`). La limite par fichier
     `attachment_max_size` est écrite en dérivée : `min(25, attachments_max_total)`.
   - **Base de données** : SQLite (défaut) ou MariaDB/MySQL. En MariaDB, un test de
     connexion réel est effectué ; la base est créée (`utf8mb4`) à l'installation.
   - **Administrateur** : compte admin local (`admin_user` + mot de passe ≥ 8 caractères).
     Si vous laissez ces champs vides, le login admin retombe sur un utilisateur `is_admin`
     authentifié via IMAP (voir [administration.md](administration.md)).
   - **Récapitulatif** : contrôle des valeurs puis bouton **Installer**.
4. L'installation écrit `app/config/config.php` (un fichier existant est archivé en
   `config.<horodatage>.bak`), applique les migrations, tente de **détecter le domaine
   d'hébergement** (SRV → autoconfig Mozilla → MX → sondage) et l'ajoute en table
   `domains`, puis pose le verrou `app/storage/INSTALLED`.
   Au clic sur **Installer**, un écran de progression pleine page s'affiche :
   la détection de domaine peut faire durer l'opération jusqu'à ~2 minutes —
   ne fermez pas la page (l'installation se terminerait malgré tout côté serveur).
   Trois domaines publics sont aussi **pré-autorisés en comptes secondaires** —
   `gmail.com`, `outlook.com`, `yahoo.com` (OAuth2 requis, login primaire refusé ;
   voir [domaines.md](domaines.md) §8). Supprimables dans admin → *Domaines*.
5. **Après l'installation** : supprimez `public/setup/` (facultatif — le verrou le
   désactive déjà) et **sauvegardez `app/storage/app.key`** (voir §6).

> Si `app/config/` n'est pas inscriptible, l'assistant affiche le contenu de `config.php`
> à créer manuellement. L'installation se poursuit une fois le fichier en place.

## 4. Installation manuelle

```bash
cp app/config/config.example.php app/config/config.php   # éditer les valeurs
php -r 'require "app/src/bootstrap.php"; (new MXWebMail\Application\App(__DIR__."/app"))->db()->migrate();'
touch app/storage/INSTALLED
```

La migration est de toute façon appliquée au premier accès (`App::db()`), il suffit donc
de créer `config.php` puis `app/storage/INSTALLED` (le contenu importe peu, seul
`is_file()` est testé). `app/storage/app.key` est généré automatiquement au premier
chiffrement de credential.

## 5. Déploiement web

### Apache

```apache
<VirtualHost *:443>
    ServerName webmail.example.com
    DocumentRoot /var/www/mxwebmail/public
    <Directory /var/www/mxwebmail/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Le `.htaccess` racine peut réécrire vers `public/` en filet de transition, mais le
document root officiel est `public/`.

### Nginx

```nginx
server {
    listen 443 ssl;
    server_name webmail.example.com;
    root /var/www/mxwebmail/public;
    index index.php;

    # Doit être >= attachment_max_size (25 Mo par défaut).
    # attachments_max_total (cumul des PJ) est vérifié à l'envoi : chaque fichier
    # arrive en requête séparée.
    client_max_body_size 30m;

    # Dotfiles jamais servis
    location ~ /\.(?!well-known) { deny all; }

    # Filet : dossier privé refusé même si le root pointe par erreur sur la racine
    location ~ ^/(app|dist)(/|$) { return 404; }

    # Assets versionnés /assets/vX.Y.Z/… → fichier réel (équivalent du
    # RewriteRule du .htaccess d'Apache). TTL long : la version change à
    # chaque release. Les URLs /assets/theme|plugin/ passent par try_files
    # → PHP (leurs en-têtes restent gérés par la config « http »).
    location ~ ^/assets/v[0-9.]+/ {
        rewrite ^/assets/v[0-9.]+/(.*)$ /assets/$1 last;
    }
    location ~ ^/assets/(img|js|vendor)/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }
}
```

Remarques :

- Les `.htaccess` sont inertes sous Nginx : c'est le bloc `location ~ /\.` qui cache les
  dotfiles, et `^/(app|dist)` qui protège le dossier privé.
- `autoindex` est désactivé par défaut (équivalent de `Options -Indexes`).
- Compression optionnelle : `gzip on; gzip_types text/css application/javascript application/json image/svg+xml;`.
  Sous Apache, `public/.htaccess` couvre déjà `mod_deflate` + les TTL des assets ;
  la compression PHP (`http.gzip`) fonctionne dans les deux cas.

## 6. Étapes post-installation

1. **Supprimez `public/setup/`** une fois installé (recommandé).
2. **Sauvegardez `app/storage/app.key`** : il chiffre tous les credentials stockés
   (mots de passe IMAP, secrets de plugins). Sa perte oblige les utilisateurs à se
   reconnecter ; il ne doit jamais être servi ni versionné.
3. **Créez au moins un domaine** dans l'admin (page *Domaines*) : avec
   `restrict_domains = true` (défaut), aucun login n'est possible sans domaine configuré.
   L'installeur tente de le détecter automatiquement.
4. **Installez les plugins** depuis l'admin → *Extensions* → **Catalogue GitHub** :
   le zip de release n'embarque plus les plugins — ils se téléchargent, se mettent à
   jour et se désinstallent depuis ce catalogue (voir
   [administration.md](administration.md#extensions-plugins) et
   [plugins.md](plugins.md)). À la mise à jour du cœur, les plugins déjà installés
   sont préservés (`app/plugins/` est exclu de la copie).
5. Vérifiez `force_https` / `hsts` si vous êtes derrière un reverse proxy (voir
   [securite.md](securite.md) pour `trusted_proxies`).
6. Positionnez `admin_ip_allowlist` si le panneau admin doit être restreint à certaines IP.

## 7. URLs principales

- `/` — webmail (login → dossiers → messages)
- `/{admin_path}` — panneau d'administration (défaut `/admin`)
- `#/@settings/<onglet>` — paramètres utilisateur (général, identités, contacts, filtres
  Sieve, OpenPGP, onglets de plugins)