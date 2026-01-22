# Advanced Content Search & Reporting Plugin

## Overview

The **Advanced Content Search & Reporting** plugin is a comprehensive WordPress solution for site administrators to search, analyze, and report on website content. It provides:

- **Exact Phrase Searching**: Search for specific phrases (not tokenized keywords) across posts, pages, and custom post types.
- **Advanced Filtering**: Filter by post type and search fields (title/content).
- **Pagination Support**: Efficiently handle large result sets with a condensed pagination UI.
- **Export Functionality**: Download search results as CSV or Excel files.
- **AJAX-Powered**: Seamless search without page reloads.
- **Security-First**: Comprehensive nonce verification, capability checks, and input sanitization.
- **Performance Optimized**: Direct SQL queries with proper escaping for maximum efficiency.

## Plugin Architecture

### File Structure

```
advanced-content-search/
├── advanced-content-search.php      # Main plugin file
├── uninstall.php                    # Uninstall hook
├── README.md                        # Primary Documentation
├── DOCUMENTATION.md                 # Detailed User Guide
├── TECHNICAL.md                     # Technical Implementation Details
├── INSTALL.md                       # Installation Instructions
├── admin/
│   ├── class-admin.php              # Admin menu and page rendering
│   └── diagnostic-ajax.php          # Diagnostic tools (Optional)
├── includes/
│   ├── class-engine.php             # Core search logic
│   └── class-diagnostic.php         # Diagnostic logic (Optional)
└── assets/
    ├── js/
    │   └── admin.js                 # AJAX and UI logic
    └── css/
        └── admin.css                # Admin page styling
```

### Core Components

#### 1. Main Plugin File (`advanced-content-search.php`)

- Plugin header with metadata.
- Autoloader for class loading.
- Initialization and lifecycle hooks.

#### 2. Admin Class (`admin/class-admin.php`)

- Registers admin menu item.
- Enqueues scripts and styles.
- Handles AJAX requests and exports.
- Renders the search interface.

#### 3. Search Engine Class (`includes/class-engine.php`)

- Core search logic with exact phrase matching.
- Uses `$wpdb` for direct, performant SQL queries.
- Implements the pagination calculations and filtering.

#### 4. JavaScript (`assets/js/admin.js`)

- Handles the AJAX search cycle and result rendering.
- Manages the condensed pagination UI.
- Triggers file downloads for exports.

#### 5. CSS (`assets/css/admin.css`)

- Responsive styling for the admin interface.
- Includes specific rules for the results table and pagination.

## Technical Implementation Details

### Why `$wpdb` Instead of `WP_Query`?

The plugin uses `$wpdb->prepare()` and direct SQL queries instead of `WP_Query` for:

1.  **Exact Phrase Matching**: `WP_Query` tokenizes search terms by default. We need a literal `LIKE` match.
2.  **Performance**: Direct SQL avoids the overhead of instantiating full `WP_Post` objects until needed.
3.  **SQL Control**: Precise control over the `WHERE` clause for complex phrase matching.

### Security Measures

- **Nonce Verification**: All AJAX actions are protected by time-limited nonces.
- **Capability Checks**: Access is restricted to users with `manage_options` (Administrators).
- **Prepared Statements**: All database interactions use `$wpdb->prepare` to prevent SQL injection.
- **Input Sanitization**: All user inputs are sanitized using `sanitize_text_field` or `absint`.
- **Output Escaping**: All dynamic content is escaped using `esc_html`, `esc_attr`, etc.

## Documentation index

For more detailed information, please refer to:

- [INSTALL.md](INSTALL.md): Detailed installation and setup guide.
- [DOCUMENTATION.md](DOCUMENTATION.md): Full user manual and usage examples.
- [TECHNICAL.md](TECHNICAL.md): Deep dive into the architecture, security, and performance.

---

**Last Updated**: January 2026
