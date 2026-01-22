<?php
if (!defined('ABSPATH')) {
    exit;
}

// Redirects the page to display results after clicking run audit

/* function not working at the moment
add_action('admin_init', 'mta_handle_run_audit');
function mta_handle_run_audit() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if (!isset($_POST['mta_run_audit'])) return;

    $args = [
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => 25,
        'paged' => 1
    ];

    do {
        $query = new WP_Query($args);
        foreach ($query->posts as $post) {
            $issues = mta_scan_post_for_issues($post->ID);
            update_post_meta($post->ID, '_mta_issues', $issues);
        }
        $args['paged']++;
    } while ($query->have_posts());

    update_option('mta_last_audit_run', current_time('mysql'));
    wp_redirect(admin_url('admin.php?page=metatag-auditor&audit=done'));
    exit;
}
*/

// CSV export logic for post id hooked early to avoid header errors
add_action('admin_init', 'mta_handle_id_export');
function mta_handle_id_export() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if (!isset($_POST['mta_export_ids'])) return;

    $type_filter = sanitize_text_field($_GET['mta_filter_type'] ?? '');

    $args = [
        'post_type' => $type_filter ? [$type_filter] : ['post', 'page'],
        'post_status' => 'publish',
        'meta_query' => [['key' => '_mta_issues', 'compare' => 'EXISTS']],
        'posts_per_page' => -1
    ];

    $query = new WP_Query($args);

    $ids = array_map(function($post) {
        return $post->ID;
    }, $query->posts);

    $slug = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    $first_slug = $slug ? explode('/', $slug)[0] : 'root';

    $formatted_date = date('d-m-Y');

    $filename = 'post_ids_' . sanitize_title($first_slug) . '_' . $formatted_date . '.txt';

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo implode(',', $ids);
    exit;
}


// CSV export logic for meta data hooked early to avoid header errors
add_action('admin_init', 'mta_handle_csv_export');

function mta_handle_csv_export() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    if (!isset($_POST['mta_export_csv'])) return;

    $issue_filter = sanitize_text_field($_GET['mta_filter_issue'] ?? '');
    $type_filter = sanitize_text_field($_GET['mta_filter_type'] ?? '');

    $args = [
        'post_type' => $type_filter ? [$type_filter] : ['post', 'page'],
        'post_status' => 'publish',
        'meta_query' => [
            ['key' => '_mta_issues', 'compare' => 'EXISTS']
        ],
        'posts_per_page' => -1
    ];

    $query = new WP_Query($args);

    header('Content-Type: text/csv; charset=UTF-8');

    $url_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    $first_slug = $url_path ? explode('/', $url_path)[0] : 'root';

    $formatted_date = date('d-m-Y');

    $filename = 'meta_audit_' . sanitize_title($first_slug) . '_' . $formatted_date . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');


    // Add UTF-8 BOM to preserve Japanese characters
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Title', 'Post URL', 'Issue Type', 'Issue Text', 'Audit Date']);

    foreach ($query->posts as $post) {
        $issues = get_post_meta($post->ID, '_mta_issues', true);
        if (!is_array($issues) || empty($issues)) continue;

        if ($issue_filter) {
            $match = false;
            foreach ($issues as $log) {
                foreach ($log['issues'] as $i) {
                    $text = is_array($i) ? $i['text'] ?? '' : $i;
                    if (stripos($text, $issue_filter) !== false) {
                        $match = true;
                        break 2;
                    }
                }
            }
            if (!$match) continue;
        }

        $latest = end($issues);
        foreach ($latest['issues'] as $i) {
            $type = is_array($i) ? $i['type'] ?? 'unknown' : 'legacy';
            $text = is_array($i) ? $i['text'] ?? '' : $i;
            fputcsv($output, [
                $post->ID,
                $post->post_title,
                get_permalink($post->ID),
                $type,
                $text,
                $latest['time']
            ]);
        }
    }

    fclose($output);
    exit;
}

