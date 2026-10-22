# Dépendances PHP vendorées — Sabre/*

Sources aplaties depuis les paquets Composer officiels (`<pkg>/lib/` →
`src/Sabre/<Ns>/`). Chargement par `src/bootstrap.php` : PSR-4 `Sabre\` +
`functions.php` requis explicitement.

| Namespace | Paquet | Version figée | Source |
|---|---|---|---|
| `Sabre\VObject` | sabre/vobject | 5.0.0 | https://github.com/sabre-io/vobject |
| `Sabre\Xml` | sabre/xml | 4.1.0 | https://github.com/sabre-io/xml |
| `Sabre\Uri` | sabre/uri | 3.1.0 | https://github.com/sabre-io/uri |

Mise à jour manuelle : télécharger la release GitHub voulue, copier le contenu
de `lib/` dans le dossier du namespace + `LICENSE`, vérifier que les
`functions.php` requis dans `src/bootstrap.php` existent toujours.
