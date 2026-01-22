<?php
/**
 * Uninstall hook for Advanced Content Search plugin
 *
 * Called when the plugin is deleted (not just deactivated)
 *
 * @package Advanced_Content_Search
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up transients
delete_transient( 'advanced_content_search_cache' );

// Clean up plugin options if any were stored
delete_option( 'advanced_content_search_settings' );
