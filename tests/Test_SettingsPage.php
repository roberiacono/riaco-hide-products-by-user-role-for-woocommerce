<?php
/**
 * Tests for the Settings_Page admin service.
 *
 * @package Riaco\HideProducts\Tests
 */

use Riaco\HideProducts\Plugin;
use Riaco\HideProducts\Admin\Settings_Page;

class Test_SettingsPage extends WP_UnitTestCase {

	private Plugin        $plugin;
	private Settings_Page $settings_page;

	public function set_up(): void {
		parent::set_up();
		$this->plugin        = new Plugin( dirname( __DIR__ ) . '/riaco-hide-products-by-user-role.php' );
		$this->settings_page = new Settings_Page( $this->plugin );

		delete_option( $this->plugin->option_key );
		wp_cache_delete( 'riaco_hpburfw_rules', 'riaco_hpburfw' );
	}

	public function tear_down(): void {
		// Reset $_POST so no test bleeds into another.
		$_POST = array();
		wp_cache_flush();
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// add_settings_section label
	// -------------------------------------------------------------------------

	public function test_add_settings_section_shows_no_count_when_no_rules(): void {
		delete_option( $this->plugin->option_key );

		$sections = $this->settings_page->add_settings_section( array() );

		$this->assertArrayHasKey( 'riaco_hpburfw_rules', $sections );
		$this->assertStringNotContainsString( '(', $sections['riaco_hpburfw_rules'] );
	}

	public function test_add_settings_section_shows_count_when_rules_exist(): void {
		update_option(
			$this->plugin->option_key,
			array(
				array( 'order' => 0, 'role' => 'guest', 'target' => 'all_products', 'terms' => array() ),
				array( 'order' => 1, 'role' => 'subscriber', 'target' => 'product_cat', 'terms' => array( 5 ) ),
			)
		);

		$sections = $this->settings_page->add_settings_section( array() );

		$this->assertStringContainsString( '(2)', $sections['riaco_hpburfw_rules'] );
	}

	public function test_add_settings_section_preserves_existing_sections(): void {
		$original = array( 'general' => 'General', 'inventory' => 'Inventory' );
		$sections = $this->settings_page->add_settings_section( $original );

		$this->assertArrayHasKey( 'general', $sections );
		$this->assertArrayHasKey( 'inventory', $sections );
	}

	// -------------------------------------------------------------------------
	// save_custom_settings — sanitization and persistence
	// -------------------------------------------------------------------------

	private function set_up_admin_user_and_nonce(): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user     = get_user_by( 'id', $admin_id );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $admin_id );

