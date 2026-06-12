# RIACO Hide Products by User Role for WooCommerce

## Project Overview

- **Plugin slug**: `riaco-hide-products-by-user-role-for-woocommerce`
- **Main file**: `riaco-hide-products-by-user-role.php`
- **Version**: 1.1.0
- **Author**: Roberto Iacono
- **Text domain**: `riaco-hide-products-by-user-role-for-woocommerce`
- **License**: GPL v2 or later
- **Repository**: https://github.com/roberiacono/riaco-hide-products-by-user-role-for-woocommerce
- **WordPress.org**: Published on the official plugin repository

**Purpose**: Hide WooCommerce products, product categories, and product variations based on WordPress user roles (including unauthenticated guests).

**Requirements**: WordPress 6.2+, PHP 7.4+, WooCommerce 5.0+. Tested up to WC 10.8. HPOS compatible.

---

## Directory Structure

```
riaco-hide-products-by-user-role-for-woocommerce/
├── riaco-hide-products-by-user-role.php     Plugin entry point (header + bootstrap)
├── uninstall.php                            Cleanup on plugin deletion
├── readme.txt                               WordPress.org plugin readme
├── composer.json                            Dev dependencies (PHPUnit, polyfills)
├── phpunit.xml.dist                         PHPUnit configuration
├── wp-tests-config.php                      Local DB config for tests (gitignored)
├── assets/
│   └── admin/
│       ├── admin.js                         jQuery UI for settings page rules table
│       └── style.css                        Admin styling
├── includes/
│   ├── class-autoloader.php                 PSR-0 autoloader
│   ├── class-plugin.php                     Main plugin class
│   ├── Interfaces/
│   │   └── service-interface.php            ServiceInterface contract
│   ├── Admin/
│   │   ├── class-custom-taxonomy.php        Taxonomy registration & term creation
│   │   ├── class-settings-page.php          WooCommerce Settings page (global rules)
│   │   └── class-product-visibility-tab.php Product/variation edit tab
│   └── Frontend/
│       └── class-product-visibility.php     Query filtering engine
├── languages/
│   └── riaco-hide-products-by-user-role.pot Translation template
└── tests/
    ├── bootstrap.php                        PHPUnit bootstrap (loads WP + WC + plugin)
    ├── Test_Plugin.php                      Plugin class tests
    ├── Test_CustomTaxonomy.php              Taxonomy registration & default terms tests
    ├── Test_ProductVisibility.php           Three-level filtering engine tests
    └── Test_SettingsPage.php               Settings save/sanitization tests
```

---

## Namespace & Autoloading

- **Namespace root**: `Riaco\HideProducts`
- **Autoloader**: PSR-0, registered in `includes/class-autoloader.php`
- **File naming**: CamelCase class name → kebab-case filename
  - Classes: `class-{kebab-case}.php`
  - Interfaces: `{kebab-case}-interface.php` (no `class-` prefix)
- **Namespace-to-path mapping**: Sub-namespaces become sub-directories

Examples:
```
Riaco\HideProducts\Plugin                       → includes/class-plugin.php
Riaco\HideProducts\Admin\Custom_Taxonomy        → includes/Admin/class-custom-taxonomy.php
Riaco\HideProducts\Frontend\Product_Visibility  → includes/Frontend/class-product-visibility.php
Riaco\HideProducts\Interfaces\ServiceInterface  → includes/Interfaces/service-interface.php
```

---

## Service Architecture

All major components implement `ServiceInterface` (one method: `register(): void`).

`class-plugin.php` instantiates services and calls `register()` on each:
- **Always loaded**: `Admin\Custom_Taxonomy`
- **Admin only**: `Admin\Product_Visibility_Tab`, `Admin\Settings_Page`
- **Frontend only**: `Frontend\Product_Visibility`

When adding a new service, implement `ServiceInterface`, place it in the appropriate subdirectory, and add it to `load_services()` in `class-plugin.php`.

---

## Data Model

### Global Rules — `riaco_hpburfw_rules` (WordPress option)

Array of rule objects stored via `update_option()`:

