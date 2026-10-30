<?php
declare(strict_types=1);

return [
	'plugin.attachments-clamav.description'        => 'Analyse antivirale ClamAV (clamd) des pièces jointes uploadées',
	'plugin.attachments-clamav.f.socket'           => 'Socket clamd',
	'plugin.attachments-clamav.f.socket.help'      => 'tcp://hôte:port ou unix:///chemin/vers/clamd.ctl',
	'plugin.attachments-clamav.f.timeout'          => 'Délai (secondes)',
	'plugin.attachments-clamav.f.on_error'         => 'Quand clamd est injoignable',
	'plugin.attachments-clamav.f.on_error.allow'   => 'Autoriser l\'upload (log warning)',
	'plugin.attachments-clamav.f.on_error.deny'    => 'Rejeter l\'upload (fail-closed)',
	'plugin.attachments-clamav.f.on_error.help'    => 'deny bloque tout upload tant que clamd est indisponible',
	'plugin.attachments-clamav.virus_found'        => 'Virus détecté dans « {name} »',
	'plugin.attachments-clamav.unavailable'        => 'Antivirus injoignable',
	'plugin.attachments-clamav.bad_response'       => 'Réponse inattendue du scanner : {msg}',
	'plugin.attachments-clamav.read_failed'         => 'Impossible de lire le fichier « {name} »',
];
