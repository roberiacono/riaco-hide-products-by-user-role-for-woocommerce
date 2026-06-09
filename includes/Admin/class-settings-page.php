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
				'label'    => esc_html__( 'All Products', 'riaco-hide-products-by-user-role-for-woocommerce' ),
				'taxonomy' => null,
			),
			array(
				'id'       => 'product_cat',
				'label'    => esc_html__( 'Product Category', 'riaco-hide-products-by-user-role-for-woocommerce' ),
				'taxonomy' => 'product_cat',
				'terms'    => $this->get_taxonomy_tree( 'product_cat' ),
			),
			array(
				'id'       => 'product_tag',
				'label'    => esc_html__( 'Product Tag', 'riaco-hide-products-by-user-role-for-woocommerce' ),
				'taxonomy' => 'product_tag',
				'terms'    => $this->get_taxonomy_tree( 'product_tag' ),
			),
		);

		/**
		 * Filter the available targets for hiding products.
		 *
		 * @param array $targets Array of targets.
		 */
		$targets = apply_filters( 'riaco_hpburfw_targets', $targets );

		$localize_data = array(
			'roles'          => $roles,
			'targets'        => $targets,
			'rules'          => ! empty( $rules ) ? $rules : array(),
			'move_up'        => __( 'Move up', 'riaco-hide-products-by-user-role-for-woocommerce' ),
			'move_down'      => __( 'Move down', 'riaco-hide-products-by-user-role-for-woocommerce' ),
			'remove_row'     => __( 'Remove', 'riaco-hide-products-by-user-role-for-woocommerce' ),
			'duplicate_row'  => __( 'Duplicate', 'riaco-hide-products-by-user-role-for-woocommerce' ),
			'confirm_remove' => __( 'Remove this rule?', 'riaco-hide-products-by-user-role-for-woocommerce' ),
			'no_rules'       => __( 'No rules yet. Click "Add Rule" to create your first visibility rule.', 'riaco-hide-products-by-user-role-for-woocommerce' ),
		);

		/**
		 * Filter the JS localized data object for the rules admin screen.
		 *
		 * @param array $localize_data The data array passed to wp_localize_script.
		 */
		$localize_data = apply_filters( 'riaco_hpburfw_localize_data', $localize_data );

		wp_localize_script( 'riaco-hpburfw-admin-js', 'riaco_hpburfw_data', $localize_data );

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
		$count = count( get_option( $this->plugin->option_key, array() ) );
		$label = $count > 0
			/* translators: %d: number of active rules */
			? sprintf( esc_html__( 'Hide by User Roles (%d)', 'riaco-hide-products-by-user-role-for-woocommerce' ), $count )
			: esc_html__( 'Hide by User Roles', 'riaco-hide-products-by-user-role-for-woocommerce' );

		$sections['riaco_hpburfw_rules'] = $label;
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

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if (
			! isset( $_POST['riaco_hpburfw_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['riaco_hpburfw_nonce'] ), 'riaco_hpburfw_save_rules' )
		) {
			return;
		}

		if ( ! isset( $_POST['riaco_hpburfw_rules'] ) ) {
			update_option( 'riaco_hpburfw_rules', array() );
			wp_cache_delete( 'riaco_hpburfw_rules', 'riaco_hpburfw' );
			\WC_Admin_Settings::add_message( esc_html__( 'Rules saved.', 'riaco-hide-products-by-user-role-for-woocommerce' ) );
			return;
		}

		if ( ! is_array( $_POST['riaco_hpburfw_rules'] ) ) {
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

			/**
			 * Filter to sanitize and extend individual rule fields before saving.
			 *
			 * @param array $sanitized_rule The sanitized rule (base fields only).
			 * @param array $rule           The raw submitted rule (may contain extra PRO fields).
			 */
			$sanitized_rule = apply_filters( 'riaco_hpburfw_rule_sanitize', $sanitized_rule, $rule );

			$sanitized_rules[] = $sanitized_rule;
		}

		update_option( 'riaco_hpburfw_rules', $sanitized_rules );
		wp_cache_delete( 'riaco_hpburfw_rules', 'riaco_hpburfw' );
		\WC_Admin_Settings::add_message( esc_html__( 'Rules saved.', 'riaco-hide-products-by-user-role-for-woocommerce' ) );
		do_action( 'riaco_hpburfw_rules_saved', $sanitized_rules );
	}

	/**
	 * Render the settings page.
	 */
	public function settings_page() {
		?>
		
		<div class="wrap">
			<h1><?php echo esc_html__( 'Hide products by user roles', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></h1>
			<p>
				<?php echo esc_html__( 'Set global hide by user roles rules for products.', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?>
				<?php echo esc_html__( 'Rules at the top take precedence. Use the arrows to reorder. If a user matches an "All Products" rule, lower rules are not evaluated.', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?>
			</p>

			<div class="riaco-table-responsive">
				<table class="wp-list-table widefat fixed striped" id="riaco-hpburfw-rules">
					<colgroup>
						<col style="width: 100px;">
						<col>
						<col>
						<col>
						<col style="width: 150px;">
					</colgroup>
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Priority', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'User Role', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'Target', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'Terms', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></th>
								<th><?php echo esc_html__( 'Actions', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?></th>
							<?php do_action( 'riaco_hpburfw_settings_table_columns' ); ?>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
				<?php do_action( 'riaco_hpburfw_settings_page_after_table', $this->plugin ); ?>
				<p>
					<button type="button" class="button" id="add-rule">
						<?php echo esc_html__( 'Add Rule', 'riaco-hide-products-by-user-role-for-woocommerce' ); ?>
					</button>
				</p>

		</div>
		<?php wp_nonce_field( 'riaco_hpburfw_save_rules', 'riaco_hpburfw_nonce' ); ?>

		<?php
	}

	/**
	 * Build a hierarchical (nested) array of taxonomy terms in a single DB query.
	 *
	 * @param string $taxonomy Taxonomy name (e.g. 'product_cat').
	 * @return array
	 */
	private function get_taxonomy_tree( string $taxonomy ): array {
		$all_terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
			return array();
		}

		$flat = array();
		foreach ( $all_terms as $term ) {
			$flat[ $term->term_id ] = array(
				'term_id'  => $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'parent'   => $term->parent,
				'children' => array(),
			);
		}

		$tree = array();
		foreach ( $flat as $id => &$node ) {
			if ( $node['parent'] && isset( $flat[ $node['parent'] ] ) ) {
				$flat[ $node['parent'] ]['children'][] = &$node;
			} else {
				$tree[] = &$node;
			}
		}
		unset( $node );

		$strip_parent = function ( array &$nodes ) use ( &$strip_parent ) {
			foreach ( $nodes as &$node ) {
				unset( $node['parent'] );
				$strip_parent( $node['children'] );
			}
		};
		$strip_parent( $tree );

		return $tree;
	}
}
