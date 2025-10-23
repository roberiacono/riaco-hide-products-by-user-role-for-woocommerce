<?php
/**
 * Main plugin class.
 *
 * @package Riaco\HideProducts
 */

namespace Riaco\HideProducts;

use Riaco\HideProducts\Interfaces\ServiceInterface;

use Riaco\HideProducts\Admin\CustomTaxonomy;
use Riaco\HideProducts\Admin\ProductVisibilityTab;
use Riaco\HideProducts\Admin\SettingsPage;

use Riaco\HideProducts\Frontend\ProductVisibility;

/**
 * Main plugin class.
 */
class Plugin {

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

	public $taxonomy = 'riaco_hpburfw_visibility_role';

	/**
	 * Constructor.
	 *
	 * @param string $file The main plugin file.
	 */
	public function __construct( string $file ) {
		$this->file   = $file;
		$this->loaded = false;
	}

	private function load_services(): void {
		$this->services[] = new CustomTaxonomy( $this );

		if ( is_admin() ) {
			$this->services[] = new ProductVisibilityTab();
			$this->services[] = new SettingsPage( $this );
		}

		if ( ! is_admin() ) {
			$this->services[] = new ProductVisibility( $this );
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

	public function init() {
		$this->load_services();
		$this->register();

		do_action( 'riaco_hpburfw_loaded', $this );
	}


	private function register(): void {
		foreach ( $this->services as $service ) {
			if ( $service instanceof ServiceInterface ) {
				$service->register();
			}
		}
	}

	public function get_roles() {
		$roles = array_merge(
			array( 'guest' => array( 'name' => __( 'Guest', 'riaco-hide-products' ) ) ),
			wp_roles()->roles
		);
		return $roles;
	}
}
