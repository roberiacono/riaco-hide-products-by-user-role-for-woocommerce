<?php
/**
 * Tests for the Product_Visibility frontend service.
 *
 * Core of the plugin logic: build_visibility_conditions() is private but is
 * exercised through apply_hide_rules_to_args(), filter_wc_product_query(),
 * maybe_hide_variation(), and filter_no_products_block().
 *
 * @package Riaco\HideProducts\Tests
 */

use Riaco\HideProducts\Plugin;
use Riaco\HideProducts\Frontend\Product_Visibility;

class Test_ProductVisibility extends WP_UnitTestCase {

	private Plugin $plugin;

	/** @var array A rule that hides all products for guest. */
	private array $global_guest_rule = array(
		'order'  => 0,
		'role'   => 'guest',
		'target' => 'all_products',
		'terms'  => array(),
	);

	public function set_up(): void {
		parent::set_up();
		$this->plugin = new Plugin( dirname( __DIR__ ) . '/riaco-hide-products-by-user-role.php' );
		delete_option( 'riaco_hpburfw_rules' );
		wp_cache_delete( 'riaco_hpburfw_rules', 'riaco_hpburfw' );
		wp_set_current_user( 0 ); // guest by default
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		wp_cache_flush();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function make_pv( array $rules = array() ): Product_Visibility {
		update_option( 'riaco_hpburfw_rules', $rules );
		wp_cache_delete( 'riaco_hpburfw_rules', 'riaco_hpburfw' );
		return new Product_Visibility( $this->plugin );
	}

	private function make_simple_product(): WC_Product_Simple {
		$p = new WC_Product_Simple();
		$p->set_name( 'Test Product' );
		$p->set_status( 'publish' );
		$p->set_regular_price( '10' );
		$p->save();
		return $p;
	}

	private function make_variation( int $parent_id ): WC_Product_Variation {
		$v = new WC_Product_Variation();
		$v->set_parent_id( $parent_id );
		$v->set_status( 'publish' );
		$v->save();
		return $v;
	}

	// -------------------------------------------------------------------------
	// apply_hide_rules_to_args — no rules
	// -------------------------------------------------------------------------

	public function test_no_rules_does_not_apply_global_hide(): void {
		$pv   = $this->make_pv( array() );
		$args = array( 'post_type' => 'product' );

		$result = $pv->apply_hide_rules_to_args( $args );

		// Level 3 (product-specific taxonomy) always fires, so args will have a
		// tax_query — but no global hide (post__in) should be added with no rules.
		$this->assertArrayNotHasKey( 'post__in', $result );
		$this->assertArrayHasKey( 'tax_query', $result );
	}

	// -------------------------------------------------------------------------
	// apply_hide_rules_to_args — global hide (all_products)
	// -------------------------------------------------------------------------

	public function test_all_products_rule_for_guest_sets_post_in_zero(): void {
		$pv     = $this->make_pv( array( $this->global_guest_rule ) );
		$result = $pv->apply_hide_rules_to_args( array() );

		$this->assertArrayHasKey( 'post__in', $result );
		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	public function test_all_products_rule_does_not_apply_to_different_role(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$pv     = $this->make_pv( array( $this->global_guest_rule ) );
		$result = $pv->apply_hide_rules_to_args( array() );

		// No post__in — rule was for guest only.
		$this->assertArrayNotHasKey( 'post__in', $result );
	}

	public function test_all_products_rule_applies_to_matching_logged_in_role(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$rule = array(
			'order'  => 0,
			'role'   => 'subscriber',
			'target' => 'all_products',
			'terms'  => array(),
		);
		$pv     = $this->make_pv( array( $rule ) );
		$result = $pv->apply_hide_rules_to_args( array() );

		$this->assertArrayHasKey( 'post__in', $result );
		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	// -------------------------------------------------------------------------
	// apply_hide_rules_to_args — category-level hide
	// -------------------------------------------------------------------------

	public function test_category_rule_adds_not_in_tax_query(): void {
		$cat_rule = array(
			'order'  => 0,
			'role'   => 'guest',
			'target' => 'product_cat',
			'terms'  => array( 5, 10 ),
		);

		$pv     = $this->make_pv( array( $cat_rule ) );
		$result = $pv->apply_hide_rules_to_args( array() );

		$this->assertArrayHasKey( 'tax_query', $result );

		$found = false;
		foreach ( $result['tax_query'] as $condition ) {
			if ( is_array( $condition ) && isset( $condition['taxonomy'] ) && 'product_cat' === $condition['taxonomy'] ) {
				$this->assertSame( 'NOT IN', $condition['operator'] );
				$this->assertSame( array( 5, 10 ), $condition['terms'] );
				$found = true;
			}
		}
		$this->assertTrue( $found, 'product_cat NOT IN tax_query condition not found.' );
	}

	public function test_two_rules_for_same_target_merge_term_ids(): void {
		$rules = array(
			array( 'order' => 0, 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( 5 ) ),
			array( 'order' => 1, 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( 10 ) ),
		);

		$pv     = $this->make_pv( $rules );
		$result = $pv->apply_hide_rules_to_args( array() );

		$this->assertArrayHasKey( 'tax_query', $result );

		$merged_terms = null;
		foreach ( $result['tax_query'] as $condition ) {
			if ( is_array( $condition ) && isset( $condition['taxonomy'] ) && 'product_cat' === $condition['taxonomy'] ) {
				$merged_terms = $condition['terms'];
			}
		}

		$this->assertNotNull( $merged_terms );
		$this->assertContains( 5, $merged_terms );
		$this->assertContains( 10, $merged_terms );
	}

	public function test_rules_for_different_targets_produce_separate_tax_conditions(): void {
		$rules = array(
			array( 'order' => 0, 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( 5 ) ),
			array( 'order' => 1, 'role' => 'guest', 'target' => 'product_tag', 'terms' => array( 99 ) ),
		);

		$pv     = $this->make_pv( $rules );
		$result = $pv->apply_hide_rules_to_args( array() );

		$this->assertArrayHasKey( 'tax_query', $result );

		$found_cat = false;
		$found_tag = false;
		foreach ( $result['tax_query'] as $condition ) {
			if ( ! is_array( $condition ) || ! isset( $condition['taxonomy'] ) ) {
				continue;
			}
			if ( 'product_cat' === $condition['taxonomy'] ) {
				$found_cat = true;
			}
			if ( 'product_tag' === $condition['taxonomy'] ) {
				$found_tag = true;
			}
		}
		$this->assertTrue( $found_cat, 'product_cat condition missing.' );
		$this->assertTrue( $found_tag, 'product_tag condition missing.' );
	}

	// -------------------------------------------------------------------------
	// apply_hide_rules_to_args — product-specific (custom taxonomy)
	// -------------------------------------------------------------------------

	public function test_product_specific_hide_adds_not_in_slug_condition(): void {
		$pv     = $this->make_pv( array() ); // no global/category rules
		$result = $pv->apply_hide_rules_to_args( array() );

		// The custom taxonomy NOT IN condition is always added (Level 3).
		$this->assertArrayHasKey( 'tax_query', $result );

		$found = false;
		foreach ( $result['tax_query'] as $condition ) {
			if ( is_array( $condition ) && isset( $condition['taxonomy'] ) && 'riaco_hpburfw_visibility_role' === $condition['taxonomy'] ) {
				$this->assertSame( 'NOT IN', $condition['operator'] );
				$this->assertSame( 'slug', $condition['field'] );
				$this->assertContains( 'hide-for-guest', $condition['terms'] );
				$found = true;
			}
		}
		$this->assertTrue( $found, 'Custom taxonomy NOT IN condition not found in tax_query.' );
	}

	public function test_product_specific_hide_slug_uses_logged_in_role(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$pv     = $this->make_pv( array() );
		$result = $pv->apply_hide_rules_to_args( array() );

		$this->assertArrayHasKey( 'tax_query', $result );

		$found = false;
		foreach ( $result['tax_query'] as $condition ) {
			if ( is_array( $condition ) && isset( $condition['taxonomy'] ) && 'riaco_hpburfw_visibility_role' === $condition['taxonomy'] ) {
				$this->assertContains( 'hide-for-subscriber', $condition['terms'] );
				$this->assertNotContains( 'hide-for-guest', $condition['terms'] );
				$found = true;
			}
		}
		$this->assertTrue( $found );
	}

	// -------------------------------------------------------------------------
	// apply_hide_rules_to_args — existing tax_query merging
	// -------------------------------------------------------------------------

	public function test_existing_tax_query_wrapped_in_and_group(): void {
		$existing_tax_query = array(
			'relation' => 'OR',
			array( 'taxonomy' => 'product_cat', 'terms' => array( 1 ), 'field' => 'term_id', 'operator' => 'IN' ),
		);

		$pv     = $this->make_pv( array( $this->global_guest_rule ) );
		$result = $pv->apply_hide_rules_to_args( array( 'tax_query' => $existing_tax_query ) );

		// Global rule — uses post__in, not tax_query.
		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	public function test_existing_tax_query_with_category_rule_is_nested_and(): void {
		$existing = array(
			'relation' => 'OR',
			array( 'taxonomy' => 'product_cat', 'terms' => array( 1 ), 'field' => 'term_id', 'operator' => 'IN' ),
		);
		$cat_rule = array( 'order' => 0, 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( 5 ) );

		$pv     = $this->make_pv( array( $cat_rule ) );
		$result = $pv->apply_hide_rules_to_args( array( 'tax_query' => $existing ) );

		$this->assertArrayHasKey( 'tax_query', $result );
		$this->assertSame( 'AND', $result['tax_query']['relation'] );
		// The nested structure should contain our existing query as one element.
		$this->assertContains( $existing, $result['tax_query'] );
	}

	// -------------------------------------------------------------------------
	// Filter: riaco_hpburfw_user_roles
	// -------------------------------------------------------------------------

	public function test_user_roles_filter_overrides_roles(): void {
		// User is a subscriber, but filter returns 'guest'.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$callback = function ( $roles ) {
			return array( 'guest' );
		};
		add_filter( 'riaco_hpburfw_user_roles', $callback );

		$pv     = $this->make_pv( array( $this->global_guest_rule ) );
		$result = $pv->apply_hide_rules_to_args( array() );

		remove_filter( 'riaco_hpburfw_user_roles', $callback );

		// After filter, user treated as guest — rule should apply.
		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	// -------------------------------------------------------------------------
	// Filter: riaco_hpburfw_visibility_rules
	// -------------------------------------------------------------------------

	public function test_visibility_rules_filter_injects_rules(): void {
		$callback = function ( $rules ) {
			$rules[] = $this->global_guest_rule;
			return $rules;
		};
		add_filter( 'riaco_hpburfw_visibility_rules', $callback );

		$pv     = $this->make_pv( array() ); // empty option
		$result = $pv->apply_hide_rules_to_args( array() );

		remove_filter( 'riaco_hpburfw_visibility_rules', $callback );

		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	// -------------------------------------------------------------------------
	// Filter: riaco_hpburfw_rule_applies
	// -------------------------------------------------------------------------

	public function test_rule_applies_filter_can_veto_matching_rule(): void {
		// Guest with a global hide rule, but filter vetoes it.
		$callback = function ( $applies ) {
			return false; // never applies
		};
		add_filter( 'riaco_hpburfw_rule_applies', $callback );

		$pv     = $this->make_pv( array( $this->global_guest_rule ) );
		$result = $pv->apply_hide_rules_to_args( array() );

		remove_filter( 'riaco_hpburfw_rule_applies', $callback );

		$this->assertArrayNotHasKey( 'post__in', $result );
	}

	public function test_rule_applies_filter_can_force_apply_for_non_matching_role(): void {
		// Subscriber user, guest rule — filter forces it to apply anyway.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$callback = function ( $applies ) {
			return true; // always applies
		};
		add_filter( 'riaco_hpburfw_rule_applies', $callback );

		$pv     = $this->make_pv( array( $this->global_guest_rule ) ); // guest rule
		$result = $pv->apply_hide_rules_to_args( array() );

		remove_filter( 'riaco_hpburfw_rule_applies', $callback );

		// Filter forced rule to apply even though user is subscriber.
		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	// -------------------------------------------------------------------------
	// Rules sorted by 'order'
	// -------------------------------------------------------------------------

	public function test_rules_are_processed_in_order_ascending(): void {
		$cat_rule        = array( 'order' => 10, 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( 5 ) );
		$all_rule_first  = array( 'order' => 1, 'role' => 'guest', 'target' => 'all_products', 'terms' => array() );

		// all_products rule has lower order (higher priority).
		$pv     = $this->make_pv( array( $cat_rule, $all_rule_first ) );
		$result = $pv->apply_hide_rules_to_args( array() );

		// all_products rule wins because order=1 < order=10.
		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	// -------------------------------------------------------------------------
	// filter_wc_product_query
	// -------------------------------------------------------------------------

	public function test_filter_wc_product_query_sets_post_in_for_global_hide(): void {
		$pv    = $this->make_pv( array( $this->global_guest_rule ) );
		$query = new WP_Query();

		$pv->filter_wc_product_query( $query );

		$this->assertSame( array( 0 ), $query->get( 'post__in' ) );
	}

	public function test_filter_wc_product_query_adds_tax_query_for_category_rule(): void {
		$cat_rule = array( 'order' => 0, 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( 7 ) );
		$pv       = $this->make_pv( array( $cat_rule ) );
		$query    = new WP_Query();

		$pv->filter_wc_product_query( $query );

		$tax_query = $query->get( 'tax_query' );
		$this->assertNotEmpty( $tax_query );
	}

	// -------------------------------------------------------------------------
	// maybe_hide_variation
	// -------------------------------------------------------------------------

	public function test_variation_with_no_hide_term_is_not_hidden(): void {
		$product   = $this->make_simple_product();
		$variation = $this->make_variation( $product->get_id() );
		// No terms assigned to the variation.

		$pv             = $this->make_pv( array() );
		$variation_data = array( 'price_html' => '$10' );

		$result = $pv->maybe_hide_variation( $variation_data, $product, $variation );

		$this->assertSame( $variation_data, $result );
	}

	public function test_variation_with_hide_for_guest_term_is_hidden_for_guest(): void {
		$product   = $this->make_simple_product();
		$variation = $this->make_variation( $product->get_id() );

		wp_set_object_terms( $variation->get_id(), array( 'hide-for-guest' ), 'riaco_hpburfw_visibility_role' );

		$pv             = $this->make_pv( array() );
		$variation_data = array( 'price_html' => '$10' );

		$result = $pv->maybe_hide_variation( $variation_data, $product, $variation );

		$this->assertFalse( $result );
	}

	public function test_variation_with_hide_for_guest_is_shown_to_subscriber(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$product   = $this->make_simple_product();
		$variation = $this->make_variation( $product->get_id() );
		wp_set_object_terms( $variation->get_id(), array( 'hide-for-guest' ), 'riaco_hpburfw_visibility_role' );

		$pv             = $this->make_pv( array() );
		$variation_data = array( 'price_html' => '$10' );

		$result = $pv->maybe_hide_variation( $variation_data, $product, $variation );

		$this->assertSame( $variation_data, $result );
	}

	public function test_variation_is_hidden_when_global_rule_matches(): void {
		$product   = $this->make_simple_product();
		$variation = $this->make_variation( $product->get_id() );

		// No variation-specific terms, but global rule hides all for guest.
		$pv             = $this->make_pv( array( $this->global_guest_rule ) );
		$variation_data = array( 'price_html' => '$10' );

		$result = $pv->maybe_hide_variation( $variation_data, $product, $variation );

		$this->assertFalse( $result );
	}

	public function test_variation_false_input_returned_as_is(): void {
		$product   = $this->make_simple_product();
		$variation = $this->make_variation( $product->get_id() );

		$pv    = $this->make_pv( array( $this->global_guest_rule ) );
		$result = $pv->maybe_hide_variation( false, $product, $variation );

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// Filter: riaco_hpburfw_is_variation_hidden
	// -------------------------------------------------------------------------

	public function test_is_variation_hidden_filter_can_force_show(): void {
		$product   = $this->make_simple_product();
		$variation = $this->make_variation( $product->get_id() );
		wp_set_object_terms( $variation->get_id(), array( 'hide-for-guest' ), 'riaco_hpburfw_visibility_role' );

		$callback = function () {
			return false; // always show
		};
		add_filter( 'riaco_hpburfw_is_variation_hidden', $callback );

		$pv             = $this->make_pv( array() );
		$variation_data = array( 'price_html' => '$10' );
		$result         = $pv->maybe_hide_variation( $variation_data, $product, $variation );

		remove_filter( 'riaco_hpburfw_is_variation_hidden', $callback );

		$this->assertSame( $variation_data, $result );
	}

	public function test_is_variation_hidden_filter_can_force_hide(): void {
		$product   = $this->make_simple_product();
		$variation = $this->make_variation( $product->get_id() );
		// No hide terms assigned.

		$callback = function () {
			return true; // always hide
		};
		add_filter( 'riaco_hpburfw_is_variation_hidden', $callback );

		$pv             = $this->make_pv( array() );
		$variation_data = array( 'price_html' => '$10' );
		$result         = $pv->maybe_hide_variation( $variation_data, $product, $variation );

		remove_filter( 'riaco_hpburfw_is_variation_hidden', $callback );

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// maybe_hide_product_in_rest_api
	// -------------------------------------------------------------------------

	public function test_rest_api_edit_context_skips_filtering(): void {
		$pv      = $this->make_pv( array( $this->global_guest_rule ) );
		$request = new WP_REST_Request( 'GET', '/wc/v3/products' );
		$request->set_param( 'context', 'edit' );

		$result = $pv->maybe_hide_product_in_rest_api( array(), $request );

		$this->assertArrayNotHasKey( 'post__in', $result );
	}

	public function test_rest_api_view_context_is_filtered(): void {
		$pv      = $this->make_pv( array( $this->global_guest_rule ) );
		$request = new WP_REST_Request( 'GET', '/wc/v3/products' );
		$request->set_param( 'context', 'view' );

		$result = $pv->maybe_hide_product_in_rest_api( array(), $request );

		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	public function test_rest_api_no_context_is_filtered(): void {
		$pv      = $this->make_pv( array( $this->global_guest_rule ) );
		$request = new WP_REST_Request( 'GET', '/wc/v3/products' );

		$result = $pv->maybe_hide_product_in_rest_api( array(), $request );

		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	// -------------------------------------------------------------------------
	// fibosearch_compatibility
	// -------------------------------------------------------------------------

	public function test_fibosearch_compatibility_applies_rules(): void {
		$pv     = $this->make_pv( array( $this->global_guest_rule ) );
		$result = $pv->fibosearch_compatibility( array() );

		$this->assertSame( array( 0 ), $result['post__in'] );
	}

	// -------------------------------------------------------------------------
	// Message HTML helpers
	// -------------------------------------------------------------------------

	public function test_get_login_message_contains_login_link(): void {
		$pv      = $this->make_pv( array() );
		$message = $pv->get_login_message();

		$this->assertStringContainsString( 'href=', $message );
		$this->assertStringContainsString( 'wp-login.php', $message );
		$this->assertStringContainsString( 'Log in', $message );
	}

	public function test_get_login_message_contains_register_link_when_registration_enabled(): void {
		update_option( 'users_can_register', 1 );

		$pv      = $this->make_pv( array() );
		$message = $pv->get_login_message();

		$this->assertStringContainsString( 'Register', $message );
	}

	public function test_get_hidden_for_role_message_contains_logout_link(): void {
		$pv      = $this->make_pv( array() );
		$message = $pv->get_hidden_for_role_message();

		$this->assertStringContainsString( 'wp-login.php?action=logout', $message );
		$this->assertStringContainsString( 'Log out', $message );
	}

	// -------------------------------------------------------------------------
	// filter_no_products_block
	// -------------------------------------------------------------------------

	public function test_filter_no_products_block_unchanged_for_wrong_block_name(): void {
		$pv      = $this->make_pv( array( $this->global_guest_rule ) );
		$content = '<p>No results</p>';
		$block   = array( 'blockName' => 'core/paragraph' );

		$result = $pv->filter_no_products_block( $content, $block );

		$this->assertSame( $content, $result );
	}

	public function test_filter_no_products_block_unchanged_when_no_global_hide_rule(): void {
		$cat_rule = array( 'order' => 0, 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( 1 ) );
		$pv       = $this->make_pv( array( $cat_rule ) );
		$content  = '<p>No results</p>';
		$block    = array( 'blockName' => 'woocommerce/product-collection-no-results' );

		$result = $pv->filter_no_products_block( $content, $block );

		$this->assertSame( $content, $result );
	}

	public function test_filter_no_products_block_returns_login_message_for_guest(): void {
		$pv      = $this->make_pv( array( $this->global_guest_rule ) );
		$block   = array( 'blockName' => 'woocommerce/product-collection-no-results' );

		$result = $pv->filter_no_products_block( '<p>No results</p>', $block );

		$this->assertStringContainsString( 'Log in', $result );
		$this->assertStringContainsString( 'wp-login.php', $result );
	}

	public function test_filter_no_products_block_returns_role_message_for_logged_in_user(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$sub_rule = array( 'order' => 0, 'role' => 'subscriber', 'target' => 'all_products', 'terms' => array() );
		$pv       = $this->make_pv( array( $sub_rule ) );
		$block    = array( 'blockName' => 'woocommerce/product-collection-no-results' );

		$result = $pv->filter_no_products_block( '<p>No results</p>', $block );

		$this->assertStringContainsString( 'Log out', $result );
		$this->assertStringNotContainsString( 'Log in', $result );
	}
}
