<?php
// Constantes globales chargées avant le bootstrap (index.php).
// Uniquement des define() — jamais de sortie ni d'effet de bord.
//
// MXWM_APP_DIR : chemin du dossier privé app/. Pour le sortir de la racine
// web : déplacer le dossier puis adapter le chemin (absolu ou relatif).
define('MXWM_APP_DIR', dirname(__DIR__) . '/app');
