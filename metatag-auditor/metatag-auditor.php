<?php
/**
 * Plugin Name: MetaTag Auditor
 * Plugin URI: https://github.com/bnzxs/Wordpress-Custom-Plugins/tree/main/metatag-auditor
 * Description: Advanced SEO auditor with a premium dashboard. Detects issues with titles, meta descriptions, H1 tags, canonicals, noindex, and social media tags (OG/Twitter). Features real-time batch scanning, health scoring, and data exports.
 * Version: 2.1.0
 * Author: Carlou Benedict Luchavez
 * Author URI: 
 *      - Github: https://github.com/bnzxs/
 *      - LinkedIn: https://www.linkedin.com/in/carlou-benedict-luchavez-2b38a9166/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path: /languages
 * Text Domain: metatag-auditor
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}


define('MTA_PATH', plugin_dir_path(__FILE__));

require_once plugin_dir_path(__FILE__) . 'includes/admin-ui.php';
require_once plugin_dir_path(__FILE__) . 'includes/scanner.php';
require_once plugin_dir_path(__FILE__) . 'includes/logger.php';

