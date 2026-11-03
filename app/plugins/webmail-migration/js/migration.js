/**
 * Page admin du plugin webmail-migration (hook admin.mounts, section "page").
 * Chargé dynamiquement par le binding pluginMount - ctx.admin = true.
 * API : /api/admin/plugin/webmail-migration/{upload|scan|run|reset}
 */
export default function mount(el, ctx) {
	const t = ctx.t;
	const K = 'plugin.webmail-migration.';
	const call = (action, body) => ctx.api.post(`/admin/plugin/webmail-migration/${action}`, body || {});
	const esc = (s) => String(s ?? '').replace(/[&<>"']/g,
		c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

	el.innerHTML = `
		<h5>${t(K + 'title')}</h5>
		<div class="card mb-3"><div class="card-body">
			<p class="text-muted small">${t(K + 'intro')}</p>
			<div class="row g-2 align-items-center mb-2">
				<div class="col-auto"><label class="form-label mb-0 small">${t(K + 'source_path')}</label></div>
				<div class="col"><input id="sm-path" class="form-control form-control-sm" placeholder="${t(K + 'path_placeholder')}"></div>
			</div>
			<div class="row g-2 align-items-center mb-2">
				<div class="col-auto"><label class="form-label mb-0 small">${t(K + 'source_zip')}</label></div>
				<div class="col-auto"><input id="sm-file" type="file" accept=".zip,application/zip" class="form-control form-control-sm"></div>
				<div class="col-auto"><button id="sm-upload" class="btn btn-sm btn-outline-secondary">${t(K + 'upload')}</button></div>
				<div class="col small text-muted" id="sm-upload-info"></div>
			</div>
			<div class="d-flex gap-2">
				<button id="sm-scan" class="btn btn-sm btn-primary">${t(K + 'scan')}</button>
				<button id="sm-run" class="btn btn-sm btn-danger" disabled>${t(K + 'run')}</button>
				<button id="sm-reset" class="btn btn-sm btn-outline-secondary ms-auto">${t(K + 'reset')}</button>
			</div>
		</div></div>
		<div id="sm-report"></div>
		<div class="card mt-3"><div class="card-body small text-muted">
			<p class="mb-1"><i class="fa-solid fa-circle-info me-1"></i>${t(K + 'note_passwords')}</p>
			<p class="mb-0"><i class="fa-solid fa-circle-info me-1"></i>${t(K + 'note_sieve')}</p>
		</div></div>`;

	const $ = (id) => el.querySelector('#' + id);
	let uploadId = '';
	const err = (m) => { if (m) { ctx.toast.error(m); } };

	const source = () => uploadId ? { upload: uploadId } : { path: $('sm-path').value.trim() };

	function badge(ok, okTxt, koTxt) {
		return `<span class="badge ${ok ? 'text-bg-success' : 'text-bg-secondary'}">${esc(ok ? okTxt : koTxt)}</span>`;
	}

	function renderScan(r) {
		const h = [];
		h.push(`<div class="card mb-3"><div class="card-body">
			<h6>${t(K + 'domains')} <span class="badge text-bg-secondary">${r.domains.length}</span></h6>`);
		if (r.domains.length) {
			h.push(`<table class="table table-sm align-middle"><thead><tr>
				<th>${t('common.name')}</th><th>IMAP</th><th>SMTP</th><th>${t(K + 'col_status')}</th></tr></thead><tbody>`);
			for (const d of r.domains) {
				h.push(`<tr><td>${esc(d.name)}</td>
					<td class="small">${esc(d.imap_host)}:${esc(d.imap_port)} ${esc(d.imap_encryption)}</td>
					<td class="small">${esc(d.smtp_host)}:${esc(d.smtp_port)} ${esc(d.smtp_encryption)}</td>
					<td>${badge(!d.exists, t(K + 'dom_new'), t(K + 'dom_exists'))}</td></tr>`);
			}
			h.push('</tbody></table>');
		}
		h.push('</div></div>');

		h.push(`<div class="card mb-3"><div class="card-body">
			<h6>${t(K + 'users')} <span class="badge text-bg-secondary">${r.users.length}</span></h6>`);
		if (r.users.length) {
			h.push(`<table class="table table-sm align-middle"><thead><tr>
				<th>Email</th><th>${t(K + 'col_status')}</th><th>${t(K + 'col_contacts')}</th>
				<th>${t(K + 'col_identities')}</th><th>${t(K + 'col_pgp')}</th>
				<th>${t(K + 'col_settings')}</th><th></th></tr></thead><tbody>`);
			for (const u of r.users) {
				h.push(`<tr><td>${esc(u.email)}</td>
					<td>${badge(u.exists, t(K + 'user_exists'), t(K + 'user_missing'))}</td>
					<td>${u.contacts}</td><td>${u.identities}</td><td>${u.pgp}</td>
					<td>${u.settings ? '✓' : '-'}</td>
					<td class="small text-muted">${u.additional && u.additional.length
						? esc(t(K + 'additional_accounts', { n: u.additional.length })) : ''}</td></tr>`);
			}
			h.push('</tbody></table>');
		}
		h.push('</div></div>');
		$('sm-report').innerHTML = h.join('');
		$('sm-run').disabled = false;
	}

	function contactCell(c) {
		if (!c) { return '-'; }
		return esc(t(K + 'contacts_report', { imported: c.imported, merged: c.merged, skipped: c.skipped }));
	}

	function renderRun(r) {
		const h = [`<div class="card mb-3"><div class="card-body">
			<h6>${t(K + 'done_title')}</h6>
			<p class="small">${t(K + 'domains')}: ${r.domains.added} ${t(K + 'imported')}, ${r.domains.skipped} ${t(K + 'skipped')}</p>`];
		if (r.users.length) {
			h.push(`<table class="table table-sm align-middle"><thead><tr>
				<th>Email</th><th>${t(K + 'col_contacts')}</th><th>${t(K + 'col_identities')}</th>
				<th>${t(K + 'col_settings')}</th><th>${t(K + 'col_pgp')}</th><th>${t(K + 'warnings')}</th></tr></thead><tbody>`);
			for (const u of r.users) {
				if (u.skipped) {
					h.push(`<tr class="text-muted"><td>${esc(u.email)}</td><td colspan="5">${t(K + 'user_missing')}</td></tr>`);
					continue;
				}
				h.push(`<tr><td>${esc(u.email)}</td>
					<td class="small">${contactCell(u.contacts)}</td>
					<td>${u.identities}</td>
					<td class="small">${esc((u.settings || []).join(', ')) || '-'}</td>
					<td>${u.pgp}</td>
					<td class="small text-warning">${esc((u.warnings || []).join(' ; '))}</td></tr>`);
			}
			h.push('</tbody></table>');
		}
		if (r.skipped_users && r.skipped_users.length) {
			h.push(`<p class="small text-muted">${t(K + 'skipped_users')}: ${esc(r.skipped_users.join(', '))}</p>`);
		}
		if (r.warnings && r.warnings.length) {
			h.push(`<p class="small text-warning">${esc(r.warnings.join(' ; '))}</p>`);
		}
		h.push('</div></div>');
		$('sm-report').innerHTML = h.join('');
	}

	$('sm-upload').onclick = async () => {
		err('');
		const f = $('sm-file').files && $('sm-file').files[0];
		if (!f) { return; }
		const fd = new FormData();
		fd.append('file', f);
		try {
			const r = await ctx.api.upload('/admin/plugin/webmail-migration/upload', fd);
			uploadId = r.upload;
			$('sm-upload-info').textContent = `${r.files} fichiers > ${r.root}`;
			$('sm-path').value = '';
		} catch (e) { err(e.message); }
	};

	$('sm-scan').onclick = async () => {
		err('');
		$('sm-scan').disabled = true;
		try {
			renderScan(await call('scan', source()));
		} catch (e) { err(e.message); }
		$('sm-scan').disabled = false;
	};

	$('sm-run').onclick = async () => {
		err('');
		$('sm-run').disabled = true;
		try {
			renderRun(await call('run', source()));
		} catch (e) { err(e.message); $('sm-run').disabled = false; }
	};

	$('sm-reset').onclick = async () => {
		err('');
		try {
			const r = await call('reset');
			uploadId = '';
			$('sm-upload-info').textContent = `${r.purged} ✓`;
		} catch (e) { err(e.message); }
	};
}
