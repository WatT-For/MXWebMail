/**
 * Filtres Sieve — document « règles » porté par le marqueur MARKER en
 * première ligne du script (miroir de MXWebMail\Sieve\ScriptBuilder).
 * Module pur (pas de Knockout) : descripteurs, gabarits, lecture du marqueur.
 */

export const MARKER = '# MXWM-RULES ';

/** Doc règles si la source est gérée par l'assistant, sinon null. */
export function parseMarker(source) {
	const line = (source || '').split(/\r?\n/, 1)[0] || '';
	if (!line.startsWith(MARKER)) { return null; }
	try {
		const doc = JSON.parse(line.slice(MARKER.length));
		return doc && Array.isArray(doc.rules) ? doc : null;
	} catch { return null; }
}

// Descripteurs : key = clé i18n ; module = extension Sieve requise
// (capacité « SIEVE » du serveur, minuscules — SieveClient::Modules()).
export const FIELDS = [
	{ value: 'from',    key: 'settings.filters.field_from' },
	{ value: 'to',      key: 'settings.filters.field_to' },
	{ value: 'cc',      key: 'settings.filters.field_cc' },
	{ value: 'subject', key: 'settings.filters.field_subject' },
	{ value: 'header',  key: 'settings.filters.field_header' },
];

export const OPS = [
	{ value: 'contains',     key: 'settings.filters.op_contains' },
	{ value: 'is',           key: 'settings.filters.op_is' },
	{ value: 'not_contains', key: 'settings.filters.op_not_contains' },
	{ value: 'starts_with',  key: 'settings.filters.op_starts_with' },
	{ value: 'regex',        key: 'settings.filters.op_regex', module: 'regex' },
];

export const ACTIONS = [
	{ value: 'fileinto', key: 'settings.filters.act_fileinto', arg: 'folder', module: 'fileinto' },
	{ value: 'redirect', key: 'settings.filters.act_redirect', arg: 'email' },
	{ value: 'keep',     key: 'settings.filters.act_keep' },
	{ value: 'discard',  key: 'settings.filters.act_discard' },
	{ value: 'seen',     key: 'settings.filters.act_seen', module: 'imap4flags' },
	{ value: 'reject',   key: 'settings.filters.act_reject', arg: 'text', module: 'reject' },
	{ value: 'stop',     key: 'settings.filters.act_stop' },
];

export const TEMPLATES = [
	{ value: 'empty',    key: 'settings.filters.tpl_empty' },
	{ value: 'antispam', key: 'settings.filters.tpl_antispam' },
	{ value: 'sender',   key: 'settings.filters.tpl_sender' },
];

export function newRule() {
	return { match: 'all',
		conditions: [{ field: 'from', op: 'contains', value: '' }],
		actions: [{ type: 'keep' }] };
}

/** Règles d'un gabarit. ctx.junk = fullName du dossier Junk détecté. */
export function templateRules(name, ctx = {}) {
	switch (name) {
		case 'antispam':
			return [{ match: 'all',
				conditions: [{ field: 'header', op: 'is', value: 'YES', header: 'X-Spam-Flag' }],
				actions: [{ type: 'fileinto', arg: ctx.junk || 'Junk' }, { type: 'stop' }] }];
		case 'sender':
			return [{ match: 'all',
				conditions: [{ field: 'from', op: 'contains', value: '' }],
				actions: [{ type: 'fileinto', arg: 'INBOX' }] }];
		default:
			return [newRule()];
	}
}
