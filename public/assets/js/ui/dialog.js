/**
 * Boîtes de dialogue Bootstrap — remplacement de alert/confirm/prompt natifs.
 * Chaque appel crée une modale éphémère, détruite à la fermeture.
 * La Promise résout après hidden.bs.modal → pas d'empilement de backdrops
 * lors de dialogues séquentiels.
 *
 * dialog.alert(message, opts)   → Promise<void>
 * dialog.confirm(message, opts) → Promise<boolean>     (Échap/backdrop = false)
 * dialog.prompt(message, opts)  → Promise<string|null> (Échap/backdrop/Annuler = null)
 * dialog.pick(items, opts)      → Promise<value|null>  (Échap/backdrop/Annuler = null)
 * dialog.form(opts)             → Promise<{name:value}|null>
 *
 * opts : { title, variant: info|success|warning|danger, icon, okLabel, cancelLabel }
 * prompt : + { inputType, defaultValue, placeholder }
 * pick   : items = [{ label, value, icon?, depth?, disabled? }] ; + { searchable }
 *          (okLabel: null interne — pas de bouton OK en mode liste)
 * form   : + { fields: [{ name, label?, type: 'text'|'select',
 *            options?: [{label,value,depth?,disabled?}], defaultValue?, placeholder? }] }
 *          (option.depth indente le label par des espaces insécables)
 */
import { t } from '../i18n.js';

const VARIANTS = {
	info:    { btn: 'btn-primary', icon: 'fa-circle-info' },
	success: { btn: 'btn-success', icon: 'fa-circle-check' },
	warning: { btn: 'btn-warning', icon: 'fa-triangle-exclamation' },
	danger:  { btn: 'btn-danger',  icon: 'fa-triangle-exclamation' },
};

function el(tag, className, text) {
	const e = document.createElement(tag);
	if (className) { e.className = className; }
	if (text !== undefined) { e.textContent = text; }
	return e;
}

