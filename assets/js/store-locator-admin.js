(function () {
	'use strict';
	const root = document.getElementById('sl-import-status');
	if (!root) return;

	const token  = root.dataset.token;
	const total  = parseInt(root.dataset.total, 10) || 0;
	const force  = root.dataset.force === '1';
	const bar    = document.getElementById('sl-import-bar');
	const msg    = document.getElementById('sl-import-msg');
	const summary = document.getElementById('sl-import-summary');
	const download = document.getElementById('sl-import-download');

	let offset = 0;

	async function tick() {
		const body = new FormData();
		body.append('action', 'sl_import_chunk');
		body.append('nonce', SL_IMPORT.nonce);
		body.append('token', token);
		body.append('offset', String(offset));
		if (force) body.append('force', '1');

		try {
			const res = await fetch(SL_IMPORT.ajax_url, { method: 'POST', body, credentials: 'same-origin' });
			const json = await res.json();
			if (!json.success) throw new Error((json.data && json.data.message) || 'Errore');

			offset = json.data.offset;
			bar.value = offset;
			msg.textContent = SL_IMPORT.strings.progress
				.replace('%1$d', offset)
				.replace('%2$d', json.data.total || total);

			if (json.data.done) {
				msg.textContent = SL_IMPORT.strings.done;
				const s = json.data.stats || {};
				summary.style.display = '';
				summary.innerHTML =
					'<li>Created: '   + (s.created || 0)        + '</li>' +
					'<li>Updated: '   + (s.updated || 0)        + '</li>' +
					'<li>Geocoded: '  + (s.geocoded || 0)       + '</li>' +
					'<li>Geocode failed: ' + (s.geocode_failed || 0) + '</li>' +
					'<li>Skipped: '   + (s.skipped || 0)        + '</li>';
				if (json.data.download_url) {
					download.innerHTML = '<a href="' + json.data.download_url + '" class="button">' + SL_IMPORT.strings.download + '</a>';
				}
				return;
			}
			tick();
		} catch (e) {
			msg.textContent = SL_IMPORT.strings.failed + ' ' + e.message;
		}
	}

	msg.textContent = SL_IMPORT.strings.starting;
	tick();
})();
