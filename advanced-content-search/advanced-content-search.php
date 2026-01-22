<?php
/**
 * Plugin Name: Advanced Content Search & Reporting
 * Plugin URI: https://example.com/advanced-content-search
 * Description: A comprehensive content search and reporting tool for WordPress site administrators. Search posts, pages, and custom post types by exact phrases with export capabilities.
 * Version: 1.0.0
 * Author: Carlou Benedict Luchavez
 * Author URI: 
 * 		- Github: https://github.com/bnzxs/
 * 		- LinkedIn: https://www.linkedin.com/in/carlou-benedict-luchavez-2b38a9166/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: advanced-content-search
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Advanced_Content_Search
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants
 */
define( 'ADVANCED_CONTENT_SEARCH_VERSION', '1.0.0' );
define( 'ADVANCED_CONTENT_SEARCH_FILE', __FILE__ );
define( 'ADVANCED_CONTENT_SEARCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADVANCED_CONTENT_SEARCH_URL', plugin_dir_url( __FILE__ ) );
define( 'ADVANCED_CONTENT_SEARCH_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes
 *
 * @param string $class Class name
 * @return void
 */
function advanced_content_search_autoloader( $class ) {
	// Only handle our plugin classes
	if ( strpos( $class, 'Advanced_Content_Search' ) === false ) {
		return;
	}

	$class_path = str_replace( 'Advanced_Content_Search_', '', $class );
	$class_path = strtolower( str_replace( '_', '-', $class_path ) );
	$file = ADVANCED_CONTENT_SEARCH_DIR . 'includes/class-' . $class_path . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

spl_autoload_register( 'advanced_content_search_autoloader' );

/**
 * Load admin class
 */
require_once ADVANCED_CONTENT_SEARCH_DIR . 'admin/class-admin.php';

/**
 * Initialize plugin
 *
 * @return void
 */
function advanced_content_search_init() {
	// Check if user has admin capabilities
	if ( is_admin() ) {
		new Advanced_Content_Search_Admin();
	}
}

add_action( 'plugins_loaded', 'advanced_content_search_init' );

/**
 * Activation hook
 *
 * @return void
 */
function advanced_content_search_activate() {
	// Ensure WordPress database is available
	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}
	
	// Plugin can create cache directory if needed
	$cache_dir = ADVANCED_CONTENT_SEARCH_DIR . 'cache';
	if ( ! is_dir( $cache_dir ) ) {
		wp_mkdir_p( $cache_dir );
	}
}

register_activation_hook( ADVANCED_CONTENT_SEARCH_FILE, 'advanced_content_search_activate' );

/**
 * Deactivation hook
 *
 * @return void
 */
function advanced_content_search_deactivate() {
	// Clean up transients or temporary data if needed
	delete_transient( 'advanced_content_search_cache' );
}

register_deactivation_hook( ADVANCED_CONTENT_SEARCH_FILE, 'advanced_content_search_deactivate' );
