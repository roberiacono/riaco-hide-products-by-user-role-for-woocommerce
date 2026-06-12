=== Hide Products by User Role for WooCommerce ===
Contributors: prototipo88
Tags: hide products, woocommerce, user role, product visibility, restrict products
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.1.1
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 10.8.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Control WooCommerce product visibility by user role. Hide products, categories, and variations from guests or any role — no coding required.

== Description ==

**Hide Products by User Role for WooCommerce** gives store owners complete control over product visibility based on WordPress user roles — including guests (unauthenticated visitors). Set global rules from the WooCommerce settings screen, or configure visibility per product and per variation, all without writing a line of code.

Whether you run a B2B wholesale store, a membership site, or simply want to keep certain products away from guests, this plugin enforces visibility at the query level so hidden products never leak through search, archives, the REST API, or direct URL access.

### Why store owners choose this plugin

- **Run B2B and B2C from a single store** — keep wholesale and retail catalogs side by side and show each audience only what they should see
- **Protect member-only or exclusive products** — restrict visibility to subscribers, members, or any custom role
- **Increase catalog relevance** — show each user role a curated product set and reduce catalog noise
- **Block guest browsing** — require an account before any product is visible, turning your shop into a private catalog
- **Zero performance overhead** — visibility is enforced at the WordPress/WooCommerce query level, not as post-load filtering

### Key Features

**Global visibility rules (WooCommerce > Settings > Products > Hide by User Roles)**

- Hide **all products** from a role with a single rule
- Hide products by **category** or **tag** per role
- Unlimited rules with drag-and-drop **priority ordering**
- **Duplicate** existing rules to create variations quickly
- Rule-count badge in the WooCommerce settings navigation

**Per-product and per-variation control**

- Dedicated **"Hide by Role"** tab on every WooCommerce product edit screen
- Select any combination of roles to hide that individual product from
- Full support for **variable products**: set visibility separately on each variation

**Complete query coverage — hidden means truly hidden**

- Shop page, category archives, and tag archives
- WordPress and WooCommerce **search results**
- **FiboSearch** (DGWT WooCommerce Ajax Search) compatibility
- **WooCommerce REST API** (with block-editor edit-context exemption so admins can still select products)
- **Single product page redirect** — guests are sent to the login page; logged-in restricted users are sent to the shop
- **Block themes** — replaces the "No products found" block with a role-aware login or logout prompt

**Developer-friendly extensibility**

- 20+ filters and actions covering every stage of the visibility pipeline
- Inject runtime rules (subscription-based, time-limited) without touching plugin files
- Add custom taxonomy targets, override redirect URLs, or map membership levels to roles
- HPOS (High Performance Order Storage) compatible

### Perfect for

- **Wholesale / B2B stores** — hide retail products from registered wholesalers, or hide wholesale products from regular customers
- **Membership and subscription sites** — restrict premium products to paying members
- **Private catalogs** — require an account before any product is shown
- **Tiered product access** — show different product sets to different customer segments

== Installation ==

= Automatic installation =

1. In your WordPress admin, go to **Plugins > Add New**.
2. Search for **Hide Products by User Role for WooCommerce**.
3. Click **Install Now**, then **Activate**.

= Manual installation =

1. Download the plugin ZIP from WordPress.org.
2. In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP file and click **Install Now**, then **Activate**.

= First-time setup =

**Step 1 — Add a global rule**

1. Go to **WooCommerce > Settings > Products > Hide by User Roles**.
2. Click **Add Rule**.
3. Choose a **User Role** (e.g. *Guest*, *Subscriber*, or any custom role).
4. Choose a **Target**: *All Products* to hide everything, *Product Category* to hide a category, or *Product Tag* to hide by tag.
5. For category or tag targets, select the specific terms in the Terms column.
6. Use the **arrow buttons** to set rule priority. Rules at the top are evaluated first; an *All Products* rule short-circuits all lower rules for that role.
7. Click **Save changes**.

**Step 2 — Hide an individual product (optional)**

1. Open any WooCommerce product in the editor.
2. Click the **Hide by Role** tab in the Product Data panel.
3. Check the roles that should not see this product.
4. Click **Update**.

