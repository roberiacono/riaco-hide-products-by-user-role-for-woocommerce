<?php
namespace Riaco\HideProducts\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

use function wp_get_current_user;

class ProductVisibility implements ServiceInterface {

	private $rules;
	private $user_roles;
	private $plugin;

	public function __construct( $plugin ) {
		$this->plugin     = $plugin;
		$this->rules      = $this->get_visibility_rules();
		$this->user_roles = $this->get_current_user_roles();
	}

	public function register(): void {

		// Standard WP product query. Use it only in search
		add_action( 'pre_get_posts', array( $this, 'filter_product_query' ) );

		// WooCommerce product query (use WooCommerce hooks)
		add_action( 'woocommerce_product_query', array( $this, 'filter_wc_product_query' ) );

		add_action( 'template_redirect', array( $this, 'maybe_hide_single_product_page' ) );

		add_filter( 'rest_product_query', array( $this, 'maybe_hide_product_in_rest_api' ), 10, 2 );

		add_filter( 'woocommerce_available_variation', array( $this, 'maybe_hide_variation' ), 10, 3 );

		// FiboSearch
		add_filter( 'dgwt/wcas/search_query/args', array( $this, 'fibosearch_compatibility' ), 10, 1 );

		// Replace product content using render_block (block theme compatible)
		// add_filter( 'render_block', array( $this, 'filter_single_product_content_block' ), 10, 2 );

		// Remove data structured from the source code of hidden products
		// add_action( 'template_redirect', array( $this, 'maybe_hide_structured_data' ), 5 );
		// add_filter( 'woocommerce_structured_data_enabled', '__return_false' );
	}

	private function get_current_user_roles(): array {
		$user = wp_get_current_user();
		return $user->exists() ? $user->roles : array( 'guest' );
	}

	private function get_visibility_rules() {

		$rules = get_option( 'riaco_hpburfw_rules', array() );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		return $rules;
	}


	private function apply_visibility_query( \WP_Query $query ): void {

		// error_log( 'Applying visibility query for user roles: ' . print_r( $this->user_roles, true ) );

		if ( empty( $this->rules ) ) {
			return;
		}

		// 1️. Global rule for all products
		if ( $this->has_global_hide_rule() ) {
			// error_log( 'Hiding all products for user roles: ' . print_r( $this->user_roles, true ) );
			$this->hide_all_products( $query );
			return;
		}

		// 2️. Category-specific hide
		$category_terms = $this->get_hidden_category_terms();

		if ( ! empty( $category_terms ) ) {
			error_log( 'Hiding products in categories: ' . print_r( $category_terms, true ) );
			$this->exclude_category_terms( $query, $category_terms );
		}

		// 3️. Product-specific visibility via taxonomy
		$this->exclude_by_custom_taxonomy( $query );
		// error_log( 'Hiding products by single product custom taxonomy' );

		error_log( 'Final query args: ' . print_r( $query->query_vars, true ) );
	}

	private function has_global_hide_rule(): bool {
		// error_log( 'Checking for global hide rules for user roles: ' . print_r( $this->user_roles, true ) );
		// error_log( '$this->rules: ' . print_r( $this->rules, true ) );
		foreach ( $this->rules as $rule ) {
			if ( in_array( $rule['role'], $this->user_roles, true ) && 'all_products' === $rule['target'] ) {
				return true;
			}
		}
		return false;
	}

	private function hide_all_products( \WP_Query $query ): void {
		$query->set( 'post_parent', -1 ); // no results
		add_filter( 'render_block', array( $this, 'filter_no_products_block' ), 10, 2 );
	}

	private function get_hidden_category_terms(): array {
		$terms = array();
		foreach ( $this->rules as $rule ) {
			if (
				in_array( $rule['role'], $this->user_roles, true )
				&& 'category' === $rule['target']
				&& ! empty( $rule['category'] )
			) {
				$terms[] = $rule['category'];
			}
		}
		return array_unique( $terms );
	}

