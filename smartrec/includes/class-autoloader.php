<?php
/**
 * PSR-4 style autoloader for SmartRec plugin.
 *
 * @package SmartRec
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( $class ) {
		$prefix   = 'SmartRec\\';
		$base_dir = plugin_dir_path( __FILE__ );

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		// Map namespace parts to directories.
		$namespace_map = array(
			'Core'     => '',
			'Tracking' => 'Tracking/',
			'Engines'  => 'Engines/',
			'Display'  => 'Display/',
			'Cache'    => 'Cache/',
			'API'      => 'API/',
			'Admin'    => 'Admin/',
			'Cron'     => 'Cron/',
		);

		// Get the namespace parts.
		$parts     = explode( '\\', $relative_class );
		$class_name = array_pop( $parts );
		$namespace  = implode( '\\', $parts );

		// Determine directory.
		$directory = '';
		if ( isset( $namespace_map[ $namespace ] ) ) {
			$directory = $namespace_map[ $namespace ];
		} elseif ( ! empty( $namespace ) ) {
			$directory = str_replace( '\\', '/', $namespace ) . '/';
		}

		// Convert CamelCase class name to file name (WordPress style: class-name-here).
		$dashed    = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_name );
		$dashed    = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $dashed );
		$file_name = 'class-' . strtolower( str_replace( '_', '-', $dashed ) );

		// Handle interfaces.
		if ( strpos( $class_name, 'Interface' ) !== false ) {
			$clean_name = str_replace( 'Interface', '', $class_name );
			$clean_name = trim( $clean_name, '_' );
			$dashed_iface = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $clean_name );
			$dashed_iface = preg_replace( '/([A-Z]+)([A-Z][a-z])/', '$1-$2', $dashed_iface );
			$file_name    = 'interface-' . strtolower( str_replace( '_', '-', $dashed_iface ) );
		}

		$file = $base_dir . $directory . $file_name . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
