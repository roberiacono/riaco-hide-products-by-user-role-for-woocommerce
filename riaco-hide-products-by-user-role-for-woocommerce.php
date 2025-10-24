<?php
/**
 * Plugin Name: RIACO Hide Products by User Role
 * Description: Hide WooCommerce products by WordPress user role.
 * Version:     1.0.0
 * Author:      RIACO
 * Text Domain: riaco-hide-products-by-user-role-for-woocommerce
 * Domain Path: /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Autoloader;
use Riaco\HideProducts\Plugin;


require_once __DIR__ . '/includes/class-autoloader.php';
// Register the autoloader
Autoloader::register();

// Now you can instantiate your plugin
$riaco_plugin = new Plugin( __FILE__ );
$riaco_plugin->load();

// define compatibility with HPOS Woo
function riaco_hpburfw_hpos_compatibility() {

	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true // true (compatible, default) or false (not compatible)
		);
	}
}
add_action( 'before_woocommerce_init', 'riaco_hpburfw_hpos_compatibility' );
