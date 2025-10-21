<?php
namespace Riaco\HideProducts\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

class ProductVisibility implements ServiceInterface {

	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'filter_product_query' ) );

		// Replace product content using render_block (block theme compatible)
		// add_filter( 'render_block', array( $this, 'filter_single_product_content_block' ), 10, 2 );

		// Remove data structured from the source code of hidden products
		// add_action( 'template_redirect', array( $this, 'maybe_hide_structured_data' ), 5 );
		// add_filter( 'woocommerce_structured_data_enabled', '__return_false' );

		add_action( 'template_redirect', array( $this, 'maybe_hide_single_product_page' ) );
	}


	/**
	 * Hide products in shop, category, and tag archives
	 */
	public function filter_product_query( $query ): void {

		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! is_shop() && ! is_product_taxonomy() && ! is_product_category() && ! is_product_tag() ) {
			return;
		}

		// Meta query to hide products for users not in allowed roles
		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );

		// Step 1: check global hide settings for each role
		$global_hidden_roles = $this->get_global_hidden_roles_by_user_roles( $user_roles );

		// Step 2: if user has a globally hidden role, show no products

		if ( ! empty( $global_hidden_roles ) ) {

			$query->set( 'post_parent', -1 );  // A non-existent parent ID

			// Filter WooCommerce "No products found" block output (block theme)
			add_filter( 'render_block', array( $this, 'filter_no_products_block' ), 10, 2 );

			return;
		}

		// Hide products that have any of the user's roles as _riaco_hpburfw_role

		$meta_query = array(
			'relation' => 'OR',

			// Show products with no restriction (no meta key)
			array(
				'key'     => '_riaco_hpburfw_role',
				'compare' => 'NOT EXISTS',
			),

			// Show products that are NOT restricted to this user
			array(
				'key'     => '_riaco_hpburfw_role',
				'value'   => $user_roles,
				'compare' => 'NOT IN',
			),
		);

		$query->set( 'meta_query', $meta_query );
	}

	private function get_global_hidden_roles_by_user_roles( $user_roles ) {
		$global_hidden_roles = array();
		foreach ( $user_roles as $role ) {
			$option_key = "riaco_hpburfw_hide_{$role}";
			if ( 'yes' === get_option( $option_key, 'no' ) ) {
				$global_hidden_roles[] = $role;
			}
		}
		return $global_hidden_roles;
	}


	/**
	 * Replace "No products found" block content (for block themes)
	 */
	public function filter_no_products_block( string $block_content, array $block ): string {
		// error_log( 'Filtering block: ' . $block['blockName'] );
		if ( $block['blockName'] === 'woocommerce/product-collection-no-results' ) {
			// Custom message for guest users (example)
			$user = wp_get_current_user();
			if ( ! $user->exists() ) {
				return '<p class="woocommerce-info">' . esc_html__( 'You must be logged in to view products.', 'riaco-hide-products' ) . '</p>';
			}

			return '<p class="woocommerce-info">' . esc_html__( 'Products are hidden for your role.', 'riaco-hide-products' ) . '</p>';
		}

		return $block_content;
	}



	/**
	 * Replaces all WooCommerce product blocks with a notice
	 */
	/*
	public function filter_single_product_content_block( string $block_content, array $block ): string {
		if ( ! is_product() ) {
			return $block_content; // only affect single product pages
		}

		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );

		// Step 1: check global hide settings for each role
		$global_hidden_roles = $this->get_global_hidden_roles_by_user_roles( $user_roles );

		// Step 2: if user has a globally hidden role, show no products

		if ( ! empty( $global_hidden_roles ) ) {
			// We want to replace the first single product template block with the password form. We also want to remove all other single product template blocks.
			// This array doesn't contains all the blocks. For example, it missing the breadcrumbs blocks: it doesn't make sense replace the breadcrumbs with the password form.
			$single_product_template_blocks = array( 'woocommerce/product-image-gallery', 'woocommerce/product-details', 'woocommerce/add-to-cart-form', 'woocommerce/product-meta', 'woocommerce/product-rating', 'woocommerce/related-products', 'core/post-excerpt', 'core/post-terms' );
			// error_log( '$block[blockName] ' . print_r( $block['blockName'], true ) );
			if ( isset( $block['blockName'] ) && in_array( $block['blockName'], $single_product_template_blocks, true ) ) {
				return '';
			}

			if ( isset( $block['blockName'] ) && in_array( $block['blockName'], array( 'woocommerce/product-price' ), true ) ) {
				$user = wp_get_current_user();
				if ( ! $user->exists() ) {
					return $this->get_login_message();
				}

				return $this->get_hidden_for_role_message();
			}
		}
		return $block_content;
	} */

	public function get_login_message(): string {
		$login_url    = wp_login_url( get_permalink() ); // Redirect back to this product after login.
		$register_url = '';

		if ( get_option( 'users_can_register' ) ) {
			$register_url = wp_registration_url();
		}

		$message  = '<div class="woocommerce-info">';
		$message .= esc_html__( 'You must be logged in to view this product.', 'riaco-hide-products' ) . ' ';
		$message .= '<a href="' . esc_url( $login_url ) . '" class="woocommerce-button login-link">';
		$message .= esc_html__( 'Log in', 'riaco-hide-products' ) . '</a>';

		if ( $register_url ) {
			$message .= ' ' . esc_html__( 'or', 'riaco-hide-products' ) . ' ';
			$message .= '<a href="' . esc_url( $register_url ) . '" class="woocommerce-button register-link">';
			$message .= esc_html__( 'Register', 'riaco-hide-products' ) . '</a>';
		}

		$message .= '</div>';

		return $message;
	}

	public function get_hidden_for_role_message(): string {
		$logout_url = wp_logout_url( wc_get_page_permalink( 'shop' ) ); // Redirect to shop after logout.
		$shop_url   = wc_get_page_permalink( 'shop' );

		$message  = '<div class="woocommerce-info">';
		$message .= esc_html__( 'This product is hidden for your user role.', 'riaco-hide-products' ) . ' ';

		$message .= '<a href="' . esc_url( $shop_url ) . '" class="woocommerce-button shop-link">';
		$message .= esc_html__( 'Return to shop', 'riaco-hide-products' ) . '</a>';

		$message .= ' ' . esc_html__( 'or', 'riaco-hide-products' ) . ' ';

		$message .= '<a href="' . esc_url( $logout_url ) . '" class="woocommerce-button logout-link">';
		$message .= esc_html__( 'Log out', 'riaco-hide-products' ) . '</a>';

		$message .= '</div>';

		return $message;
	}

	/*
	public function maybe_hide_structured_data(): void {
		if ( ! is_product() ) {
			return;
		}

		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );

		// Determine if product is hidden for the user.
		// $hidden_roles = (array) get_post_meta( $product->get_id(), '_riaco_hpburfw_role', true );

		// if ( array_intersect( $user_roles, $hidden_roles ) || ( in_array( 'guest', $hidden_roles, true ) && ! $user->exists() ) ) {
			// Disable WooCommerce structured data for this product.

		// }
	} */

	public function maybe_hide_single_product_page(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );

		// Step 1: check global hide settings for each role
		$global_hidden_roles = $this->get_global_hidden_roles_by_user_roles( $user_roles );

		if ( ! empty( $global_hidden_roles ) ) {
			if ( ! $user->exists() ) {
				wp_safe_redirect( wp_login_url( get_permalink( $post ) ) );
				exit;
			}
			// logged-in but blocked
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
	}
}
