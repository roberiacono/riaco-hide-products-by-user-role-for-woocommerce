<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Riaco\HideProducts\Tests
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Use our plugin-specific wp-tests-config.php (separate test DB, no conflicts).
define( 'WP_TESTS_CONFIG_FILE_PATH', dirname( __DIR__ ) . '/wp-tests-config.php' );

// Point to Composer-installed polyfills so tests work with PHPUnit 9.
define(
	'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
	dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills'
);

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo 'Could not find ' . $_tests_dir . '/includes/functions.php — run bin/install-wp-tests.sh first.' . PHP_EOL;
	exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Load WooCommerce and this plugin before WordPress fires muplugins_loaded.
 */
function riaco_hide_load_plugins(): void {
	$wc_file = dirname( dirname( dirname( __DIR__ ) ) ) . '/plugins/woocommerce/woocommerce.php';
	if ( ! file_exists( $wc_file ) ) {
		echo 'WooCommerce not found at: ' . $wc_file . PHP_EOL;
		exit( 1 );
	}
	require_once $wc_file;
	require_once dirname( __DIR__ ) . '/riaco-hide-products-by-user-role.php';
}
tests_add_filter( 'muplugins_loaded', 'riaco_hide_load_plugins' );

/**
 * Install WooCommerce DB tables before test queries touch them.
 */
function riaco_hide_install_wc_tables(): void {
	if ( class_exists( 'WC_Install' ) ) {
		WC_Install::install();
	}
}
tests_add_filter( 'setup_theme', 'riaco_hide_install_wc_tables' );

// Boot the WP test environment (installs WP, fires all init hooks).
require "{$_tests_dir}/includes/bootstrap.php";

// Ensure WC_Admin_Settings exists so save handler tests can call add_message().
if ( ! class_exists( 'WC_Admin_Settings' ) && defined( 'WC_ABSPATH' ) ) {
	$wc_admin_settings_file = WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
	if ( file_exists( $wc_admin_settings_file ) ) {
		require_once $wc_admin_settings_file;
	}
}
