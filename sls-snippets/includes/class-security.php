<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** CSP, sandbox, and library validation helpers. */
final class Security {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct(){}

    /** Build CSP meta content string (filterable). */
    public static function csp(array $options = []): string {
        $allow_eval = !empty($options['allowEval']);
        $script = "'unsafe-inline' https:" . ($allow_eval ? " 'unsafe-eval'" : '');
        $style  = "'unsafe-inline' https:";
        $parts = [
            "default-src 'none'",
            "script-src $script",
            "style-src $style",
            "img-src data: https:",
            "font-src https:",
            "connect-src https:",
        ];
        $csp = implode('; ', $parts);
        return apply_filters('sls_snippets_csp', $csp, $options);
    }

    /** Sandbox flags for iframe (filterable). */
    public static function sandbox(array $options = []): string {
        $flags = ['allow-scripts','allow-modals','allow-pointer-lock','allow-popups-to-escape-sandbox'];
        if (!empty($options['allowForms']))      $flags[] = 'allow-forms';
        if (!empty($options['allowSameOrigin'])) $flags[] = 'allow-same-origin';
        $sandbox = implode(' ', $flags);
        return apply_filters('sls_snippets_iframe_sandbox', $sandbox, $options);
    }

    /** Polyfill: ends-with for PHP 7.4 compatibility (avoid PHP 8's str_ends_with). */
    private static function ends_with(string $haystack, string $needle): bool {
        $nlen = strlen($needle);
        if ($nlen === 0) return true;
        return substr($haystack, -$nlen) === $needle;
    }

    /** Validate a library entry based on settings; return sanitized or null. */
    public static function sanitize_library($lib, array $allowed_domains = [], bool $require_sri = false){
        if (!is_array($lib)) return null;
        $type = isset($lib['type']) && in_array($lib['type'], ['css','js'], true) ? $lib['type'] : null;
        $url  = isset($lib['url']) ? esc_url_raw($lib['url']) : '';
        if (!$type || !$url) return null;

        $p = wp_parse_url($url);
        if (empty($p['scheme']) || strtolower($p['scheme']) !== 'https') return null; // https only
        if (!empty($p['host']) && !empty($allowed_domains)){
            $host = strtolower($p['host']);
            $ok = false;
            foreach ($allowed_domains as $d){
                $d = strtolower(trim($d)); if ($d==='') continue;
                if ($host === $d || self::ends_with($host, '.' . $d)) { $ok = true; break; }
            }
            if (!$ok) return null;
        }
        $out = ['type'=>$type, 'url'=>$url];
        if (!empty($lib['sri'])){
            $sri = preg_replace('/[^A-Za-z0-9+\/=\-_:]/','', (string)$lib['sri']);
            if ($sri) $out['sri'] = $sri;
        } elseif ($require_sri) {
            return null; // required but missing
        }
        return $out;
    }
}