**Step 3 — Hide a specific variation (optional)**

1. Open a variable product and expand the **Variations** section.
2. Each variation has a **Hide by Role** section with per-role checkboxes.
3. Check the roles to hide that variation from, then save.

== Frequently Asked Questions ==

= Can I hide all products from guests? =

Yes. Add a rule, set the role to *Guest* and the target to *All Products*. Unauthenticated visitors will see no products on the shop page, in search results, or on any product page — direct URL access redirects them to the login page.

= Will hidden products be removed from search results too? =

Yes. The plugin filters all WooCommerce product queries, including shop, category and tag archives, search results, and the WooCommerce REST API.

= Does it redirect users who access a hidden product's URL directly? =

Yes. Guests are redirected to the WordPress login page (with a return URL so they land back on the product after logging in). Logged-in users whose role hides the product are redirected to the shop. You can override the redirect URL with the `riaco_hpburfw_redirect_url` filter.

= Can I hide individual product variations? =

Yes. Open a variable product, expand any variation in the **Variations** tab, and you will find a **Hide by Role** section with per-role checkboxes.

= Does it work with WooCommerce HPOS (High Performance Order Storage)? =

Yes. The plugin declares full compatibility with WooCommerce High Performance Order Storage.

= Does it work with block themes and the WooCommerce block editor? =

Yes. The plugin filters block-based product collection queries and replaces the "No products found" block with a relevant login or logout message when a global hide rule is active for the current user's role.

= Does it work with FiboSearch / DGWT WooCommerce Ajax Search? =

Yes. Visibility rules are applied automatically to FiboSearch queries.

= How does rule priority work? =

Rules are evaluated from the top of the list downward. If an *All Products* rule matches the current user's role, evaluation stops immediately — no lower rules are checked. Move higher-priority rules to the top using the arrow buttons. Rules with equal priority are evaluated in the order they were created.

= Can I add custom targets such as custom taxonomies? =

Yes. Use the `riaco_hpburfw_targets` filter to add any registered taxonomy as a target in the settings page dropdown.

= Can I add rules programmatically or at runtime? =

Yes. Use the `riaco_hpburfw_visibility_rules` filter to inject rules at runtime (useful for subscription-based or time-limited rules) without saving them to the database. Use `riaco_hpburfw_rule_applies` to add extra conditions on top of the base role match.

= Is it compatible with membership or subscription plugins? =

The plugin works with any user role that WordPress recognises. If your membership plugin assigns roles, those roles will appear in the settings automatically. Use the `riaco_hpburfw_roles` filter to expose non-role membership levels, and `riaco_hpburfw_rule_applies` to add time-based or subscription-status conditions.

== Screenshots ==

1. Global rules settings page — add, prioritize, and duplicate visibility rules.
2. Product edit screen — "Hide by Role" tab for per-product role control.
3. Variation visibility — per-variation role checkboxes inside the variable product editor.

== Changelog ==

= 1.1.1 =
* Fixed per-product hiding silently failing without global rules.

= 1.1.0 =
* Added Product Tag as a global rule target alongside Product Category.
* Added duplicate rule button in the global rules settings table.
* Added Priority column to the rules table and rule-count badge in the WooCommerce settings nav.
* Added Settings quick-link in the Plugins list for faster access.
* Added admin footer review prompt on the plugin settings screen.
* Added inline confirmation dialog on rule removal to prevent accidental deletes.
* Added empty-state message when no rules exist yet.
* Added 10+ new extensibility filters and actions for developer extension plugins.
* Added FiboSearch (DGWT WooCommerce Ajax Search) compatibility.
* Added block theme support: replaces the "No products" block with a role-aware login or logout message.
* Fixed visibility not applied to WooCommerce REST API product queries.
* Fixed variation hide rules not respected for authenticated users accessing products via REST API.
* Fixed text domain to match plugin slug.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.1 =
Bug fix: per-product hiding now works correctly when no global rules are configured.

= 1.1.0 =
Includes REST API bug fixes and significant new features. Update recommended for all users.
