<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** Return plugin URL for a relative asset path. */
function asset_url(string $rel): string {
    return trailingslashit(\SLS_SNIPPETS_URL) . ltrim($rel, '/');
}

/** Safe JSON encode (readable + unicode friendly). */
function json_encode_safe($data): string {
    return wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** Capability map (filterable). */
function caps(): array {
    $caps = [
        'manage' => 'manage_options', // manage settings
        'edit'   => 'edit_posts',     // create/edit snippets
    ];
    return apply_filters('sls_snippets_capabilities', $caps);
}

/** Fetch plugin settings with defaults. */
function get_settings(): array {
    $defaults = [
        'kill_switch'     => false,
        'allowed_domains' => [],
        'require_sri'     => false,
        'default_height'  => 360,
    ];
    $opt = get_option('sls_snippets_settings');
    $arr = is_array($opt) ? $opt : [];
    if (!empty($arr['allowed_domains']) && is_string($arr['allowed_domains'])){
        $arr['allowed_domains'] = array_values(array_filter(array_map('trim', explode("\n", $arr['allowed_domains']))));
    }
    return wp_parse_args($arr, $defaults);
}
