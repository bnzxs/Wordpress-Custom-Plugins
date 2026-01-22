/**
 * Advanced Content Search Admin JavaScript
 *
 * Handles AJAX requests for search and export functionality
 *
 * @package Advanced_Content_Search
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	/**
	 * Plugin initialization
	 */
	$(document).ready(function () {
		const plugin = new AdvancedContentSearch();
		plugin.init();
	});

	/**
	 * Advanced Content Search Plugin Class
	 */
	window.AdvancedContentSearch = function () {
		this.currentPage = 1;
		this.currentQuery = '';
		this.currentPostTypes = [];
		this.currentSearchFields = [];
		this.totalResults = 0;
	};

	/**
	 * Initialize plugin
	 */
	AdvancedContentSearch.prototype.init = function () {
		const self = this;

		// Search button click handler
		$(document).on('click', '#acs-search-btn', function (e) {
			e.preventDefault();
			self.performSearch(1);
		});

		// Reset button click handler
		$(document).on('click', '#acs-reset-btn', function (e) {
			e.preventDefault();
			self.resetSearch();
		});

		// Export CSV button click handler
		$(document).on('click', '#acs-export-csv', function (e) {
			e.preventDefault();
			self.exportCSV();
		});

		// Export XLSX button click handler
		$(document).on('click', '#acs-export-xlsx', function (e) {
			e.preventDefault();
			self.exportXLSX();
		});

		// Pagination click handler
		$(document).on('click', '.acs-pagination a', function (e) {
			e.preventDefault();
			const page = $(this).data('page');
			if (page) {
				self.performSearch(page);
			}
		});

		// Allow Enter key to search
		$(document).on('keypress', '#acs-search-query', function (e) {
			if (e.which === 13) {
				e.preventDefault();
				self.performSearch(1);
			}
		});
	};

	/**
	 * Perform search via AJAX
	 *
	 * @param {number} page Page number
	 */
	AdvancedContentSearch.prototype.performSearch = function (page) {
		const self = this;
		const searchQuery = $('#acs-search-query').val().trim();

		if (!searchQuery) {
			this.showError(advancedContentSearch.strings.searchRequired);
			return;
		}

		// Get selected post types
		const postTypes = [];
		$('input[name="post_types[]"]:checked').each(function () {
			postTypes.push($(this).val());
		});

		if (postTypes.length === 0) {
			this.showError('Please select at least one post type to search.');
			return;
		}

		// Get selected search fields
		const searchFields = [];
		$('input[name="search_fields[]"]:checked').each(function () {
			searchFields.push($(this).val());
		});

		if (searchFields.length === 0) {
			this.showError('Please select at least one search field.');
			return;
		}

		// Store current search parameters
		this.currentQuery = searchQuery;
		this.currentPostTypes = postTypes;
		this.currentSearchFields = searchFields;
		this.currentPage = page;

		// Show loading indicator
		$('#acs-search-loading').show();
		$('#acs-search-results-container').hide();
		$('#acs-error-message').hide();

		// Perform AJAX request
		$.ajax({
			url: advancedContentSearch.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'advanced_search_content',
				nonce: advancedContentSearch.nonce,
				search_query: searchQuery,
				post_types: postTypes,
				search_fields: searchFields,
				paged: page,
			},
			success: function (response) {
				$('#acs-search-loading').hide();

				if (response.success) {
					self.displayResults(response.data);
					$('#acs-search-results-container').show();
				} else {
					self.showError(response.data.message || advancedContentSearch.strings.error);
				}
			},
			error: function (xhr, status, error) {
				$('#acs-search-loading').hide();
				console.error('AJAX Error:', error);
				self.showError(advancedContentSearch.strings.error);
			},
		});
	};

	/**
	 * Display search results in table
	 *
	 * @param {object} data Search results data
	 */
	AdvancedContentSearch.prototype.displayResults = function (data) {
		const self = this;
		const $tbody = $('#acs-search-results-tbody');
		const $table = $('#acs-search-results-table');

		$tbody.empty();

		if (data.results.length === 0) {
			$tbody.html(
				'<tr><td colspan="6" style="text-align: center;">' +
				advancedContentSearch.strings.noResults +
				'</td></tr>'
			);
			$('#acs-pagination').empty();
			this.totalResults = 0;
			return;
		}

		// Store total for export
		this.totalResults = data.total;

		// Add each result to the table
		data.results.forEach(function (result) {
			const row = self.createResultRow(result, data.search_query);
			$tbody.append(row);
		});

		// Display pagination
		this.displayPagination(data);

		// Update export buttons state
		$('#acs-export-csv, #acs-export-xlsx').prop('disabled', false);
	};

	/**
	 * Create a table row for a search result
	 *
	 * @param {object} result Search result object
	 * @param {string} searchQuery The search query
	 * @return {string} HTML for table row
	 */
	AdvancedContentSearch.prototype.createResultRow = function (result, searchQuery) {
		const self = this;
		const viewUrl = this.escapeHtml(result.url);
		const editUrl = this.escapeHtml(result.edit_url);
		const title = this.escapeHtml(result.title);
		const postType = this.escapeHtml(result.post_type_label);
		const searchPhrase = this.escapeHtml(searchQuery);

		return (
			'<tr>' +
			'<td>' + result.id + '</td>' +
			'<td><strong>' + title + '</strong></td>' +
			'<td>' + postType + '</td>' +
			'<td>' + searchPhrase + '</td>' +
			'<td><a href="' + viewUrl + '" target="_blank" rel="noopener noreferrer">' +
			'View' +
			'</a></td>' +
			'<td><a href="' + editUrl + '" target="_blank" rel="noopener noreferrer">' +
			'Edit' +
			'</a></td>' +
			'</tr>'
		);
	};

	/**
	 * Display pagination controls
	 *
	 * @param {object} data Search results data
	 */
	AdvancedContentSearch.prototype.displayPagination = function (data) {
		const $pagination = $('#acs-pagination');
		$pagination.empty();

		if (data.total_pages <= 1) {
			return;
		}

		let html = '<div class="tablenav"><div class="pagination">';

		// Previous button
		if (data.paged > 1) {
			html +=
				'<a href="#" class="acs-pagination button" data-page="' +
				(data.paged - 1) +
				'">&larr; Previous</a> ';
		} else {
			html += '<span class="button disabled">&larr; Previous</span> ';
		}

		// Page numbers
		const range = 2; // Number of pages before and after current page
		const showFirst = 1;
		const showLast = data.total_pages;

		for (let i = 1; i <= data.total_pages; i++) {
			// Always show first and last pages
			if (
				i === showFirst ||
				i === showLast ||
				(i >= data.paged - range && i <= data.paged + range)
			) {
				if (i === data.paged) {
					html += '<span class="button disabled" style="font-weight: bold;">' + i + '</span> ';
				} else {
					html +=
						'<a href="#" class="acs-pagination button" data-page="' +
						i +
						'">' +
						i +
						'</a> ';
				}
			} 
			// Show ellipsis for gaps
			else if (
				(i === showFirst + 1 && i < data.paged - range) ||
				(i === showLast - 1 && i > data.paged + range)
			) {
				html += '<span class="button disabled">...</span> ';
			}
		}

		// Next button
		if (data.paged < data.total_pages) {
			html +=
				'<a href="#" class="acs-pagination button" data-page="' +
				(data.paged + 1) +
				'">Next &rarr;</a>';
		} else {
			html += '<span class="button disabled">Next &rarr;</span>';
		}

		html +=
			'</div><div style="padding: 5px 0;">' +
			'Showing page ' +
			data.paged +
			' of ' +
			data.total_pages +
			' (' +
			data.total +
			' total results)' +
			'</div></div>';

		$pagination.html(html);
	};

	/**
	 * Export results to CSV
	 */
	AdvancedContentSearch.prototype.exportCSV = function () {
		const self = this;

		if (!this.currentQuery) {
			this.showError('Please perform a search first.');
			return;
		}

		// Disable button during export
		$('#acs-export-csv').prop('disabled', true).text('Exporting...');

		$.ajax({
			url: advancedContentSearch.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'advanced_export_csv',
				nonce: advancedContentSearch.exportNonceCSV,
				search_query: this.currentQuery,
				post_types: this.currentPostTypes,
				search_fields: this.currentSearchFields,
			},
			success: function (response) {
				$('#acs-export-csv').prop('disabled', false).text('⬇ Export to CSV');

				if (response.success) {
					self.downloadFile(response.data.csv, response.data.filename);
				} else {
					self.showError(response.data.message || 'Export failed.');
				}
			},
			error: function (xhr, status, error) {
				$('#acs-export-csv').prop('disabled', false).text('⬇ Export to CSV');
				console.error('Export Error:', error);
				self.showError('An error occurred during export.');
			},
		});
	};

	/**
	 * Export results to XLSX
	 */
	AdvancedContentSearch.prototype.exportXLSX = function () {
		const self = this;

		if (!this.currentQuery) {
			this.showError('Please perform a search first.');
			return;
		}

		// Disable button during export
		$('#acs-export-xlsx').prop('disabled', true).text('Exporting...');

		$.ajax({
			url: advancedContentSearch.ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'advanced_export_xlsx',
				nonce: advancedContentSearch.exportNonceXLSX,
				search_query: this.currentQuery,
				post_types: this.currentPostTypes,
				search_fields: this.currentSearchFields,
			},
			success: function (response) {
				$('#acs-export-xlsx').prop('disabled', false).text('⬇ Export to Excel');

				if (response.success) {
					self.downloadFile(response.data.xlsx, response.data.filename);
				} else {
					self.showError(response.data.message || 'Export failed.');
				}
			},
			error: function (xhr, status, error) {
				$('#acs-export-xlsx').prop('disabled', false).text('⬇ Export to Excel');
				console.error('Export Error:', error);
				self.showError('An error occurred during export.');
			},
		});
	};

	/**
	 * Download file from text content
	 *
	 * @param {string} content File content
	 * @param {string} filename Filename for download
	 */
	AdvancedContentSearch.prototype.downloadFile = function (content, filename) {
		const element = document.createElement('a');
		element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(content));
		element.setAttribute('download', filename);
		element.style.display = 'none';

		document.body.appendChild(element);
		element.click();
		document.body.removeChild(element);
	};

	/**
	 * Reset search form
	 */
	AdvancedContentSearch.prototype.resetSearch = function () {
		$('#acs-search-query').val('');
		$('#acs-search-results-container').hide();
		$('#acs-error-message').hide();
		$('#acs-search-loading').hide();
		this.currentQuery = '';
		this.currentPage = 1;
	};

	/**
	 * Show error message
	 *
	 * @param {string} message Error message
	 */
	AdvancedContentSearch.prototype.showError = function (message) {
		const $errorDiv = $('#acs-error-message');
		$('#acs-error-text').text(message);
		$errorDiv.show();
	};

	/**
	 * Escape HTML special characters
	 *
	 * @param {string} text Text to escape
	 * @return {string} Escaped text
	 */
	AdvancedContentSearch.prototype.escapeHtml = function (text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	};
})(jQuery);