```php
[
    [
        'order'  => 0,              // Integer: priority order (lower = higher priority)
        'role'   => 'guest',        // String: WP role key, or 'guest' for unauthenticated users
        'target' => 'product_cat',  // String: 'all_products', 'product_cat', or custom taxonomy slug
        'terms'  => [12, 34],       // Array of int: term IDs for the selected target taxonomy
    ],
    // ...
]
```

### Per-Product Visibility — Custom Taxonomy

- **Taxonomy slug**: `riaco_hpburfw_visibility_role`
- **Applied to**: `product` and `product_variation` post types
- **Term slug pattern**: `hide-for-{sanitize_title($role_key)}` (e.g., `hide-for-guest`, `hide-for-shop-manager`)
- **Storage**: Standard WordPress taxonomy — `wp_term_relationships` table
- **Assignment**: `wp_set_object_terms($product_id, ['hide-for-guest'], 'riaco_hpburfw_visibility_role')`

Default terms are created for all registered roles plus `guest` on plugin activation.

**Critical invariant**: Every place that constructs a term slug from a role key **must** use `sanitize_title( $role_key )` — not `sanitize_text_field()` and not the raw key. `sanitize_title()` converts underscores to hyphens (e.g., `shop_manager` → `shop-manager`), which is what the taxonomy actually stores. Using any other sanitization produces a mismatch and silently breaks hiding for that role.

---

## Three-Level Filtering (Priority Order)

The single source of truth is `Frontend\Product_Visibility::build_visibility_conditions()`, which returns one of:

- `[]` — no conditions apply; callers skip modification entirely.
- `['post__in' => [0]]` — global hide rule matched; all products hidden.
- `['tax_query' => ['relation' => 'AND', ...]]` — tax_query conditions for levels 2 and/or 3.

Two callers consume the result:

- `apply_visibility_query(\WP_Query)` — used by `woocommerce_product_query` and `pre_get_posts`.
- `apply_hide_rules_to_args(array)` — used by `rest_product_query` and FiboSearch.

Both merge the returned `tax_query` group in a nested AND so that any pre-existing `'relation' => 'OR'` set by another plugin is not broken.

The three levels themselves:

1. **Global hide** — only evaluated when global rules exist. If a rule matches the user's role with `target = 'all_products'`, hide all products and return early.
2. **Target-based hiding** — only evaluated when global rules exist. If rules match the user's role targeting a taxonomy (e.g., `product_cat`), add a `NOT IN` condition for those term IDs.
3. **Product-specific hiding** — applied when the current user has at least one role that maps to a visible term slug. Adds a `NOT IN` condition on `riaco_hpburfw_visibility_role` to exclude products with the matching `hide-for-{role}` term assigned. Works independently of global rules — a product can be hidden per-product even with no global rules configured.

If no conditions were produced by any level (e.g., guest with no hide-for-guest terms assigned to any product and no global rules), the function returns `[]` and callers leave the query untouched.

`maybe_hide_variation()` also applies levels 1 and 2 before checking variation-specific terms.

---

## WordPress & WooCommerce Hooks

### Admin hooks (registered by admin services)
| Hook | Type | Purpose |
|---|---|---|
| `init` | action | Register taxonomy; create default terms |
| `woocommerce_get_sections_products` | filter | Add settings section |
| `woocommerce_settings_products` | filter | Render settings page |
| `woocommerce_settings_save_products` | action | Save global rules |
| `admin_enqueue_scripts` | action | Enqueue admin JS/CSS |
| `woocommerce_product_data_tabs` | filter | Add "Hide by Role" product tab |
| `woocommerce_product_data_panels` | action | Render product tab content |
| `woocommerce_process_product_meta` | action | Save product-level visibility |
| `woocommerce_product_after_variable_attributes` | action | Add variation visibility fields |
| `woocommerce_save_product_variation` | action | Save variation visibility |

