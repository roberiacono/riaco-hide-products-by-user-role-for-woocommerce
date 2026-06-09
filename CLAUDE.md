# RIACO Hide Products by User Role for WooCommerce

## Project Overview

- **Plugin slug**: `riaco-hide-products-by-user-role-for-woocommerce`
- **Main file**: `riaco-hide-products-by-user-role.php`
- **Version**: 1.0.0
- **Author**: Roberto Iacono
- **Text domain**: `riaco-hide-products-by-user-role`
- **License**: GPL v2 or later
- **Repository**: https://github.com/roberiacono/riaco-hide-products-by-user-role-for-woocommerce
- **WordPress.org**: Published on the official plugin repository

**Purpose**: Hide WooCommerce products, product categories, and product variations based on WordPress user roles (including unauthenticated guests).

**Requirements**: WordPress 6.2+, PHP 7.4+, WooCommerce 5.0+. Tested up to WC 10.3. HPOS compatible.

---

## Directory Structure

```
riaco-hide-products-by-user-role-for-woocommerce/
├── riaco-hide-products-by-user-role.php     Plugin entry point (header + bootstrap)
├── uninstall.php                            Cleanup on plugin deletion
├── readme.txt                               WordPress.org plugin readme
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
└── languages/
    └── riaco-hide-products-by-user-role.pot Translation template
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
- **Term slug pattern**: `hide-for-{role_key}` (e.g., `hide-for-guest`, `hide-for-subscriber`)
- **Storage**: Standard WordPress taxonomy — `wp_term_relationships` table
- **Assignment**: `wp_set_object_terms($product_id, ['hide-for-guest'], 'riaco_hpburfw_visibility_role')`

Default terms are created for all registered roles plus `guest` on plugin activation.

---

## Three-Level Filtering (Priority Order)

Applied in `Frontend\Product_Visibility::apply_visibility_query()`:

1. **Global hide** — if a rule exists for the user's role with `target = 'all_products'`, hide all products and return early.
2. **Target-based hiding** — if rules exist for the user's role targeting a taxonomy (e.g., `product_cat`), add a `NOT IN` `tax_query` to exclude products in those terms.
3. **Product-specific hiding** — add a `NOT IN` `tax_query` on `riaco_hpburfw_visibility_role` to exclude products with the matching `hide-for-{role}` term assigned.

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
| `pre_get_posts` | action | Filter search queries |
| `template_redirect` | action | Hide single product pages (redirect) |
| `rest_product_query` | filter | Filter REST API product queries |
| `woocommerce_available_variation` | filter | Hide product variations |
| `dgwt/wcas/search_query/args` | filter | FiboSearch compatibility |

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

---

## Security Conventions

Always follow these patterns when adding features:

- **Nonces** — verify before processing any form save:
  - Settings page: `riaco_hpburfw_save_rules`
  - Product tab save: `riaco_hpburfw_visibility_save`
  - Variation save: `riaco_hpburfw_save_visibility`
- **Capability check**: `current_user_can('manage_woocommerce')` for admin operations
- **Output escaping**: `esc_html__()`, `esc_url()`, `esc_attr()` — never output raw data
- **Input sanitization**: `sanitize_text_field()`, `sanitize_key()`, `absint()` — never trust raw input

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

The JS manages the dynamic rules table entirely client-side; on form submit the PHP handler sanitizes and persists the state.

---

## Tooling

- **No build pipeline** — PHP files are edited directly; JS/CSS are plain files (no transpilation, no bundling)
- **No composer.json** — no PHP dependencies beyond WordPress/WooCommerce core
- **No package.json** — no Node.js tooling
- **No automated tests** — no PHPUnit, no Jest
- **No CI/CD** — no GitHub Actions or similar
- **No linting config** — no PHPCS, ESLint, or Stylelint

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
