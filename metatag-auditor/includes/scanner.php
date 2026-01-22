<?php 
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scan a single batch of posts (AJAX handler)
 */
add_action('wp_ajax_mta_scan_batch', 'mta_scan_batch_ajax');
function mta_scan_batch_ajax() {
    check_ajax_referer('mta_scan_nonce', 'nonce');
    
    // Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $batch = isset($_POST['batch']) ? intval($_POST['batch']) : 1;
    $per_page = 2; // Small batch size to prevent 504 Timeouts
    
    // Attempt to extend execution time per batch
    if (function_exists('set_time_limit')) {
        set_time_limit(0);
    }
    
    $args = [
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $batch,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ids' // Optimization
    ];
    
    $query = new WP_Query($args);
    $scanned = 0;
    $errors = [];

    foreach ($query->posts as $post_id) {
        try {
            mta_scan_single_post($post_id);
            $scanned++;
        } catch (Exception $e) {
            $errors[] = "Post {$post_id}: " . $e->getMessage();
        }
    }

    $has_more = $query->max_num_pages > $batch;
    
    // Clear any white space/output before JSON
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Save Progress for Resume Capability
    if ($has_more) {
        update_option('mta_scan_progress', [
            'batch' => $batch,
            'total' => $query->max_num_pages,
            'time' => time()
        ]);
    } else {
        delete_option('mta_scan_progress');
        update_option('mta_last_audit_run', current_time('mysql'));
    }
    
    wp_send_json_success([
        'scanned' => $scanned,
        'batch' => $batch,
        'total_batches' => $query->max_num_pages,
        'has_more' => $has_more,
        'errors' => $errors
    ]);
}
// Helper to scan a single post (Optimized)
function mta_scan_single_post($post_id) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

    $permalink = get_permalink($post_id);
    
    // Dynamic URL Fix (Localhost vs Prod DB)
    $home = home_url();
    $p_parsed = parse_url($permalink);
    $h_parsed = parse_url($home);
    
    if (isset($p_parsed['host'], $h_parsed['host']) && $p_parsed['host'] !== $h_parsed['host']) {
        $url = $h_parsed['scheme'] . '://' . $h_parsed['host'];
        if (isset($h_parsed['port'])) $url .= ':' . $h_parsed['port'];
        $url .= $p_parsed['path'] ?? '/';
        if (isset($p_parsed['query'])) $url .= '?' . $p_parsed['query'];
    } else {
        $url = $permalink;
    }

    // Fetch only head (first 8KB for more meta data) for performance
    $response = wp_remote_get($url, [
        'timeout' => 15, 
        'sslverify' => false,
        'reject_unsafe_urls' => false,
        'headers' => ['Range' => 'bytes=0-8192'] 
    ]);

    if (is_wp_error($response)) {
        return; 
    }

    $html = wp_remote_retrieve_body($response);
    if (empty($html)) return;

    $issues = [];
    $expected_canonical = rtrim($permalink, '/');

    // 1. Robots / Noindex
    if (preg_match('/<meta name=["\']robots["\'] content=["\']([^"\']+)["\']/i', $html, $robots)) {
        if (stripos($robots[1], 'noindex') !== false) {
            $issues[] = ['type' => 'critical', 'text' => 'noindex detected'];
        }
    }

    // 2. Canonical
    preg_match_all('/<link[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $canonicals);
    $canonicals_found = $canonicals[1] ?? [];

    if (count($canonicals_found) === 0) {
        $issues[] = ['type' => 'critical', 'text' => 'missing canonical'];
    } elseif (count($canonicals_found) > 1) {
        $issues[] = ['type' => 'critical', 'text' => 'multiple canonicals'];
    } else {
        $actual = rtrim($canonicals_found[0], '/');
        if ($actual !== $expected_canonical) {
            $issues[] = ['type' => 'warning', 'text' => 'canonical mismatch: ' . $actual];
        }
    }

    // 3. Title Tag
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title_match)) {
        $title = trim($title_match[1]);
        $len = mb_strlen($title);
        if ($len < 30) {
            $issues[] = ['type' => 'info', 'text' => "Title too short ({$len} chars)"];
        } elseif ($len > 60) {
            $issues[] = ['type' => 'info', 'text' => "Title too long ({$len} chars)"];
        }
    } else {
        $issues[] = ['type' => 'warning', 'text' => 'Missing <title> tag'];
    }

    // 4. Meta Description
    if (preg_match('/<meta name=["\']description["\'] content=["\']([^"\']*)["\']/i', $html, $desc_match)) {
        $desc = trim($desc_match[1]);
        $len = mb_strlen($desc);
        if ($len < 120) {
            $issues[] = ['type' => 'info', 'text' => "Description too short ({$len} chars)"];
        } elseif ($len > 160) {
            $issues[] = ['type' => 'info', 'text' => "Description too long ({$len} chars)"];
        }
    } else {
        $issues[] = ['type' => 'warning', 'text' => 'Missing meta description'];
    }

    // 5. H1 Tags
    preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1_matches);
    $h1_count = count($h1_matches[0]);
    if ($h1_count === 0) {
        $issues[] = ['type' => 'warning', 'text' => 'Missing H1 tag'];
    } elseif ($h1_count > 1) {
        $issues[] = ['type' => 'warning', 'text' => "Multiple H1 tags detected ({$h1_count})"];
    }

    // 6. Open Graph
    if (!preg_match('/property=["\']og:title["\']/i', $html)) {
        $issues[] = ['type' => 'info', 'text' => 'Missing og:title'];
    }
    if (!preg_match('/property=["\']og:image["\']/i', $html)) {
        $issues[] = ['type' => 'info', 'text' => 'Missing og:image'];
    }

    // 7. Twitter Card
    if (!preg_match('/name=["\']twitter:card["\']/i', $html)) {
        $issues[] = ['type' => 'info', 'text' => 'Missing twitter:card'];
    }

    // Centralized Logging
    mta_log_issue($post_id, $issues);
}

function mta_batch_scan_all_pages() {
    if (function_exists('set_time_limit')) set_time_limit(0);

    $paged = 1;
    $batch_size = 50;

    do {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => $batch_size,
            'paged' => $paged,
            'fields' => 'ids'
        ];
        
        $query = new WP_Query($args);
        
        foreach ($query->posts as $post_id) {
             mta_scan_single_post($post_id);
        }
        
        $paged++;
        
    } while ($query->max_num_pages >= $paged);
}

