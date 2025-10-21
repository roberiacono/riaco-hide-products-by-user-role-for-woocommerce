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

	private $plugin;

	function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'maybe_create_default_terms' ), 11 );
	}

	public function register_taxonomy(): void {
		$labels = array(
			'name'          => __( 'Hide by Role', 'riaco-hide-products' ),
			'singular_name' => __( 'Hide by Role', 'riaco-hide-products' ),
		);

		register_taxonomy(
			$this->plugin->taxonomy,
			array(
				'product',
				'product_variation',
			),
			array(
				'labels'            => $labels,

				'public'            => false,
				'show_ui'           => true, // hidden from admin menu
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'rewrite'           => false,
				'show_admin_column' => false,
				'query_var'         => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_woocommerce',
					'edit_terms'   => 'manage_woocommerce',
					'delete_terms' => 'manage_woocommerce',
					'assign_terms' => 'manage_woocommerce',
				),
			)
		);
	}
	/**
	 * Create default terms for all user roles (including guest).
	 */
	public function maybe_create_default_terms(): void {
		$taxonomy = $this->plugin->taxonomy;

		// Ensure taxonomy is registered first
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		// Get all WordPress roles
		$roles = wp_roles()->roles;

		// Add the virtual "guest" role at the top
		$roles = array_merge(
			array( 'guest' => array( 'name' => __( 'Guest', 'riaco-hide-products' ) ) ),
			$roles
		);

		foreach ( $roles as $role_key => $role_data ) {
			$term_slug = 'hide-for-' . sanitize_title( $role_key );
			$term_name = $role_data['name'];

			if ( ! term_exists( $term_slug, $taxonomy ) ) {
				wp_insert_term(
					$term_name,
					$taxonomy,
					array(
						'slug' => $term_slug,
					)
				);
			}
		}
	}
}
