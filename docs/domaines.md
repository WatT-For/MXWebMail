# Domaines et serveurs mail

Les serveurs IMAP/SMTP/Sieve se configurent **par domaine** dans le panneau admin
(*Domaines*). Chaque domaine décrit comment joindre le serveur de messagerie de ses
utilisateurs.

## 1. Champs d'un domaine

| Champ | Valeur |
|---|---|
| `name` | Nom de domaine (ex. `example.com`), ou `*` pour le catch-all. |
| `imap_host` | Hôte du serveur IMAP. |
| `imap_port` | Port IMAP (défaut `993`). |
| `imap_encryption` | `ssl` (défaut), `tls` (STARTTLS) ou `none`. |
| `smtp_host` | Hôte du serveur SMTP (envoi). |
| `smtp_port` | Port SMTP (défaut `465`). |
| `smtp_encryption` | `ssl` (défaut), `tls` ou `none`. |
| `sieve_host` | Hôte ManageSieve (optionnel). |
| `sieve_port` | Port Sieve (défaut `4190`). |
| `active` | Activer/désactiver le domaine. |

## 2. Options avancées (`options`, JSON)

Le champ `options` accepte un objet JSON avec des réglages fins par domaine :

- `whitelist` — liste d'adresses autorisées pour ce domaine ;
- `secondary_only` — le domaine n'est utilisable qu'en **compte secondaire**
  (ajout de compte depuis un utilisateur déjà connecté) ; le login primaire est
  refusé avec « Domaine réservé aux comptes secondaires » ;
- `oauth` — provider OAuth2 du domaine (`google`, `microsoft`, `yahoo`) : les
  comptes secondaires s'ajoutent par consentement provider (popup), jamais par
  mot de passe (voir §8) ;
- `imap.shortLogin` — tronquer le login ;
- `imap.lowerLogin` — forcer le login en minuscules ;
- `imap.timeout` — délai de connexion IMAP ;
- `imap.disabled_capabilities` — capacités IMAP à désactiver ;
- `smtp.useAuth` — forcer/désactiver l'authentification SMTP ;
- `smtp.timeout` — délai de connexion SMTP ;
- `sieve.encryption` — chiffrement de la connexion Sieve.

## 3. Domaine `*` (catch-all)

Le domaine `*` fournit les **paramètres par défaut** appliqués à tout domaine non déclaré —
mais uniquement quand `restrict_domains = false` : sous `restrict_domains`, `*` est
ignoré et ne peut pas autoriser un login primaire. C'est le moyen le plus simple de
servir plusieurs domaines derrière un même serveur mail.

## 4. Résolution du domaine au login

Au login, le domaine est déterminé ainsi :

1. Si l'utilisateur saisit un login avec `@domaine`, ce domaine est utilisé.
2. Sinon, `default_domain` s'applique : `auto` (défaut) = hôte HTTP sans `www.` ni port ;
   un nom fixe ; ou `''` (aucun).
3. Si `restrict_domains = true` (défaut), seuls les domaines **nommés** présents en
   table sont acceptés (`*` et la détection sont ignorés) — **créez donc au moins un
   domaine avant tout login**.

## 5. Détection automatique

`autodetect.*` (désactivé par défaut) permet de découvrir la configuration d'un domaine
inconnu au moment du login :

- `enabled` : active la détection ;
- `use_ispdb` : interroge la base Mozilla (`autoconfig.thunderbird.net`) ;
- `probe` : sonde `imap.`/`mail.`/`smtp.<domaine>`.

L'ordre de détection est : DNS SRV → autoconfig Mozilla → MX → sondage de ports.

Sieve est détecté quelle que soit la source : `_sieve._tcp` (SRV), sinon sonde du
port **4190** sur `sieve.<domaine>` (si `probe` actif ou via le bouton *Détecter*)
puis sur l'hôte IMAP trouvé — Dovecot sert presque toujours ManageSieve sur le
même hôte. Le chiffrement n'a pas à être configuré : la connexion bascule en
STARTTLS automatiquement quand le serveur l'annonce.

Le bouton **Détecter** de la page *Domaines* lance la même détection à la demande, sans
dépendre de `autodetect.enabled`, et remplit les champs du domaine.

> L'installeur `/setup/` tente également cette détection sur l'hôte du vhost (et ses
> parents) pour créer le premier domaine automatiquement.

## 6. Test

Le bouton **Tester la connexion** valide IMAP, SMTP et Sieve séparément. Utilisez-le après
chaque modification pour vérifier que les identifiants, ports et chiffrements sont corrects
avant de laisser les utilisateurs se connecter.

