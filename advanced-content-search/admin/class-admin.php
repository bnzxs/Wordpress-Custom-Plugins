<?php
/**
 * Admin class for Advanced Content Search plugin
 *
 * Handles admin menu, page rendering, and AJAX requests
 *
 * @package Advanced_Content_Search
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Content Search Admin Class
 */
class Advanced_Content_Search_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_advanced_search_content', array( $this, 'handle_ajax_search' ) );
		add_action( 'wp_ajax_advanced_export_csv', array( $this, 'handle_csv_export' ) );
		add_action( 'wp_ajax_advanced_export_xlsx', array( $this, 'handle_xlsx_export' ) );
	}

	/**
	 * Register admin menu
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		// Add top-level menu
		add_menu_page(
			esc_html__( 'Content Search & Reporting', 'advanced-content-search' ),
			esc_html__( 'Content Search', 'advanced-content-search' ),
			'manage_options',
			'advanced-content-search',
			array( $this, 'render_admin_page' ),
			'dashicons-search',
			25
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// Only enqueue on our plugin page
		if ( 'toplevel_page_advanced-content-search' !== $hook ) {
			return;
		}

		// Enqueue main admin CSS
		wp_enqueue_style(
			'advanced-content-search-admin',
			ADVANCED_CONTENT_SEARCH_URL . 'assets/css/admin.css',
			array(),
			ADVANCED_CONTENT_SEARCH_VERSION
		);

		// Enqueue main admin JS
		wp_enqueue_script(
			'advanced-content-search-admin',
			ADVANCED_CONTENT_SEARCH_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ADVANCED_CONTENT_SEARCH_VERSION,
			true
		);

		// Localize script with AJAX data
		wp_localize_script(
			'advanced-content-search-admin',
			'advancedContentSearch',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'advanced_content_search_nonce' ),
				'exportNonceCSV'     => wp_create_nonce( 'advanced_export_csv_nonce' ),
				'exportNonceXLSX'    => wp_create_nonce( 'advanced_export_xlsx_nonce' ),
				'strings'            => array(
					'searching'      => esc_html__( 'Searching...', 'advanced-content-search' ),
					'noResults'      => esc_html__( 'No results found.', 'advanced-content-search' ),
					'error'          => esc_html__( 'An error occurred during search.', 'advanced-content-search' ),
					'searchRequired' => esc_html__( 'Please enter a search term.', 'advanced-content-search' ),
					'exportCSV'      => esc_html__( 'Export to CSV', 'advanced-content-search' ),
					'exportXLSX'     => esc_html__( 'Export to Excel', 'advanced-content-search' ),
				),
			)
		);
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_admin_page() {
		// Verify user has access
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'advanced-content-search' ) );
		}

		?>
		<div class="wrap acs-wrap">
			<h1><?php esc_html_e( 'Content Search & Reporting', 'advanced-content-search' ); ?></h1>
			
			<div class="acs-search-box">
				<form id="acs-search-form" method="post">
					<div class="acs-search-field">
						<label for="acs-search-query">
							<?php esc_html_e( 'Search Query (exact phrase):', 'advanced-content-search' ); ?>
						</label>
						<input 
							type="text" 
							id="acs-search-query" 
							name="search_query" 
							placeholder="<?php esc_attr_e( 'Enter search phrase...', 'advanced-content-search' ); ?>"
							class="widefat"
						>
						<small><?php esc_html_e( 'Enter an exact phrase to search for. For example: "hello world" will only match that exact phrase, not "hello" or "world" separately.', 'advanced-content-search' ); ?></small>
					</div>

					<div class="acs-search-field">
						<label for="acs-post-types">
							<?php esc_html_e( 'Post Types to Search:', 'advanced-content-search' ); ?>
						</label>
						<div class="acs-checkboxes">
							<?php
							// Get public post types
							$post_types = get_post_types(
								array(
									'public'       => true,
									'show_in_menu' => true,
								),
								'objects'
							);

							foreach ( $post_types as $post_type ) {
								$checked = in_array( $post_type->name, array( 'post', 'page' ), true ) ? 'checked' : '';
								?>
								<label class="acs-checkbox-label">
									<input 
										type="checkbox" 
										name="post_types[]" 
										value="<?php echo esc_attr( $post_type->name ); ?>"
										<?php echo esc_attr( $checked ); ?>
									>
									<?php echo esc_html( $post_type->label ); ?>
								</label>
								<?php
							}
							?>
						</div>
					</div>

					<div class="acs-search-field">
						<label for="acs-search-fields">
							<?php esc_html_e( 'Search In:', 'advanced-content-search' ); ?>
						</label>
						<div class="acs-checkboxes">
							<label class="acs-checkbox-label">
								<input type="checkbox" name="search_fields[]" value="title" checked>
								<?php esc_html_e( 'Post Title', 'advanced-content-search' ); ?>
							</label>
							<label class="acs-checkbox-label">
								<input type="checkbox" name="search_fields[]" value="content" checked>
								<?php esc_html_e( 'Post Content', 'advanced-content-search' ); ?>
							</label>
						</div>
					</div>

					<div class="acs-search-actions">
						<button type="button" id="acs-search-btn" class="button button-primary">
							<?php esc_html_e( 'Search', 'advanced-content-search' ); ?>
						</button>
						<button type="button" id="acs-reset-btn" class="button">
							<?php esc_html_e( 'Reset', 'advanced-content-search' ); ?>
						</button>
					</div>
				</form>
			</div>

			<div id="acs-search-loading" class="notice notice-info" style="display: none;">
				<p><?php esc_html_e( 'Searching...', 'advanced-content-search' ); ?></p>
			</div>

			<div id="acs-search-results-container" style="display: none;">
				<div class="acs-results-header">
					<h2><?php esc_html_e( 'Search Results', 'advanced-content-search' ); ?></h2>
					<div class="acs-export-buttons">
						<button type="button" id="acs-export-csv" class="button">
							<?php esc_html_e( '⬇ Export to CSV', 'advanced-content-search' ); ?>
						</button>
						<button type="button" id="acs-export-xlsx" class="button">
							<?php esc_html_e( '⬇ Export to Excel', 'advanced-content-search' ); ?>
						</button>
					</div>
				</div>

				<div id="acs-search-results-table-wrapper">
					<table class="wp-list-table widefat striped" id="acs-search-results-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'advanced-content-search' ); ?></th>
								<th><?php esc_html_e( 'Title', 'advanced-content-search' ); ?></th>
								<th><?php esc_html_e( 'Type', 'advanced-content-search' ); ?></th>
								<th><?php esc_html_e( 'Matched Phrase', 'advanced-content-search' ); ?></th>
								<th><?php esc_html_e( 'URL', 'advanced-content-search' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'advanced-content-search' ); ?></th>
							</tr>
						</thead>
						<tbody id="acs-search-results-tbody">
							<!-- Results will be populated here -->
						</tbody>
					</table>
				</div>

				<div id="acs-pagination" class="tablenav bottom">
					<!-- Pagination will be populated here -->
				</div>
			</div>

			<div id="acs-error-message" class="notice notice-error" style="display: none;">
				<p id="acs-error-text"></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX search request
	 *
	 * @return void
	 */
	public function handle_ajax_search() {
		// Verify nonce
		check_ajax_referer( 'advanced_content_search_nonce', 'nonce' );

		// Verify capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Insufficient permissions.', 'advanced-content-search' ) ),
				403
			);
		}

		try {
			// Get search parameters
			$search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
			$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) ) : array( 'post', 'page' );
			$search_fields = isset( $_POST['search_fields'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['search_fields'] ) ) : array( 'title', 'content' );
			$paged = isset( $_POST['paged'] ) ? absint( wp_unslash( $_POST['paged'] ) ) : 1;

			// Validate search query
			if ( empty( $search_query ) ) {
				wp_send_json_error(
					array( 'message' => esc_html__( 'Please enter a search term.', 'advanced-content-search' ) )
				);
				return;
			}

			// Validate post types are safe
			$post_types = array_filter( $post_types, function ( $post_type ) {
				return post_type_exists( $post_type );
			} );

			if ( empty( $post_types ) ) {
				wp_send_json_error(
					array( 'message' => esc_html__( 'Please select at least one post type.', 'advanced-content-search' ) )
				);
				return;
			}

			// Validate search fields
			$valid_fields = array( 'title', 'content' );
			$search_fields = array_intersect( $search_fields, $valid_fields );

			if ( empty( $search_fields ) ) {
				wp_send_json_error(
					array( 'message' => esc_html__( 'Please select at least one search field.', 'advanced-content-search' ) )
				);
				return;
			}

			// Perform the search
			$search_engine = new Advanced_Content_Search_Engine();
			$results = $search_engine->search(
				$search_query,
				$post_types,
				$search_fields,
				$paged
			);

			if ( is_wp_error( $results ) ) {
				error_log( 'ACS Search Error: ' . $results->get_error_message() );
				wp_send_json_error(
					array( 'message' => $results->get_error_message() )
				);
				return;
			}

			// Prepare response
			$response = array(
				'results'       => $results['items'],
				'total'         => $results['total'],
				'total_pages'   => $results['total_pages'],
				'paged'         => $paged,
				'search_query'  => $search_query,
				'post_types'    => $post_types,
				'search_fields' => $search_fields,
			);

			wp_send_json_success( $response );
		} catch ( Exception $e ) {
			error_log( 'ACS Search Exception: ' . $e->getMessage() );
			wp_send_json_error(
				array( 'message' => esc_html__( 'An unexpected error occurred during search.', 'advanced-content-search' ) )
			);
		}
	}

	/**
	 * Handle CSV export request
	 *
	 * @return void
	 */
	public function handle_csv_export() {
		// Verify nonce
		check_ajax_referer( 'advanced_export_csv_nonce', 'nonce' );

		// Verify capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Insufficient permissions.', 'advanced-content-search' ) ),
				403
			);
		}

		// Get search parameters
		$search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) ) : array( 'post', 'page' );
		$search_fields = isset( $_POST['search_fields'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['search_fields'] ) ) : array( 'title', 'content' );

		// Validate search query
		if ( empty( $search_query ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid search query.', 'advanced-content-search' ) )
			);
		}

		// Validate post types
		$post_types = array_filter( $post_types, function ( $post_type ) {
			return post_type_exists( $post_type );
		} );

		if ( empty( $post_types ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid post types.', 'advanced-content-search' ) )
			);
		}

		// Validate search fields
		$valid_fields = array( 'title', 'content' );
		$search_fields = array_intersect( $search_fields, $valid_fields );

		if ( empty( $search_fields ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid search fields.', 'advanced-content-search' ) )
			);
		}

		// Get all results (no pagination for export)
		$search_engine = new Advanced_Content_Search_Engine();
		$results = $search_engine->search(
			$search_query,
			$post_types,
			$search_fields,
			1,
			-1 // Get all results
		);

		if ( is_wp_error( $results ) ) {
			wp_send_json_error(
				array( 'message' => $results->get_error_message() )
			);
		}

		// Generate CSV
		$csv_data = $this->generate_csv( $results['items'], $search_query );

		// Send JSON response with CSV data
		wp_send_json_success(
			array(
				'csv' => $csv_data,
				'filename' => 'advanced-search-results-' . gmdate( 'Y-m-d-His' ) . '.csv',
			)
		);
	}

	/**
	 * Handle XLSX export request
	 *
	 * @return void
	 */
	public function handle_xlsx_export() {
		// Verify nonce
		check_ajax_referer( 'advanced_export_xlsx_nonce', 'nonce' );

		// Verify capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Insufficient permissions.', 'advanced-content-search' ) ),
				403
			);
		}

		// Get search parameters
		$search_query = isset( $_POST['search_query'] ) ? sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) : '';
		$post_types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) ) : array( 'post', 'page' );
		$search_fields = isset( $_POST['search_fields'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['search_fields'] ) ) : array( 'title', 'content' );

		// Validate search query
		if ( empty( $search_query ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid search query.', 'advanced-content-search' ) )
			);
		}

		// Validate post types
		$post_types = array_filter( $post_types, function ( $post_type ) {
			return post_type_exists( $post_type );
		} );

		if ( empty( $post_types ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid post types.', 'advanced-content-search' ) )
			);
		}

		// Validate search fields
		$valid_fields = array( 'title', 'content' );
		$search_fields = array_intersect( $search_fields, $valid_fields );

		if ( empty( $search_fields ) ) {
			wp_send_json_error(
				array( 'message' => esc_html__( 'Invalid search fields.', 'advanced-content-search' ) )
			);
		}

		// Get all results (no pagination for export)
		$search_engine = new Advanced_Content_Search_Engine();
		$results = $search_engine->search(
			$search_query,
			$post_types,
			$search_fields,
			1,
			-1 // Get all results
		);

		if ( is_wp_error( $results ) ) {
			wp_send_json_error(
				array( 'message' => $results->get_error_message() )
			);
		}

		// Generate XLSX (simulated as CSV-like format for basic support)
		$xlsx_data = $this->generate_xlsx( $results['items'], $search_query );

		// Send JSON response with XLSX data
		wp_send_json_success(
			array(
				'xlsx' => $xlsx_data,
				'filename' => 'advanced-search-results-' . gmdate( 'Y-m-d-His' ) . '.xlsx',
			)
		);
	}

	/**
	 * Generate CSV data from search results
	 *
	 * @param array  $results Search results
	 * @param string $search_query Original search query
	 * @return string CSV formatted data
	 */
	private function generate_csv( $results, $search_query ) {
		// Create CSV in memory
		$output = fopen( 'php://memory', 'w' );

		// Add CSV header
		fputcsv( $output, array(
			'ID',
			'Title',
			'Post Type',
			'Matched Phrase',
			'URL',
			'Edit URL',
		) );

		// Add results
		foreach ( $results as $result ) {
			fputcsv( $output, array(
				$result['id'],
				$result['title'],
				$result['post_type'],
				$search_query,
				$result['url'],
				$result['edit_url'],
			) );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Generate XLSX data from search results
	 *
	 * Uses a comma-separated format that can be opened in Excel
	 * For full XLSX support, consider using a library like PhpSpreadsheet
	 *
	 * @param array  $results Search results
	 * @param string $search_query Original search query
	 * @return string XLSX-compatible formatted data
	 */
	private function generate_xlsx( $results, $search_query ) {
		// Use the same CSV format for now
		// In production, you would use a library like PhpSpreadsheet
		return $this->generate_csv( $results, $search_query );
	}
}
