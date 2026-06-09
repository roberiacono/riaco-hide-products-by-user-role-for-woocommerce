<?php
/**
 * Product Visibility Frontend Service.
 *
 * @package Riaco\HideProducts\Frontend
 */

namespace Riaco\HideProducts\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

use function wp_get_current_user;

/**
 * Class Product_Visibility
 */
class Product_Visibility implements ServiceInterface {

	/**
	 * Array of visibility rules from settings.
	 *
	 * @var array
	 */
	private $rules;

	/**
	 * Current user roles.
	 *
	 * @var array
	 */
	private $user_roles;

	/**
	 * Reference to main plugin class.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Main plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin     = $plugin;
		$this->rules      = $this->get_visibility_rules();
		$this->user_roles = $this->get_current_user_roles();
	}

	/**
	 * Register the service.
	 */
	public function register(): void {

		// Standard WP product query. Use it only in search.
		add_action( 'pre_get_posts', array( $this, 'filter_product_query' ) );

		// WooCommerce product query (use WooCommerce hooks).
		add_action( 'woocommerce_product_query', array( $this, 'filter_wc_product_query' ) );

		// Single product page visibility.
		add_action( 'template_redirect', array( $this, 'maybe_hide_single_product_page' ) );

		// REST API.
		add_filter( 'rest_product_query', array( $this, 'maybe_hide_product_in_rest_api' ), 10, 2 );

		// Hide variations.
		add_filter( 'woocommerce_available_variation', array( $this, 'maybe_hide_variation' ), 10, 3 );

		// Plugin FiboSearch compatibility.
		add_filter( 'dgwt/wcas/search_query/args', array( $this, 'fibosearch_compatibility' ), 10, 1 );

		// Block theme "no products" message (registered once; callback self-gates).
		add_filter( 'render_block', array( $this, 'filter_no_products_block' ), 10, 2 );
	}

	/**
	 * Get current user roles.
	 */
	private function get_current_user_roles(): array {
		$user  = wp_get_current_user();
		$roles = $user->exists() ? $user->roles : array( 'guest' );
		return apply_filters( 'riaco_hpburfw_user_roles', $roles, $user );
	}

	/**
	 * Get visibility rules from options.
	 */
	private function get_visibility_rules() {

		$cached = wp_cache_get( 'riaco_hpburfw_rules', 'riaco_hpburfw' );
		if ( false !== $cached ) {
			return $cached;
		}

		$rules = get_option( 'riaco_hpburfw_rules', array() );

		if ( ! is_array( $rules ) ) {
			$rules = array();
		}

		$rules = apply_filters( 'riaco_hpburfw_visibility_rules', $rules );

		usort( $rules, fn( $a, $b ) => ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 ) );

		wp_cache_set( 'riaco_hpburfw_rules', $rules, 'riaco_hpburfw', HOUR_IN_SECONDS );

