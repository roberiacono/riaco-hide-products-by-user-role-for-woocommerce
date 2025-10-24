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

		$roles = $this->plugin->get_roles();
		$rules = get_option( 'riaco_hpburfw_rules', array() );

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
					<th></th>
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
}