### Frontend hooks (registered by `Frontend\Product_Visibility`)
| Hook | Type | Purpose |
|---|---|---|
| `plugins_loaded` | action | Initialize plugin |
| `woocommerce_product_query` | action | Filter WooCommerce product queries |
| `pre_get_posts` | action | Filter non-WC search queries (bails when `wc_query` is set to avoid double-application with `woocommerce_product_query`) |
| `template_redirect` | action | Hide single product pages (redirect) |
| `rest_product_query` | filter | Filter REST API product queries |
| `woocommerce_available_variation` | filter | Hide product variations |
| `dgwt/wcas/search_query/args` | filter | FiboSearch compatibility |
| `render_block` | filter | Replace "no products" block message when global hide rule active |

---

## Custom Extensibility Hooks

### Filter: `riaco_hpburfw_targets`
Allows adding custom visibility targets to the settings page dropdown.

```php
add_filter( 'riaco_hpburfw_targets', function( $targets ) {
    $targets[] = [
        'id'       => 'my_custom_taxonomy',
        'label'    => 'My Custom Taxonomy',
        'taxonomy' => 'my_custom_taxonomy',
        'terms'    => [], // hierarchical term tree
    ];
    return $targets;
} );
```

### Action: `riaco_hpburfw_loaded`
Fires after the plugin fully initializes, passing the `Plugin` instance.

```php
add_action( 'riaco_hpburfw_loaded', function( $plugin ) {
    // $plugin is the Riaco\HideProducts\Plugin instance
} );
```

### Filter: `riaco_hpburfw_visibility_rules`
Modify the global rules array before it is cached and applied to queries. Useful for injecting runtime rules (e.g., subscription-based or time-limited rules).

```php
add_filter( 'riaco_hpburfw_visibility_rules', function( $rules ) {
    $rules[] = [
        'order'  => 99,
        'role'   => 'subscriber',
        'target' => 'product_cat',
        'terms'  => [42],
    ];
    return $rules;
} );
```

### Filter: `riaco_hpburfw_user_roles`
Override the roles used for the current user's visibility check. Useful for granting exceptions, mapping custom roles, or testing.

```php
add_filter( 'riaco_hpburfw_user_roles', function( $roles, $user ) {
    // Treat premium members as administrators for visibility purposes.
    if ( $user->exists() && in_array( 'premium_member', $user->roles, true ) ) {
        return [ 'administrator' ];
    }
    return $roles;
}, 10, 2 );
```

### Filter: `riaco_hpburfw_redirect_url`
Customize the URL blocked users are redirected to when they try to access a hidden single product page. External URLs are supported — the plugin uses `wp_redirect()`, not `wp_safe_redirect()`.

```php
add_filter( 'riaco_hpburfw_redirect_url', function( $url, $product_id, $user ) {
    return home_url( '/members-only/' );
}, 10, 3 );
```

### Filter: `riaco_hpburfw_roles`
Add, remove, or rename roles in the plugin's managed list. Affects the settings UI, product tab checkboxes, variation fields, and default term creation everywhere `get_roles()` is called.

```php
add_filter( 'riaco_hpburfw_roles', function( $roles ) {
    $roles['premium_member'] = [ 'name' => 'Premium Member' ];
    return $roles;
} );
```

### Action: `riaco_hpburfw_rules_saved`
Fires after global rules are persisted to the database. Useful for cache invalidation, audit logging, or syncing to external systems.

```php
add_action( 'riaco_hpburfw_rules_saved', function( $rules ) {
    // Invalidate a custom cache keyed on the rules.
    delete_transient( 'my_plugin_visibility_cache' );
} );
```

### Filter: `riaco_hpburfw_rule_applies`
Determines whether a specific rule applies to the current user. Runs in `has_global_hide_rule()` and `get_hidden_target_terms()` — covering all query-level and single-product-page visibility checks. Use this to add extra conditions (date ranges, subscriptions, capabilities) on top of the base role match.

```php
add_filter( 'riaco_hpburfw_rule_applies', function( $applies, $rule, $user ) {
    // Example: rule only applies between its start_date and end_date.
    if ( $applies && ! empty( $rule['start_date'] ) ) {
        $now = current_time( 'timestamp' );
        $start = strtotime( $rule['start_date'] );
        $end   = ! empty( $rule['end_date'] ) ? strtotime( $rule['end_date'] ) : PHP_INT_MAX;
        return $now >= $start && $now <= $end;
    }
    return $applies;
}, 10, 3 );
```