		return $rules;
	}

	/**
	 * Apply visibility rules to a WP_Query instance.
	 *
	 * @param \WP_Query $query The WP_Query instance to modify.
	 */
	private function apply_visibility_query( \WP_Query $query ): void {

		if ( empty( $this->rules ) ) {
			return;
		}

		// 1️. Global rule for all products.
		if ( $this->has_global_hide_rule() ) {
			$this->hide_all_products( $query );
			return;
		}

		// 2️. Category-specific hide.
		$target_terms = $this->get_hidden_target_terms();

		if ( ! empty( $target_terms ) ) {
			$this->exclude_target_terms( $query, $target_terms );
		}

		// 3️. Product-specific visibility via taxonomy.
		$this->exclude_by_custom_taxonomy( $query );
	}

	/**
	 * Check if there is a global hide rule for all products for current user roles.
	 */
	private function has_global_hide_rule(): bool {
		$user = wp_get_current_user();
		foreach ( $this->rules as $rule ) {
			$role_matches = in_array( $rule['role'], $this->user_roles, true );
			$applies      = apply_filters( 'riaco_hpburfw_rule_applies', $role_matches, $rule, $user );
			if ( $applies && 'all_products' === $rule['target'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Hide all products in the query.
	 *
	 * @param \WP_Query $query The WP_Query instance to modify.
	 */
	private function hide_all_products( \WP_Query $query ): void {
		$query->set( 'post__in', array( 0 ) ); // no results.
	}

	/**
	 * Get target terms to hide based on rules and current user roles.
	 */
	private function get_hidden_target_terms(): array {
		$terms = array();

		foreach ( $this->rules as $rule ) {
			// Skip incomplete rules.
			if (
				empty( $rule['role'] ) ||
				empty( $rule['target'] ) ||
				empty( $rule['terms'] )
			) {
				continue;
			}

			// Only include rules that match current user roles.
			$role_matches = in_array( $rule['role'], $this->user_roles, true );
			$applies      = apply_filters( 'riaco_hpburfw_rule_applies', $role_matches, $rule, wp_get_current_user() );
			if ( ! $applies ) {
				continue;
			}

			// Initialize target key if missing.
			if ( ! isset( $terms[ $rule['target'] ] ) ) {
				$terms[ $rule['target'] ] = array();
			}

			// Merge term IDs (flatten).
			$terms[ $rule['target'] ] = array_merge(
				$terms[ $rule['target'] ],
				array_map( 'intval', (array) $rule['terms'] )
			);
		}

		// Make term IDs unique for each target.
		foreach ( $terms as $target => $term_ids ) {
			$terms[ $target ] = array_values( array_unique( $term_ids ) );
		}

		return $terms;
	}

	/**
	 * Exclude products with specified target terms from the query.
	 *
	 * @param \WP_Query $query The WP_Query instance to modify.
	 * @param array     $target_terms Array of target taxonomies and their term IDs to exclude.
	 */
	private function exclude_target_terms( \WP_Query $query, array $target_terms ): void {

		$tax_query = (array) $query->get( 'tax_query' );

		// Make term IDs unique for each target.
		foreach ( $target_terms as $target => $term_ids ) {

			$tax_query[] = array(
				'taxonomy'         => $target,
				'field'            => 'term_id',
				'terms'            => array_values( array_unique( $term_ids ) ),
				'operator'         => 'NOT IN',
				'include_children' => true,
			);
		}
		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Get hidden terms of the custom taxonomy based on current user roles.
	 */
	private function get_hidden_terms_of_custom_taxonomy(): array {
		$hidden_terms = array_map(
			function ( $role ) {
				return 'hide-for-' . $role;
			},
			$this->user_roles
		);
		return $hidden_terms;
	}

	/**
	 * Exclude products by custom taxonomy terms from the query.
	 *
	 * @param \WP_Query $query The WP_Query instance to modify.
	 */
	private function exclude_by_custom_taxonomy( \WP_Query $query ): void {
		$hidden_terms = $this->get_hidden_terms_of_custom_taxonomy();

		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => $this->plugin->custom_taxonomy,
			'field'    => 'slug',
			'terms'    => $hidden_terms,
			'operator' => 'NOT IN',
		);
		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Hide products in shop, category, and tag archives
	 *
	 * @param \WP_Query $query The WP_Query instance to modify.
	 */
	public function filter_product_query( \WP_Query $query ): void {

		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$is_product_search = $query->is_search();

		if ( ! $is_product_search ) {
			return;
		}

		$this->apply_visibility_query( $query );
	}


	/**
	 * Replace "No products found" block content (for block themes)
	 *
	 * @param string $block_content The original block content.
	 * @param array  $block The block data.
	 */
	public function filter_no_products_block( string $block_content, array $block ): string {
		if ( 'woocommerce/product-collection-no-results' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( empty( $this->rules ) || ! $this->has_global_hide_rule() ) {
			return $block_content;
		}

		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return wp_kses_post( $this->get_login_message() );
		}

		return wp_kses_post( $this->get_hidden_for_role_message() );
	}

	/**
	 * Maybe hide single product page based on visibility rules.
	 */
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
		$product_id = $post->ID;

		$should_hide = $this->has_global_hide_rule()
			|| $this->has_global_target_terms_hide_rule( $product_id )
			|| $this->has_product_specific_hide_rule( $product_id );

		$should_hide = apply_filters( 'riaco_hpburfw_is_product_hidden', $should_hide, $product_id, $user );

		if ( $should_hide ) {
			$this->redirect_blocked_user( $user, $product_id );
		}
	}

	/**
	 * Redirect blocked user to login or shop page.
	 *
	 * @param \WP_User $user Current user object.
	 *  @param int      $product_id Current product ID.
	 */
	private function redirect_blocked_user( \WP_User $user, int $product_id ): void {
		if ( ! $user->exists() ) {
			$url = apply_filters( 'riaco_hpburfw_redirect_url', wp_login_url( get_permalink( $product_id ) ), $product_id, $user );
			wp_safe_redirect( $url );
			exit;
		}

		$url = apply_filters( 'riaco_hpburfw_redirect_url', wc_get_page_permalink( 'shop' ), $product_id, $user );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Check if product has product-specific hide rule.
	 *
	 * @param int $product_id Product ID.
	 */
	private function has_product_specific_hide_rule( $product_id ) {
		$hidden_terms = $this->get_hidden_terms_of_custom_taxonomy();

		$assigned_terms = wp_get_object_terms(
			$product_id,
			$this->plugin->custom_taxonomy,
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

	/**
	 * Check if product has global target terms hide rule.
	 *
	 * @param int $product_id Product ID.
	 */
	private function has_global_target_terms_hide_rule( $product_id ) {
		$hidden_target_terms = $this->get_hidden_target_terms();

		if ( ! empty( $hidden_target_terms ) ) {
			// If product has any of these terms, hide it.
			foreach ( $hidden_target_terms as $key => $terms ) {
				if ( has_term( $terms, $key, $product_id ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Filter WooCommerce product query.
	 *
	 * @param \WC_Product_Query $query The WooCommerce product query.
	 */
	public function filter_wc_product_query( $query ): void {
		$this->apply_visibility_query( $query );
	}

	/**
	 * Maybe hide a product variation based on visibility rules.
	 *
	 * @param array|false           $variation_data Variation data or false to hide.
	 * @param \WC_Product           $product Parent product.
	 * @param \WC_Product_Variation $variation Variation product.
	 */
	public function maybe_hide_variation( $variation_data, $product, $variation ) {
		if ( ! $variation_data ) {
			return $variation_data;
		}

		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' );
		$user_roles = apply_filters( 'riaco_hpburfw_user_roles', $user_roles, $user );

		// Build the slugs for terms we should hide.
		$hidden_terms = array_map( fn( $r ) => 'hide-for-' . sanitize_title( $r ), $user_roles );

		// Get variation terms.
		$variation_terms = wp_get_object_terms( $variation->get_id(), $this->plugin->custom_taxonomy, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $variation_terms ) ) {
			return $variation_data;
		}

		$should_hide = false;
		foreach ( $variation_terms as $term ) {
			if ( in_array( $term, $hidden_terms, true ) ) {
				$should_hide = true;
				break;
			}
		}

		$should_hide = apply_filters( 'riaco_hpburfw_is_variation_hidden', $should_hide, $variation->get_id(), $user );

		return $should_hide ? false : $variation_data;
	}

	/**
	 * Apply hide rules to WP_Query args array.
	 *
	 * @param array $args WP_Query args.
	 */
	public function apply_hide_rules_to_args( $args ) {

		if ( empty( $this->rules ) ) {
			return $args;
		}

		// 1️. Global rule for all products
		if ( $this->has_global_hide_rule() ) {
			$args['post__in'] = array( 0 );
			return $args;
		}

		// 2️. Category-specific hide
		$target_terms = $this->get_hidden_target_terms();

		if ( ! empty( $target_terms ) ) {

			// Make term IDs unique for each target.
			foreach ( $target_terms as $target => $term_ids ) {
				$args['tax_query'][] = array(
					'taxonomy'         => $target,
					'field'            => 'term_id',
					'terms'            => array_values( array_unique( $term_ids ) ),
					'operator'         => 'NOT IN',
					'include_children' => true,
				);
			}
		}

		// 3️. Product-specific visibility via taxonomy.
		$hidden_terms = $this->get_hidden_terms_of_custom_taxonomy();

		if ( ! isset( $args['tax_query'] ) ) {
			$args['tax_query'] = array();
		}

		$args['tax_query'][] = array(
			'taxonomy' => $this->plugin->custom_taxonomy,
			'field'    => 'slug',
			'terms'    => $hidden_terms,
			'operator' => 'NOT IN',
		);

		return $args;
	}

	/**
	 * FiboSearch compatibility
	 *
	 * @param array $args WP_Query args.
	 */
	public function fibosearch_compatibility( $args ) {
		return $this->apply_hide_rules_to_args( $args );
	}

	/**
	 * Maybe hide products in REST API based on visibility rules.
	 *
	 * @param array            $args WP_Query args.
	 * @param \WP_REST_Request $request The REST API request.
	 */
	public function maybe_hide_product_in_rest_api( $args, $request ) {
		return $this->apply_hide_rules_to_args( $args );
	}

	/**
	 * Get login message HTML.
	 */
	public function get_login_message(): string {
		$login_url    = wp_login_url( get_permalink() ); // Redirect back to this product after login.
		$register_url = '';

		if ( get_option( 'users_can_register' ) ) {
			$register_url = wp_registration_url();
		}

		$message  = '<div class="woocommerce-info">';
		$message .= esc_html__( 'You must be logged in to view this product.', 'riaco-hide-products-by-user-role-for-woocommerce' ) . ' ';
		$message .= '<a href="' . esc_url( $login_url ) . '" class="woocommerce-button login-link">';
		$message .= esc_html__( 'Log in', 'riaco-hide-products-by-user-role-for-woocommerce' ) . '</a>';

		if ( $register_url ) {
			$message .= ' ' . esc_html__( 'or', 'riaco-hide-products-by-user-role-for-woocommerce' ) . ' ';
			$message .= '<a href="' . esc_url( $register_url ) . '" class="woocommerce-button register-link">';
			$message .= esc_html__( 'Register', 'riaco-hide-products-by-user-role-for-woocommerce' ) . '</a>';
		}

		$message .= '</div>';

		return $message;
	}

	/**
	 * Get hidden for role message HTML.
	 */
	public function get_hidden_for_role_message(): string {
		$logout_url = wp_logout_url( wc_get_page_permalink( 'shop' ) ); // Redirect to shop after logout.
		$shop_url   = wc_get_page_permalink( 'shop' );

		$message  = '<div class="woocommerce-info">';
		$message .= esc_html__( 'Products are hidden for your user role.', 'riaco-hide-products-by-user-role-for-woocommerce' ) . ' ';

		$message .= '<a href="' . esc_url( $logout_url ) . '" class="woocommerce-button logout-link">';
		$message .= esc_html__( 'Log out', 'riaco-hide-products-by-user-role-for-woocommerce' ) . '</a>.';

		$message .= '</div>';

		return $message;
	}
}
