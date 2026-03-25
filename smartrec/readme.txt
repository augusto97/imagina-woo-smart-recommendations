=== SmartRec — Intelligent Product Recommendations for WooCommerce ===
Contributors: smartrec
Tags: woocommerce, recommendations, product recommendations, cross-sell, upsell
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced, behavior-driven product recommendation engine for WooCommerce. No external APIs — everything runs locally within WordPress.

== Description ==

SmartRec is a powerful product recommendation plugin for WooCommerce that analyzes customer behavior to deliver intelligent, personalized product suggestions across your store.

**Key Features:**

* **7 Recommendation Engines** — Similar Products, Bought Together, Viewed Together, Recently Viewed, Trending Products, Complementary Products, and a Personalized Mix that combines all engines.
* **Behavior Tracking** — Tracks product views, add-to-cart events, purchases, and recommendation clicks to continuously improve suggestions.
* **Multiple Display Locations** — Automatically shows recommendations on product pages, cart, checkout, category pages, empty cart, thank you page, and My Account.
* **Flexible Layouts** — Grid, slider, list, and minimal layouts. Fully responsive and theme-compatible.
* **AJAX Loading** — Optional lazy loading for fast page loads.
* **Shortcode & Widget** — Use `[smartrec]` shortcode or the WordPress widget anywhere.
* **Admin Dashboard** — Analytics, settings, complementary rules, and tools all in one place.
* **Performance Optimized** — Intelligent caching, background processing via WP-Cron, and batch database operations.
* **Privacy Compliant** — Respects Do Not Track, integrates with cookie consent plugins, configurable data retention.
* **Developer Friendly** — Extensive hooks and filters for customization. Template overrides from your theme.
* **100% Local** — No external API calls. All data stays on your server.

== Installation ==

1. Upload the `smartrec` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to WooCommerce → SmartRec to configure settings.
4. Recommendations will start appearing automatically as tracking data is collected.

== Frequently Asked Questions ==

= Does this plugin require any external services? =

No. SmartRec runs entirely within WordPress using your database and WP-Cron.

= How long until recommendations start working? =

The Similar Products and Trending engines work immediately. Bought Together and Viewed Together engines need tracking data to accumulate — typically a few days of normal store traffic.

= Can I customize the appearance? =

Yes. Override templates by copying them to `yourtheme/smartrec/`. CSS custom properties make styling easy. You can also add custom CSS in the admin settings.

= Is it compatible with HPOS? =

Yes. SmartRec is fully compatible with WooCommerce High-Performance Order Storage.

== Changelog ==

= 1.0.0 =
* Initial release.
* 7 recommendation engines.
* Behavior tracking (JavaScript + server-side).
* Multiple display locations with WooCommerce hooks.
* Admin dashboard with analytics.
* Shortcode and widget support.
* AJAX lazy loading.
* Cache system with warmer.
* Background processing via WP-Cron.
* Template override system.
* GDPR/privacy compliance features.