function open({ title = t('dialog.info'), message = '', variant = 'info',
	icon = null, input = null, okLabel = 'OK', cancelLabel = null,
	list = null, searchable = false, fields = null }) {
	return new Promise(resolve => {
		const v = VARIANTS[variant] || VARIANTS.info;
		let result = (input || list || fields) ? null : (cancelLabel !== null ? false : undefined);

		const modal = el('div', 'modal fade');
		modal.tabIndex = -1;
		const content = el('div', 'modal-content');

		const header = el('div', 'modal-header');
		const h5 = el('h5', 'modal-title');
		h5.append(el('i', `fa-solid ${icon || v.icon} me-2`), document.createTextNode(title));
		header.append(h5);

		const body = el('div', 'modal-body');
		let filterEl = null;
		let firstEnabled = null;
		if (list) {
			const group = el('div', 'list-group');
			group.style.maxHeight = '50vh';
			group.style.overflow = 'auto';
			const entries = list.map(item => {
				const a = el('a', 'list-group-item list-group-item-action');
				a.href = '#';
				a.style.paddingLeft = (12 + (item.depth || 0) * 16) + 'px';
				if (item.icon) { a.append(el('i', `fa-solid ${item.icon} me-2 text-muted`)); }
				a.append(document.createTextNode(item.label));
				if (item.disabled) {
					a.classList.add('disabled');
					a.tabIndex = -1;
				} else {
					a.onclick = e => { e.preventDefault(); done(item.value); };
					if (!firstEnabled) { firstEnabled = a; }
				}
				group.append(a);
				return { node: a, item };
			});
			if (searchable) {
				filterEl = el('input', 'form-control form-control-sm mb-2');
				filterEl.type = 'search';
				filterEl.placeholder = t('app.search');
				filterEl.addEventListener('input', () => {
					const q = filterEl.value.trim().toLowerCase();
					for (const { node, item } of entries) {
						node.style.display = item.label.toLowerCase().includes(q) ? '' : 'none';
					}
				});
				filterEl.addEventListener('keydown', e => {
					if (e.key !== 'Enter') { return; }
					e.preventDefault();
					const hit = entries.find(({ node, item }) =>
						!item.disabled && node.style.display !== 'none');
					if (hit) { done(hit.item.value); }
				});
				body.append(filterEl);
			}
			body.append(group);
		} else {
			const msg = el('div');
			msg.style.whiteSpace = 'pre-wrap';
			msg.textContent = message;
			body.append(msg);
		}

		let inputEl = null;
		if (input) {
			inputEl = el('input', 'form-control mt-2');
			inputEl.type = input.inputType || 'text';
			inputEl.value = input.defaultValue || '';
			if (input.placeholder) { inputEl.placeholder = input.placeholder; }
			if (inputEl.type === 'password') { inputEl.autocomplete = 'off'; }
			body.append(inputEl);
		}

		const fieldEls = [];
		if (fields) {
			for (const f of fields) {
				const group = el('div', 'mb-2');
				if (f.label) { group.append(el('label', 'form-label form-label-sm mb-1', f.label)); }
				let ctrl;
				if (f.type === 'select') {
					ctrl = el('select', 'form-select form-select-sm');
					for (const o of f.options || []) {
						const opt = el('option', null, '  '.repeat(o.depth || 0) + o.label);
						opt.value = o.value;
						if (o.disabled) { opt.disabled = true; }
						ctrl.append(opt);
					}
					ctrl.value = f.defaultValue ?? '';
				} else {
					ctrl = el('input', 'form-control form-control-sm');
					ctrl.type = f.inputType || 'text';
					ctrl.value = f.defaultValue || '';
					if (f.placeholder) { ctrl.placeholder = f.placeholder; }
					ctrl.addEventListener('keydown', e => {
						if (e.key === 'Enter') { e.preventDefault(); done(collect()); }
					});
				}
				group.append(ctrl);
				body.append(group);
				fieldEls.push({ name: f.name, ctrl });
			}
		}
		const collect = () => {
			const out = {};
			for (const { name, ctrl } of fieldEls) { out[name] = ctrl.value; }
			return out;
		};

		const footer = el('div', 'modal-footer');
		if (cancelLabel !== null) {
			const cancel = el('button', 'btn btn-sm btn-outline-secondary', cancelLabel);
			cancel.type = 'button';
			cancel.onclick = () => done(input || list || fields ? null : false);
			footer.append(cancel);
		}
		let ok = null;
		if (okLabel !== null) {
			ok = el('button', `btn btn-sm ${v.btn}`, okLabel);
			ok.type = 'button';
			ok.onclick = () => done(fields ? collect() : (input ? inputEl.value : true));
			footer.append(ok);
		}

		content.append(header, body, footer);
		const dialogEl = el('div', 'modal-dialog');
		dialogEl.append(content);
		modal.append(dialogEl);
		document.body.append(modal);

		const m = new bootstrap.Modal(modal);
		const done = val => { result = val; m.hide(); };
		modal.addEventListener('hidden.bs.modal', () => {
			m.dispose();
			modal.remove();
			resolve(result);
		});
		modal.addEventListener('shown.bs.modal', () => {
			(inputEl || fieldEls[0]?.ctrl || filterEl || ok || firstEnabled || modal).focus();
			if (inputEl && input.defaultValue) { inputEl.select(); }
		});
		if (inputEl) {
			inputEl.addEventListener('keydown', e => {
				if (e.key === 'Enter') { e.preventDefault(); done(inputEl.value); }
			});
		}
		m.show();
	});
}

export const dialog = {
	alert:   (message, opts = {}) => open({ ...opts, message }),
	confirm: (message, opts = {}) => open({ ...opts, message, cancelLabel: opts.cancelLabel ?? t('common.cancel') }),
	prompt:  (message, opts = {}) => open({ ...opts, message, cancelLabel: opts.cancelLabel ?? t('common.cancel'), input: opts }),
	pick:    (items, opts = {}) => open({ ...opts, list: items, okLabel: null, cancelLabel: opts.cancelLabel ?? t('common.cancel') }),
	form:    (opts = {}) => open({ ...opts, cancelLabel: opts.cancelLabel ?? t('common.cancel') }),
};
