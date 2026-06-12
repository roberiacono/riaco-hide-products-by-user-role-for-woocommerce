<?php
/**
 * Plugin Name: RIACO Hide Products by User Role
 * Description: Hide WooCommerce products by WordPress user role.
 * Version:     1.1.0
 * Author:      Roberto Iacono
 * Author URI:  https://riacoplugins.com/
 * Plugin URI:  https://wordpress.org/plugins/riaco-hide-products-by-user-role/
 * Text Domain: riaco-hide-products-by-user-role-for-woocommerce
 * Domain Path: /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least:  6.2
 * Requires PHP:       7.4
 * Requires Plugins:   woocommerce
 * WC requires at least: 5.0
 * WC tested up to:   10.8.1
 * WC HPOS compatible: yes
 *
 * @package     Riaco\HideProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RIACO_HPBURFW_VERSION', '1.1.0' );

use Riaco\HideProducts\Autoloader;
use Riaco\HideProducts\Plugin;

require_once __DIR__ . '/includes/class-autoloader.php';

Autoloader::register();

// Now you can instantiate your plugin.
$riaco_plugin = new Plugin( __FILE__ );
$riaco_plugin->load();

