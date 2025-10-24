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
		$target_terms = $this->get_hidden_target_terms();

		if ( ! empty( $target_terms ) ) {
			// error_log( 'Hiding products in categories: ' . print_r( $target_terms, true ) );
			$this->exclude_target_terms( $query, $target_terms );
		}

		// 3️. Product-specific visibility via taxonomy
		$this->exclude_by_custom_taxonomy( $query );

		// error_log( 'Final query args: ' . print_r( $query->query_vars, true ) );
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

	private function get_hidden_target_terms(): array {
		$terms = array();

		foreach ( $this->rules as $rule ) {
			// Skip incomplete rules
			if (
				empty( $rule['role'] ) ||
				empty( $rule['target'] ) ||
				empty( $rule['terms'] )
			) {
				continue;
			}

			// Only include rules that match current user roles
			if ( ! in_array( $rule['role'], $this->user_roles, true ) ) {
				continue;
			}

			// Initialize target key if missing
			if ( ! isset( $terms[ $rule['target'] ] ) ) {
				$terms[ $rule['target'] ] = array();
			}

			// Merge term IDs (flatten)
			$terms[ $rule['target'] ] = array_merge(
				$terms[ $rule['target'] ],
				array_map( 'intval', (array) $rule['terms'] )
			);
		}

		// Make term IDs unique for each target
		foreach ( $terms as $target => $term_ids ) {
			$terms[ $target ] = array_values( array_unique( $term_ids ) );
		}

		return $terms;
	}


	private function exclude_target_terms( \WP_Query $query, array $target_terms ): void {

		$tax_query = (array) $query->get( 'tax_query' );

		// Make term IDs unique for each target
		foreach ( $target_terms as $target => $term_ids ) {

			$tax_query[] = array(
				'taxonomy'         => $target,
				'field'            => 'term_id',
				'terms'            => array_values( array_unique( $term_ids ) ),
				'operator'         => 'NOT IN',
				'include_children' => false, // prevent hiding products in child categories
			);
		}
		$query->set( 'tax_query', $tax_query );
	}

	private function get_hidden_terms_of_custom_taxonomy() {
		$hidden_terms = array_map(
			function ( $role ) {
				return 'hide-for-' . $role;
			},
			$this->user_roles
		);
		return $hidden_terms;
	}

	private function exclude_by_custom_taxonomy( \WP_Query $query ): void {
		$hidden_terms = $this->get_hidden_terms_of_custom_taxonomy();

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
		// error_log( 'Filtering product query filter_product_query' );
		$this->apply_visibility_query( $query );
	}


	/**
	 * Replace "No products found" block content (for block themes)
	 */
	public function filter_no_products_block( string $block_content, array $block ): string {
		if ( $block['blockName'] === 'woocommerce/product-collection-no-results' ) {
			$user = wp_get_current_user();

			if ( ! $user->exists() ) {
				return $this->get_login_message();
			}

			return $this->get_hidden_for_role_message();
		}

		return $block_content;
	}





	public function maybe_hide_single_product_page(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		if ( empty( $this->rules ) ) {
			return;
		}

		$user       = wp_get_current_user();
		$user_roles = $this->user_roles;

		$product_id = $post->ID;

		// 1️. Global rule for all products
		if ( $this->has_global_hide_rule() ) {
			// error_log( 'Hiding all products for user roles: ' . print_r( $this->user_roles, true ) );
			$this->redirect_blocked_user( $user, $product_id );
		}

		// 2. hide based on target terms (e.g., categories)
		if ( $this->has_global_target_terms_hide_rule( $product_id ) ) {
			$this->redirect_blocked_user( $user, $product_id );
		}

		// 3. Product-specific visibility (taxonomy: riaco_hpburfw_visibility_role)
		if ( $this->has_product_specific_hide_rule( $product_id ) ) {
			$this->redirect_blocked_user( $user, $product_id );
		}
	}

	private function redirect_blocked_user( \WP_User $user, int $product_id ): void {
		if ( ! $user->exists() ) {
			wp_safe_redirect( wp_login_url( get_permalink( $product_id ) ) );
			exit;
		}

		wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
		exit;
	}

	private function has_product_specific_hide_rule( $product_id ) {
		$hidden_terms = $this->get_hidden_terms_of_custom_taxonomy();

		$assigned_terms = wp_get_object_terms(
			$product_id,
			$this->plugin->taxonomy,
			array( 'fields' => 'slugs' )
		);

		if ( is_wp_error( $assigned_terms ) ) {
			$assigned_terms = array();
		}

		if ( array_intersect( $hidden_terms, $assigned_terms ) ) {
			return true;
		}
		return false;
	}

	private function has_global_target_terms_hide_rule( $product_id ) {
		$hidden_target_terms = $this->get_hidden_target_terms();

		// error_log( 'Hidden target terms: ' . print_r( $hidden_target_terms, true ) );

		if ( ! empty( $hidden_target_terms ) ) {
			// error_log( 'Hiding products in categories: ' . print_r( $category_terms, true ) );
			// If product has any of these terms, hide it
			foreach ( $hidden_target_terms as $key => $terms ) {
				// error_log( 'Checking terms for taxonomy ' . $key . ': ' . print_r( $terms, true ) );
				if ( has_term( $terms, $key, $product_id ) ) {
					return true;
				}
			}
		}
		return false;
	}



	public function filter_wc_product_query( $query ): void {
		// error_log( 'Filtering product query filter_wc_product_query' );
		$this->apply_visibility_query( $query );
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

	public function apply_hide_rules_to_args( $args ) {

		if ( empty( $this->rules ) ) {
			return $args;
		}

		// 1️. Global rule for all products
		if ( $this->has_global_hide_rule() ) {
			// error_log( 'Hiding all products for user roles: ' . print_r( $this->user_roles, true ) );
			$args['post_parent'] = -1;
			return $args;
		}

		// 2️. Category-specific hide
		$target_terms = $this->get_hidden_target_terms();

		if ( ! empty( $target_terms ) ) {

			// Make term IDs unique for each target
			foreach ( $target_terms as $target => $term_ids ) {
				$args['tax_query'][] = array(
					'taxonomy'         => $target,
					'field'            => 'term_id',
					'terms'            => array_values( array_unique( $term_ids ) ),
					'operator'         => 'NOT IN',
					'include_children' => false, // prevent hiding products in child categories
				);
			}
		}

		// 3️. Product-specific visibility via taxonomy
		$hidden_terms = $this->get_hidden_terms_of_custom_taxonomy();

		$tax_query[] = array(
			'taxonomy' => $this->plugin->taxonomy,
			'field'    => 'slug',
			'terms'    => $hidden_terms,
			'operator' => 'NOT IN',
		);

		return $args;
	}

	/**
	 * FiboSearch compatibility
	 */
	public function fibosearch_compatibility( $args ) {
		return $this->apply_hide_rules_to_args( $args );
	}

	public function maybe_hide_product_in_rest_api( $args, $request ) {
		return $this->apply_hide_rules_to_args( $args );
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
		$message .= esc_html__( 'Products are hidden for your user role.', 'riaco-hide-products' ) . ' ';

		$message .= '<a href="' . esc_url( $logout_url ) . '" class="woocommerce-button logout-link">';
		$message .= esc_html__( 'Log out', 'riaco-hide-products' ) . '</a>.';

		$message .= '</div>';

		return $message;
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
}
