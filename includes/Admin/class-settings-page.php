<?php
/**
 * Settings Page class.
 *
 * @package Riaco\HideProducts\Admin
 */

namespace Riaco\HideProducts\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

/**
 * Settings Page class.
 */
class Settings_Page implements ServiceInterface {

	/**
	 * The main plugin instance.
	 *
	 * @var class
	 */
	private $plugin;


	/**
	 * Constructor.
	 *
	 * @param class $plugin The main plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register the service.
	 */
	public function register(): void {

		add_filter( 'woocommerce_get_sections_products', array( $this, 'add_settings_section' ) );

		add_filter( 'woocommerce_settings_products', array( $this, 'add_custom_settings_fields' ) );
		add_action( 'woocommerce_settings_save_products', array( $this, 'save_custom_settings' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts() {

		$screen = get_current_screen();
		// Bail early if not on WooCommerce settings screen.
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';

		// Bail early if not on our specific tab and section.
		if ( 'products' !== $current_tab || 'riaco_hpburfw_rules' !== $current_section ) {
			return;
		}

		$roles = $this->plugin->get_roles();
		$rules = get_option( $this->plugin->option_key, array() );

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
				'label'    => __( 'All Products', 'riaco-hide-products-by-user-role-for-woocommerce' ),
				'taxonomy' => null,
			),
			array(
				'id'       => 'product_cat',
				'label'    => __( 'Product Category', 'riaco-hide-products-by-user-role-for-woocommerce' ),
				'taxonomy' => 'product_cat',
				'terms'    => $this->get_taxonomy_tree( 'product_cat' ),
			),
		);

		/**
		 * Filter the available targets for hiding products.
		 *
		 * @param array $targets Array of targets.
		 */
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

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'riaco-hpburfw-admin-css',
			plugins_url( 'assets/admin/style.css', $this->plugin->file ),
			array(),
			$this->plugin->version
		);
	}

	/**
	 * Add settings section.
	 *
	 * @param array $sections Existing sections.
	 * @return array Modified sections.
	 */
	public function add_settings_section( array $sections ): array {
		$sections['riaco_hpburfw_rules'] = __( 'Hide by User Roles', 'riaco-hide-products-by-user-role-for-woocommerce' );
		return $sections;
	}

	/**
	 * Add custom settings fields.
	 */
	public function add_custom_settings_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';
		if ( 'riaco_hpburfw_rules' !== $section ) {
			return;
		}

		$this->settings_page();
	}

	/**
	 * Save custom settings.
	 */
	public function save_custom_settings(): void {

		if (
			! isset( $_POST['riaco_hpburfw_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['riaco_hpburfw_nonce'] ), 'riaco_hpburfw_save_rules' )
		) {
			return;
		}

		if ( ! isset( $_POST['riaco_hpburfw_rules'] ) || ! is_array( $_POST['riaco_hpburfw_rules'] ) ) {
			return;
		}

		$sanitized_rules = array();

		$raw_rules = filter_input( INPUT_POST, 'riaco_hpburfw_rules', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		foreach ( $raw_rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue; // Skip invalid entries.
			}

			$sanitized_rule = array(
				'order'  => isset( $rule['order'] ) ? absint( $rule['order'] ) : 0,
				'role'   => isset( $rule['role'] ) ? sanitize_text_field( $rule['role'] ) : '',
				'target' => isset( $rule['target'] ) ? sanitize_text_field( $rule['target'] ) : '',
				'terms'  => array(),
			);

			if ( isset( $rule['terms'] ) && is_array( $rule['terms'] ) ) {
				// Make sure all term IDs are integers.
				$sanitized_rule['terms'] = array_map( 'absint', $rule['terms'] );
			}

			$sanitized_rules[] = $sanitized_rule;
		}

		update_option( 'riaco_hpburfw_rules', $sanitized_rules );
	}

	/**
	 * Render the settings page.
	 */
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
		<?php wp_nonce_field( 'riaco_hpburfw_save_rules', 'riaco_hpburfw_nonce' ); ?>

		<?php
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
				'children' => $this->get_taxonomy_tree( $taxonomy, $term->term_id ), // recursive.
			);
		}

		return $tree;
	}
}