## 7. Cas courant : un seul serveur mail

Pour un serveur unique (ex. Postfix + Dovecot sur `mail.example.com` desservant
`example.com`) :

1. Créez le domaine `example.com` avec `imap_host = mail.example.com`, `smtp_host =
   mail.example.com`, et éventuellement `sieve_host`.
2. Laissez `default_domain = auto` et `restrict_domains = true`.
3. Les utilisateurs se connectent avec `user@example.com` ou simplement `user` (le
   domaine est déduit de l'hôte HTTP).

Variante multi-domaines : un domaine `*` avec `restrict_domains = false` sert de
catch-all — tout domaine non déclaré hérite de ses paramètres (voir §3 ; attention,
le login primaire reste alors possible sur tous les domaines — `secondary_only`
peut le refuser, voir §8).

## 8. Domaines publics en compte secondaire (Gmail, Outlook, Yahoo)

Gmail, Outlook.com et Yahoo n'acceptent plus l'authentification IMAP par simple
mot de passe — l'ajout de ces comptes passe par **OAuth2** (consentement dans
une popup, XOAUTH2/OAUTHBEARER ensuite pour IMAP et SMTP).

Les identifiants d'application OAuth2 (client ID, secret, tenant Microsoft) sont
**propres à chaque utilisateur** : l'admin n'enregistre aucune app — il autorise
seulement le provider sur le domaine. Chaque utilisateur crée sa propre
application dans la console développeur du provider.

### Configuration (admin)

Ces trois domaines sont **pré-créés à l'installation** (compte secondaire +
provider OAuth déjà réglés). Si vous les avez supprimés, recréez-les ainsi :

1. **Créez le domaine** (*Domaines → Ajouter* ou bouton *Détecter*) :
   `imap.gmail.com:993` / `smtp.gmail.com:465` pour Gmail,
   `outlook.office365.com:993` / `smtp.office365.com:587` pour Outlook,
   `imap.mail.yahoo.com:993` / `smtp.mail.yahoo.com:465` pour Yahoo.
2. Cochez **Compte secondaire uniquement** (login primaire refusé) et choisissez
   le provider dans **Auth OAuth2**.

### Enregistrement de l'application (utilisateur)

Au premier ajout d'un compte du domaine, le webmail demande les identifiants de
l'app et les enregistre pour l'utilisateur — gérables ensuite dans *Réglages →
Applications OAuth2* (une app par provider, réutilisée pour tous les comptes de
ce provider). URI de redirection à déclarer dans la console :
`<base>/api/oauth/callback`.

- **Google** (Cloud Console → APIs & Services → Credentials → client OAuth Web,
  scope `https://mail.google.com/`) : scope *restreint* — une app en mode
  « Testing » est limitée à ~100 utilisateurs test et ses refresh tokens
  expirent après ~7 jours ; la production exige la vérification Google.
- **Microsoft** (Entra → App registrations, `signInAudience` incluant les
  comptes personnels) : permissions déléguées « Office 365 Exchange Online »
  `IMAP.AccessAsUser.All` + `SMTP.Send`, scope `offline_access` implicite.
  Bannière « éditeur non vérifié » possible au consentement (non bloquante).
- **Yahoo** (Developer Console → app OpenID Connect + Mail API, scopes
  `openid`, `mail-r`, `mail-w`) : l'accès Mail API exige une **approbation
  préalable par Yahoo** (formulaire de demande + revue manuelle) et la
  propagation de la redirect URI peut prendre ~24 h.

### Fonctionnement

- L'utilisateur ajoute le compte depuis le menu comptes : saisie de l'adresse →
  (première fois : saisie des identifiants d'app) → popup de consentement → le
  compte apparaît avec un badge provider.
- Le *refresh token* est stocké chiffré (`accounts.imap_pass`) ; chaque
  connexion IMAP/SMTP échange un access token éphémère. Microsoft et Yahoo
  **réémettent un refresh token à chaque usage** : il est remplacé
  automatiquement en base.
- Le secret d'app est chiffré (`oauth_apps.secret`, même mécanisme que les
  mots de passe) et n'est jamais renvoyé à l'interface — « vide = inchangé »
  à l'édition.
- **Modifier ou supprimer une app** casse les comptes associés : le refresh
  token reste lié au client_id d'origine — supprimez puis ré-ajoutez le compte.
- Pas de ManageSieve chez ces providers : les filtres Sieve sont indisponibles
  sur les comptes OAuth.

Combiner `secondary_only` sur le domaine `*` et des domaines internes normaux
réserve le login primaire aux domaines listés tout en laissant les secondaires
ouverts partout — utile sans `restrict_domains`.