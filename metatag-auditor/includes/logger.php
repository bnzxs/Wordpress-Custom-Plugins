<?php
if (!defined('ABSPATH')) {
    exit;
}

function mta_log_issue($post_id, $issues) {
    if (empty($issues)) {
        delete_post_meta($post_id, '_mta_issues');
        return;
    }

    $log = [
        'time' => current_time('mysql'),
        'issues' => $issues
    ];

    $existing = get_post_meta($post_id, '_mta_issues', true);
    if (!is_array($existing)) $existing = [];

    // Prepend new log and slice to keep only the last 5
    array_unshift($existing, $log);
    $existing = array_slice($existing, 0, 5);

    update_post_meta($post_id, '_mta_issues', $existing);
}