// Render admin page
function mta_render_admin_page() {
    echo '<style>
        :root {
            --mta-primary: #4f46e5;
            --mta-primary-hover: #4338ca;
            --mta-bg: #f8fafc;
            --mta-card: #ffffff;
            --mta-text: #1e293b;
            --mta-text-muted: #64748b;
            --mta-border: #e2e8f0;
            --mta-success: #10b981;
            --mta-warning: #f59e0b;
            --mta-critical: #ef4444;
            --mta-info: #3b82f6;
            --mta-radius: 12px;
            --mta-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        .mta-dashboard {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--mta-text);
            margin-top: 20px;
        }

        .mta-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .mta-stat-card {
            background: var(--mta-card);
            padding: 24px;
            border-radius: var(--mta-radius);
            box-shadow: var(--mta-shadow);
            border: 1px solid var(--mta-border);
            text-align: center;
        }

        .mta-stat-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--mta-text-muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .mta-stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--mta-primary);
        }

        .mta-actions {
            background: var(--mta-card);
            padding: 20px;
            border-radius: var(--mta-radius);
            margin-bottom: 20px;
            border: 1px solid var(--mta-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .mta-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .mta-btn-primary {
            background: var(--mta-primary);
            color: white;
        }

        .mta-btn-primary:hover {
            background: var(--mta-primary-hover);
        }

        .mta-btn-secondary {
            background: var(--mta-bg);
            color: var(--mta-text);
            border: 1px solid var(--mta-border);
        }

        .mta-btn-secondary:hover {
            background: #eef2f6;
        }

        .mta-table-container {
            background: var(--mta-card);
            border-radius: var(--mta-radius);
            padding: 0;
            border: 1px solid var(--mta-border);
            box-shadow: var(--mta-shadow);
            overflow: hidden;
        }

        .mta-table {
            width: 100%;
            border-collapse: collapse;
        }

        .mta-table th {
            text-align: left;
            padding: 15px 20px;
            background: var(--mta-bg);
            border-bottom: 1px solid var(--mta-border);
            font-weight: 600;
            color: var(--mta-text-muted);
        }

        .mta-table td {
            padding: 15px 20px;
            border-bottom: 1px solid var(--mta-border);
        }

        .mta-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .mta-badge-critical { background: #fee2e2; color: #b91c1c; }
        .mta-badge-warning { background: #fef3c7; color: #b45309; }
        .mta-badge-info { background: #dbeafe; color: #1d4ed8; }

        .mta-progress-wrap {
            margin: 20px 0;
            display: none;
        }

        .mta-progress-bar {
            height: 12px;
            background: var(--mta-border);
            border-radius: 6px;
            overflow: hidden;
        }

        .mta-progress-fill {
            height: 100%;
            background: var(--mta-primary);
            transition: width 0.3s ease;
            width: 0%;
        }

        .mta-row-issue {
            margin-bottom: 6px;
            line-height: 1.4;
        }
    </style>';
    // Stats Calculation
    $all_posts = new WP_Query([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);
    $total_audited = count($all_posts->posts);
    
    $issue_posts = new WP_Query([
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'meta_query' => [['key' => '_mta_issues', 'compare' => 'EXISTS']],
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);
    $total_with_issues = count($issue_posts->posts);
    
    $total_critical = 0;
    $total_issues_count = 0;
    foreach ($issue_posts->posts as $pid) {
        $meta = get_post_meta($pid, '_mta_issues', true);
        if (is_array($meta) && !empty($meta)) {
            $latest = reset($meta); // Newest is at index 0 now
            foreach ($latest['issues'] as $i) {
                $total_issues_count++;
                if (isset($i['type']) && $i['type'] === 'critical') {
                    $total_critical++;
                }
            }
        }
    }

    $health_score = $total_audited > 0 ? round((($total_audited - $total_with_issues) / $total_audited) * 100) : 100;

    echo '<div class="wrap mta-dashboard">';
    echo '<h1>MetaTag Auditor <small style="font-size: 14px; font-weight: normal; color: var(--mta-text-muted);">2.1.0</small></h1>';

    // Stats Section
    echo '<div class="mta-stats">';
    echo '<div class="mta-stat-card"><span class="mta-stat-label">Health Score</span><div class="mta-stat-value" style="color:' . ($health_score > 80 ? 'var(--mta-success)' : ($health_score > 50 ? 'var(--mta-warning)' : 'var(--mta-critical)')) . ';">' . $health_score . '%</div></div>';
    echo '<div class="mta-stat-card"><span class="mta-stat-label">Total Audited</span><div class="mta-stat-value">' . $total_audited . '</div></div>';
    echo '<div class="mta-stat-card"><span class="mta-stat-label">Issues Found</span><div class="mta-stat-value">' . $total_issues_count . '</div></div>';
    echo '<div class="mta-stat-card"><span class="mta-stat-label">Critical</span><div class="mta-stat-value" style="color:var(--mta-critical);">' . $total_critical . '</div></div>';
    echo '</div>';

    // Action Bar
    echo '<div class="mta-actions">';
    echo '<div>';
    $progress = get_option('mta_scan_progress');
    $can_resume = false;
    if ($progress && is_array($progress)) {
        $resume_batch = intval($progress['batch']) + 1;
        $resume_total = intval($progress['total']);
        if ($resume_batch <= $resume_total) $can_resume = true;
    }

    if ($can_resume) {
        echo '<button type="button" id="mta-resume-audit-btn" class="mta-btn mta-btn-primary" data-batch="' . $resume_batch . '">Resume Audit (' . round(($resume_batch/$resume_total)*100) . '%)</button> ';
        echo '<button type="button" id="mta-run-audit-btn" class="mta-btn mta-btn-secondary">New Audit</button>';
    } else {
        echo '<button type="button" id="mta-run-audit-btn" class="mta-btn mta-btn-primary">Run Full Audit</button>';
    }
    echo '</div>';

    echo '<div style="display:flex; gap:10px;">';
    echo '<form method="post" style="display:inline;">';
    echo '<button type="submit" name="mta_export_ids" class="mta-btn mta-btn-secondary">Export IDs</button> ';
    echo '<button type="submit" name="mta_export_csv" class="mta-btn mta-btn-secondary">Export CSV</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Progress Bar
    echo '<div id="mta-progress-bar" class="mta-progress-wrap">';
    echo '<div class="mta-progress-bar"><div id="mta-progress-fill" class="mta-progress-fill"></div></div>';
    echo '<p id="mta-status-text" style="font-weight:600; margin-top:10px;"></p>';
    echo '<ul id="mta-error-list" style="color:var(--mta-critical); list-style:disc; margin-left:20px;"></ul>';
    echo '</div>';

    // Filters
    echo '<div style="margin-bottom:20px;">';
    echo '<form method="get" style="display:flex; gap:10px; align-items:center;">';
    echo '<input type="hidden" name="page" value="metatag-auditor">';
    echo '<select name="mta_filter_issue" style="border-radius:6px;">';
    echo '<option value="">All Issue Types</option>';
    echo '<option value="noindex">Noindex</option>';
    echo '<option value="canonical">Canonical Issues</option>';
    echo '<option value="title">Title Issues</option>';
    echo '<option value="description">Description Issues</option>';
    echo '<option value="h1">H1 Issues</option>';
    echo '<option value="og:">Social (OG/Twitter)</option>';
    echo '</select>';
    echo '<select name="mta_filter_type" style="border-radius:6px;">';
    echo '<option value="">All Content Types</option>';
    echo '<option value="post">Posts</option>';
    echo '<option value="page">Pages</option>';
    echo '</select>';
    echo '<button type="submit" class="mta-btn mta-btn-secondary" style="padding:6px 15px;">Filter</button>';
    if (isset($_GET['mta_filter_issue']) || isset($_GET['mta_filter_type'])) {
        echo '<a href="' . admin_url('admin.php?page=metatag-auditor') . '" class="mta-btn mta-btn-secondary" style="padding:6px 15px;">Clear</a>';
    }
    echo '</form>';
    echo '</div>';

    // Results Table & Pagination Logic
    $issue_filter = sanitize_text_field($_GET['mta_filter_issue'] ?? '');
    $type_filter = sanitize_text_field($_GET['mta_filter_type'] ?? '');
    $paged = isset($_GET['mta_paged']) ? max(1, intval($_GET['mta_paged'])) : 1;
    $per_page = 10;

    $args = [
        'post_type' => $type_filter ? [$type_filter] : ['post', 'page'],
        'post_status' => 'publish',
        'meta_query' => [['key' => '_mta_issues', 'compare' => 'EXISTS']],
        'posts_per_page' => -1
    ];
    $query = new WP_Query($args);

    // Apply filtering in memory
    $filtered_posts = [];
    foreach ($query->posts as $post) {
        $issues_log = get_post_meta($post->ID, '_mta_issues', true);
        if (!is_array($issues_log) || empty($issues_log)) continue;

        $latest = reset($issues_log);
        
        if ($issue_filter) {
            $match = false;
            foreach ($latest['issues'] as $i) {
                $text = is_array($i) ? $i['text'] ?? '' : $i;
                if (stripos($text, $issue_filter) !== false) {
                    $match = true;
                    break;
                }
            }
            if (!$match) continue;
        }
        $filtered_posts[] = $post;
    }

    $total_items = count($filtered_posts);
    $total_pages = ceil($total_items / $per_page);
    $offset = ($paged - 1) * $per_page;
    $paged_posts = array_slice($filtered_posts, $offset, $per_page);

    echo '<div class="mta-table-container">';
    echo '<table class="mta-table">';
    echo '<thead><tr><th>ID</th><th>Page/Post</th><th>Audit Details</th><th>Last Checked</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    if (empty($paged_posts)) {
        echo '<tr><td colspan="5" style="text-align:center; padding:40px;">No issues found matching your filters.</td></tr>';
    } else {
        foreach ($paged_posts as $post) {
            $issues_log = get_post_meta($post->ID, '_mta_issues', true);
            $latest = reset($issues_log);

            echo '<tr>';
            echo '<td>' . $post->ID . '</td>';
            echo '<td>';
            echo '<strong>' . esc_html($post->post_title) . '</strong><br>';
            echo '<small style="color:var(--mta-text-muted);">' . esc_url(get_permalink($post->ID)) . '</small>';
            echo '</td>';
            
            echo '<td>';
            foreach ($latest['issues'] as $i) {
                $type = $i['type'] ?? 'info';
                $text = $i['text'] ?? $i;
                echo '<div class="mta-row-issue">';
                echo '<span class="mta-badge mta-badge-' . esc_attr($type) . '">' . esc_html($type) . '</span> ';
                echo '<span>' . esc_html($text) . '</span>';
                echo '</div>';
            }
            echo '</td>';
            
            echo '<td>' . esc_html($latest['time']) . '</td>';
            echo '<td>';
            echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-small">Edit</a> ';
            echo '<a href="' . get_permalink($post->ID) . '" target="_blank" class="button button-small">View</a>';
            echo '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';

    // Pagination Controls
    if ($total_pages > 1) {
        echo '<div style="margin-top:20px; display:flex; justify-content:center; gap:5px; align-items:center;">';
        $base_url = admin_url('admin.php?page=metatag-auditor');
        if ($issue_filter) $base_url = add_query_arg('mta_filter_issue', $issue_filter, $base_url);
        if ($type_filter) $base_url = add_query_arg('mta_filter_type', $type_filter, $base_url);

        // Prev Link
        if ($paged > 1) {
            echo '<a href="' . add_query_arg('mta_paged', $paged - 1, $base_url) . '" class="mta-btn mta-btn-secondary" style="padding:5px 12px;">&laquo; Prev</a>';
        }

        // Sliding window pagination
        $range = 2; // How many pages to show around current
        $show_dots_after_first = false;
        $show_dots_before_last = false;

        for ($i = 1; $i <= $total_pages; $i++) {
            // Always show first, last, and pages within range
            if ($i == 1 || $i == $total_pages || ($i >= $paged - $range && $i <= $paged + $range)) {
                $active_style = ($i === $paged) ? 'background:var(--mta-primary); color:white; border-color:var(--mta-primary);' : '';
                echo '<a href="' . add_query_arg('mta_paged', $i, $base_url) . '" class="mta-btn mta-btn-secondary" style="padding:5px 12px; ' . $active_style . '">' . $i . '</a>';
            } 
            // Show dots after first page if there's a gap
            elseif ($i == 2 && $paged > $range + 2) {
                echo '<span style="color:var(--mta-text-muted); padding:0 5px;">...</span>';
            }
            // Show dots before last page if there's a gap
            elseif ($i == $total_pages - 1 && $paged < $total_pages - $range - 1) {
                echo '<span style="color:var(--mta-text-muted); padding:0 5px;">...</span>';
            }
        }

        // Next Link
        if ($paged < $total_pages) {
            echo '<a href="' . add_query_arg('mta_paged', $paged + 1, $base_url) . '" class="mta-btn mta-btn-secondary" style="padding:5px 12px;">Next &raquo;</a>';
        }
        echo '</div>';
    }

    // AJAX Script
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var isScanning = false;
        var mta_ajax_url = (typeof ajaxurl !== 'undefined') ? ajaxurl : '<?php echo admin_url('admin-ajax.php'); ?>';

        var totalBatches = 0;
        var currentBatch = 1;

        var btnRun = $('#mta-run-audit-btn');
        var btnResume = $('#mta-resume-audit-btn');
        
        var progressBar = $('#mta-progress-bar');
        var progressFill = $('#mta-progress-fill');
        var statusText = $('#mta-status-text');
        var errorList = $('#mta-error-list');

        btnRun.on('click', function(e) { e.preventDefault(); startScan(1); });
        
        if (btnResume.length) {
            btnResume.on('click', function(e) {
                e.preventDefault();
                startScan($(this).data('batch'));
            });
        }

        function startScan(batch) {
            if (isScanning) return;
            isScanning = true;
            btnRun.css('opacity', '0.5').css('pointer-events', 'none');
            if(btnResume.length) btnResume.css('opacity', '0.5').css('pointer-events', 'none');
            
            progressBar.fadeIn();
            statusText.text('Initializing scan batch ' + batch + '...').show();
            errorList.empty().hide();
            
            currentBatch = batch;
            processBatch(batch);
        }

        function processBatch(batch) {
            $.ajax({
                url: mta_ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mta_scan_batch',
                    batch: batch,
                    nonce: '<?php echo wp_create_nonce("mta_scan_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        totalBatches = data.total_batches;
                        var percent = Math.min(100, Math.round((currentBatch / totalBatches) * 100));
                        progressFill.css('width', percent + '%');
                        statusText.text('Scanning: Batch ' + currentBatch + ' of ' + totalBatches + ' (' + percent + '%)');
                        
                        if (data.errors && data.errors.length > 0) {
                            errorList.show();
                            $.each(data.errors, function(i, err) {
                                errorList.append('<li>' + err + '</li>');
                            });
                        }
                        
                        if (data.has_more) {
                            currentBatch++;
                            processBatch(currentBatch);
                        } else {
                            statusText.text('Complete! Refreshing results...');
                            setTimeout(function() { window.location.reload(); }, 1000);
                        }
                    } else {
                        handleError('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown'));
                    }
                },
                error: function(xhr, status, error) {
                    handleError('Request failed (' + xhr.status + '). Progress saved.');
                }
            });
        }
        
        function handleError(msg) {
            isScanning = false;
            btnRun.css('opacity', '1').css('pointer-events', 'auto');
            statusText.html('<span style="color:var(--mta-critical);">' + msg + '</span>');
        }
    });
    </script>
    <?php
    echo '</div>'; // End wrap
}

function mta_register_admin_page() {
    add_management_page(
        'MetaTag Auditor',
        'MetaTag Auditor',
        'manage_options',
        'metatag-auditor',
        'mta_render_admin_page'
    );
}
add_action('admin_menu', 'mta_register_admin_page');