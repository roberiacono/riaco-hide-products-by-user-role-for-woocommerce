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

$custom_taxonomy = 'riaco_hpburfw_visibility_role';

// Example inside a condition
if ( ! taxonomy_exists( $custom_taxonomy ) ) {
	register_taxonomy(
		$custom_taxonomy,
		array(
			'product',
			'product_variation',
		)
	);
}


// remove all custom taxonomies
$terms = get_terms(
	array(
		'taxonomy'   => $custom_taxonomy,
		'hide_empty' => false,
	)
);


foreach ( $terms as $singular_term ) {
	error_log( "Deleting term: {$singular_term->term_id} - {$singular_term->name}" );
	wp_delete_term( $singular_term->term_id, $custom_taxonomy );
}

// Remove the taxonomy children option
delete_option( "{$custom_taxonomy}_children" );
