<?php
/**
 * Diagnostic AJAX handler for Advanced Content Search
 *
 * @package Advanced_Content_Search
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run diagnostic tests
 */
function advanced_handle_diagnostic() {
	// Verify nonce
	check_ajax_referer( 'advanced_content_search_nonce', 'nonce' );

	// Verify capability
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
	}

	// Load diagnostic class
	require_once ADVANCED_CONTENT_SEARCH_DIR . 'includes/class-diagnostic.php';

	// Run diagnostics
	$diagnostics = Advanced_Content_Search_Diagnostic::run_diagnostics();

	wp_send_json_success( $diagnostics );
}

add_action( 'wp_ajax_advanced_run_diagnostic', 'advanced_handle_diagnostic' );

/**
 * Test database connection directly
 */
function advanced_test_db_direct() {
	global $wpdb;

	$results = array(
		'timestamp' => current_time( 'mysql' ),
		'php_version' => phpversion(),
		'mysql_version' => $wpdb->db_version(),
		'wordpress_version' => get_bloginfo( 'version' ),
		'table_prefix' => $wpdb->prefix,
	);

	// Test basic connection
	$ping = $wpdb->get_var( 'SELECT 1' );
	$results['database_connection'] = ( '1' === $ping ) ? 'OK' : 'FAIL';

	// Test posts table
	$post_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'" );
	$results['published_posts'] = (int) $post_count;
	$results['last_error'] = $wpdb->last_error;

	wp_send_json_success( $results );
}

add_action( 'wp_ajax_nopriv_advanced_test_db', 'advanced_test_db_direct' );
add_action( 'wp_ajax_advanced_test_db', 'advanced_test_db_direct' );
