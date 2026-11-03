/**
 * MXWebMail - client API.
 * Réponse uniforme { ok, data | error }. CSRF en header X-CSRF-Token.
 */
import { t } from './i18n.js';
import { toast } from './ui/toast.js';

const cfg = window.MXWM || { base: '', csrf: '' };

export class ApiError extends Error {
	constructor(code, message, status) {
		super(message);
		this.code = code;
		this.status = status;
	}
}

// Mode maintenance : le serveur répond 503 {code:'maintenance'} sur /api/*.
// On recharge - le serveur sert alors la page maintenance sur '/'. Un seul
// reload même si plusieurs requêtes échouent en parallèle.
let maintenanceRedirect = false;
function maintenanceReload() {
	if (maintenanceRedirect) { return; }
	maintenanceRedirect = true;
	location.reload();
}

async function request(method, path, body = null, formData = null) {
	const opts = {
		method,
		headers: { 'X-CSRF-Token': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
		credentials: 'same-origin',
	};
	if (formData) {
		opts.body = formData;
	} else if (body !== null) {
		opts.headers['Content-Type'] = 'application/json';
		opts.body = JSON.stringify(body);
	}
	const res = await fetch(cfg.base + '/api' + path, opts);
	const json = await res.json().catch(() => ({ ok: false, error: { code: 'http', message: 'HTTP ' + res.status } }));
	if (!json.ok) {
		// 'updating' : mise à jour en cours - reload vers la page maintenance
		// (qui sonde /api/ping et revient seule à la fin de l'opération).
		if (json.error?.code === 'maintenance' || json.error?.code === 'updating') { maintenanceReload(); }
		throw new ApiError(json.error?.code || 'error', json.error?.message || t('errors.generic'), res.status);
	}
	return json.data;
}

/**
 * Upload multipart via XHR - seul moyen d'avoir la progression
 * (xhr.upload.onprogress) : fetch ne l'expose pas. La promesse retournée
 * expose .abort() pour annuler le transfert en cours.
 */
function upload(path, formData, onProgress = null) {
	let xhr;
	const p = new Promise((resolve, reject) => {
		xhr = new XMLHttpRequest();
		xhr.open('POST', cfg.base + '/api' + path, true);
		xhr.setRequestHeader('X-CSRF-Token', cfg.csrf);
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		if (onProgress) {
			xhr.upload.onprogress = (e) => {
				if (e.lengthComputable && e.total > 0) {
					onProgress(Math.round(e.loaded / e.total * 100));
				}
			};
		}
		xhr.onload = () => {
			let json;
			try {
				json = JSON.parse(xhr.responseText);
			} catch {
				json = { ok: false, error: { code: 'http', message: 'HTTP ' + xhr.status } };
			}
			if (!json.ok) {
				if (json.error?.code === 'maintenance') { maintenanceReload(); }
				reject(new ApiError(json.error?.code || 'error',
					json.error?.message || t('errors.generic'), xhr.status));
			} else {
				resolve(json.data);
			}
		};
		xhr.onerror = () => reject(new ApiError('http', 'HTTP ' + xhr.status, xhr.status));
		xhr.onabort = () => reject(new ApiError('aborted', t('errors.generic'), xhr.status));
		xhr.ontimeout = () => reject(new ApiError('http', 'HTTP ' + xhr.status, xhr.status));
		xhr.send(formData);
	});
	p.abort = () => xhr?.abort();
	return p;
}

export const api = {
	get: (path) => request('GET', path),
	post: (path, body = {}) => request('POST', path, body),
	upload: (path, formData, onProgress = null) => upload(path, formData, onProgress),
};

/** Encode un nom de dossier IMAP en base64url pour les routes /folders/{name}. */
export function b64url(str) {
	return btoa(unescape(encodeURIComponent(str)))
		.replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', '');
}

export function base() { return cfg.base; }
export function csrf() { return cfg.csrf; }

// Rejets non gérés (méthode save*/delete* sans catch) : signaler plutôt
// que silence. api.js est chargé partout - y compris login/admin-login
// qui n'ont pas le bundle JS Bootstrap > console dans ce cas.
// 'aborted' = annulation volontaire (api.upload abort), jamais une erreur.
window.addEventListener('unhandledrejection', (e) => {
	const reason = e.reason;
	if (reason?.code === 'aborted') { return; }
	const msg = reason?.message || String(reason || t('errors.generic'));
	if (window.bootstrap?.Toast) { toast.error(msg); }
	else { console.warn('Unhandled rejection:', reason); }
});
