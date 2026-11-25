<?php
/**
 * Coquille "carte" des pages de connexion (login, admin-login, maintenance) :
 * <body class="login-page"> + carte centrée + entête icône/marque.
 * La page ouvre la carte avec ob_start() sur son contenu puis l'inclut.
 * Variables attendues :
 * - $cardIcon      : classe FA de l'icône (ex. 'fa-envelope')
 * - $cardIconClass : classe de couleur ('text-primary' par défaut)
 * - $cardBrand     : libellé sous l'icône, déjà échappé
 * - $cardHeadClass : classes de l'en-tête ('text-center mb-4' par défaut)
 * - $cardBodyClass : classe(s) additionnelle(s) du card-body ('' = aucune)
 * - $cardContent   : HTML du corps (formulaire, message…)
 */
?>
<body class="login-page">
<div class="card login-card shadow-sm">
	<div class="card-body p-4 <?= $cardBodyClass ?? '' ?>">
		<div class="<?= $cardHeadClass ?? 'text-center mb-4' ?>">
			<i class="fa-solid <?= $cardIcon ?? 'fa-envelope' ?> fa-2x <?= $cardIconClass ?? 'text-primary' ?> mb-2"></i>
			<div class="brand-logo"><?= $cardBrand ?? '' ?></div>
		</div>
		<?= $cardContent ?? '' ?>
	</div>
</div>
