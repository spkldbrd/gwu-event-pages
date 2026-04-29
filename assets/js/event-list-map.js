/**
 * List / map toggle, Leaflet map, and map filters (Grant Writing, Grant Management, Subawards).
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

	function readMapFilters(mapPane) {
		var leftEl = mapPane.querySelector('.gwu-hpl-filter-col[value="left"]');
		var rightEl = mapPane.querySelector('.gwu-hpl-filter-col[value="right"]');
		var subEl = mapPane.querySelector('.gwu-hpl-filter-subaward');
		return {
			left: leftEl ? leftEl.checked : true,
			right: rightEl ? rightEl.checked : true,
			subaward: subEl ? subEl.checked : true,
		};
	}

	function markerMatchesFilters(m, f) {
		var t = (m.type_name || '').toLowerCase();
		if (t === 'subaward') {
			return f.subaward;
		}
		var col = m.column || '';
		if (col === 'left') {
			return f.left;
		}
		if (col === 'right') {
			return f.right;
		}
		if (!f.left && !f.right) {
			return false;
		}
		return true;
	}

	function applyMarkers(markerGroup, markersData, map) {
		var mapPane = markerGroup._gwuMapPane;
		var f = readMapFilters(mapPane);
		markerGroup.clearLayers();
		markersData.forEach(function (m) {
			if (!markerMatchesFilters(m, f)) {
				return;
			}
			if (typeof m.lat !== 'number' || typeof m.lng !== 'number') {
				return;
			}
			L.marker([m.lat, m.lng]).bindPopup(markerPopupHtml(m)).addTo(markerGroup);
		});
		var b = markerGroup.getBounds();
		if (b.isValid()) {
			map.fitBounds(b.pad(0.14));
		} else {
			map.setView([39.5, -98.35], 4);
		}
		map.invalidateSize(true);
	}

	function initMap(root, mapPane) {
		var canvas = mapPane.querySelector('.gwu-hpl-map-canvas');
		if (!canvas) {
			canvas = mapPane;
		}

		if (canvas._gwuLeafletMap) {
			applyMarkers(canvas._gwuMarkerGroup, canvas._gwuMarkersData, canvas._gwuLeafletMap);
			return canvas._gwuLeafletMap;
		}

		fixLeafletIcons();

		var markersData = parseMarkers(root);
		canvas._gwuMarkersData = markersData;
		var cfg = window.gwuEpMapDefaults || {};
		var geoUrl = cfg.geoJsonUrl || '';

		var map = L.map(canvas, { scrollWheelZoom: false });
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 18,
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		}).addTo(map);

		var markerGroup = L.featureGroup().addTo(map);
		markerGroup._gwuMapPane = mapPane;

		canvas._gwuLeafletMap = map;
		canvas._gwuMarkerGroup = markerGroup;

		if (!mapPane._gwuFilterWired) {
			mapPane._gwuFilterWired = true;
			mapPane.querySelectorAll('.gwu-hpl-filter-col, .gwu-hpl-filter-subaward').forEach(function (cb) {
				cb.addEventListener('change', function () {
					if (canvas._gwuLeafletMap && canvas._gwuMarkerGroup) {
						applyMarkers(canvas._gwuMarkerGroup, canvas._gwuMarkersData, canvas._gwuLeafletMap);
					}
				});
			});
		}

		function afterGeo() {
			applyMarkers(markerGroup, markersData, map);
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
				.finally(afterGeo);
		} else {
			afterGeo();
		}

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

		function setView(mode) {
			var isMap = mode === 'map';
			root.setAttribute('data-gwu-hpl-view', mode);
			listPane.hidden = isMap;
			mapPane.hidden = !isMap;
			mapBtn.classList.toggle('is-active', isMap);
			listBtn.classList.toggle('is-active', !isMap);
			mapBtn.setAttribute('aria-pressed', isMap ? 'true' : 'false');
			listBtn.setAttribute('aria-pressed', !isMap ? 'true' : 'false');
		}

		mapBtn.addEventListener('click', function () {
			setView('map');
			var leafletMap = initMap(root, mapPane);
			window.requestAnimationFrame(function () {
				leafletMap.invalidateSize(true);
			});
		});

		listBtn.addEventListener('click', function () {
			setView('list');
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.gwu-hpl-view').forEach(bind);
	});
})();
