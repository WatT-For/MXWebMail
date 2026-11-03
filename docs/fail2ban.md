# Fail2ban

MXWebMail peut écrire un journal d'authentification dédié, pensé pour être lu par
[fail2ban](https://github.com/fail2ban/fail2ban) afin de bannir les IP qui multiplient
les échecs de connexion. Cette page décrit l'activation du journal et une intégration
fail2ban complète (filtre, jail, rotation).

## Activation

Trois clés de `app/config/config.php` (référence :
[configuration.md](configuration.md#journal-dauthentification-type-fail2ban)),
également éditables dans l'admin, section *Logs* :

| Clé | Défaut | Rôle |
|---|---|---|
| `auth_logging` | `false` | Active le journal. |
| `auth_logging_filename` | `auth.log` | Fichier dans `app/storage/logs`. |
| `auth_logging_format` | `{date:Y-m-d H:i:s} [{request:ip}] {imap:login} {result}` | Format de ligne. |

Avec le format par défaut, une ligne ressemble à :

```
2026-10-27 14:30:05 [203.0.113.10] user@example.com AUTH FAILED (401)
```

- `{result}` vaut `AUTH OK`, `AUTH FAILED (<code http>)` ou `AUTH FAILED (totp)`
  (mauvais code TOTP saisi - la demande initiale de second facteur n'est pas un échec
  et ne journalise rien).
- Les tentatives sur le **login admin** sont couvertes : le login est préfixé
  `admin:` (ex. `admin:admintest`). Un login IMAP valide mais non admin est
  journalisé `AUTH FAILED (403)`.
- Les tentatives déjà bloquées par le rate-limit interne sont journalisées
  `AUTH FAILED (429)` - elles comptent donc aussi pour fail2ban (récidive).
- Les sauts de ligne sont neutralisés dans le login et le résultat : une entrée
  forgée ne peut pas injecter de fausses lignes dans le fichier.

## Filtre

`/etc/fail2ban/filter.d/mxwebmail.conf` (pour le format par défaut) :

```ini
[Definition]
failregex = \[<HOST>\] \S+ AUTH FAILED
datepattern = %%Y-%%m-%%d %%H:%%M:%%S
```

Si `auth_logging_format` est personnalisé, adapter `failregex` (et `datepattern` si
le `{date:FMT}` change - fail2ban exige un horodatage reconnaissable en début de
ligne ou identifiable via `datepattern`).

Vérifier que le filtre matche le fichier réel :

```bash
fail2ban-regex /var/www/mxwebmail/app/storage/logs/auth.log \
    /etc/fail2ban/filter.d/mxwebmail.conf
```

## Jail

`/etc/fail2ban/jail.d/mxwebmail.conf` (ou `jail.local`) :

```ini
[mxwebmail]
enabled  = true
filter   = mxwebmail
logpath  = /var/www/mxwebmail/app/storage/logs/auth.log
maxretry = 5
findtime = 600
bantime  = 3600
```

Puis `fail2ban-client reload` et `fail2ban-client status mxwebmail`.

## Points de vigilance

- **Fuseau horaire** : fail2ban compare l'horodatage des lignes à l'heure système.
  Laisser `logs.time_zone` vide (= fuseau du serveur) ou le régler sur le **même**
  fuseau que le système qui exécute fail2ban - un décalage fausse `findtime` et le
  ban.
- **Reverse proxy** : sans `trusted_proxies` renseigné, le journal contient l'IP du
  proxy - fail2ban bannirait alors le proxy et couperait l'accès à **tous** les
  utilisateurs. Déclarer les proxies de confiance et vérifier que l'IP journalisée
  est bien celle du client.
- **Rotation** : le fichier grossit sans limite. Exemple `logrotate` :

  ```
  /var/www/mxwebmail/app/storage/logs/auth.log {
      weekly
      rotate 8
      missingok
      notifempty
      compress
      copytruncate
  }
  ```

- **Complément, pas remplacement** : fail2ban agit au niveau réseau, le rate-limit
  interne (`login_max_attempts`, `login_lock_seconds` - voir
  [securite.md](securite.md#rate-limit-du-login)) reste la première ligne de défense
  applicative. Les deux sont indépendants et cumulables.
