<?php
declare(strict_types=1);
/** Nederlandstalig woordenboek van de plugin report-abuse. */
return [
	'plugin.report-abuse.label' => 'Phishing-melding',
	'plugin.report-abuse.description' => 'Knop "Melden" in de actiebalk: stuurt aangevinkte berichten als .eml-bijlagen naar een door admin vastgelegd abuse-adres',
	'plugin.report-abuse.action' => 'Melden',
	'plugin.report-abuse.confirm' => '{n} bericht(en) melden aan het abuse-adres? Ze worden als .eml-bijlagen doorgestuurd.',
	'plugin.report-abuse.done' => '{n} bericht(en) gemeld',
	'plugin.report-abuse.done_partial' => '{n} gemeld, {f} mislukt',
	'plugin.report-abuse.not_configured' => 'Melden is niet geconfigureerd (abuse-adres ontbreekt)',
	'plugin.report-abuse.bad_items' => 'Ongeldige selectie',
	'plugin.report-abuse.failed' => 'De melding is mislukt',
	'plugin.report-abuse.body' => "Phishing-melding.\r\n\r\nAccount: {account}\r\nMap: {folder}\r\nDatum: {date}\r\n\r\nHet gemelde bericht is bijgevoegd als .eml-bestand.",
	'plugin.report-abuse.f.abuse_address' => 'Abuse-adres',
	'plugin.report-abuse.f.abuse_address.help' => 'Ontvanger van meldingen (bijv. abuse@domein.tld of een SOC-adres). Leeg = plugin inactief',
	'plugin.report-abuse.f.subject_prefix' => 'Onderwerpvoorvoegsel',
	'plugin.report-abuse.f.subject_prefix.help' => 'Voorgevoegd aan het onderwerp van het gemelde bericht',
	'plugin.report-abuse.f.mark_spam' => 'Ook als spam markeren',
	'plugin.report-abuse.f.mark_spam.help' => 'Na de melding worden de berichten als spam gemarkeerd (verplaatsen/flag volgens account) - voedt ook het spamleren',
];
