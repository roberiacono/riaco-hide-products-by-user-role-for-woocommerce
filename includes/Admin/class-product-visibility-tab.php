<?php
/**
 * Product Metabox class.
 *
 * @package Riaco\HideProducts\Admin
 */
namespace Riaco\HideProducts\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;


class ProductVisibilityTab implements ServiceInterface {

	/**
	 * Register hooks
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_tab_content' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
	}

	/**
	 * Add a new tab in Product Data
	 */
	public function add_tab( array $tabs ): array {
		$tabs['riaco_visibility'] = array(
			'label'    => __( 'Visibility by Role', 'riaco-hide-products' ),
			'target'   => 'riaco_visibility_tab',
			'class'    => array(),
			'priority' => 50,
		);

		return $tabs;
	}

	/**
	 * Render the tab content
	 */
	public function render_tab_content(): void {
		global $post;

		// Nonce for security
		wp_nonce_field( 'riaco_hpburfw_visibility_save', 'riaco_hpburfw_visibility_nonce' );

		echo '<div id="riaco_visibility_tab" class="panel woocommerce_options_panel hidden">';
		echo '<div class="option_group" style="padding: 1em 1.5em;">';
		echo '<h4>' . esc_html__( 'Hide this product for users with this role.', 'riaco-hide-products' ) . '</h4>';
		echo '</div>';

		echo '<div class="option_group">';

		// Get all roles
		$roles = wp_roles()->roles;
		// Prepend guest as a virtual role
		$roles = array_merge(
			array( 'guest' => array( 'name' => __( 'Guest', 'riaco-hide-products' ) ) ),
			$roles
		);

		// Get assigned taxonomy terms for this product
		$assigned_terms = wp_get_object_terms( $post->ID, 'riaco_hpburfw_visibility_role', array( 'fields' => 'slugs' ) );
		// Normalize for comparison
		$assigned_terms = is_array( $assigned_terms ) ? $assigned_terms : array();

		// Output checkboxes using WooCommerce helper function
		foreach ( $roles as $role_key => $role_data ) {
			$term_slug = 'hide-for-' . $role_key;

			woocommerce_wp_checkbox(
				array(
					'id'          => 'riaco_hpburfw_role_' . $role_key,
					'label'       => $role_data['name'],
					'description' => '',
					'value'       => in_array( $term_slug, $assigned_terms, true ) ? 'yes' : 'no',
				)
			);
		}

		echo '</div>';
		echo '</div>';
	}
	/**
	 * Save the product meta when product is saved
	 */
	public function save_product_meta( int $post_id ): void {
		if ( empty( $_POST['riaco_hpburfw_visibility_nonce'] ) || ! wp_verify_nonce( $_POST['riaco_hpburfw_visibility_nonce'], 'riaco_hpburfw_visibility_save' ) ) {
			// error_log( 'Nonce verification failed for product visibility save.' );
			return;
		}

		// Start with guest role
		$roles_data = array_merge(
			array( 'guest' => array( 'name' => __( 'Guest', 'riaco-hide-products' ) ) ),
			wp_roles()->roles
		);

		$terms = array();

		foreach ( $roles_data as $role_key => $role_data ) {
			$field_id = 'riaco_hpburfw_role_' . $role_key;
			if ( ! empty( $_POST[ $field_id ] ) && $_POST[ $field_id ] === 'yes' ) {
				$terms[] = 'hide-for-' . sanitize_text_field( $role_key );
			}
		}

		wp_set_object_terms( $post_id, $terms, 'riaco_hpburfw_visibility_role', false );
	}
}
