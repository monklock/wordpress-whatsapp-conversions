=== WordPress WhatsApp Conversions ===
Contributors: monklock
Tags: whatsapp, conversions, ga4, webhook
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.0.0
License: Proprietary

Tracks confirmed WhatsApp conversations through Meta webhooks and sends verified leads to GA4.

== Description ==

The plugin creates a conversion only after a valid incoming WhatsApp text message references an active intent token.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Configure the required constants in `wp-config.php`.
3. Activate the plugin in WordPress.

== Privacy ==

The plugin does not store message text or customer phone numbers. WhatsApp sender IDs are stored only as keyed hashes.
