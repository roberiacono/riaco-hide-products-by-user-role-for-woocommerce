<?php
/**
 * Custom Taxonomy class.
 *
 * @package Riaco\HideProducts\Admin
 */
namespace Riaco\HideProducts\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;


class CustomTaxonomy implements ServiceInterface {
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	public function register_taxonomy(): void {
		$labels = array(
			'name'          => __( 'Visibility by Role', 'riaco-hide-products' ),
			'singular_name' => __( 'Visibility Role', 'riaco-hide-products' ),
			'search_items'  => __( 'Search Visibility Roles', 'riaco-hide-products' ),
			'all_items'     => __( 'All Roles', 'riaco-hide-products' ),
			'edit_item'     => __( 'Edit Role Visibility', 'riaco-hide-products' ),
			'update_item'   => __( 'Update Role Visibility', 'riaco-hide-products' ),
			'add_new_item'  => __( 'Add New Role Visibility', 'riaco-hide-products' ),
			'new_item_name' => __( 'New Role Visibility', 'riaco-hide-products' ),
			'menu_name'     => __( 'Product Visibility', 'riaco-hide-products' ),
		);

		register_taxonomy(
			'riaco_hpburfw_visibility_role',
			'product',
			array(
				'labels' => $labels,
				/*
				'public'            => false,
				'show_ui'           => false, // hidden from admin menu
				'show_in_rest'      => false,
				'hierarchical'      => false,
				'rewrite'           => false,
				'show_admin_column' => false,
				'query_var'    => true,
				'capabilities' => array(),*/
			)
		);
	}
}
