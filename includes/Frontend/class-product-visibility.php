<?php
namespace Riaco\HideProducts\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

class ProductVisibility implements ServiceInterface  {

	public function register(): void {
		// add_filter( 'woocommerce_product_is_visible', array( $this, 'filter_product_visibility' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_product_query' ) );
	}

	/**
	 * Hide single product pages for users not in selected roles
	 */
	/*
	public function filter_product_visibility( bool $visible, int $product_id ): bool {
		$not_allowed_roles = (array) get_post_meta( $product_id, '_riaco_hpfw_roles', true );

		error_log( '$not_allowed_roles ' . print_r( $not_allowed_roles, true ) );
		if ( empty( $not_allowed_roles ) ) {
			return $visible; // visible if no roles selected
		}

		$user       = wp_get_current_user();
		$user_roles = $user->exists() ? $user->roles : array( 'guest' ); // add guest role if not logged in

		return empty( array_intersect( $not_allowed_roles, $user_roles ) );
	} */


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

		// Hide products that have any of the user's roles as _riaco_hpfw_role
		$meta_query = array(
			'relation' => 'OR',

			// Show products with no restriction (no meta key)
			array(
				'key'     => '_riaco_hpfw_role',
				'compare' => 'NOT EXISTS',
			),

			// Show products that are NOT restricted to this user
			array(
				'key'     => '_riaco_hpfw_role',
				'value'   => $user_roles,
				'compare' => 'NOT IN',
			),
		);

		$query->set( 'meta_query', $meta_query );
	}
}
