<?php
namespace Riaco\HideProducts\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

class SettingsPage implements ServiceInterface {

	private $plugin;

	function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}


	public function enqueue_admin_scripts() {

		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'riaco-hpburfw-hide-products' ) {
			return;
		}

		global $wp_roles;

		$roles      = $wp_roles->roles;
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		$rules = get_option( 'riaco_hpburfw_rules', array() );

		wp_enqueue_script(
			'riaco-hpburfw-admin-js',
			plugins_url( 'assets/admin/admin.js', $this->plugin->file ),
			array( 'jquery' ),
			$this->plugin->version,
			true
		);

		wp_localize_script(
			'riaco-hpburfw-admin-js',
			'riacoData',
			array(
				'roles'      => $roles,
				'categories' => $categories,
				'rules'      => $rules,
			)
		);

		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style( 'dashicons' ); // WooCommerce uses dashicons

		wp_enqueue_style(
			'riaco-hpburfw-admin-css',
			plugins_url( 'assets/admin/style.css', $this->plugin->file ),
			array(),
			$this->plugin->version
		);
	}

	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Hide Products by User Role',
			'Hide by Role',
			'manage_options',
			'riaco-hpburfw-hide-products',
			array( $this, 'settings_page' )
		);
	}

	function register_settings() {
		register_setting( 'riaco_hpburfw_group', 'riaco_hpburfw_rules' );
	}

	function settings_page() {

		?>
		<div class="wrap">
	<h1>Product Visibility Rules</h1>
	<p>
	Set default global visibility rules for products.
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'riaco_hpburfw_group' ); ?>
		<table class="wp-list-table widefat fixed striped" id="riaco-hpburfw-rules">
			<thead>
				<tr>
					<th>Priority</th>
					<th>User Role</th>
					<th>Target</th>
					<th>Terms</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>

		<p>
			<button type="button" class="button" id="add-rule">Add Rule</button>
		</p>

		<?php submit_button(); ?>
	</form>
</div>

		<?php
	}

	/**
	 * Add settings to our section
	 */
	public function add_settings_fields( $settings, $current_section ): array {
		if ( 'riaco_visibility' !== $current_section ) {
			return $settings;
		}

		$roles = array_merge(
			array( 'guest' => array( 'name' => __( 'Guest', 'riaco-hide-products' ) ) ),
			wp_roles()->roles
		);

		$new_settings = array(
			array(
				'title' => __( 'Hide Products by User Role', 'riaco-hide-products' ),
				'type'  => 'title',
				'desc'  => __( 'Set default global visibility rules for products.', 'riaco-hide-products' ),
				'id'    => 'riaco_hpburfw_settings_title',
			),
		);

		foreach ( $roles as $role_key => $role_data ) {
			$new_settings[] = array(
				'title'   => sprintf( __( 'Hide for %s', 'riaco-hide-products' ), esc_html( $role_data['name'] ) ),
				'id'      => "riaco_hpburfw_hide_{$role_key}",
				'type'    => 'checkbox',
				'default' => 'no',
			);
		}

		$new_settings[] = array(
			'type' => 'sectionend',
			'id'   => 'riaco_hpburfw_settings_end',
		);

		return $new_settings;
	}
}
