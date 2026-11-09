<?php
declare(strict_types=1);
/** Słownik polski wtyczki report-abuse. */
return [
	'plugin.report-abuse.label' => 'Zgłaszanie phishingu',
	'plugin.report-abuse.description' => 'Przycisk "Zgłoś" na pasku akcji: przesyła zaznaczone wiadomości jako załączniki .eml na adres abuse zarządzany przez administratora',
	'plugin.report-abuse.action' => 'Zgłoś',
	'plugin.report-abuse.confirm' => 'Zgłosić {n} wiadomość(i) na adres abuse? Zostaną przesłane jako załączniki .eml.',
	'plugin.report-abuse.done' => 'Zgłoszono {n} wiadomość(i)',
	'plugin.report-abuse.done_partial' => '{n} zgłoszono, {f} niepowodzenie',
	'plugin.report-abuse.not_configured' => 'Zgłaszanie nie jest skonfigurowane (brak adresu abuse)',
	'plugin.report-abuse.bad_items' => 'Nieprawidłowy wybór',
	'plugin.report-abuse.failed' => 'Zgłoszenie nie powiodło się',
	'plugin.report-abuse.body' => "Zgłoszenie phishingu.\r\n\r\nKonto: {account}\r\nFolder: {folder}\r\nData: {date}\r\n\r\nZgłoszona wiadomość jest załączona jako plik .eml.",
	'plugin.report-abuse.f.abuse_address' => 'Adres abuse',
	'plugin.report-abuse.f.abuse_address.help' => 'Odbiorca zgłoszeń (np. abuse@domena.tld lub adres SOC). Puste = wtyczka nieaktywna',
	'plugin.report-abuse.f.subject_prefix' => 'Prefiks tematu',
	'plugin.report-abuse.f.subject_prefix.help' => 'Dodawany przed tematem zgłaszanej wiadomości',
	'plugin.report-abuse.f.mark_spam' => 'Oznacz także jako spam',
	'plugin.report-abuse.f.mark_spam.help' => 'Po zgłoszeniu oznacza wiadomości jako spam (przeniesienie/flaga wg konta) - zasila także uczenie antyspamowe',
];