		$_POST['riaco_hpburfw_nonce'] = wp_create_nonce( 'riaco_hpburfw_save_rules' );
	}

	public function test_save_sanitizes_order_as_absint(): void {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			$this->markTestSkipped( 'WC_Admin_Settings not available.' );
		}

		$this->set_up_admin_user_and_nonce();
		$_POST['riaco_hpburfw_rules'] = array(
			array( 'order' => '-5', 'role' => 'guest', 'target' => 'all_products', 'terms' => array() ),
		);

		$this->settings_page->save_custom_settings();

		$saved = get_option( $this->plugin->option_key );
		$this->assertSame( 5, $saved[0]['order'] );
	}

	public function test_save_sanitizes_role_as_text_field(): void {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			$this->markTestSkipped( 'WC_Admin_Settings not available.' );
		}

		$this->set_up_admin_user_and_nonce();
		// Inline HTML is stripped; text content of non-script tags is kept.
		$_POST['riaco_hpburfw_rules'] = array(
			array( 'order' => '0', 'role' => 'guest<b>bad</b>', 'target' => 'all_products', 'terms' => array() ),
		);

		$this->settings_page->save_custom_settings();

		$saved = get_option( $this->plugin->option_key );
		$this->assertSame( 'guestbad', $saved[0]['role'] );
	}

	public function test_save_sanitizes_terms_as_absint(): void {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			$this->markTestSkipped( 'WC_Admin_Settings not available.' );
		}

		$this->set_up_admin_user_and_nonce();
		$_POST['riaco_hpburfw_rules'] = array(
			array( 'order' => '0', 'role' => 'guest', 'target' => 'product_cat', 'terms' => array( '5', '10', '-3' ) ),
		);

		$this->settings_page->save_custom_settings();

		$saved = get_option( $this->plugin->option_key );
		$this->assertSame( array( 5, 10, 3 ), $saved[0]['terms'] );
	}

	public function test_save_empty_post_rules_clears_option(): void {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			$this->markTestSkipped( 'WC_Admin_Settings not available.' );
		}

		update_option( $this->plugin->option_key, array( array( 'order' => 0, 'role' => 'guest', 'target' => 'all_products', 'terms' => array() ) ) );
		$this->set_up_admin_user_and_nonce();
		// No 'riaco_hpburfw_rules' key in POST — clears the option.

		$this->settings_page->save_custom_settings();

		$saved = get_option( $this->plugin->option_key );
		$this->assertSame( array(), $saved );
	}

	public function test_save_bails_without_capability(): void {
		// Set a subscriber without manage_woocommerce.
		$sub_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $sub_id );
		$_POST['riaco_hpburfw_nonce'] = wp_create_nonce( 'riaco_hpburfw_save_rules' );
		$_POST['riaco_hpburfw_rules'] = array(
			array( 'order' => '0', 'role' => 'guest', 'target' => 'all_products', 'terms' => array() ),
		);

		$this->settings_page->save_custom_settings();

		// Option should not have been updated.
		$this->assertFalse( get_option( $this->plugin->option_key, false ) );
	}

	public function test_save_bails_without_nonce(): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user     = get_user_by( 'id', $admin_id );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $admin_id );
		// No nonce in POST.
		$_POST['riaco_hpburfw_rules'] = array(
			array( 'order' => '0', 'role' => 'guest', 'target' => 'all_products', 'terms' => array() ),
		);

		$this->settings_page->save_custom_settings();

		$this->assertFalse( get_option( $this->plugin->option_key, false ) );
	}

	// -------------------------------------------------------------------------
	// Filters and actions fired during save
	// -------------------------------------------------------------------------

	public function test_rule_sanitize_filter_is_applied_on_save(): void {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			$this->markTestSkipped( 'WC_Admin_Settings not available.' );
		}

		$this->set_up_admin_user_and_nonce();
		$_POST['riaco_hpburfw_rules'] = array(
			array( 'order' => '0', 'role' => 'guest', 'target' => 'all_products', 'terms' => array(), 'custom_field' => 'hello' ),
		);

		$callback = function ( $sanitized, $raw ) {
			$sanitized['custom_field'] = sanitize_text_field( $raw['custom_field'] ?? '' );
			return $sanitized;
		};
		add_filter( 'riaco_hpburfw_rule_sanitize', $callback, 10, 2 );

		$this->settings_page->save_custom_settings();

		remove_filter( 'riaco_hpburfw_rule_sanitize', $callback, 10 );

		$saved = get_option( $this->plugin->option_key );
		$this->assertSame( 'hello', $saved[0]['custom_field'] );
	}

	public function test_rules_saved_action_fires_after_save(): void {
		if ( ! class_exists( 'WC_Admin_Settings' ) ) {
			$this->markTestSkipped( 'WC_Admin_Settings not available.' );
		}

		$this->set_up_admin_user_and_nonce();
		$_POST['riaco_hpburfw_rules'] = array(
			array( 'order' => '0', 'role' => 'guest', 'target' => 'all_products', 'terms' => array() ),
		);

		$fired = false;
		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		add_action( 'riaco_hpburfw_rules_saved', $callback );

		$this->settings_page->save_custom_settings();

		remove_action( 'riaco_hpburfw_rules_saved', $callback );

		$this->assertTrue( $fired );
	}
}
