===  GWU Event Pages ===
Contributors: digitalsolution
Tags: events, shortcode, grant writing
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
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
4. Add `[public_event_list]` to any page where you want the public event listing.

== Shortcode ==

`[public_event_list]`           — Renders the cached two-column event list.
`[public_event_list cache="0"]` — Forces a fresh fetch (useful when testing).

== Changelog ==

= 1.0.0 =
* Initial release.
