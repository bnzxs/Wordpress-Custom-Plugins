<?php
/**
 * Diagnostic class for testing database connection and queries
 *
 * @package Advanced_Content_Search
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Content Search Diagnostic Class
 */
class Advanced_Content_Search_Diagnostic {

	/**
	 * Run all diagnostics
	 *
	 * @return array
	 */
	public static function run_diagnostics() {
		global $wpdb;

		$results = array();

		// Check database connection
		$results['database_connection'] = self::test_database();

		// Check posts table
		$results['posts_table'] = self::test_posts_table( $wpdb );

		// Test basic query
		$results['basic_query'] = self::test_basic_query( $wpdb );

		// Test LIKE query
		$results['like_query'] = self::test_like_query( $wpdb );

		// Test prepared statement
		$results['prepared_statement'] = self::test_prepared_statement( $wpdb );

		return $results;
	}

	/**
	 * Test database connection
	 *
	 * @return array
	 */
	private static function test_database() {
		global $wpdb;

		try {
			$pong = $wpdb->get_var( 'SELECT 1' );
			if ( '1' === $pong ) {
				return array(
					'status'  => 'success',
					'message' => 'Database connection successful',
				);
			} else {
				return array(
					'status'  => 'error',
					'message' => 'Database query returned unexpected result',
				);
			}
		} catch ( Exception $e ) {
			return array(
				'status'  => 'error',
				'message' => 'Database connection failed: ' . $e->getMessage(),
			);
		}
	}

	/**
	 * Test posts table exists and has data
	 *
	 * @param object $wpdb WordPress database object
	 * @return array
	 */
	private static function test_posts_table( $wpdb ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'" );

		if ( null === $count ) {
			return array(
				'status'  => 'error',
				'message' => 'Could not query posts table',
				'error'   => $wpdb->last_error,
			);
		}

		return array(
			'status'   => 'success',
			'message'  => 'Posts table accessible',
			'total'    => (int) $count,
			'has_data' => (int) $count > 0,
		);
	}

	/**
	 * Test basic SELECT query
	 *
	 * @param object $wpdb WordPress database object
	 * @return array
	 */
	private static function test_basic_query( $wpdb ) {
		$results = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' LIMIT 1" );

		if ( null === $results || empty( $wpdb->last_error ) === false ) {
			return array(
				'status'  => 'error',
				'message' => 'Basic query failed',
				'error'   => $wpdb->last_error,
			);
		}

		return array(
			'status'       => 'success',
			'message'      => 'Basic query works',
			'result_count' => count( (array) $results ),
		);
	}

	/**
	 * Test LIKE query
	 *
	 * @param object $wpdb WordPress database object
	 * @return array
	 */
	private static function test_like_query( $wpdb ) {
		$search_term = 'test';
		$like_clause = '%' . $wpdb->esc_like( $search_term ) . '%';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s AND post_status = 'publish'",
				$like_clause
			)
		);

		if ( null === $count || ! empty( $wpdb->last_error ) ) {
			return array(
				'status'   => 'error',
				'message'  => 'LIKE query failed',
				'error'    => $wpdb->last_error,
				'like_val' => $like_clause,
			);
		}

		return array(
			'status'       => 'success',
			'message'      => 'LIKE query works',
			'search_term'  => $search_term,
			'results_with_term' => (int) $count,
		);
	}

	/**
	 * Test prepared statement with multiple placeholders
	 *
	 * @param object $wpdb WordPress database object
	 * @return array
	 */
	private static function test_prepared_statement( $wpdb ) {
		$search_like = '%' . $wpdb->esc_like( 'test' ) . '%';
		$post_types  = array( 'post', 'page' );

		$query_params = array(
			$search_like,
			$search_like,
		);

		foreach ( $post_types as $post_type ) {
			$query_params[] = $post_type;
		}

		$search_where = '(' . $wpdb->posts . '.post_title LIKE %s OR ' . $wpdb->posts . '.post_content LIKE %s)';
		$post_type_where = 'post_type IN (%s,%s)';

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE {$search_where} AND {$post_type_where} AND post_status = 'publish'",
			$query_params
		);

		$count = $wpdb->get_var( $sql );

		if ( null === $count || ! empty( $wpdb->last_error ) ) {
			return array(
				'status'        => 'error',
				'message'       => 'Prepared statement query failed',
				'error'         => $wpdb->last_error,
				'generated_sql' => $sql,
				'params'        => $query_params,
			);
		}

		return array(
			'status'        => 'success',
			'message'       => 'Prepared statement query works',
			'generated_sql' => $sql,
			'result_count'  => (int) $count,
		);
	}
}
