# Advanced Content Search - Technical Documentation

## Search Engine Logic

The plugin uses the `Advanced_Content_Search_Engine` class to perform literal searches.

### Direct SQL Over WP_Query

We use `$wpdb` because `WP_Query` does not support literal phrase matching without significant filtering.

```sql
SELECT ID, post_title, post_content, post_type
FROM wp_posts
WHERE (post_title LIKE '%search%' OR post_content LIKE '%search%')
  AND post_type IN ('post', 'page')
  AND post_status = 'publish'
ORDER BY post_date DESC
LIMIT 20 OFFSET 0
```

### Security Layer

- **Sanitization**: All inputs are sanitized using `sanitize_text_field` or `absint`.
- **Prepared Statements**: All SQL queries use `$wpdb->prepare` with `%s` and `%d` placeholders to eliminate SQL injection risks.
- **CSRF Protection**: Nonces are verified for every AJAX call (`advanced_content_search_nonce`).
- **Authorization**: Only users with the `manage_options` capability can execute searches or exports.

## Frontend Infrastructure

- **Namespacing**: All CSS classes and JS IDs use the `acs-` prefix (e.g., `#acs-pagination`) to prevent style collisions with other plugins.
- **AJAX Actions**:
  - `advanced_search_content`
  - `advanced_export_csv`
  - `advanced_export_xlsx`

## Performance

For sites with extremely large datasets (100k+ posts), the plugin performs `LIKE` operations with moderate overhead. Future versions may implement FULLTEXT indexing options for even faster lookups.

---

**Last Updated**: January 2026
