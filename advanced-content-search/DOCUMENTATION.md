# Advanced Content Search - Complete Documentation

## Executive Summary

**Advanced Content Search & Reporting** is a production-ready WordPress plugin that enables site administrators to search for exact phrases across posts, pages, and custom post types.

**Exact Phrase Matching** - "hello world" matches only that phrase, not "hello" and "world" separately.  
**Advanced Filtering** - Search by post type and specific fields (title/content).  
**AJAX-Powered** - Seamless search without page reloads.  
**Export Features** - CSV and Excel export of results.  
**Condensed Pagination** - Smart display logic for large result sets.  
**Enterprise Security** - Nonce verification, capability checks, prepared statements, output escaping.

---

## Quick Installation

1.  **Upload** `advanced-content-search` folder to `/wp-content/plugins/`.
2.  **Activate** from WordPress admin: Plugins → Installed Plugins → Activate.
3.  **Access** new "Content Search" menu item in the admin sidebar.
4.  **Search** - Enter a phrase and start finding content instantly.

---

## File Structure

- `advanced-content-search.php`: Main entry point.
- `admin/class-admin.php`: Admin interface and AJAX handlers.
- `includes/class-engine.php`: Core search logic.
- `assets/js/admin.js`: Frontend logic and result rendering.
- `assets/css/admin.css`: Admin styling.

---

## Features In-Depth

### 1. Exact Phrase Search

Unlike default WordPress search which tokenizes words, this plugin searches for the literal string provided.

- Input: `"how to install"`
- Result: Matches only posts containing that specific sequence of words.

### 2. Export Capabilities

Support for exporting all found results to:

- **CSV**: Best for data analysis and audits.
- **Excel**: Spreadsheet-ready format.

---

## Support & Contributing

The plugin follows WordPress coding standards and is designed for maximum compatibility with modern stacks (PHP 7.4+, WP 5.0+).

---

**Last Updated**: January 2026
