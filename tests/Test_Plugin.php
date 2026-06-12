<?php
/**
 * Tests for the Plugin class.
 *
 * @package Riaco\HideProducts\Tests
 */

use Riaco\HideProducts\Plugin;
use Riaco\HideProducts\Interfaces\ServiceInterface;

class Test_Plugin extends WP_UnitTestCase {

	private Plugin $plugin;

	public function set_up(): void {
		parent::set_up();
		$this->plugin = new Plugin( dirname( __DIR__ ) . '/riaco-hide-products-by-user-role.php' );
	}

	// -------------------------------------------------------------------------
	// get_roles
	// -------------------------------------------------------------------------

	public function test_get_roles_includes_guest(): void {
		$roles = $this->plugin->get_roles();
		$this->assertArrayHasKey( 'guest', $roles );
	}

	public function test_get_roles_guest_is_first(): void {
		$roles = $this->plugin->get_roles();
		$this->assertSame( 'guest', array_key_first( $roles ) );
	}

	public function test_get_roles_includes_registered_wp_roles(): void {
		$roles = $this->plugin->get_roles();
		$this->assertArrayHasKey( 'administrator', $roles );
		$this->assertArrayHasKey( 'subscriber', $roles );
	}

	public function test_get_roles_filter_adds_custom_role(): void {
		$callback = function ( $roles ) {
			$roles['premium'] = array( 'name' => 'Premium Member' );
			return $roles;
		};
		add_filter( 'riaco_hpburfw_roles', $callback );

		$roles = $this->plugin->get_roles();

		remove_filter( 'riaco_hpburfw_roles', $callback );

		$this->assertArrayHasKey( 'premium', $roles );
		$this->assertSame( 'Premium Member', $roles['premium']['name'] );
	}

	public function test_get_roles_filter_can_remove_role(): void {
		$callback = function ( $roles ) {
			unset( $roles['subscriber'] );
			return $roles;
		};
		add_filter( 'riaco_hpburfw_roles', $callback );

		$roles = $this->plugin->get_roles();

		remove_filter( 'riaco_hpburfw_roles', $callback );

		$this->assertArrayNotHasKey( 'subscriber', $roles );
	}

	// -------------------------------------------------------------------------
	// add_action_links
	// -------------------------------------------------------------------------

	public function test_add_action_links_prepends_settings_link(): void {
		$existing = array( '<a href="/existing">Deactivate</a>' );
		$result   = $this->plugin->add_action_links( $existing );

		$this->assertCount( 2, $result );
		$this->assertStringContainsString( 'Settings', $result[0] );
		$this->assertStringContainsString( 'wc-settings', $result[0] );
		$this->assertSame( $existing[0], $result[1] );
	}

	// -------------------------------------------------------------------------
	// is_loaded / load / register_service
	// -------------------------------------------------------------------------

	public function test_is_loaded_is_false_by_default(): void {
		$fresh = new Plugin( '/tmp/fake.php' );
		$this->assertFalse( $fresh->is_loaded() );
	}

	public function test_register_service_calls_register_on_service(): void {
		$service = new class implements ServiceInterface {
			public bool $registered = false;
			public function register(): void {
				$this->registered = true;
			}
		};

		$this->plugin->register_service( $service );

		$this->assertTrue( $service->registered );
	}

	// -------------------------------------------------------------------------
	// admin_footer_review_text
	// -------------------------------------------------------------------------

	public function test_admin_footer_review_text_returns_original_when_not_on_settings_screen(): void {
		// No screen set — should return unchanged.
		$original = 'Original footer text';
		$result   = $this->plugin->admin_footer_review_text( $original );
		$this->assertSame( $original, $result );
	}
}
