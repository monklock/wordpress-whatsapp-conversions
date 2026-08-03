=== WordPress WhatsApp Conversions ===
Contributors: monklock
Tags: whatsapp, conversions, ga4, webhook
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 1.0.0

Tracks confirmed WhatsApp conversations through Meta webhooks and sends verified leads to GA4.

== Description ==

The plugin creates the `whatsapp_lead` event only after a valid incoming WhatsApp text message references an active intent token. It verifies Meta webhook signatures, prevents duplicate conversions, and delivers privacy-safe events to GA4 in the background.

Opening WhatsApp without sending a message is not a conversion.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`.
2. Configure `WWC_PHONE_NUMBER`, `WWC_VERIFY_TOKEN`, `WWC_APP_SECRET`, `WWC_GA4_MEASUREMENT_ID`, and `WWC_GA4_API_SECRET` in `wp-config.php`.
3. Keep a published WordPress page with the `go-whatsapp` slug.
4. Activate the plugin in WordPress.
5. Configure the Meta callback URL as `/wp-json/wordpress-whatsapp-conversions/v1/webhook` and subscribe to `messages`.

The page slug, WhatsApp message text, and allowed `from` sources can be customized with the `wwc_redirect_page_slug`, `wwc_whatsapp_message_text`, and `wwc_allowed_sources` filters.

When upgrading from an early build, rename the constants in `wp-config.php`. Existing intents are migrated to the generic table automatically; back up the database first.

See `README.md` for complete Russian and English setup instructions.

== Frequently Asked Questions ==

= Does opening WhatsApp create a conversion? =

No. A signed incoming text-message webhook containing the active intent token is required.

= Does the plugin require WooCommerce? =

No. WooCommerce Action Scheduler is used when available; otherwise the plugin falls back to WP-Cron.

= Are failed GA4 requests retried automatically? =

No. Version 1.0 records the failure without automatic retries.

== Privacy ==

The plugin does not store message text or customer phone numbers. WhatsApp sender IDs are stored only as keyed hashes. Tokens, advertising IDs, sender hashes, and full URLs are not sent to GA4.

== Changelog ==

= 1.0.0 =

* Initial release.
* Added generic plugin identifiers, legacy data migration, and site customization filters.
