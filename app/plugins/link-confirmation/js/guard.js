/**
 * link-confirmation — modale de confirmation avant ouverture d'un lien du
 * corps d'un message (#mail-body) ou d'un .eml aperçu (#eml-preview-body).
 * Config injectée par le hook app.head du plugin (window.MXWM_LINKCONFIRM).
 * Chargé en defer : document.body et bootstrap existent déjà ; en revanche
 * window.MXWM.t (module ES i18n.js, après nous dans le document) n'est pas
 * encore posé → les libellés sont résolus paresseusement à l'affichage.
 */
(function () {
	'use strict';

	var cfg = window.MXWM_LINKCONFIRM;
	if (!cfg || !document.body || !window.bootstrap) {
		return;
	}

	function tt(k) {
		var t = window.MXWM && window.MXWM.t;
		return t ? t('plugin.link-confirmation.' + k) : k;
	}

	var SKIP_KEY = 'mxwm.linkconfirm.skip';
	var skipped = {};
	try {
		(JSON.parse(localStorage.getItem(SKIP_KEY) || '[]') || [])
			.forEach(function (d) { skipped[d] = true; });
	} catch (e) { /* localStorage indisponible : mémorisation ignorée */ }

	var IP_HOST = /^(?:\d{1,3}\.){3}\d{1,3}$|^\[[0-9a-f:]+\]$/i;
	var TEXT_URL = /^(?:https?:\/\/)?(?:[\w-]+\.)+[a-z]{2,}(?:[/?:#]\S*)?$/i;
	var HTTP = /^https?:$/;

	/** host === domaine ou sous-domaine — suffixe « point collé » uniquement. */
	function hostIn(host, domain) {
		return host === domain || host.slice(-domain.length - 1) === '.' + domain;
	}

	function trusted(host) {
		if (skipped[host]) { return true; }
		var list = cfg.whitelist || [];
		for (var i = 0; i < list.length; i++) {
			if (list[i] && hostIn(host, list[i])) { return true; }
		}
		return false;
	}

	/** Signaux de leurre → clés de raisons (vide = bénin). */
	function suspicion(a, url) {
		var proto = url.protocol.toLowerCase();
		if (!HTTP.test(proto)) {
			// Schéma non filtré par le sanitizer (défense en profondeur) ;
			// mailto:/tel: ne sont pas « inhabituels ».
			return proto === 'mailto:' || proto === 'tel:' ? [] : ['reason_scheme'];
		}
		var r = [], host = url.hostname.toLowerCase();
		var text = (a.textContent || '').trim();
		if (TEXT_URL.test(text)) {
			var thost = '';
			try {
				thost = new URL(/^https?:\/\//i.test(text) ? text : 'https://' + text)
					.hostname.toLowerCase();
			} catch (e) {}
			if (thost && thost !== host && !hostIn(thost, host) && !hostIn(host, thost)) {
				r.push('reason_text_mismatch');
			}
		}
		if (IP_HOST.test(host)) { r.push('reason_ip'); }
		if (host.indexOf('xn--') !== -1) { r.push('reason_punycode'); }
		if (url.username || url.password) { r.push('reason_creds'); }
		return r;
	}

	var modal = null, el = null;
	var pending = { href: '', proto: '', host: '' };

	function buildModal() {
		el = document.createElement('div');
		el.className = 'modal fade';
		el.id = 'link-confirm-modal';
		el.tabIndex = -1;
		el.innerHTML =
			'<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
			+ '<div class="modal-header">'
			+ '<h5 class="modal-title"></h5>'
			+ '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>'
			+ '</div>'
			+ '<div class="modal-body">'
			+ '<p class="lc-lead"></p>'
			+ '<p class="mb-2"><code class="lc-url text-break"></code></p>'
			+ '<div class="alert alert-danger small lc-reasons" role="alert"'
			+ ' style="display:none"><ul class="mb-0 ps-3"></ul></div>'
			+ '<div class="form-check lc-remember">'
			+ '<input class="form-check-input" type="checkbox" id="lc-remember">'
			+ '<label class="form-check-label" for="lc-remember"></label>'
			+ '</div>'
			+ '</div>'
			+ '<div class="modal-footer">'
			+ '<button type="button" class="btn btn-sm btn-outline-secondary lc-cancel"'
			+ ' data-bs-dismiss="modal"></button>'
			+ '<button type="button" class="btn btn-sm btn-primary lc-continue"></button>'
			+ '</div></div></div>';
		document.body.appendChild(el);

		el.querySelector('.lc-continue').addEventListener('click', function () {
			if (cfg.remember && HTTP.test(pending.proto)
				&& el.querySelector('#lc-remember').checked) {
				skipped[pending.host] = true;
				try {
					localStorage.setItem(SKIP_KEY, JSON.stringify(Object.keys(skipped)));
				} catch (e) {}
			}
			if (HTTP.test(pending.proto)) {
				// Reproduit le target=_blank + noopener posé par le sanitizer.
				window.open(pending.href, '_blank', 'noopener');
			} else {
				// mailto:/tel:/exotique : pas d'onglet vide parasite.
				window.location.href = pending.href;
			}
			modal.hide();
		});
		modal = new bootstrap.Modal(el);
	}

	function confirmLink(href, url, reasons) {
		if (!el) { buildModal(); }
		// Libellés (re-)posés à chaque ouverture : MXWM.t arrive via module ES.
		el.querySelector('.modal-title').textContent = tt('modal.title');
		el.querySelector('.lc-lead').textContent = tt('modal.body');
		el.querySelector('.lc-url').textContent = href;
		el.querySelector('.lc-cancel').textContent = tt('modal.cancel');
		el.querySelector('.lc-continue').textContent = tt('modal.continue');
		var box = el.querySelector('.lc-reasons');
		var ul = box.querySelector('ul');
		ul.textContent = '';
		reasons.forEach(function (r) {
			var li = document.createElement('li');
			li.textContent = tt('modal.' + r);
			ul.appendChild(li);
		});
		box.style.display = reasons.length ? '' : 'none';
		var rem = el.querySelector('.lc-remember');
		rem.style.display = cfg.remember && HTTP.test(url.protocol) ? '' : 'none';
		rem.querySelector('label').textContent = tt('modal.remember');
		rem.querySelector('input').checked = false;
		pending = {
			href: href,
			proto: url.protocol.toLowerCase(),
			host: url.hostname.toLowerCase(),
		};
		modal.show();
	}

	/** <a href> cliqué à l'intérieur des corps de mails rendus, sinon null. */
	function anchor(e) {
		var a = e.target instanceof Element ? e.target.closest('a[href]') : null;
		return a && (a.closest('#mail-body') || a.closest('#eml-preview-body')) ? a : null;
	}

	function inspect(e) {
		var a = anchor(e);
		if (!a) { return; }
		var url;
		try { url = new URL(a.href); } catch (err) { return; }
		var proto = url.protocol.toLowerCase();
		if ((proto === 'mailto:' || proto === 'tel:') && !cfg.warn_mailto) { return; }
		var reasons = suspicion(a, url);
		if (HTTP.test(proto)
			&& (trusted(url.hostname.toLowerCase())
				|| (cfg.mode === 'suspicious' && !reasons.length))) {
			return;
		}
		e.preventDefault();
		confirmLink(a.href, url, reasons);
	}

	// click : bouton gauche, ctrl/cmd+clic et Entrée clavier (button === 0).
	document.addEventListener('click', function (e) {
		if (e.button === 0) { inspect(e); }
	});
	// auxclick : clic milieu — preventDefault annule l'ouverture d'onglet.
	document.addEventListener('auxclick', function (e) {
		if (e.button === 1) { inspect(e); }
	});
	// mousedown bouton 1 : coupe l'autoscroll parasite sur les liens ciblés.
	document.addEventListener('mousedown', function (e) {
		if (e.button === 1 && anchor(e)) { e.preventDefault(); }
	});
})();
