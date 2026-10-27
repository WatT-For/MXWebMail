# Politique de sécurité

## Versions supportées

| Version             | Supportée          |
| ------------------- | ------------------ |
| Dernière release    | :white_check_mark: |
| Versions antérieures| :x:                |

Le projet est en développement (`0.x`) : seule la dernière release publiée
reçoit les correctifs de sécurité. Maintenez votre installation à jour depuis
le panneau d'administration (onglet « À propos »).

## Signaler une vulnérabilité

**N'ouvrez pas d'issue publique** pour une faille de sécurité.

Utilisez le signalement privé de GitHub :
[Security → Report a vulnerability](https://github.com/WatT-For/MXWebMail/security/advisories/new)

Indiquez si possible :

- la version concernée (visible dans l'admin, « À propos ») ;
- la description de la vulnérabilité et son impact ;
- un scénario de reproduction (étapes, requête, PoC) ;
- toute piste de correction.

Les rapports sont traités au mieux (projet maintenu par un développeur seul,
sans SLA) : accusé de réception et correctif selon la criticité. Le signaleur
est crédité dans l'avis de sécurité, sauf demande contraire.

## Périmètre

- **Dans le scope** : le code de ce dépôt (cœur, plugins et thèmes livrés,
  assistant d'installation).
- **Hors scope** : vos serveurs IMAP/SMTP/Sieve, l'hébergement, la
  configuration du serveur web, les dépendances tierces (à signaler en amont,
  sauf si notre intégration est en cause).

Le modèle de sécurité et les recommandations de durcissement sont décrits dans
[docs/securite.md](docs/securite.md).
