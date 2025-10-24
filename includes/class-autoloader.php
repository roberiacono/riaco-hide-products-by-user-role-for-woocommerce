<?php
/**
 * Autoloads Plugin classes using PSR-0 standard.
 *
 * @package Riaco\HideProducts
 */

namespace Riaco\HideProducts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader.
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 *
	 * @param bool $prepend Whether to prepend the autoloader on the stack.
	 */
	public static function register( $prepend = false ) {
		spl_autoload_register( array( new self(), 'autoload' ), true, $prepend );
	}

	/**
	 * Autoload function.
	 *
	 * @param string $class The fully-qualified class name.
	 */
	public static function autoload( $class ) {
		$prefix = 'Riaco\\HideProducts\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		// Remove namespace prefix.
		$relative_class = substr( $class, strlen( $prefix ) );

		// Convert namespace separators to directory separators.
		$path = str_replace( '\\', '/', $relative_class );

		// Convert CamelCase class name to hyphenated lowercase for file name.
		$parts      = explode( '/', $path );
		$class_name = array_pop( $parts );

		// Detect interfaces directory.
		$is_interface = (
		! empty( $parts )
		&& strtolower( end( $parts ) ) === 'interfaces'
		);

		// Build file name.
		if ( $is_interface ) {
			// For interfaces: service-interface.php.
			$file_name = strtolower(
				preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name )
			) . '.php';
		} else {
			// For normal classes: class-product-metabox.php.
			// Convert CamelCase class name to hyphenated lowercase for file name.
			$file_name = 'class-' . strtolower(
				preg_replace( array( '/([a-z])([A-Z])/', '/_/' ), array( '$1-$2', '-' ), $class_name )
			) . '.php';

		}

		// Build the full path.
		$file = __DIR__ . '/' . implode( '/', $parts ) . '/' . $file_name;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
