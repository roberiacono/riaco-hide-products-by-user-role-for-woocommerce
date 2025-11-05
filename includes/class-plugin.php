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
	public string $version = '1.0.0';
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

		add_action( 'plugins_loaded', array( $this, 'init' ) );
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
	 * Retrieves all user roles including 'guest'.
	 *
	 * @return array
	 */
	public function get_roles() {
		$roles = array_merge(
			array( 'guest' => array( 'name' => esc_html__( 'Guest', 'riaco-hide-products-by-user-role' ) ) ),
			wp_roles()->roles
		);
		return $roles;
	}
}
