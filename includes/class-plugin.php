<?php
/**
 * Main plugin class.
 *
 * @package Riaco\HideProducts
 */

namespace Riaco\HideProducts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

use Riaco\HideProducts\Admin\Custom_Taxonomy;
use Riaco\HideProducts\Admin\Product_Visibility_Tab;
use Riaco\HideProducts\Admin\Settings_Page;

use Riaco\HideProducts\Frontend\Product_Visibility;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public string $version = '1.1.0';
	/**
	 * The main plugin file.
	 *
	 * @var string
	 */
	public string $file;

	/**
	 * Flag to track if the plugin is loaded.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $loaded;

	/**
	 * Array of services to be registered.
	 *
	 * @var array
	 */
	private array $services = array();

	/**
	 * Taxonomy for user role visibility.
	 *
	 * @var string
	 */
	public string $custom_taxonomy = 'riaco_hpburfw_visibility_role';

	/**
	 * Option key for storing rules.
	 *
	 * @var string
	 */
	public string $option_key = 'riaco_hpburfw_rules';

	/**
	 * Constructor.
	 *
	 * @param string $file The main plugin file.
	 */
	public function __construct( string $file ) {
		$this->file   = $file;
		$this->loaded = false;
	}

	/**
	 * Loads the services.
	 */
	private function load_services(): void {
		$this->services[] = new Custom_Taxonomy( $this );

		if ( is_admin() ) {
			$this->services[] = new Product_Visibility_Tab( $this );
			$this->services[] = new Settings_Page( $this );
		}

		if ( ! is_admin() ) {
			$this->services[] = new Product_Visibility( $this );
		}
	}

	/**
	 * Checks if the plugin is loaded.
	 *
	 * @return bool
	 */
	public function is_loaded() {
		return $this->loaded;
	}

	/**
	 * Loads the plugin into WordPress.
	 *
	 * @since 1.0.0
	 */
	public function load() {
		if ( $this->is_loaded() ) {
			return;
		}

		$this->loaded = true;
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'add_action_links' ) );

		if ( is_admin() ) {
			add_filter( 'admin_footer_text', array( $this, 'admin_footer_review_text' ) );
		}
	}

	/**
	 * Appends a review request to the admin footer on the plugin's settings page.
	 *
	 * @param string $text Existing footer text.
	 * @return string
	 */
	public function admin_footer_review_text( string $text ): string {
		$screen = get_current_screen();

		if (
			! $screen ||
			'woocommerce_page_wc-settings' !== $screen->id ||
			! isset( $_GET['section'] ) || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'riaco_hpburfw_rules' !== sanitize_key( $_GET['section'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			return $text;
		}

		$review_url = 'https://wordpress.org/support/plugin/riaco-hide-products-by-user-role/reviews/';

		return sprintf(
			wp_kses(
				/* translators: %1$s: plugin name, %2$s: review URL */
				__( 'If you like %1$s please leave us a <a href="%2$s" target="_blank" rel="noopener noreferrer">★★★★★</a> rating. A huge thanks in advance!', 'riaco-hide-products-by-user-role-for-woocommerce' ),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			),
			'<strong>' . esc_html__( 'Hide Products by User Role for WooCommerce', 'riaco-hide-products-by-user-role-for-woocommerce' ) . '</strong>',
			esc_url( $review_url )
		);
	}

	/**
	 * Adds a Settings link to the plugin action links on the plugins page.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$settings_url  = admin_url( 'admin.php?page=wc-settings&tab=products&section=riaco_hpburfw_rules' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'riaco-hide-products-by-user-role-for-woocommerce' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Declares HPOS compatibility with WooCommerce.
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->file, true );
		}
	}

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->load_services();
		$this->register();

		do_action( 'riaco_hpburfw_loaded', $this );
	}

	/**
	 * Registers all services.
	 */
	private function register(): void {
		foreach ( $this->services as $service ) {
			if ( $service instanceof ServiceInterface ) {
				$service->register();
			}
		}
	}

	/**
	 * Register and immediately activate an additional service.
	 *
	 * PRO and extension plugins can call this from the `riaco_hpburfw_loaded` hook
	 * to inject new services after the core plugin has initialised.
	 *
	 * @param ServiceInterface $service The service to register.
	 */
	public function register_service( ServiceInterface $service ): void {
		$this->services[] = $service;
		$service->register();
	}

	/**
	 * Retrieves all user roles including 'guest'.
	 *
	 * @return array
	 */
	public function get_roles() {
		$roles = array_merge(
			array( 'guest' => array( 'name' => esc_html__( 'Guest', 'riaco-hide-products-by-user-role-for-woocommerce' ) ) ),
			wp_roles()->roles
		);
		return apply_filters( 'riaco_hpburfw_roles', $roles );
	}
}
