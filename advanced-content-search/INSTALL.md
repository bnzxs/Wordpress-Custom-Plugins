# Advanced Content Search - Installation Guide

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Admin-level permissions

## Installation Steps

1.  **Download** the plugin files.
2.  **Upload** the `advanced-content-search` directory to your `/wp-content/plugins/` folder.
3.  **Activate** the plugin via the **Plugins** menu in your WordPress dashboard.
4.  Navigate to the **Content Search** menu in your sidebar.

## Uninstallation

The plugin is "clean." Deleting it via the WordPress admin will:

- Deactivate the plugin.
- Clean up any temporary transients (`advanced_content_search_cache`).
- Remove the plugin files completely from the server.

## Troubleshooting

- **No Search Results**: Ensure you've selected at least one post type and search field.
- **Style Issues**: Clear your browser cache or any server-side caching (e.g., Autoptimize) to ensure the new `admin.css` is loaded.

---

**Last Updated**: January 2026
