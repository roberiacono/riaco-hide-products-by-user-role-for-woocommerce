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

/**
 * Product Metabox class.
 */
class Product_Visibility_Tab implements ServiceInterface {

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
	 * Register hooks
	 */
	public function register(): void {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_tab_content' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variation_visibility_fields' ), 15, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_visibility_fields' ), 15, 2 );
	}

	/**
	 * Add a new tab in Product Data
	 *
	 * @param array $tabs Existing tabs.
	 */
	public function add_tab( array $tabs ): array {
		$tabs['riaco_visibility'] = array(
			'label'    => esc_html__( 'Hide by Role', 'riaco-hide-products-by-user-role-for-woocommerce' ),
			'target'   => 'riaco_hpburfw_hide_by_role_tab',
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

		// Nonce for security.
		wp_nonce_field( 'riaco_hpburfw_visibility_save', 'riaco_hpburfw_visibility_nonce' );
		?>
		<div id="riaco_hpburfw_hide_by_role_tab" class="panel woocommerce_options_panel hidden">
			<div class="option_group" style="padding: 1em 1.5em;">
				<h4><?php echo esc_html__( 'Hide this product for users with this role.', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></h4>
			</div>

			<div class="option_group">
			<?php

			// Get all roles.
			$roles = $this->plugin->get_roles();

			// Get assigned taxonomy terms for this product.
			$assigned_terms = wp_get_object_terms( $post->ID, $this->plugin->custom_taxonomy, array( 'fields' => 'slugs' ) );
			// Normalize for comparison.
			$assigned_terms = is_array( $assigned_terms ) ? $assigned_terms : array();

			// Output checkboxes using WooCommerce helper function.
			foreach ( $roles as $role_key => $role_data ) {
				$term_slug = 'hide-for-' . $role_key;

				woocommerce_wp_checkbox(
					array(
						'id'          => 'riaco_hpburfw_role_' . $role_key,
						'label'       => esc_html( $role_data['name'] ),
						'description' => '',
						'value'       => in_array( $term_slug, $assigned_terms, true ) ? 'yes' : 'no',
					)
				);
			}
			?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save the product meta when product is saved
	 *
	 * @param int $post_id The product ID.
	 */
	public function save_product_meta( int $post_id ): void {

		if ( ! isset( $_POST['riaco_hpburfw_visibility_nonce'] ) ||
			( isset( $_POST['riaco_hpburfw_visibility_nonce'] ) && empty( $_POST['riaco_hpburfw_visibility_nonce'] ) ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['riaco_hpburfw_visibility_nonce'] ), 'riaco_hpburfw_visibility_save' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'riaco-hide-products-by-user-role-for-woocommerce' ) );
		}

		// Start with guest role.
		$roles_data = array_merge(
			array( 'guest' => array( 'name' => esc_html__( 'Guest', 'riaco-hide-products-by-user-role-for-woocommerce' ) ) ),
			wp_roles()->roles
		);

		$terms = array();

		foreach ( $roles_data as $role_key => $role_data ) {
			$field_id = 'riaco_hpburfw_role_' . $role_key;
			if ( ! empty( $_POST[ $field_id ] ) && 'yes' === $_POST[ $field_id ] ) {
				$terms[] = 'hide-for-' . sanitize_text_field( $role_key );
			}
		}

		wp_set_object_terms( $post_id, $terms, 'riaco_hpburfw_visibility_role', false );
	}


	/**
	 * Add visibility fields to variable product variations
	 *
	 * @param int                   $loop            The loop index.
	 * @param array                 $variation_data  The variation data.
	 * @param \WC_Product_Variation $variation The variation object.
	 */
	public function add_variation_visibility_fields( $loop, $variation_data, $variation ): void {
		$taxonomy = $this->plugin->custom_taxonomy;

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<p>' . esc_html__( 'No visibility roles found.', 'riaco-hide-products-by-user-role-for-woocommerce' ) . '</p>';
			return;
		}

		// Move "hide-for-guest" to the top if it exists.
		usort(
			$terms,
			function ( $a, $b ) {
				if ( 'hide-for-guest' === $a->slug ) {
					return -1;
				}
				if ( 'hide-for-guest' === $b->slug ) {
					return 1;
				}
				return strcasecmp( $a->name, $b->name ); // fallback alphabetical.
			}
		);

		$current_terms = wp_get_object_terms( $variation->ID, $taxonomy, array( 'fields' => 'slugs' ) );
		?>
		<div class="form-row form-row-full">';
			<h4><?php echo esc_html__( 'Hide this variation for:', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></h4>
			<?php
			foreach ( $terms as $term ) {
				$field_id = 'riaco_hpburfw_term_' . esc_attr( $term->slug ) . "_{$loop}";
				woocommerce_wp_checkbox(
					array(
						'id'    => $field_id,
						'label' => esc_html( $term->name ),
						'value' => in_array( $term->slug, $current_terms, true ) ? 'yes' : 'no',
					)
				);
			}
			?>
		</div>
		<?php
		wp_nonce_field( 'riaco_hpburfw_save_visibility', 'riaco_hpburfw_visibility_nonce' );
	}

	/**
	 * Save variation visibility fields
	 *
	 * @param int $variation_id The variation ID.
	 * @param int $i The loop index.
	 */
	public function save_variation_visibility_fields( int $variation_id, int $i ): void {
		if ( ! isset( $_POST['riaco_hpburfw_visibility_nonce'] ) ||
			( isset( $_POST['riaco_hpburfw_visibility_nonce'] ) && empty( $_POST['riaco_hpburfw_visibility_nonce'] ) ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['riaco_hpburfw_visibility_nonce'] ), 'riaco_hpburfw_save_visibility' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'riaco-hide-products-by-user-role-for-woocommerce' ) );
		}

		$taxonomy = $this->plugin->custom_taxonomy;

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		$new_terms = array();

		foreach ( $terms as $term ) {
			$field_id = 'riaco_hpburfw_term_' . $term->slug . "_{$i}";
			if ( isset( $_POST[ $field_id ] ) && 'yes' === $_POST[ $field_id ] ) {
				$new_terms[] = $term->slug;
			}
		}

		wp_set_object_terms( $variation_id, $new_terms, $taxonomy, false );
	}
}
