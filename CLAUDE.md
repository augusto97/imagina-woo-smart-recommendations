# CLAUDE.md — WooCommerce Smart Recommendations Plugin (Local Mode)

## Project Overview

Build a WordPress/WooCommerce plugin called **"SmartRec — Intelligent Product Recommendations"** that provides an advanced, behavior-driven product recommendation engine running entirely within WordPress. No external APIs, no third-party services — everything processes locally using WordPress database and WP-Cron.

This is the LOCAL version of a larger project. The architecture must be built from day one to support a future EXTERNAL mode (Meilisearch + Gorse via REST API), but this version implements everything server-side within WordPress.

### Plugin Identity

- **Plugin Name:** SmartRec — Intelligent Product Recommendations for WooCommerce
- **Text Domain:** `smartrec`
- **Minimum PHP:** 7.4
- **Minimum WordPress:** 5.8
- **Minimum WooCommerce:** 6.0
- **License:** GPL v2 or later
- **Prefix for all functions, hooks, classes:** `smartrec_`

---

## Architecture Principles

### Code Standards
- Follow WordPress Coding Standards strictly (WPCS)
- Use OOP with namespaces: `SmartRec\Core`, `SmartRec\Tracking`, `SmartRec\Engines`, `SmartRec\Display`, `SmartRec\Admin`, `SmartRec\Cache`, `SmartRec\API`
- Autoloader PSR-4 style within the plugin
- All database queries must use `$wpdb->prepare()` — no exceptions
- All user inputs must be sanitized with appropriate WordPress sanitization functions
- All outputs must be escaped with `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate
- Use WordPress nonces for all form submissions and AJAX requests
- Every public method must have PHPDoc documentation
- Use WordPress transients API for caching
- All strings must be translatable with `__()` or `esc_html__()`

### Performance Requirements
- The plugin must NOT add more than 50ms to page load time
- All recommendation queries must be cached with configurable TTL
- Background processing for heavy computations (model training, data aggregation) via WP-Cron
- Lazy loading of recommendation widgets via AJAX (optional, configurable)
- Database queries must use proper indexes
- Batch processing for large datasets — never process more than 500 records per cron run

### Future-Proof Architecture
- All recommendation engines must implement an interface `RecommendationEngineInterface`
- A `RecommendationManager` class orchestrates which engines are active and merges results
- Display layer is completely decoupled from engine layer
- Configuration is centralized in a `Settings` class using WordPress Options API
- Every engine has an `isAvailable()` method to check dependencies
- Hook system allows third-party extensions: `smartrec_before_recommendations`, `smartrec_after_recommendations`, `smartrec_filter_results`, `smartrec_engine_results`

---

## File Structure

```
smartrec/
├── smartrec.php                          # Main plugin file, bootstrap
├── uninstall.php                         # Clean uninstall handler
├── readme.txt                            # WordPress.org readme
├── CHANGELOG.md
├── assets/
│   ├── css/
│   │   ├── smartrec-frontend.css         # Frontend styles (minimal, theme-compatible)
│   │   └── smartrec-admin.css            # Admin panel styles
│   ├── js/
│   │   ├── smartrec-tracker.js           # Frontend behavior tracker
│   │   ├── smartrec-display.js           # AJAX loader for recommendations
│   │   └── smartrec-admin.js             # Admin panel scripts
│   └── images/
│       └── smartrec-icon.svg             # Plugin icon
├── includes/
│   ├── class-autoloader.php              # PSR-4 autoloader
│   ├── class-plugin.php                  # Main plugin orchestrator (singleton)
│   ├── class-activator.php               # Activation hooks, DB table creation
│   ├── class-deactivator.php             # Deactivation cleanup
│   ├── class-settings.php                # Centralized settings management
│   ├── Tracking/
│   │   ├── class-tracker.php             # Main tracking orchestrator
│   │   ├── class-event-store.php         # Database operations for events
│   │   ├── class-session-manager.php     # Session & visitor identification
│   │   └── class-data-collector.php      # WooCommerce hooks for data collection
│   ├── Engines/
│   │   ├── interface-recommendation-engine.php
│   │   ├── class-recommendation-manager.php  # Orchestrates all engines
│   │   ├── class-similar-products.php        # Content-based: same category/attributes
│   │   ├── class-bought-together.php         # Collaborative: co-purchase analysis
│   │   ├── class-viewed-together.php         # Collaborative: co-view analysis
│   │   ├── class-recently-viewed.php         # Session-based: user's recent views
│   │   ├── class-trending-products.php       # Popularity: trending/hot products
│   │   ├── class-complementary-products.php  # Cross-sell: complementary by attributes
│   │   └── class-personalized-mix.php        # Hybrid: combines all engines per user
│   ├── Display/
│   │   ├── class-renderer.php                # Template rendering engine
│   │   ├── class-widget.php                  # WordPress widget
│   │   ├── class-shortcode.php               # [smartrec] shortcode
│   │   ├── class-woocommerce-hooks.php       # Auto-injection into WC pages
│   │   └── class-elementor-widget.php        # Elementor widget (optional)
│   ├── Cache/
│   │   ├── class-cache-manager.php           # Cache orchestration
│   │   └── class-cache-warmer.php            # Pre-compute recommendations via cron
│   ├── API/
│   │   └── class-rest-api.php                # WP REST API endpoints
│   ├── Admin/
│   │   ├── class-admin-page.php              # Main admin page
│   │   ├── class-settings-page.php           # Settings tabs
│   │   ├── class-analytics-page.php          # Analytics dashboard
│   │   └── class-tools-page.php              # Tools: rebuild index, clear cache, export
│   └── Cron/
│       ├── class-cron-manager.php            # Cron job orchestrator
│       ├── class-relationship-builder.php    # Builds product relationship scores
│       └── class-data-cleanup.php            # Purge old tracking data
└── templates/
    ├── recommendation-grid.php               # Grid layout template
    ├── recommendation-slider.php             # Slider/carousel layout template
    ├── recommendation-list.php               # Simple list layout template
    └── recommendation-minimal.php            # Minimal card layout template