### Filter: `riaco_hpburfw_rule_sanitize`
Runs after each rule's base fields (`order`, `role`, `target`, `terms`) are sanitized on save. Use this to sanitize and persist extra fields submitted by extension plugins; merge them into the returned array.

```php
add_filter( 'riaco_hpburfw_rule_sanitize', function( $sanitized, $raw ) {
    $sanitized['start_date'] = ! empty( $raw['start_date'] ) ? sanitize_text_field( $raw['start_date'] ) : '';
    $sanitized['end_date']   = ! empty( $raw['end_date'] )   ? sanitize_text_field( $raw['end_date'] )   : '';
    return $sanitized;
}, 10, 2 );
```

### Filter: `riaco_hpburfw_localize_data`
Filters the entire `riaco_hpburfw_data` JS object before it is passed to `wp_localize_script`. Use this to add extension-specific data (labels, config, extra rule-type definitions) that PRO's JS will read.

```php
add_filter( 'riaco_hpburfw_localize_data', function( $data ) {
    $data['pro_date_picker_enabled'] = true;
    return $data;
} );
```

### Filter: `riaco_hpburfw_is_product_hidden`
Overrides the final boolean decision for single product page visibility, after all three built-in checks (global rule, target terms, product-specific taxonomy). Return `false` to force-show; `true` to force-hide.

```php
add_filter( 'riaco_hpburfw_is_product_hidden', function( $hidden, $product_id, $user ) {
    // Grant access to users with a specific capability regardless of rules.
    if ( $user->has_cap( 'pro_member' ) ) {
        return false;
    }
    return $hidden;
}, 10, 3 );
```

### Filter: `riaco_hpburfw_is_variation_hidden`
Overrides the final boolean decision for variation visibility in `woocommerce_available_variation`. Return `false` to force-show; `true` to force-hide.

```php
add_filter( 'riaco_hpburfw_is_variation_hidden', function( $hidden, $variation_id, $user ) {
    return $hidden;
}, 10, 3 );
```

### Action: `riaco_hpburfw_settings_table_columns`
Fires inside `<thead><tr>` after the default "Actions" column. Output additional `<th>` cells here to extend the global rules table.

```php
add_action( 'riaco_hpburfw_settings_table_columns', function() {
    echo '<th>' . esc_html__( 'Date Range', 'my-pro-plugin' ) . '</th>';
} );
```

### Action: `riaco_hpburfw_settings_page_after_table`
Fires after the rules `<table>` and before the "Add Rule" button. Useful for rendering additional settings sections or explanatory text.

```php
add_action( 'riaco_hpburfw_settings_page_after_table', function( $plugin ) {
    // Render a PRO-only settings section here.
} );
```

### Action: `riaco_hpburfw_product_tab_after_roles`
Fires inside the "Hide by Role" product tab, after the role checkboxes. Use this to add extra per-product fields (e.g., per-product date overrides).

```php
add_action( 'riaco_hpburfw_product_tab_after_roles', function( $post_id, $plugin ) {
    // Render extra fields for the product.
}, 10, 2 );
```

### Action: `riaco_hpburfw_product_tab_saved`
Fires after the product tab's role taxonomy terms are saved via `wp_set_object_terms`. Use this to persist extra per-product fields submitted in the product tab.

```php
add_action( 'riaco_hpburfw_product_tab_saved', function( $post_id, $plugin ) {
    // Save extra product tab fields here.
}, 10, 2 );
```

### Action: `riaco_hpburfw_variation_fields_after`
Fires inside the variation visibility row, after the role checkboxes. Use this to add extra per-variation fields.

```php
add_action( 'riaco_hpburfw_variation_fields_after', function( $loop, $variation_id, $plugin ) {
    // Render extra variation fields.
}, 10, 3 );
```

### Action: `riaco_hpburfw_variation_saved`
Fires after a variation's visibility terms are saved. Use this to persist extra per-variation fields.

