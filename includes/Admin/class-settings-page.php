<?php
namespace Riaco\HideProducts\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Riaco\HideProducts\Interfaces\ServiceInterface;

class SettingsPage implements ServiceInterface {
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Product Visibility', 'riaco-hide-products' ),
			__( 'Product Visibility', 'riaco-hide-products' ),
			'manage_woocommerce',
			'riaco-visibility',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		echo '<div class=\"wrap\"><h1>RIACO Visibility</h1></div>';
	}
}
