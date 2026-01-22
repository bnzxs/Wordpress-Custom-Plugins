<?php
/**
 * Search engine class for Advanced Content Search plugin
 *
 * Handles the core search logic with exact phrase matching
 *
 * @package Advanced_Content_Search
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Content Search Engine Class
 *
 * This class handles exact phrase searching across WordPress content.
 */
class Advanced_Content_Search_Engine {

	/**
	 * Results per page for pagination
	 *
	 * @var int
	 */
	private $per_page = 20;

	/**
	 * Perform content search
	 *
	 * @param string $search_phrase The exact phrase to search for
	 * @param array  $post_types Array of post types to search
	 * @param array  $search_fields Array of fields to search (title, content)
	 * @param int    $paged Current page number
	 * @param int    $per_page Results per page (-1 for all)
	 * @return array|WP_Error Array with results and pagination info, or WP_Error on failure
	 */
	public function search( $search_phrase, $post_types = array( 'post', 'page' ), $search_fields = array( 'title', 'content' ), $paged = 1, $per_page = null ) {
		global $wpdb;

		// Sanitize inputs
		$search_phrase = trim( $search_phrase );
		$paged = max( 1, absint( $paged ) );
		$per_page = null === $per_page ? $this->per_page : absint( $per_page );

		// Validate inputs
		if ( empty( $search_phrase ) ) {
			return new WP_Error( 'empty_search', esc_html__( 'Search phrase cannot be empty.', 'advanced-content-search' ) );
		}

		if ( empty( $post_types ) ) {
			return new WP_Error( 'empty_post_types', esc_html__( 'No post types specified.', 'advanced-content-search' ) );
		}

		if ( empty( $search_fields ) ) {
			return new WP_Error( 'empty_search_fields', esc_html__( 'No search fields specified.', 'advanced-content-search' ) );
		}

		// Escape the search phrase for LIKE
		$search_like = '%' . $wpdb->esc_like( $search_phrase ) . '%';
		$query_params = array();
		$search_clauses = array();

		if ( in_array( 'title', $search_fields, true ) ) {
			$search_clauses[] = "{$wpdb->posts}.post_title LIKE %s";
			$query_params[] = $search_like;
		}

		if ( in_array( 'content', $search_fields, true ) ) {
			$search_clauses[] = "{$wpdb->posts}.post_content LIKE %s";
			$query_params[] = $search_like;
		}

		$search_where = '(' . implode( ' OR ', $search_clauses ) . ')';

		foreach ( $post_types as $post_type ) {
			$query_params[] = $post_type;
		}

		$post_type_placeholders = array_fill( 0, count( $post_types ), '%s' );
		$post_type_where = 'post_type IN (' . implode( ',', $post_type_placeholders ) . ')';

		// Get total count
		$count_sql = $wpdb->prepare(
			"SELECT COUNT(ID) as total FROM {$wpdb->posts} WHERE {$search_where} AND {$post_type_where} AND post_status = 'publish'",
			$query_params
		);

		$total = (int) $wpdb->get_var( $count_sql );

		if ( ! empty( $wpdb->last_error ) ) {
			error_log( 'ACS Search Count Error: ' . $wpdb->last_error );
			return new WP_Error( 'database_error', esc_html__( 'Database error occurred during search.', 'advanced-content-search' ) );
		}

		if ( 0 === $total ) {
			return array(
				'items'       => array(),
				'total'       => 0,
				'total_pages' => 0,
				'paged'       => $paged,
			);
		}

		// Calculate pagination
		$total_pages = $per_page > 0 ? ceil( $total / $per_page ) : 1;
		$paged = min( $paged, $total_pages );
		$offset = ( $paged - 1 ) * ( $per_page > 0 ? $per_page : $total );

		$base_query = "SELECT ID, post_title, post_content, post_type FROM {$wpdb->posts} WHERE {$search_where} AND {$post_type_where} AND post_status = 'publish' ORDER BY post_date DESC";
		
		if ( $per_page > 0 ) {
			$results_sql = $wpdb->prepare( $base_query, $query_params );
			$results_sql .= ' LIMIT ' . absint( $per_page ) . ' OFFSET ' . absint( $offset );
		} else {
			$results_sql = $wpdb->prepare( $base_query, $query_params );
		}

		$results_raw = $wpdb->get_results( $results_sql );

		if ( ! empty( $wpdb->last_error ) ) {
			error_log( 'ACS Search Results Error: ' . $wpdb->last_error );
			return new WP_Error( 'database_error', esc_html__( 'Database error occurred during search.', 'advanced-content-search' ) );
		}

		$items = array();
		foreach ( $results_raw as $row ) {
			$post_type_obj = get_post_type_object( $row->post_type );
			$post_type_label = $post_type_obj ? $post_type_obj->label : $row->post_type;

			$items[] = array(
				'id'              => (int) $row->ID,
				'title'           => $row->post_title,
				'post_type'       => $row->post_type,
				'post_type_label' => $post_type_label,
				'url'             => get_permalink( $row->ID ),
				'edit_url'        => get_edit_post_link( $row->ID, 'raw' ),
				'excerpt'         => wp_trim_words( $row->post_content, 20 ),
			);
		}

		return array(
			'items'       => $items,
			'total'       => $total,
			'total_pages' => $total_pages,
			'paged'       => $paged,
		);
	}

}
