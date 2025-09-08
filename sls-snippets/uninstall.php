<?php
/**
 * Uninstall routine for SLS Snippets.
 *
 * Default behavior: remove only plugin options.
 * To also delete all snippet posts and their meta, define in wp-config.php:
 *   define('SLS_SNIPPETS_DELETE_CONTENT', true);
 */

if ( ! defined('WP_UNINSTALL_PLUGIN') ) {
    exit;
}

// Remove plugin settings option
delete_option('sls_snippets_settings');

// Optionally purge all snippet posts + meta
if ( defined('SLS_SNIPPETS_DELETE_CONTENT') && SLS_SNIPPETS_DELETE_CONTENT ) {
    global $wpdb;
    $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='sls_snippet'");
    if ($ids) {
        foreach ($ids as $post_id) {
            wp_delete_post((int)$post_id, true); // force delete (no Trash)
        }
    }
}