```php
add_action( 'riaco_hpburfw_variation_saved', function( $variation_id, $i, $plugin ) {
    // Save extra variation fields here.
}, 10, 3 );
```

---

## Version Constant

`RIACO_HPBURFW_VERSION` is defined in the main plugin file and mirrors `$plugin->version`. Extension plugins can use it for compatibility guards before `riaco_hpburfw_loaded` fires:

```php
if ( ! defined( 'RIACO_HPBURFW_VERSION' ) || version_compare( RIACO_HPBURFW_VERSION, '1.0.0', '<' ) ) {
    return; // Required free plugin version not active.
}
```

---

## Security Conventions

Always follow these patterns when adding features:

- **Capability check first**: `current_user_can('manage_woocommerce')` — check this **before** the nonce in every save handler
- **Nonces** — verify after the capability check:
  - Settings page field/action: `riaco_hpburfw_nonce` / `riaco_hpburfw_save_rules`
  - Product tab field/action: `riaco_hpburfw_visibility_nonce` / `riaco_hpburfw_visibility_save`
  - Variation field/action: `riaco_hpburfw_variation_nonce` / `riaco_hpburfw_save_visibility`
  - Note: product tab and variation use **different field names** to avoid collision
- **Output escaping**: `esc_html__()`, `esc_url()`, `esc_attr()` — never output raw data
- **Input sanitization**: `sanitize_text_field()`, `sanitize_key()`, `absint()` — never trust raw input
- **`$_POST` vs `filter_input`**: Use `wp_unslash( $_POST['key'] )` — do **not** use `filter_input(INPUT_POST, ...)`. On some PHP-FPM + Nginx stacks `INPUT_POST` is not populated after WordPress initialises, causing `filter_input` to return `null` even when `$_POST` has data.

---

## Admin JavaScript

**File**: `assets/admin/admin.js`  
**Dependency**: jQuery (loaded as a WordPress script dependency)  
**Localized data**: `riaco_hpburfw_data` (injected via `wp_localize_script`)

```js
riaco_hpburfw_data = {
    roles:   [...],  // All WP roles + guest
    targets: [...],  // Available targets (from riaco_hpburfw_targets filter)
    rules:   [...],  // Current saved rules
    labels:  { move_up, move_down, remove }
}
```

Key functions: `renderRow(index, rule)`, `refreshTable()`, `addRow()`, `moveUp(index)`, `moveDown(index)`, `removeRow(index)`.

**HTML escaping**: The file defines a local `escHtml(s)` helper that must be used whenever inserting any server-supplied string (role names, target labels, term names) into template literals. Never insert these values raw.

The JS manages the dynamic rules table entirely client-side; on form submit the PHP handler sanitizes and persists the state.

**Custom jQuery events** (fired for extension plugins to hook into):

| Event | Triggered on | Data |
|---|---|---|
| `riaco_hpburfw:rule_added` | `document` | `{ index, rule }` |
| `riaco_hpburfw:rule_removed` | `document` | `{ index }` |
| `riaco_hpburfw:rule_duplicated` | `document` | `{ index }` |
| `riaco_hpburfw:table_refreshed` | `document` | `{ rules }` |
| `riaco_hpburfw:row_rendered` | the `<tr>` element | `{ index, rule }` |
| `riaco_hpburfw:target_changed` | `document` | `{ index, target }` |
| `riaco_hpburfw:role_changed` | `document` | `{ index, role }` |

`riaco_hpburfw:row_rendered` is the primary hook for injecting per-row extra cells. It fires on the `<tr>` element so extension JS can use `$(tr).append(...)` to add extra `<td>` cells matching any `<th>` columns added via `riaco_hpburfw_settings_table_columns`.

---

## Tooling

- **No build pipeline** — PHP files are edited directly; JS/CSS are plain files (no transpilation, no bundling)
- **No package.json** — no Node.js tooling
- **No CI/CD** — no GitHub Actions or similar
- **No linting config** — no PHPCS, ESLint, or Stylelint

### Automated Tests (PHPUnit)

Tests use WordPress's integration test framework (`WP_UnitTestCase`) with a real MySQL database and WooCommerce loaded.

