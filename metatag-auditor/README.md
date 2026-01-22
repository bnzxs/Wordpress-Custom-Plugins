# MetaTag Auditor

A WordPress plugin that automatically detects and reports SEO meta tag issues across your entire website, including noindex tags and canonical URL problems.

## Overview

MetaTag Auditor helps you maintain proper SEO by scanning all published posts and pages for common meta tag issues that could hurt your search engine rankings. It identifies problems with robots meta tags and canonical URLs, then presents them in an easy-to-review dashboard.

## Features

- **Comprehensive Scanning**: Audits all published posts and pages for meta tag issues
- **Issue Detection**:
  - Noindex tags (pages blocked from search engines)
  - Missing canonical URLs
  - Canonical URL mismatches
  - Multiple canonical tags (invalid configuration)
- **Smart Filtering**: Filter results by issue type or post type (posts/pages)
- **Export Options**:
  - Export affected post IDs as `.txt` file
  - Export full audit report as `.csv` file
  - UTF-8 support for international characters (Japanese, Chinese, etc.)
- **Audit History**: Tracks when issues were detected with timestamps
- **Smart Auditing**:
  - **AJAX-Powered**: Scans happen in the background without reloading the page
  - **Resume Capability**: Automatically saves progress so you can resume interrupted scans
  - **Timeout Protection**: Uses ultra-small batches (2 posts) to work on any hosting environment

## Installation

### Method 1: Manual Upload

1. Download or clone this plugin to your WordPress plugins directory:

   ```
   wp-content/plugins/metatag-auditor/
   ```

2. Log in to your WordPress admin dashboard

3. Navigate to **Plugins** → **Installed Plugins**

4. Find "MetaTag Auditor" and click **Activate**

### Method 2: Direct Installation

1. Upload the `metatag-auditor` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress

## How to Use

### Running Your First Audit

1. Go to **Tools** → **MetaTag Auditor** in your WordPress admin dashboard

2. Click the **"Run Audit Now"** button to scan your entire site

3. Wait for the scan to complete. You can see the live progress bar.

4. If the scan stops or you close the page, you can come back and click **"Resume Audit"** to continue where you left off.

### Understanding the Results

The audit results table shows:

| Column           | Description                                |
| ---------------- | ------------------------------------------ |
| **ID**           | WordPress post/page ID                     |
| **Post**         | Title of the post/page (clickable to edit) |
| **URL**          | Public URL of the page (clickable to view) |
| **Issues**       | Detected problems with meta tags           |
| **Last Checked** | Timestamp of when this issue was detected  |

### Issue Types Explained

#### Noindex Detected

```html
<meta name="robots" content="noindex" />
```

**Problem**: This page is blocked from search engine indexing  
**Impact**: The page won't appear in Google/Bing search results  
**Action**: Remove the noindex tag if you want the page indexed

#### Missing Canonical

**Problem**: No canonical URL tag found  
**Impact**: Search engines may have difficulty determining the preferred URL  
**Action**: Add a canonical tag to specify the authoritative URL

#### Canonical Mismatch

```html
<link rel="canonical" href="https://example.com/wrong-url/" />
```

**Problem**: The canonical URL doesn't match the actual page URL  
**Impact**: May confuse search engines about which URL to index  
**Action**: Update the canonical tag to match the correct URL

#### Multiple Canonicals

**Problem**: More than one canonical tag found on the page  
**Impact**: Invalid HTML; search engines may ignore all canonical tags  
**Action**: Remove duplicate canonical tags, keep only one

### Filtering Results

Use the dropdown filters to narrow down results:

- **Issue Type Filter**: Show only specific issues (noindex, missing canonical, etc.)
- **Post Type Filter**: Show only posts or only pages

Click **Filter** to apply your selections.

### Exporting Data

#### Export Post IDs

- Click **"Export Post IDs"** to download a `.txt` file
- Contains comma-separated list of affected post IDs
- Filename format: `post_ids_[site]_[date].txt`
- Useful for bulk operations or external tools

#### Export to CSV

- Click **"Export to CSV"** to download a full report
- Includes: ID, Title, URL, Issue Type, Issue Details, Audit Date
- Filename format: `meta_audit_[site]_[date].csv`
- UTF-8 encoded (preserves special characters)
- Perfect for sharing with team or importing to spreadsheets

## Technical Details

### What Gets Scanned

- **Post Types**: Posts and Pages (published only)
- **Batch Size**: 2 posts per scan cycle (Safe Mode)
- **Detection Method**: Fetches actual HTML output (Head only, first 4KB) and parses meta tags
- **Storage**: Issues logged in post meta (`_mta_issues`)

### System Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- `wp_remote_get()` function enabled (for fetching page HTML)

### File Structure

```
metatag-auditor/
├── metatag-auditor.php    # Main plugin file
├── includes/
│   ├── admin-ui.php       # Admin dashboard and UI
│   ├── scanner.php        # Core scanning logic
│   └── logger.php         # Issue logging functions
└── README.md              # This file
```

## Troubleshooting

### Audit button doesn't work

- Check that you have "manage_options" capability (Administrator role)
- Ensure your server allows `wp_remote_get()` requests to your own domain
- Check for JavaScript errors in browser console

### No issues found but I know there are problems

- Verify the pages are published (not drafts)
- Check if caching is preventing fresh HTML from being fetched
- Ensure canonical tags are in the `<head>` section of your HTML

### Export files are empty

- Make sure you've run an audit first
- Check that issues were actually detected
- Verify you have write permissions

### Special characters appear garbled in CSV

- The CSV export includes UTF-8 BOM for proper encoding
- Open the file in Excel/Google Sheets (not Notepad)
- If still garbled, try importing as UTF-8 explicitly

## Changelog

### Version 0.4

- Re-implemented AJAX Scanner for smoother interface
- Added "Resume Capability" to continue interrupted scans
- Reduced batch size to 2 for maximum server compatibility
- Restored "Canonical Mismatch" check
- Fixed fatal errors on resume

### Version 0.3

- Fixed variable scope issue in scanner pagination
- Improved batch scanning logic
- Added UTF-8 BOM for CSV exports

### Version 0.2

- Added CSV export functionality
- Added post ID export
- Implemented filtering by issue type and post type

### Version 0.1

- Initial release
- Basic scanning and detection
- Admin dashboard

## Author

**Carlou**

## License

This plugin is provided as-is for WordPress installations.

## Contributing

Found a bug or have a feature request? Feel free to modify and improve this plugin for your needs.

---

**Need Help?** Check the WordPress admin dashboard under **Tools** → **MetaTag Auditor** to get started!
