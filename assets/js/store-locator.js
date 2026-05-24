/* global EmblaCarousel, SL_DATA */
(function () {
	'use strict';

	// ---------- Google Maps async ready ----------
	let resolveMap;
	const mapsReady = new Promise(function (resolve) {
		resolveMap = resolve;
		if (window.google && window.google.maps && window.google.maps.Map) {
			resolve();
		}
	});
	window.slInitMap = function () { resolveMap(); };

	// ---------- State ----------
	const state = {
		map: null,
		stores: [],         // [{...store, marker, _distance?}]
		searchPoint: null,  // { lat, lng } | null
		searchMarker: null,
		radius: 25,
		activeId: null,
		embla: null,
		visible: [],        // currently rendered stores (in card order)
	};

	const $ = (sel, root) => (root || document).querySelector(sel);
	const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

	// ---------- Utilities ----------
	function haversineKm(a, b) {
		const R = 6371;
		const toRad = d => d * Math.PI / 180;
		const dLat = toRad(b.lat - a.lat);
		const dLng = toRad(b.lng - a.lng);
		const lat1 = toRad(a.lat);
		const lat2 = toRad(b.lat);
		const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
		return 2 * R * Math.asin(Math.sqrt(h));
	}

	function formatKm(km) {
		return km.toFixed(2).replace('.', ',');
	}

	function escapeHtml(str) {
		return String(str || '').replace(/[&<>"']/g, c => ({
			'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
		})[c]);
	}

	// ---------- Cards ----------
	function cardHtml(store) {
		const dist = (store._distance != null)
			? `<small>${escapeHtml(SL_DATA.strings.distance.replace('%s', formatKm(store._distance)))}</small>`
			: '<small>&nbsp;</small>';
		const phone = store.phone
			? `<a class="cell leaf" href="tel:${escapeHtml(store.phone_tel || store.phone)}"><i class="phone" aria-hidden="true"></i><span class="subspace-h">${escapeHtml(store.phone)}</span></a>`
			: '';
		const addressParts = [
			store.address,
			[store.cap, store.city].filter(Boolean).join(' '),
			store.province,
			store.country
		].filter(Boolean).join(', ');
		return `
			<div class="store-card">
				<div class="slide-txt">
					${dist}
					<h3>${escapeHtml(store.name)}</h3>
					${phone}
					<div class="slide-details">
						<address>${escapeHtml(addressParts)}</address>
						<a class="cta" href="${escapeHtml(store.directions_url)}" target="_blank" rel="noopener" aria-label="vai all'indirizzo su Google Maps"><i class="d-arrow" aria-hidden="true"></i></a>
					</div>
				</div>
			</div>`;
	}

	function buildSlides(stores) {
		const wrap = $('#sl-slides');
		// Preserve the first intro slide; replace everything after.
		const intro = wrap.querySelector('.slide-intro');
		wrap.innerHTML = '';
		if (intro) wrap.appendChild(intro);

		stores.forEach((store) => {
			const article = document.createElement('article');
			article.className = 'slide cat-slide';
			article.setAttribute('role', 'group');
			article.setAttribute('aria-roledescription', 'slide');
			article.dataset.storeId = String(store.id);
			article.innerHTML = cardHtml(store);
			article.addEventListener('click', (e) => {
				if (e.target.closest('a')) return; // let links work
				selectStore(store.id, { fromCard: true });
			});
			wrap.appendChild(article);
		});

		state.visible = stores;
		toggleEmpty(stores.length === 0);
		setupEmbla();
	}

	function toggleEmpty(empty) {
		const el = $('#sl-empty');
		if (!el) return;
		if (empty && state.searchPoint) {
			el.textContent = SL_DATA.strings.no_results;
			el.hidden = false;
		} else {
			el.hidden = true;
		}
	}

	// ---------- Embla ----------
	function setupEmbla() {
		const wrap = $('#sl-slides');
		if (!wrap) return;
		wrap.classList.add('embla__container');
		const root = wrap.parentElement; // .slide-box
		root.classList.add('embla');

		if (state.embla) {
			state.embla.reInit();
		} else {
			state.embla = EmblaCarousel(root, {
				align: 'start',
				containScroll: 'trimSnaps',
				slidesToScroll: 1,
				dragFree: false,
			});
			$('#sl-prev')?.addEventListener('click', () => state.embla.scrollPrev());
			$('#sl-next')?.addEventListener('click', () => state.embla.scrollNext());
			state.embla.on('select', renderDots);
			state.embla.on('reInit', renderDots);
		}
		renderDots();
	}

	function renderDots() {
		const dots = $('#sl-dots');
		if (!dots || !state.embla) return;
		const snaps = state.embla.scrollSnapList();
		const selected = state.embla.selectedScrollSnap();
		dots.innerHTML = snaps.map((_, i) =>
			`<button type="button" class="sl-dot${i === selected ? ' is-active' : ''}" data-i="${i}" aria-label="slide ${i + 1}"></button>`
		).join('');
		$$('.sl-dot', dots).forEach(btn => {
			btn.addEventListener('click', () => state.embla.scrollTo(Number(btn.dataset.i)));
		});
	}

	// ---------- Map / Markers ----------
	function makePinEl(active) {
		const el = document.createElement('div');
		el.className = 'sl-pin' + (active ? ' is-active' : '');
		el.style.backgroundImage = `url(${active ? SL_DATA.pins.active : SL_DATA.pins.default})`;
		return el;
	}

	function addMarker(store) {
		const marker = new google.maps.marker.AdvancedMarkerElement({
			position: { lat: store.lat, lng: store.lng },
			map: state.map,
			content: makePinEl(false),
			title: store.name,
		});
		marker.addListener('click', () => selectStore(store.id, { fromMarker: true }));
		store.marker = marker;
	}

	function setMarkerActive(store, active) {
		if (!store || !store.marker) return;
		store.marker.content = makePinEl(active);
		if (active) store.marker.zIndex = 999;
	}

	function clearActive() {
		if (state.activeId == null) return;
		const prev = state.stores.find(s => s.id === state.activeId);
		setMarkerActive(prev, false);
		state.activeId = null;
	}

	function selectStore(id, opts = {}) {
		clearActive();
		const store = state.stores.find(s => s.id === id);
		if (!store) return;
		state.activeId = id;
		setMarkerActive(store, true);

		if (!opts.skipPan && state.map) {
			state.map.panTo({ lat: store.lat, lng: store.lng });
			if (state.map.getZoom() < 11) state.map.setZoom(12);
		}

		// Scroll carousel to this card
		const idx = state.visible.findIndex(s => s.id === id);
		if (idx >= 0 && state.embla) {
			state.embla.scrollTo(idx + 1); // +1 for the intro slide at index 0
		}

		// Mark card active
		$$('.slide.cat-slide', $('#sl-slides')).forEach(el => {
			el.classList.toggle('is-active', el.dataset.storeId === String(id));
		});
	}

	function setMarkersVisible(visibleStores) {
		if (!state.map) return;
		const visIds = new Set(visibleStores.map(s => s.id));
		state.stores.forEach(s => {
			if (s.marker) {
				s.marker.map = visIds.has(s.id) ? state.map : null;
			}
		});
	}

	function fitBoundsTo(stores, searchPoint) {
		if (!state.map) return;
		if (stores.length === 0 && !searchPoint) return;
		const bounds = new google.maps.LatLngBounds();
		stores.forEach(s => bounds.extend({ lat: s.lat, lng: s.lng }));
		if (searchPoint) bounds.extend(searchPoint);
		if (!bounds.isEmpty()) {
			state.map.fitBounds(bounds, 80);
		}
	}

	function dropSearchMarker(pt) {
		if (!state.map) return;
		if (state.searchMarker) state.searchMarker.map = null;
		const el = document.createElement('div');
		el.className = 'sl-search-dot';
		state.searchMarker = new google.maps.marker.AdvancedMarkerElement({
			position: pt,
			map: state.map,
			content: el,
			zIndex: 1000,
		});
	}

	// ---------- Search / filter ----------
	function applyFilter() {
		let list;
		if (state.searchPoint) {
			list = state.stores
				.map(s => {
					s._distance = haversineKm(state.searchPoint, { lat: s.lat, lng: s.lng });
					return s;
				})
				.filter(s => s._distance <= state.radius)
				.sort((a, b) => a._distance - b._distance);
		} else {
			state.stores.forEach(s => { s._distance = null; });
			list = state.stores.slice();
		}
		buildSlides(list);
		setMarkersVisible(list);
		fitBoundsTo(list, state.searchPoint);
		clearActive();
	}

	async function doSearch(query) {
		const url = SL_DATA.rest_url + 'geocode?q=' + encodeURIComponent(query);
		try {
			const res = await fetch(url, { headers: { 'X-WP-Nonce': SL_DATA.nonce } });
			if (!res.ok) throw new Error('not ok');
			const data = await res.json();
			state.searchPoint = { lat: data.lat, lng: data.lng };
			dropSearchMarker(state.searchPoint);
			applyFilter();
		} catch (e) {
			alert(SL_DATA.strings.search_failed);
		}
	}

	// ---------- Init ----------
	async function init() {
		// Load stores in parallel with maps.
		const storesPromise = fetch(SL_DATA.rest_url + 'stores', {
			headers: { 'X-WP-Nonce': SL_DATA.nonce }
		}).then(r => r.json()).catch(() => []);

		const mapEl = $('#store-map');

		if (SL_DATA.has_maps_key && mapEl) {
			await mapsReady;
			state.map = new google.maps.Map(mapEl, {
				center: SL_DATA.center,
				zoom: SL_DATA.zoom,
				mapId: 'SL_MAP',
				disableDefaultUI: false,
				mapTypeControl: false,
				streetViewControl: false,
				fullscreenControl: false,
			});
		} else if (mapEl) {
			mapEl.innerHTML = '<div class="sl-map-fallback">Mappa non disponibile: configurare la chiave Google Maps in Store Locator → Impostazioni.</div>';
		}

		state.stores = await storesPromise;
		if (state.map) state.stores.forEach(addMarker);

		applyFilter();

		// Form submit
		$('#sl-form')?.addEventListener('submit', (e) => {
			e.preventDefault();
			const q = ($('#sl-address')?.value || '').trim();
			if (q) doSearch(q);
		});

		// Radius radios
		$$('#store-options input[name="radius"]').forEach(input => {
			input.addEventListener('change', () => {
				state.radius = Number(input.value) || 25;
				if (state.searchPoint) applyFilter();
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
