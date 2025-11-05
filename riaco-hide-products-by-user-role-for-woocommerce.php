<?php
/**
 * Plugin Name: RIACO Hide Products by User Role For WooCommerce
 * Description: Hide WooCommerce products by WordPress user role.
 * Version:     1.0.0
 * Author:      Roberto Iacono
 * Text Domain: riaco-hide-products-by-user-role-for-woocommerce
 * Domain Path: /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  woocommerce
 * WC requires at least: 5.0
 * WC tested up to:  10.3
 * WC HPOS compatible: yes
 *
 * @package     Riaco\HideProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Autoloader;
use Riaco\HideProducts\Plugin;

require_once __DIR__ . '/includes/class-autoloader.php';

Autoloader::register();

// Now you can instantiate your plugin.
$riaco_plugin = new Plugin( __FILE__ );
$riaco_plugin->load();

/**
 * Define compatibility with HPOS Woo
 * */
function riaco_hpburfw_hpos_compatibility() {

	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true // true (compatible, default) or false (not compatible).
		);
	}
}
add_action( 'before_woocommerce_init', 'riaco_hpburfw_hpos_compatibility' );
