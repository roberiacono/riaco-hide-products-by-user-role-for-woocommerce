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
	/**
	 * The main plugin file.
	 *
	 * @var string
	 */
	private string $file;

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
	 * Constructor.
	 *
	 * @param string $file The main plugin file.
	 */
	public function __construct( string $file ) {
		$this->file   = $file;
		$this->loaded = false;

		$this->services[] = new CustomTaxonomy();

		if ( is_admin() ) {
			$this->services[] = new ProductVisibilityTab();
			$this->services[] = new SettingsPage();
		}

		if ( ! is_admin() ) {
			$this->services[] = new ProductVisibility();
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
}