```

---

## Database Schema

Create custom tables on plugin activation. All tables use the WordPress table prefix.

### Table: `{prefix}smartrec_events`
Stores all user behavior events (views, add-to-cart, purchases, clicks).

```sql
CREATE TABLE {prefix}smartrec_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(64) NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT 0,
    event_type ENUM('view', 'cart_add', 'cart_remove', 'purchase', 'click', 'wishlist') NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    source_product_id BIGINT UNSIGNED DEFAULT 0 COMMENT 'Product that led to this event (referrer)',
    quantity INT UNSIGNED DEFAULT 1,
    context VARCHAR(50) DEFAULT '' COMMENT 'page_type: product, category, cart, search',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_session (session_id, event_type),
    INDEX idx_product (product_id, event_type),
    INDEX idx_user (user_id, event_type),
    INDEX idx_created (created_at),
    INDEX idx_source_product (source_product_id, event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `{prefix}smartrec_product_relationships`
Pre-computed product relationship scores. Updated by cron jobs.

```sql
CREATE TABLE {prefix}smartrec_product_relationships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    related_product_id BIGINT UNSIGNED NOT NULL,
    relationship_type ENUM('bought_together', 'viewed_together', 'similar', 'complementary') NOT NULL,
    score DECIMAL(10,6) NOT NULL DEFAULT 0.000000 COMMENT 'Relationship strength 0-1',
    occurrences INT UNSIGNED DEFAULT 0,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_relationship (product_id, related_product_id, relationship_type),
    INDEX idx_product_type (product_id, relationship_type, score DESC),
    INDEX idx_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `{prefix}smartrec_product_scores`
Aggregated product popularity scores for trending/popular engines.

```sql
CREATE TABLE {prefix}smartrec_product_scores (
    product_id BIGINT UNSIGNED NOT NULL,
    views_24h INT UNSIGNED DEFAULT 0,
    views_7d INT UNSIGNED DEFAULT 0,
    views_30d INT UNSIGNED DEFAULT 0,
    purchases_24h INT UNSIGNED DEFAULT 0,
    purchases_7d INT UNSIGNED DEFAULT 0,
    purchases_30d INT UNSIGNED DEFAULT 0,
    cart_adds_7d INT UNSIGNED DEFAULT 0,
    trending_score DECIMAL(10,6) DEFAULT 0.000000,
    conversion_rate DECIMAL(5,4) DEFAULT 0.0000,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    INDEX idx_trending (trending_score DESC),
    INDEX idx_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Table: `{prefix}smartrec_user_profiles`
Aggregated user preference profiles for personalization.

```sql
CREATE TABLE {prefix}smartrec_user_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(64) NOT NULL DEFAULT '',
    preferred_categories TEXT COMMENT 'JSON: {cat_id: weight, ...}',
    preferred_attributes TEXT COMMENT 'JSON: {attr_name: {value: weight, ...}, ...}',
    preferred_price_range VARCHAR(50) DEFAULT '' COMMENT 'min-max',
    viewed_products TEXT COMMENT 'JSON: [product_id, ...] last 50',
    purchased_products TEXT COMMENT 'JSON: [product_id, ...] last 100',
    profile_vector TEXT COMMENT 'JSON: computed preference vector for future external mode',
    last_active DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    INDEX idx_session (session_id),
    INDEX idx_active (last_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**IMPORTANT:** On activation, check if tables exist before creating. On uninstall (not deactivation), drop all tables and delete all options. Use `dbDelta()` for table creation to handle upgrades.

---

## Tracking System

### Session Management (`SessionManager`)
- Generate a unique session ID per visitor using a secure random token stored in a cookie (`smartrec_session`, 30-day expiry, httponly, SameSite=Lax)
- If user is logged in, link session to user_id
- When user logs in, merge anonymous session data into user profile
- Session cookie must comply with GDPR — only set if user has consented (check for common cookie consent plugins: CookieYes, Complianz, CookieBot). If no consent plugin detected, set cookie by default but provide a filter `smartrec_tracking_enabled` for store owners to control this.

### Event Tracking (`Tracker` + `DataCollector`)

#### Frontend JavaScript Tracker (`smartrec-tracker.js`)
Track user behavior on the frontend via lightweight JavaScript. Send events via AJAX to the WordPress REST API.

Events to track:
1. **Product View** — fires when user lands on a single product page. Debounce: only track if user stays 2+ seconds.
2. **Category Browse** — track which categories user is browsing.
3. **Add to Cart** — hook into WooCommerce's `added_to_cart` jQuery event AND the REST API for block-based cart.
4. **Remove from Cart** — hook into `removed_from_cart` event.
5. **Click on Recommendation** — track when user clicks a product from any SmartRec widget (to measure recommendation quality).
6. **Search Query** — track what terms the user searches for.

**JavaScript must be:**
- Under 5KB minified
- No external dependencies (vanilla JS)
- Uses `navigator.sendBeacon()` for reliability (fallback to fetch)
- Batches events — send max every 5 seconds or on page unload
- Respects `Do Not Track` browser header
- Provides `window.smartrecTracker.disable()` method for consent management

#### Server-Side Data Collection (`DataCollector`)
Hook into WooCommerce PHP actions for reliable event tracking:

```php
// Product view (server-side backup for JS tracker)
add_action('woocommerce_after_single_product', [$this, 'trackProductView']);

// Add to cart
add_action('woocommerce_add_to_cart', [$this, 'trackAddToCart'], 10, 6);

// Remove from cart
add_action('woocommerce_cart_item_removed', [$this, 'trackRemoveFromCart'], 10, 2);

// Purchase completed
add_action('woocommerce_order_status_completed', [$this, 'trackPurchase']);
add_action('woocommerce_order_status_processing', [$this, 'trackPurchase']);

// Also track on payment complete for immediate capture
add_action('woocommerce_payment_complete', [$this, 'trackPurchase']);
```

**Deduplication:** Each event has a composite key of `session_id + event_type + product_id + 5-minute window`. Drop duplicates within that window.

#### Event Store (`EventStore`)
- Batch insert events using single `INSERT INTO ... VALUES (...), (...), (...)` queries
- Implement a buffer: events accumulate in a PHP static array during the request and flush once at `shutdown` hook
- For high-traffic sites, provide an option to write events to a file buffer and process via cron (setting: `smartrec_event_buffer_mode` = 'direct' | 'file')

---

## Recommendation Engines

### Interface: `RecommendationEngineInterface`

```php
namespace SmartRec\Engines;

interface RecommendationEngineInterface {
    /**
     * Get engine unique identifier
     */
    public function getId(): string;

    /**
     * Get human-readable engine name
     */
    public function getName(): string;

    /**
     * Get engine description
     */
    public function getDescription(): string;

    /**
     * Check if this engine is available and properly configured
     */
    public function isAvailable(): bool;

    /**
     * Get recommendations for a specific context
     *
     * @param int   $productId  Current product ID (0 if not on product page)
     * @param int   $userId     Current user ID (0 if guest)
     * @param string $sessionId Current session ID
     * @param array  $args      Additional arguments (limit, exclude, context, etc.)
     * @return array Array of ['product_id' => int, 'score' => float, 'reason' => string]
     */
    public function getRecommendations(int $productId, int $userId, string $sessionId, array $args = []): array;

    /**
     * Get the default number of products this engine returns
     */
    public function getDefaultLimit(): int;

    /**
     * Get engine priority (higher = shown first)
     */
    public function getPriority(): int;

    /**
     * Get the minimum amount of data needed for this engine to work
     */
    public function getMinimumDataRequirements(): array;
}
```

### Engine 1: Similar Products (`SimilarProducts`)
**Algorithm:** Content-based filtering using WooCommerce product attributes.

**How it works:**
1. For the current product, extract: categories, tags, attributes (color, size, brand, etc.), price range
2. Score other products by attribute overlap:
   - Same category: +0.3 per shared category
   - Same tag: +0.15 per shared tag
   - Same attribute value: +0.2 per shared attribute (e.g., same brand, same color)
   - Price within ±30% range: +0.1
   - Same product type (simple, variable, etc.): +0.05
3. Normalize scores to 0-1 range
4. Exclude current product, out-of-stock products, and products in exclude list
5. Return top N results sorted by score

**Fallback:** If insufficient data, fall back to WooCommerce's native related products (same category).

### Engine 2: Bought Together (`BoughtTogether`)
**Algorithm:** Co-purchase frequency analysis.

**How it works (Cron-based relationship builder):**
1. Query all orders from the last 90 days (configurable)
2. For each order with 2+ items, create pairs of all item combinations
3. For each pair, calculate:
   - `support` = count of orders containing both items / total orders
   - `confidence` = count of orders containing both / count of orders containing product A
   - `lift` = confidence / (probability of product B appearing in any order)
   - `score` = weighted combination: `0.4 * lift_normalized + 0.4 * confidence + 0.2 * support_normalized`
4. Store in `smartrec_product_relationships` table with type `bought_together`
5. Only store relationships with score > configurable threshold (default 0.01)

**Real-time query:**
1. Look up pre-computed relationships for current product
2. Sort by score DESC, limit to N results
3. If insufficient results, supplement with products from same categories that have high purchase counts

### Engine 3: Viewed Together (`ViewedTogether`)
**Algorithm:** Co-view session analysis.

**How it works (Cron-based):**
1. Analyze sessions from last 30 days
2. For each session with 2+ product views, create pairs
3. Weight by recency: views in same session within 10 minutes get higher weight
4. Calculate co-view score similar to co-purchase but with view events
5. Store in relationships table with type `viewed_together`

**Real-time query:** Same pattern as BoughtTogether.

### Engine 4: Recently Viewed (`RecentlyViewed`)
**Algorithm:** Session-based, last N products viewed by current visitor.

**How it works:**
1. Query events table for current session_id (or user_id if logged in)
2. Filter by event_type = 'view'
3. Order by created_at DESC
4. Exclude current product
5. Return unique products, most recent first

**No cron needed** — this is always real-time.

### Engine 5: Trending Products (`TrendingProducts`)
**Algorithm:** Time-weighted popularity scoring.

**How it works (Cron-based score builder):**
1. Aggregate events by product for different time windows (24h, 7d, 30d)
2. Calculate trending score using exponential decay:
   ```
   trending_score = (views_24h * 10) + (views_7d * 3) + (views_30d * 1)
                  + (purchases_24h * 50) + (purchases_7d * 15) + (purchases_30d * 5)
                  + (cart_adds_7d * 8)
   ```
3. Normalize to 0-1 range across all products
4. Calculate conversion_rate = purchases / views (minimum 10 views to count)
5. Store in `smartrec_product_scores` table

**Real-time query:**
1. Select from product_scores, ordered by trending_score DESC
2. Can filter by category if on a category page
3. Optionally boost products with high conversion rates

### Engine 6: Complementary Products (`ComplementaryProducts`)
**Algorithm:** Attribute-based cross-selling using product taxonomy relationships.

**How it works:**
1. Define complementary category rules in settings (admin configurable):
   ```
   Example rules:
   - "Laptops" → ["Laptop Bags", "Mouse", "Keyboards", "Screen Protectors"]
   - "Smartphones" → ["Phone Cases", "Screen Protectors", "Chargers", "Earphones"]
   - "Dresses" → ["Shoes", "Bags", "Jewelry", "Belts"]
   ```
2. For current product, find its categories
3. Look up complementary categories from rules
4. From complementary categories, select products sorted by:
   - Popularity score (from product_scores table): 40% weight
   - Price compatibility (similar price range as current product): 30% weight
   - Rating: 30% weight
5. Admin can also manually define complementary products per product (custom meta box)

**Fallback:** If no rules defined, use WooCommerce's native cross-sells.

### Engine 7: Personalized Mix (`PersonalizedMix`)
**Algorithm:** Hybrid engine that combines results from all other engines weighted by user profile.

**How it works:**
1. Build/update user profile from their event history:
   - Extract preferred categories (weighted by recency and frequency)
   - Extract preferred price range (median of viewed/purchased products)
   - Extract preferred attributes (most common values in their history)
2. Query each active engine for current context
3. Re-score results based on user profile:
   - Product in preferred category: score * 1.3
   - Product in preferred price range: score * 1.2
   - Product with preferred attributes: score * 1.15
   - Product already viewed (but not purchased): score * 0.7 (variety boost)
   - Product already purchased: score * 0.3 (they already have it)
4. Merge, deduplicate (keep highest score), sort by final score
5. Apply diversity filter: no more than 3 products from same category in final results

**This engine is the "smart" default** — when admin doesn't configure which engine to show in a specific location, this one runs.

### Recommendation Manager (`RecommendationManager`)
Orchestrates all engines:

```php
public function getRecommendations(string $location, int $productId, array $args = []): array {
    // 1. Determine which engines to use for this location
    $engines = $this->getEnginesForLocation($location);

    // 2. Check cache first
    $cacheKey = $this->buildCacheKey($location, $productId, $args);
    $cached = $this->cache->get($cacheKey);
    if ($cached !== false) return $cached;

    // 3. Query each engine
    $allResults = [];
    foreach ($engines as $engine) {
        if (!$engine->isAvailable()) continue;
        $results = $engine->getRecommendations($productId, $userId, $sessionId, $args);
        $allResults[$engine->getId()] = $results;
    }

    // 4. Merge results based on configured strategy
    $merged = $this->mergeResults($allResults, $args);

    // 5. Apply global filters
    $merged = apply_filters('smartrec_filter_results', $merged, $location, $productId);

    // 6. Validate products (in stock, published, not excluded)
    $merged = $this->validateProducts($merged);

    // 7. Limit results
    $limit = $args['limit'] ?? $this->settings->get('default_limit', 8);
    $merged = array_slice($merged, 0, $limit);

    // 8. Cache results
    $this->cache->set($cacheKey, $merged, $this->getCacheTTL($location));

    return $merged;
}
```

---

## Display System

### Display Locations (admin configurable per location)

| Location ID | Description | Default Engine | WooCommerce Hook |
|------------|-------------|----------------|------------------|
| `single_product_below` | Below product on single page | PersonalizedMix | `woocommerce_after_single_product_summary` (priority 15) |
| `single_product_tabs` | New tab "Recommended" | BoughtTogether | `woocommerce_product_tabs` |
| `single_product_sidebar` | Sidebar widget | SimilarProducts | Widget area |
| `cart_page` | On cart page below cart | BoughtTogether + Complementary | `woocommerce_after_cart_table` |
| `cart_page_cross_sells` | Replace WC cross-sells | ComplementaryProducts | `woocommerce_cross_sell_display` |
| `checkout_page` | On checkout page | BoughtTogether | `woocommerce_after_checkout_form` |
| `category_page` | On category/archive pages | TrendingProducts | `woocommerce_after_shop_loop` |
| `empty_cart` | When cart is empty | TrendingProducts + RecentlyViewed | `woocommerce_cart_is_empty` |
| `thank_you_page` | After order confirmation | ComplementaryProducts | `woocommerce_thankyou` |
| `my_account` | In My Account area | PersonalizedMix | `woocommerce_account_dashboard` |
| `custom_shortcode` | Via [smartrec] | Configurable | Shortcode |
| `custom_widget` | Via WordPress widget | Configurable | Widget |

### Renderer (`Renderer`)
- Templates are in the `templates/` folder and can be overridden by themes in `yourtheme/smartrec/` directory
- Each template receives: `$products` (array of WC_Product), `$engine_id`, `$location`, `$settings`
- Available layouts: `grid` (default), `slider`, `list`, `minimal`
- Slider uses lightweight CSS-only horizontal scrolling (no Swiper/Slick dependency), with optional JS enhancement
- All HTML output has CSS classes following BEM: `smartrec-widget`, `smartrec-widget__title`, `smartrec-widget__grid`, `smartrec-widget__item`, etc.
- Inherits WooCommerce product card styling by using `wc_get_template_part('content', 'product')` as an option (setting: `use_wc_template`)
- Each product card shows: thumbnail, title, price, rating (optional), "Add to Cart" button (optional), reason badge (optional: "Trending", "Others also bought", "Similar to what you viewed")

### AJAX Loading (optional)
When enabled (`smartrec_ajax_loading` = true):
1. PHP outputs a placeholder `<div class="smartrec-widget smartrec-loading" data-location="..." data-product-id="...">`
2. JavaScript on `DOMContentLoaded` calls the REST API endpoint to fetch recommendations
3. Response is HTML (pre-rendered server-side) injected into the placeholder
4. Loading skeleton animation (CSS only) shows while waiting
5. Intersection Observer: only load when widget scrolls into viewport (lazy load)

### Shortcode
```
[smartrec engine="personalized_mix" limit="8" layout="grid" title="Recommended for you" columns="4" product_id="" category="" exclude="" show_price="yes" show_rating="yes" show_add_to_cart="yes" show_reason="yes" css_class=""]
```

All attributes are optional. If `product_id` is empty and we're on a product page, it auto-detects. If `engine` is empty, uses the default PersonalizedMix.

### WordPress Widget
Register a widget with the following configurable fields:
- Title
- Engine to use (dropdown of all available engines)
- Number of products
- Layout (grid/slider/list/minimal)
- Show/hide: price, rating, add-to-cart, reason badge
- Columns (for grid layout)

### Elementor Widget (Optional — only load if Elementor is active)
Register an Elementor widget in the "WooCommerce" category with the same options as the WordPress widget plus Elementor-specific style controls.

---

## Admin Panel

### Main Menu
Add admin menu under WooCommerce: `WooCommerce → SmartRec`

### Tab: Dashboard
- Overview cards: total events tracked (24h, 7d, 30d), recommendation clicks (24h, 7d, 30d), click-through rate, top recommended products
- Quick status: which engines are active, last cron run time, cache status, data volume
- Chart: recommendation clicks over last 30 days (use Chart.js loaded from CDN, or simple HTML/CSS bars)

### Tab: Settings

**General Settings:**
- Enable/Disable plugin globally (master switch)
- Default number of products per widget (1-20, default 8)
- Default layout (grid/slider/list/minimal)
- Use WooCommerce templates for product cards (yes/no)
- Show "Powered by SmartRec" (yes/no, default no)

**Tracking Settings:**
- Enable tracking (yes/no)
- Tracking method (JavaScript only / Server-side only / Both)
- Respect Do Not Track header (yes/no, default yes)
- Cookie consent integration (auto-detect / always track / never track without consent)
- Event data retention period (30/60/90/180/365 days, default 90)
- Event buffer mode (direct/file, default direct)

**Engine Settings (per engine):**
- Enable/Disable each engine individually
- Priority (1-10, determines order when combining)
- Minimum data threshold (how many events before engine activates)
- Engine-specific settings:
  - BoughtTogether: order lookback period (30/60/90/180 days), minimum co-purchase count
  - ViewedTogether: session lookback period, minimum co-view count
  - TrendingProducts: decay weights for 24h/7d/30d
  - SimilarProducts: attribute weights (category, tag, attribute, price)
  - ComplementaryProducts: category relationship rules (repeatable fields)
  - PersonalizedMix: engine weight sliders, diversity filter settings

**Display Settings (per location):**
- Enable/Disable each location
- Which engine(s) to use at this location
- Number of products for this location
- Layout override
- Title text (translatable)
- CSS class override
- AJAX loading (yes/no) per location
- Custom CSS (textarea, scoped to `.smartrec-widget`)

**Cache Settings:**
- Enable cache (yes/no, default yes)
- Cache TTL per location type (product page: 1h, category: 30min, cart: 15min, etc.)
- Cache warmer: enable/disable, frequency (hourly, twice daily, daily)
- Clear all cache button

**Advanced Settings:**
- Debug mode (log all queries and engine outputs to WooCommerce log)
- Performance: max database queries per request (default 10)
- Exclude product IDs (comma-separated)
- Exclude categories (multi-select)
- REST API: enable/disable public endpoint
- Data export: export all tracking data as CSV
- Data import: import product relationships from CSV
- Reset: delete all tracking data, delete all computed relationships, reset to defaults

### Tab: Complementary Rules
A visual interface to define which product categories complement each other:
- Left column: source category (dropdown)
- Right column: complementary categories (multi-select)
- Weight per rule (0.1 - 1.0)
- Add/Remove rules
- Bulk import from CSV

### Tab: Analytics
- **Recommendation Performance:** clicks, impressions, CTR per engine and per location
- **Top Recommended Products:** which products appear most in recommendations
- **Top Clicked Recommendations:** which recommended products get the most clicks
- **Engine Comparison:** side-by-side CTR of each engine
- **User Engagement:** average products viewed per session, recommendation influence on cart value
- Date range filter for all analytics

### Tab: Tools
- **Rebuild Relationships:** manually trigger the cron job that computes product relationships. Show progress bar.
- **Clear Cache:** clear all recommendation caches
- **Recount Scores:** recalculate all product popularity scores
- **Export Data:** export tracking events, relationships, or scores as CSV
- **Import Rules:** import complementary category rules from CSV
- **System Info:** PHP version, WC version, DB table sizes, cron status, memory usage

### Product-Level Meta Box
On each product edit page, add a meta box "SmartRec Settings" with:
- Manually defined related products (product search field, repeatable)
- Manually defined complementary products (product search field, repeatable)
- Exclude from recommendations (checkbox)
- Pin to specific locations (multi-select: which locations should always show this product)

---

## Cron Jobs

### `smartrec_build_relationships` (default: every 6 hours)
1. Process purchase data → build co-purchase relationships
2. Process view data → build co-view relationships
3. Calculate trending scores
4. Build/update user profiles
5. Log execution time and records processed

**IMPORTANT:** Process in batches of 500 records. Use `set_time_limit(0)` with a max runtime check (default 120 seconds). If max time reached, save progress and continue on next run using an offset stored in options.

### `smartrec_cache_warmer` (default: hourly)
1. Get top 100 most viewed products
2. Pre-compute recommendations for each
3. Store in cache with extended TTL (2x normal TTL)

### `smartrec_data_cleanup` (default: daily)
1. Delete events older than retention period
2. Delete orphaned relationships (for products that no longer exist)
3. Clean up expired user profiles (no activity in 90 days)
4. Optimize tables if MySQL (OPTIMIZE TABLE on very large tables)

### Cron Implementation
- Use `wp_schedule_event()` with custom intervals registered via `cron_schedules` filter
- On deactivation, unschedule all cron jobs with `wp_clear_scheduled_hook()`
- Provide WP-CLI commands as alternative: `wp smartrec build`, `wp smartrec cache:warm`, `wp smartrec cleanup`
- Admin can configure a real server cron URL to call instead of WP-Cron for reliability

---

## REST API Endpoints

Register under namespace `smartrec/v1`:

### `POST /smartrec/v1/events` (public)
Receives tracking events from JavaScript tracker.
- Accepts batch of events: `[{event_type, product_id, source_product_id, context}, ...]`
- Validates nonce OR session cookie
- Rate limited: max 20 events per request, max 60 requests per minute per session
- Returns: `{success: true, processed: int}`

### `GET /smartrec/v1/recommendations` (public)
Fetch recommendations for display.
- Params: `location`, `product_id`, `engine` (optional), `limit`, `format` (json|html)
- `format=html` returns pre-rendered template HTML for AJAX injection
- `format=json` returns array of product objects with id, title, permalink, image_url, price, rating, reason
- Cache headers: `Cache-Control: public, max-age=300`
- Returns: `{products: [...], engine: string, cached: bool}`

### `GET /smartrec/v1/trending` (public)
Fetch trending products globally or per category.
- Params: `category_id` (optional), `limit`, `period` (24h|7d|30d)
- Returns: `{products: [...]}`

### `GET /smartrec/v1/analytics` (admin only, requires `manage_woocommerce` capability)
Fetch analytics data.
- Params: `metric` (clicks|impressions|ctr|top_products), `date_from`, `date_to`, `engine`
- Returns: `{data: [...]}`

---

## Styling Guidelines

### CSS Architecture
- Use CSS custom properties (variables) for all colors, spacing, fonts — making the plugin fully theme-compatible
- Prefix all classes with `smartrec-`
- Use BEM naming: `smartrec-widget`, `smartrec-widget__title`, `smartrec-widget__grid`, `smartrec-widget__item`, `smartrec-widget__item--highlighted`
- The plugin CSS must be under 8KB minified
- Do NOT import or override WooCommerce styles — only add new styles
- Support RTL with logical properties (`margin-inline-start` instead of `margin-left`)
- Dark mode support using `prefers-color-scheme` media query

### Default CSS Variables
```css
:root {
  --smartrec-columns: 4;
  --smartrec-gap: 16px;
  --smartrec-card-padding: 12px;
  --smartrec-card-radius: 8px;
  --smartrec-card-shadow: 0 1px 3px rgba(0,0,0,0.08);
  --smartrec-title-size: 18px;
  --smartrec-badge-bg: #f0f0f0;
  --smartrec-badge-color: #333;
  --smartrec-accent: var(--wc-primary, #7f54b3);
}
```

### Responsive Grid
```css
.smartrec-widget__grid {
  display: grid;
  grid-template-columns: repeat(var(--smartrec-columns, 4), 1fr);
  gap: var(--smartrec-gap);
}

@media (max-width: 1024px) { .smartrec-widget__grid { --smartrec-columns: 3; } }
@media (max-width: 768px)  { .smartrec-widget__grid { --smartrec-columns: 2; } }
@media (max-width: 480px)  { .smartrec-widget__grid { --smartrec-columns: 1; } }
```

### Slider Layout
- Pure CSS horizontal scrolling with `overflow-x: auto; scroll-snap-type: x mandatory;`
- Optional JS enhancement: arrow buttons for left/right scrolling
- Touch-friendly with `-webkit-overflow-scrolling: touch`
- Show peek of next card (last visible card partially visible) to indicate scrollability

---

## Hooks & Filters Reference

### Actions (for developers to extend)
```php
do_action('smartrec_before_track_event', $event_type, $product_id, $session_id);
do_action('smartrec_after_track_event', $event_type, $product_id, $session_id, $event_id);
do_action('smartrec_before_recommendations', $location, $product_id, $engines);
do_action('smartrec_after_recommendations', $location, $product_id, $results);
do_action('smartrec_before_render', $location, $products, $template);
do_action('smartrec_after_render', $location, $products, $template);
do_action('smartrec_relationships_built', $relationship_type, $count);
do_action('smartrec_cache_cleared');
do_action('smartrec_plugin_activated');
do_action('smartrec_plugin_deactivated');
```

### Filters (for developers to customize)
```php
// Modify recommendations before display
apply_filters('smartrec_filter_results', $results, $location, $product_id);

// Modify engine results before merging
apply_filters('smartrec_engine_results', $results, $engine_id, $product_id);

// Modify cache key
apply_filters('smartrec_cache_key', $cache_key, $location, $product_id);

// Modify cache TTL
apply_filters('smartrec_cache_ttl', $ttl, $location);

// Modify tracking data before storage
apply_filters('smartrec_track_event_data', $data, $event_type);

// Modify template to use
apply_filters('smartrec_template', $template, $location, $layout);

// Modify product card HTML
apply_filters('smartrec_product_card_html', $html, $product, $location);

// Control tracking consent
apply_filters('smartrec_tracking_enabled', $enabled, $user_id);

// Modify engine scores
apply_filters('smartrec_product_score', $score, $product_id, $engine_id);

// Add custom engines
apply_filters('smartrec_registered_engines', $engines);

// Modify trending score formula
apply_filters('smartrec_trending_formula', $score, $metrics);

// Modify similarity score weights
apply_filters('smartrec_similarity_weights', $weights);

// Modify complementary rules
apply_filters('smartrec_complementary_rules', $rules, $product_id);

// Modify widget title
apply_filters('smartrec_widget_title', $title, $location, $engine_id);

// Exclude products from recommendations
apply_filters('smartrec_exclude_products', $exclude_ids, $location);
```

---

## Localization & i18n

- All user-facing strings wrapped in `__('string', 'smartrec')` or `esc_html__('string', 'smartrec')`
- Generate `.pot` file in `languages/smartrec.pot`
- Default language: English
- Include Spanish translation (`languages/smartrec-es_ES.po` and `.mo`) since the primary market is Latin America
- Date/time formatting uses `wp_date()` with WordPress settings
- Number formatting uses `wc_price()` for prices and `number_format_i18n()` for counts
- RTL support in CSS

---

## Uninstall Procedure

In `uninstall.php`:
1. Check `defined('WP_UNINSTALL_PLUGIN')` — exit if not
2. Only clean up if setting `smartrec_delete_data_on_uninstall` is true (default false)
3. Drop all custom tables: `smartrec_events`, `smartrec_product_relationships`, `smartrec_product_scores`, `smartrec_user_profiles`
4. Delete all options starting with `smartrec_`
5. Delete all transients starting with `smartrec_`
6. Remove all scheduled cron events
7. Clean up any user meta starting with `smartrec_`
8. Clean up any product meta starting with `_smartrec_`

---

## Testing Checklist

After development, verify:

### Functional
- [ ] Plugin activates without errors on clean WP + WC install
- [ ] Database tables created correctly with proper indexes
- [ ] Events tracked correctly (JS and server-side)
- [ ] Session management works for guests and logged-in users
- [ ] Each engine returns relevant results
- [ ] Cache works and invalidates correctly
- [ ] Cron jobs execute and process data
- [ ] Admin settings save and apply correctly
- [ ] Shortcode renders with all parameter combinations
- [ ] Widget renders in sidebar
- [ ] AJAX loading works when enabled
- [ ] REST API endpoints return correct responses
- [ ] Templates can be overridden from theme
- [ ] Uninstall cleans up everything

### Performance
- [ ] Page load impact < 50ms with cache enabled
- [ ] Database queries use indexes (explain query plans)
- [ ] No N+1 query problems
- [ ] Cron handles 10,000+ events without timeout
- [ ] Memory usage stays under 64MB per request

### Security
- [ ] All inputs sanitized
- [ ] All outputs escaped
- [ ] SQL injection impossible (prepared statements)
- [ ] XSS impossible (escaping)
- [ ] CSRF prevented (nonces)
- [ ] REST API rate limited
- [ ] No sensitive data in JavaScript
- [ ] Session cookies secure (httponly, samesite)

### Compatibility
- [ ] Works with default WordPress themes (Twenty Twenty-Four, etc.)
- [ ] Works with popular WooCommerce themes (Storefront, Flatsome, Astra)
- [ ] Works with WooCommerce HPOS (High-Performance Order Storage)
- [ ] Works with WooCommerce block-based cart and checkout
- [ ] No conflicts with popular plugins (Yoast, WPRocket, Elementor, WPML)
- [ ] PHP 7.4, 8.0, 8.1, 8.2, 8.3 compatible
- [ ] MySQL 5.7+ and MariaDB 10.3+ compatible

---

## Development Order

Build in this exact sequence:

### Phase 1: Foundation
1. Main plugin file + autoloader + Plugin class
2. Activator (database tables) + Deactivator
3. Settings class with defaults

### Phase 2: Tracking
4. SessionManager
5. EventStore
6. DataCollector (WooCommerce hooks)
7. JavaScript Tracker
8. REST API events endpoint

### Phase 3: Engines
9. RecommendationEngineInterface
10. RecommendationManager
11. SimilarProducts engine
12. RecentlyViewed engine
13. TrendingProducts engine
14. BoughtTogether engine
15. ViewedTogether engine
16. ComplementaryProducts engine
17. PersonalizedMix engine

### Phase 4: Display
18. Renderer + templates (grid, slider, list, minimal)
19. WooCommerce hooks integration
20. Shortcode
21. Widget
22. REST API recommendations endpoint
23. AJAX loading system

### Phase 5: Background Processing
24. CronManager
25. RelationshipBuilder
26. CacheWarmer
27. DataCleanup

### Phase 6: Admin
28. Admin page structure
29. Settings page with all tabs
30. Analytics page
31. Tools page
32. Product meta box

### Phase 7: Polish
33. Frontend CSS
34. Admin CSS
35. Translation files (.pot + es_ES)
36. readme.txt
37. uninstall.php
38. Final testing

---

## Important Notes for Development

1. **Always check WooCommerce is active** before doing anything: `if (!class_exists('WooCommerce')) return;`
2. **HPOS Compatibility:** Use `$order->get_items()` and OrderUtil, never query `wp_posts` directly for orders
3. **Block Cart/Checkout:** The new WC block cart uses the Store API, not traditional AJAX. Hook into `woocommerce_store_api_checkout_order_processed` for block checkout tracking
4. **Variable Products:** When tracking views, always track the parent product ID, not the variation. When tracking purchases, track the variation but store the parent as well for relationship building
5. **Cache Invalidation:** Clear recommendation cache for a product when: product updated, product deleted, product stock changes to 0, new order containing that product is placed
6. **Memory Management:** Use generators (`yield`) when iterating over large datasets in cron jobs
7. **Error Handling:** Wrap all engine queries in try/catch. An engine failure should never crash the page — log the error and return empty results
8. **Multisite:** Support WordPress Multisite by using `get_current_blog_id()` in cache keys and separate tables per site (WordPress handles this automatically with table prefix)
9. **Object Cache:** If a persistent object cache is available (Redis, Memcached), use `wp_cache_get/set` instead of transients for frequently accessed data (session data, hot product scores)
10. **WP-CLI:** Add commands under `wp smartrec` namespace: `build` (trigger relationship building), `cache:warm`, `cache:clear`, `cleanup`, `stats` (show tracking statistics), `export` (export data)
