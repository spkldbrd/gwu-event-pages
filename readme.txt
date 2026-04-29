===  GWU Event Pages ===
Contributors: digitalsolution
Tags: events, shortcode, grant writing
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.2.9
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

`[public_event_list enable_map="1"]` — Same list, plus a U.S. map toggle (state outlines from bundled GeoJSON). In-person pins use city + state (from API fields or parsed from the location line as "City, ST"; OpenStreetMap Nominatim, cached in WordPress transients), with state-level fallback if geocoding is unavailable. Map filters: Grant Writing, Grant Management, and Managing Subawards (Zoom-only events appear on the list only).

== Changelog ==

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
