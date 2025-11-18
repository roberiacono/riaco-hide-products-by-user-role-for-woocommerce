=== RIACO Hide Products by User Role ===
Contributors: prototipo88  
Tags:  hide products, product restrictions, products visibility, woocommerce 
Requires at least: 6.2
Tested up to: 6.8  
Stable tag: 1.0.0  
Requires PHP: 7.4  
License: GPL v2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html 

Hide WooCommerce products, categories, and variations based on user roles or guest access.

== Description ==

**Hide Products by User Role for WooCommerce** lets you control product visibility for different user roles — including guests — without coding.  

You can:
- Hide specific products, product categories, or all products from selected user roles.
- Apply global rules in **WooCommerce > Settings > Products > Hide by User Roles**.
- Hide products even in search, archives, and single product pages.
- Support for **variable products** — manage visibility per variation.
- Use **custom targets** (extendable via filters).

Perfect for:
- Wholesale / Retail pricing separation  
- B2B stores hiding retail items  
- Private or membership stores  
- Logged-in users only stores  

### Features
- Hide products for guests or specific user roles
- Global visibility rules via WooCommerce settings
- Role-based taxonomy and product filtering
- Compatible with WooCommerce product queries
- Hide single product pages if restricted
- Hide variation products
- Extendable via WordPress filters

== Installation ==

Upload the plugin folder to /wp-content/plugins/ or install it from the WordPress Plugin Directory.

Activate the plugin through the Plugins menu in WordPress.

Go to WooCommerce > Settings > Products > Hide by User Roles.

Add rules to hide products or categories for specific user roles.

== Frequently Asked Questions ==

= Can I hide products only for guests? =
Yes. You can create a rule with the “Guest” role and select which products or categories to hide.

= Will this remove products from search results and archives? =
Yes, hidden products are excluded from all WooCommerce queries (shop, category, tag, search, etc.).

= Does it work with custom product types or taxonomies? =
No, but you can extend it using filters to support any taxonomy.

= Can I hide product variations? =
Yes. Each variation can have its own visibility settings in the product edit screen.

== Screenshots ==

1. Global rules to hide products. 
2. Individual product rule.
3. Individual product variation rule.