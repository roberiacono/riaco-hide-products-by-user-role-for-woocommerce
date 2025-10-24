<?php
/**
 * Uninstall script for RIACO Hide Products by User Role plugin.
 *
 * @package Riaco\HideProducts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'delete_plugins' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'riaco_hpburfw_rules' );