**Prerequisites** (one-time setup):

```bash
# Install dev dependencies
composer install

# Install WordPress test library (creates /tmp/wordpress and /tmp/wordpress-tests-lib)
bash bin/install-wp-tests.sh riaco_hide_test root '' 127.0.0.1 latest

# Create wp-tests-config.php (gitignored) — copy from the template and set local DB credentials
```

`wp-tests-config.php` must define: `ABSPATH`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`, `$table_prefix`, and the standard `WP_TESTS_*` constants.

**Run tests:**

```bash
./vendor/bin/phpunit --no-coverage
```

**Test files:**

| File | What it covers |
|---|---|
| `tests/Test_Plugin.php` | `get_roles()`, `add_action_links()`, `register_service()`, `is_loaded()` |
| `tests/Test_CustomTaxonomy.php` | Taxonomy registration, default term creation, idempotency |
| `tests/Test_ProductVisibility.php` | All three filtering levels, REST API, FiboSearch, variations, filters |
| `tests/Test_SettingsPage.php` | Rule save/sanitization, nonce/capability guards, extensibility hooks |

**Known test framework behavior:**

- `_delete_all_data()` runs in `tear_down_after_class()` and deletes ALL terms from `wp_terms` / `wp_term_taxonomy` (WHERE term_id != 1) between test classes. Any test class that needs taxonomy terms must re-create them in `set_up()` — delete the transient and call `maybe_create_default_terms()` explicitly.
- `build_visibility_conditions()` returns `[]` when no conditions apply (no global rules and the current user's role has no hidden terms). Tests for the "no rules" case can assert that query args are unchanged.

---

## WordPress.org Publishing

- The plugin is published on the official WordPress.org repository
- `readme.txt` must follow the WordPress.org readme standard and keep `Tested up to` values current
- All code must comply with WordPress.org plugin review guidelines:
  - No external HTTP calls without user consent
  - No obfuscation
  - GPL-compatible license throughout
  - Proper sanitization, escaping, and nonce usage
- Plugin slug and text domain must match the directory name

---

## Known Gotchas

### `$variation` in `woocommerce_product_after_variable_attributes` is `WP_Post`, not `WC_Product_Variation`

WooCommerce's AJAX variation-loading handler (`WC_AJAX::render_variation_html`) sets `$variation = get_post($variation_id)` before including the template and firing this hook. The parameter is a `WP_Post` object — **do not call `$variation->get_id()`** (PHP Fatal Error). Use:

```php
$variation_id = method_exists( $variation, 'get_id' ) ? $variation->get_id() : absint( $variation->ID );
```

This pattern handles both the current `WP_Post` behavior and any future WooCommerce change that passes a `WC_Product_Variation`.

### `maybe_hide_variation` must compute user roles inline

The `woocommerce_available_variation` filter fires at execution time (typically from `get_available_variations()` in product templates or REST API responses). Always call `wp_get_current_user()` inside the filter callback rather than caching roles at constructor/`plugins_loaded` time. REST API authentication completes after `plugins_loaded`, so a cached value set in the constructor can incorrectly resolve to `['guest']` for authenticated users — hiding variations for admins.

### `is_admin()` is `true` for `admin-ajax.php`, `false` for REST API

`Frontend\Product_Visibility` is loaded only when `! is_admin()`. This means it **IS loaded** for REST API requests (used by the WooCommerce block product editor), but **NOT loaded** for classic-editor AJAX calls through `admin-ajax.php`. Keep this in mind when adding frontend filters — REST API requests will trigger them.

---

## Key Identifiers Reference

| Identifier | Value |
|---|---|
| Option key | `riaco_hpburfw_rules` |
| Custom taxonomy | `riaco_hpburfw_visibility_role` |
| Term slug pattern | `hide-for-{role_key}` |
| Text domain | `riaco-hide-products-by-user-role` |
| Admin script handle | `riaco-hpburfw-admin-js` |
| Admin style handle | `riaco-hpburfw-admin-css` |
| HPOS compatibility action | `before_woocommerce_init` |
