<?php
/**
 * Autoloads MyPlugin classes using PSR-0 standard.
 *
 * @author Carl Alexander
 */
namespace Riaco\HideProducts;

class Autoloader {

	public static function register( $prepend = false ) {
		spl_autoload_register( array( new self(), 'autoload' ), true, $prepend );
	}

	public static function autoload( $class ) {
		$prefix = 'Riaco\\HideProducts\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		// Remove namespace prefix
		$relative_class = substr( $class, strlen( $prefix ) );

		// Convert namespace separators to directory separators
		$path = str_replace( '\\', '/', $relative_class );

		// Convert CamelCase class name to hyphenated lowercase for file name
		$parts      = explode( '/', $path );
		$class_name = array_pop( $parts );

		// Detect interfaces directory
		$is_interface = (
		! empty( $parts )
		&& strtolower( end( $parts ) ) === 'interfaces'
		);

		// Build file name
		if ( $is_interface ) {
			// For interfaces: service-interface.php
			$file_name = strtolower(
				preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name )
			) . '.php';
		} else {
			// For normal classes: class-product-metabox.php
			$file_name = 'class-' . strtolower(
				preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name )
			) . '.php';
		}

		// Build the full path
		$file = __DIR__ . '/' . implode( '/', $parts ) . '/' . $file_name;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
