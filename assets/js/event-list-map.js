/**
 * List / map toggle and Leaflet map for [public_event_list enable_map="1"].
 */
(function () {
	'use strict';

	function fixLeafletIcons() {
		if (typeof L === 'undefined') {
			return;
		}
		delete L.Icon.Default.prototype._getIconUrl;
		L.Icon.Default.mergeOptions({
			iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
			iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
			shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
		});
	}

	function parseMarkers(root) {
		var raw = root.getAttribute('data-gwu-markers');
		if (!raw) {
			return [];
		}
		try {
			var parsed = JSON.parse(raw);
			return Array.isArray(parsed) ? parsed : [];
		} catch (e) {
			return [];
		}
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	function escapeAttr(s) {
		return String(s == null ? '' : s).replace(/"/g, '&quot;');
	}

	function markerPopupHtml(m) {
		var html = '<strong>' + escapeHtml(m.title) + '</strong><br>' + escapeHtml(m.date);
		if (m.time) {
			html += '<br>' + escapeHtml(m.time);
		}
		if (m.url) {
			html += '<br><a href="' + escapeAttr(m.url) + '" target="_blank" rel="noopener noreferrer">Event details</a>';
		}
		return html;
	}

	function initMap(root, mapEl) {
		if (mapEl._gwuLeafletMap) {
			return mapEl._gwuLeafletMap;
		}
		fixLeafletIcons();

		var markers = parseMarkers(root);
		var cfg = window.gwuEpMapDefaults || {};
		var geoUrl = cfg.geoJsonUrl || '';

		var map = L.map(mapEl, { scrollWheelZoom: false });
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 18,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
		}).addTo(map);

		var markerGroup = L.featureGroup();

		function finishLayout() {
			if (markerGroup.getLayers().length) {
				markerGroup.addTo(map);
			}
			var b = markerGroup.getBounds();
			if (b.isValid()) {
				map.fitBounds(b.pad(0.14));
			} else {
				map.setView([39.5, -98.35], 4);
			}
			map.invalidateSize(true);
		}

		if (geoUrl) {
			fetch(geoUrl, { credentials: 'same-origin' })
				.then(function (r) {
					return r.json();
				})
				.then(function (geo) {
					L.geoJSON(geo, {
						style: {
							color: '#334155',
							weight: 1,
							fillColor: '#64748b',
							fillOpacity: 0.06,
						},
					}).addTo(map);
				})
				.catch(function () {})
				.finally(function () {
					markers.forEach(function (m) {
						if (typeof m.lat !== 'number' || typeof m.lng !== 'number') {
							return;
						}
						L.marker([m.lat, m.lng]).bindPopup(markerPopupHtml(m)).addTo(markerGroup);
					});
					finishLayout();
				});
		} else {
			markers.forEach(function (m) {
				if (typeof m.lat !== 'number' || typeof m.lng !== 'number') {
					return;
				}
				L.marker([m.lat, m.lng]).bindPopup(markerPopupHtml(m)).addTo(markerGroup);
			});
			finishLayout();
		}

		mapEl._gwuLeafletMap = map;
		return map;
	}

	function bind(root) {
		var mapBtn = root.querySelector('.gwu-hpl-btn--map');
		var listBtn = root.querySelector('.gwu-hpl-btn--list');
		var listPane = root.querySelector('.gwu-hpl-pane--list');
		var mapPane = root.querySelector('.gwu-hpl-pane--map');
		if (!mapBtn || !listBtn || !listPane || !mapPane) {
			return;
		}

		mapBtn.addEventListener('click', function () {
			root.setAttribute('data-gwu-hpl-view', 'map');
			listPane.hidden = true;
			mapPane.hidden = false;
			mapBtn.hidden = true;
			listBtn.hidden = false;
			var leafletMap = initMap(root, mapPane);
			window.requestAnimationFrame(function () {
				leafletMap.invalidateSize(true);
			});
		});

		listBtn.addEventListener('click', function () {
			root.setAttribute('data-gwu-hpl-view', 'list');
			listPane.hidden = false;
			mapPane.hidden = true;
			mapBtn.hidden = false;
			listBtn.hidden = true;
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.gwu-hpl-view').forEach(bind);
	});
})();
