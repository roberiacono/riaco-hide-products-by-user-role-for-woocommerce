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

/**
 * Custom Taxonomy class.
 */
class Custom_Taxonomy implements ServiceInterface {

	/**
	 * The main plugin instance.
	 *
	 * @var class
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param class $plugin The main plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register the service.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'maybe_create_default_terms' ), 11 );
	}

	/**
	 * Register the custom taxonomy.
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name' => esc_html__( 'Hide by Role', 'riaco-hide-products-by-user-role' ),
		);

		register_taxonomy(
			$this->plugin->custom_taxonomy,
			array(
				'product',
				'product_variation',
			),
			array(
				'labels'            => $labels,

				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
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
	 *
	 * Gated by a transient keyed on the role set hash to avoid per-request DB queries.
	 */
	public function maybe_create_default_terms(): void {
		$taxonomy = $this->plugin->custom_taxonomy;

		// Ensure taxonomy is registered first.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$roles     = $this->plugin->get_roles();
		$roles_hash = md5( implode( ',', array_keys( $roles ) ) . $this->plugin->version );
		$cache_key  = 'riaco_hpburfw_default_terms_' . $roles_hash;

		if ( get_transient( $cache_key ) ) {
			return;
		}

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

		set_transient( $cache_key, 1, WEEK_IN_SECONDS );
	}
}
