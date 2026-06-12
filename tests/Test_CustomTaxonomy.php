<?php
/**
 * Tests for the Custom_Taxonomy service.
 *
 * @package Riaco\HideProducts\Tests
 */

use Riaco\HideProducts\Plugin;
use Riaco\HideProducts\Admin\Custom_Taxonomy;

class Test_CustomTaxonomy extends WP_UnitTestCase {

	private Plugin        $plugin;
	private Custom_Taxonomy $taxonomy_service;

	public function set_up(): void {
		parent::set_up();
		$this->plugin          = new Plugin( dirname( __DIR__ ) . '/riaco-hide-products-by-user-role.php' );
		$this->taxonomy_service = new Custom_Taxonomy( $this->plugin );
		// _delete_all_data() purges all terms between test classes; recreate them.
		$roles      = $this->plugin->get_roles();
		$roles_hash = md5( implode( ',', array_keys( $roles ) ) . $this->plugin->version );
		delete_transient( 'riaco_hpburfw_default_terms_' . $roles_hash );
		$this->taxonomy_service->maybe_create_default_terms();
	}

	public function tear_down(): void {
		wp_cache_flush();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Taxonomy registration
	// -------------------------------------------------------------------------

	public function test_taxonomy_is_registered_after_bootstrap(): void {
		// Plugin bootstrap fires init, which registers the taxonomy.
		$this->assertTrue( taxonomy_exists( 'riaco_hpburfw_visibility_role' ) );
	}

	public function test_taxonomy_is_not_public(): void {
		$taxonomy = get_taxonomy( 'riaco_hpburfw_visibility_role' );
		$this->assertFalse( $taxonomy->public );
	}

	public function test_taxonomy_applies_to_product_post_type(): void {
		$taxonomy     = get_taxonomy( 'riaco_hpburfw_visibility_role' );
		$object_types = (array) $taxonomy->object_type;
		$this->assertContains( 'product', $object_types );
	}

	public function test_taxonomy_applies_to_product_variation_post_type(): void {
		$taxonomy     = get_taxonomy( 'riaco_hpburfw_visibility_role' );
		$object_types = (array) $taxonomy->object_type;
		$this->assertContains( 'product_variation', $object_types );
	}

	// -------------------------------------------------------------------------
	// Default terms
	// -------------------------------------------------------------------------

	public function test_hide_for_guest_term_exists(): void {
		$this->assertTrue( (bool) term_exists( 'hide-for-guest', 'riaco_hpburfw_visibility_role' ) );
	}

	public function test_hide_for_administrator_term_exists(): void {
		$this->assertTrue( (bool) term_exists( 'hide-for-administrator', 'riaco_hpburfw_visibility_role' ) );
	}

	public function test_hide_for_subscriber_term_exists(): void {
		$this->assertTrue( (bool) term_exists( 'hide-for-subscriber', 'riaco_hpburfw_visibility_role' ) );
	}

	public function test_term_slugs_follow_hide_for_pattern(): void {
		$terms = get_terms(
			array(
				'taxonomy'   => 'riaco_hpburfw_visibility_role',
				'hide_empty' => false,
			)
		);

		$this->assertNotWPError( $terms );
		$this->assertNotEmpty( $terms );

		foreach ( $terms as $term ) {
			$this->assertStringStartsWith( 'hide-for-', $term->slug, "Term slug '{$term->slug}' does not follow hide-for-{role} pattern." );
		}
	}

	public function test_maybe_create_terms_is_idempotent(): void {
		// Running creation a second time should not duplicate terms.
		$before = get_terms( array( 'taxonomy' => 'riaco_hpburfw_visibility_role', 'hide_empty' => false ) );
		$count_before = count( $before );

		// Delete the transient cache so creation runs again.
		$roles      = $this->plugin->get_roles();
		$roles_hash = md5( implode( ',', array_keys( $roles ) ) . $this->plugin->version );
		delete_transient( 'riaco_hpburfw_default_terms_' . $roles_hash );

		$this->taxonomy_service->maybe_create_default_terms();

		$after       = get_terms( array( 'taxonomy' => 'riaco_hpburfw_visibility_role', 'hide_empty' => false ) );
		$count_after = count( $after );

		$this->assertSame( $count_before, $count_after );
	}
}
