===  GWU Event Pages ===
Contributors: digitalsolution
Tags: events, shortcode, grant writing
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.2.18
License: GPLv2 or later

Renders the public event list shortcode and provides the Event Marketing Page template for grantwritingusa.com.

== Description ==

GWU Event Pages is a companion plugin for grantwritingusa.com that:

1. Provides the `[public_event_list]` shortcode, which fetches upcoming events from the
   Hostlinks Marketing Ops REST endpoint on hostlinks.grantwritingusa.com and renders them
   in a two-column layout.  Results are cached as a WordPress transient (15 minutes).

2. Registers the "Event Marketing Page" page template used by pages that are auto-created
   when new events are added in Hostlinks.

== Installation ==

1. Upload the `gwu-event-pages` folder to `/wp-content/plugins/`.
2. Activate the plugin in the WordPress admin.
3. Optionally add the following constant to `wp-config.php` if the Hostlinks subdomain URL ever changes:
   `define( 'GWU_EP_HMO_API', 'https://hostlinks.grantwritingusa.com/wp-json/hmo/v1' );`
4. Add `[public_event_list]` to any page where you want the public event listing. Use `[public_event_list enable_map="1"]` to include the map toggle.

== Shortcode ==

`[public_event_list]`           — Renders the cached two-column event list.
`[public_event_list cache="0"]` — Forces a fresh fetch (useful when testing).

`[public_event_list enable_map="1"]` — Same list, plus a U.S. map toggle (state outlines from bundled GeoJSON). In-person pins use city + state (from API fields or parsed from the location line as "City, ST" or "City, Full State Name"; OpenStreetMap Nominatim, cached in WordPress transients), with state-level fallback if geocoding is unavailable. Map filters: Grant Writing, Grant Management, and Managing Subawards (Zoom-only events appear on the list only).

== Changelog ==

= 1.2.18 =
* Event list: "Event Details" is the full link text; for Zoom webinars the Zoom badge is inside the same link.

= 1.2.17 =
* Event list: remove `prefers-color-scheme: dark` overrides so list copy stays readable on light themes when the visitor OS is in dark mode.

= 1.2.16 =
* Map: pin disclaimer typography and padding match the map filter bar.

= 1.2.15 =
* Map: short disclaimer under the map explaining pins are approximate and offset when several events share a city.

= 1.2.14 =
* Admin Geocoding tab: table of upcoming `/public-events` rows with resolved city/state, geocode transient status, pin source, and estimated lat/lng (no Nominatim calls while viewing).
* Bulk actions: clear Nominatim transients for selected events’ city/state pairs; optional “clear and re-resolve” (max 10 pairs) with existing polite throttling.
* Map parsing: `location` lines like “Kansas City, Missouri” (full state name) now resolve to a USPS code when `city`/`state` fields are incomplete, so city geocoding can run.

= 1.2.13 =
* Map help overlay: copy set to "Scroll or Double Click to zoom. Drag to move."
* Geocoding: Nominatim `q=` now uses full state name (e.g. "Kansas City, Missouri, United States") to disambiguate border cities; transient prefix `v4_` refreshes cache after earlier misses.

= 1.2.12 =
* Map geocoding: Nominatim uses free-text `q` (e.g. "Kansas City, MO, United States") for clearer results; geocode transient key bumped to `v3_` so old caches are ignored.
* Same-city pin jitter tightened to ~2 km radius from the geocoded point.
* Admin: new **Geocoding log** tab (Event Pages) lists recent lookups, errors, cached misses, and state-centroid fallbacks; clear log button.

= 1.2.11 =
* Daily WP-Cron (next site-time midnight on first schedule) pre-warms Nominatim geocode transients from current public-events data so map shortcode renders avoid cold geocode delays. For reliable timing, trigger `wp-cron.php` from system cron if needed.

= 1.2.10 =
* Map: wider spread for same-city pins (larger city-level jitter) so overlapping workshops are easier to see at city zoom.

= 1.2.9 =
* Map: create Leaflet with `scrollWheelZoom: true` so the hover handler can enable wheel zoom (was ineffective when the handler was never registered).

= 1.2.8 =
* Map: mouse wheel zooms when the cursor is over the map (disabled when the pointer leaves, so page scroll is unaffected). Help overlay copy updated (double-click, Ctrl/⌘+scroll, wheel on map, drag).

= 1.2.7 =
* Map geocoding: when REST `city` is empty, derive the city from the leading `City, ST` segment of `location` (same pattern as list titles) so Nominatim runs instead of always falling back to the state centroid.

= 1.2.6 =
* Map pins: geocode city + state to coordinates (cached ~90 days; polite spacing to Nominatim). Fallback to previous state-centroid + spread when city/state is missing or lookup fails.

= 1.2.5 =
* Map: brief overlay hint on first open (double-click to zoom, drag to move); fades after interaction or a few seconds.

= 1.2.4 =
* Map filter: third checkbox "Managing Subawards" (default on). In-person Subaward pins follow this control separately from Grant Writing / Grant Management.

= 1.2.3 =
* Map filter bar: disclaimer on same line as filters; horizontal scroll on very narrow viewports.
* Disclaimer copy: capitalize "Switch".

= 1.2.2 =
* Map: Grant Writing / Grant Management checkboxes (default on), in-person disclaimer, taller default map height (620px), tighter intro H3/P spacing.

= 1.2.1 =
* Map mode: intro row with configurable H3 + italic paragraph (4/5 left) and both view buttons on the right (1/5). Settings under Event Pages.

= 1.2.0 =
* Optional `[public_event_list enable_map="1"]`: list default, "View map" / "View list" toggle with configurable labels and map height in Event Pages settings.
* Bundled simplified US states GeoJSON for outlines (Leaflet + OpenStreetMap tiles).

= 1.0.0 =
* Initial release.
