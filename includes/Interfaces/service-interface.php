<?php
/**
 * Service Interface.
 *
 * @package Riaco\HideProducts\Interfaces
 */

namespace Riaco\HideProducts\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ServiceInterface {
	/**
	 * Register the service.
	 */
	public function register(): void;
}
