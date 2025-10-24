<?php
namespace Riaco\HideProducts\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

class SettingsPage implements ServiceInterface {

	private $plugin;

	private string $option_key = 'riaco_hpburfw_rules';

	function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {

		// add_action( 'admin_init', array( $this, 'register_settings' ) );
		// Add section
		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_settings_section' ) );

		add_filter( 'woocommerce_settings_products', array( $this, 'add_custom_settings_fields' ) );
		add_action( 'woocommerce_settings_save_products', array( $this, 'save_custom_settings' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}


	public function enqueue_admin_scripts() {

		if ( empty( $_GET['section'] ) || 'riaco_hpburfw_rules' !== $_GET['section'] ) {
			return;
		}

		$roles = $this->plugin->get_roles();
		$rules = get_option( $this->option_key, array() );

		wp_enqueue_script(
			'riaco-hpburfw-admin-js',
			plugins_url( 'assets/admin/admin.js', $this->plugin->file ),
			array( 'jquery' ),
			$this->plugin->version,
			true
		);

		$targets = array(
			array(
				'id'       => 'all_products',
				'label'    => __( 'All Products', 'riaco' ),
				'taxonomy' => null,
			),
			array(
				'id'       => 'product_cat',
				'label'    => __( 'Product Category', 'riaco' ),
				'taxonomy' => 'product_cat',
				'terms'    => $this->get_taxonomy_tree( 'product_cat' ),
			),

			// You can extend via filter
		);
		$targets = apply_filters( 'riaco_hpburfw_targets', $targets );

		wp_localize_script(
			'riaco-hpburfw-admin-js',
			'riaco_hpburfw_data',
			array(
				'roles'   => $roles,
				'targets' => $targets,
				'rules'   => ! empty( $rules ) ? $rules : array(),
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

	public function add_settings_section( array $sections ): array {
		$sections['riaco_hpburfw_rules'] = __( 'Hide by User Roles', 'riaco-hide-products' );
		return $sections;
	}

	public function add_custom_settings_fields() {
		// exit if it is not our section
		if ( empty( $_GET['section'] ) || 'riaco_hpburfw_rules' !== $_GET['section'] ) {
			return;
		}

		$this->settings_page();
	}






	/**
	 * Build a hierarchical (nested) array of taxonomy terms.
	 *
	 * @param string $taxonomy  Taxonomy name (e.g. 'product_cat').
	 * @param int    $parent_id Parent term ID (default 0).
	 * @return array
	 */
	private function get_taxonomy_tree( string $taxonomy, int $parent_id = 0 ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'parent'     => $parent_id,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$tree = array();

		foreach ( $terms as $term ) {
			$tree[] = array(
				'term_id'  => $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'children' => $this->get_taxonomy_tree( $taxonomy, $term->term_id ), // recursive
			);
		}

		return $tree;
	}


	/*
	public function register_settings() {
		register_setting( 'riaco_hpburfw_group', $this->option_key );
	}
	*/


	public function save_custom_settings(): void {
		if ( empty( $_REQUEST['riaco_hpburfw_rules'] ) || ! is_array( $_REQUEST['riaco_hpburfw_rules'] ) ) {
			return;
		}

		// error_log( "REQUEST['riaco_hpburfw_rules'] =" . print_r( $_REQUEST['riaco_hpburfw_rules'], true ) );

		$sanitized_rules = array_map(
			function ( $rule ) {
				return array(
					'order'  => isset( $rule['order'] ) ? absint( $rule['order'] ) : 0,
					'role'   => isset( $rule['role'] ) ? sanitize_text_field( $rule['role'] ) : '',
					'target' => isset( $rule['target'] ) ? sanitize_text_field( $rule['target'] ) : '',
					// Optional terms (array of IDs)
					'terms'  => isset( $rule['terms'] ) && is_array( $rule['terms'] )
							? array_map( 'absint', $rule['terms'] )
							: array(),
				);
			},
			$_REQUEST['riaco_hpburfw_rules']
		);

		// error_log( 'sanitized_rules = ' . print_r( $sanitized_rules, true ) );

		update_option( 'riaco_hpburfw_rules', $sanitized_rules );
	}


	public function settings_page() {

		?>
		<div class="wrap">
	<h1>Hide products by user toles</h1>
	<p>
	Set global hide by user roles rules for products.
	</p>

		<div class="riaco-table-responsive">
		<table class="wp-list-table widefat fixed striped" id="riaco-hpburfw-rules">
		<colgroup>
			<col style="width: 100px;">   <!-- Priority -->
			<col>                        <!-- Role -->
			<col>                        <!-- Target -->
			<col>                        <!-- Terms -->
			<col style="width: 100px;">  <!-- Actions -->
		</colgroup>
			<thead>
				<tr>
					<th></th>
					<th>User Role</th>
					<th>Target</th>
					<th>Terms</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
		</div>
		<p>
			<button type="button" class="button" id="add-rule">Add Rule</button>
		</p>

</div>

		<?php
	}
}