	private function exclude_category_terms( \WP_Query $query, array $terms ): void {
		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $terms,
			'operator' => 'NOT IN',
		);
		$query->set( 'tax_query', $tax_query );
	}

	private function exclude_by_custom_taxonomy( \WP_Query $query ): void {
		$hidden_terms = array_map(
			function ( $role ) {
				return 'hide-for-' . $role;
			},
			$this->user_roles
		);

		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => $this->plugin->taxonomy,
			'field'    => 'slug',
			'terms'    => $hidden_terms,
			'operator' => 'NOT IN',
		);
		$query->set( 'tax_query', $tax_query );
	}



	/**
	 * Hide products in shop, category, and tag archives
	 */
	public function filter_product_query( \WP_Query $query ): void {

		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Only modify WooCommerce product queries
		// $is_shop_or_archive = is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag();
		$is_product_search = $query->is_search(); // && 'product' === $query->get( 'post_type' );

		if ( /*! $is_shop_or_archive && */ ! $is_product_search ) {
			return;
		}
		error_log( 'Filtering product query filter_product_query' );
		$this->apply_visibility_query( $query );
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
		if ( $this->is_product_hidden_for_user_globally( $post->ID, $user_roles ) ) {
			if ( ! $user->exists() ) {
				wp_safe_redirect( wp_login_url( get_permalink( $post ) ) );
				exit;
			}

			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		} else {
			// Step 2: check product-specific hidden roles
			$hidden_terms = array_map( fn( $r ) => 'hide-for-' . $r, $user_roles );

			$assigned_terms = wp_get_object_terms( $post->ID, 'riaco_hpburfw_visibility_role', array( 'fields' => 'slugs' ) );
			// Normalize for comparison
			$assigned_terms = is_array( $assigned_terms ) ? $assigned_terms : array();

			if ( array_intersect( $hidden_terms, $assigned_terms ) ) {
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

	public function maybe_hide_product_in_rest_api( $args, $request ) {
		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );

		// error_log( 'REST API request by user roles: ' . print_r( $user_roles, true ) );

		// Step 1: check global hide settings for each role
		$global_hidden_roles = $this->get_global_hidden_roles_by_user_roles( $user_roles );

		if ( ! empty( $global_hidden_roles ) ) {
			$args['post__in'] = array( 0 );
		} else {
			$hidden_terms = array_map( fn( $r ) => 'hide-for-' . $r, $user_roles );

			$taxonomy = 'riaco_hpburfw_visibility_role';

			$args['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $hidden_terms,
				'operator' => 'NOT IN',
			);
		}
		return $args;
	}

	public function filter_wc_product_query( $query ): void {
		error_log( 'Filtering product query filter_wc_product_query' );
		$this->apply_visibility_query( $query );
	}


	public function fibosearch_compatibility( $args ) {

		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );

		$hidden_terms = array_map( fn( $r ) => 'hide-for-' . $r, $user_roles );

		$taxonomy = 'riaco_hpburfw_visibility_role';

			$args['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $hidden_terms,
				'operator' => 'NOT IN',
			);

			return $args;
	}

	public function maybe_hide_variation( $variation_data, $product, $variation ) {
		if ( ! $variation_data ) {
			return $variation_data;
		}

		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );

		// Build the slugs for terms we should hide
		$hidden_terms = array_map( fn( $r ) => 'hide-for-' . sanitize_title( $r ), $user_roles );

		// Get variation terms
		$variation_terms = wp_get_object_terms( $variation->get_id(), 'riaco_hpburfw_visibility_role', array( 'fields' => 'slugs' ) );

		// error_log( 'Variation ID ' . $variation->get_id() . ' terms: ' . print_r( $variation_terms, true ) );

		// If the variation has any term that matches one of the hidden terms — hide it
		foreach ( $variation_terms as $term ) {
			if ( in_array( $term, $hidden_terms, true ) ) {
				return false; // This removes the variation entirely from the list
			}
		}

		return $variation_data;
	}

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
}
