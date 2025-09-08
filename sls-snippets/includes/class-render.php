<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** Shortcode + Block rendering. */
final class Render {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct(){
        add_shortcode('sls_snippet', [$this,'shortcode']);
        add_action('init', [$this,'register_block']);
    }

    /**
     * Shortcode renderer. Outputs a container div which the frontend script
     * hydrates into a sandboxed iframe using compiled cache or via workers.
     */
    public function shortcode($atts = []): string {
        $settings = get_settings();
        if (!empty($settings['kill_switch'])){
            return '<div class="sls-embed__fallback">'.esc_html__('Snippets are temporarily disabled by an administrator.','sls-snippets').'</div>';
        }

        $a = shortcode_atts([
            'id' => 0,
            'height' => '',
            'autorun' => 'true',
        ], $atts, 'sls_snippet');
        $id = (int)$a['id'];
        if (!$id) return '';

        // Enqueue frontend assets only when used
        wp_enqueue_style('sls-snippets-frontend');
        wp_enqueue_script('sls-snippets-frontend');

        // Data payload
        $meta_settings = json_decode(get_post_meta($id,'sls_settings',true) ?: '{}', true) ?: [];
        if ($a['height'] !== '') $meta_settings['height'] = max(100, (int)$a['height']);
        if (empty($meta_settings['height'])) $meta_settings['height'] = (int)$settings['default_height'];
        $meta_settings['autorun'] = filter_var($a['autorun'], FILTER_VALIDATE_BOOLEAN);

        $langs = [
            'html' => get_post_meta($id,'sls_lang_html',true) ?: 'html',
            'css'  => get_post_meta($id,'sls_lang_css', true) ?: 'css',
            'js'   => get_post_meta($id,'sls_lang_js',  true) ?: 'js',
        ];
        // Enforce supported languages only
        $langs['html'] = 'html';
        $langs['css']  = 'css';
        $langs['js']   = 'js';

        $sources = [
            'html' => get_post_meta($id,'sls_html',true) ?: '',
            'css'  => get_post_meta($id,'sls_css', true) ?: '',
            'js'   => get_post_meta($id,'sls_js',  true) ?: '',
        ];

        // Validate external libraries according to settings
        $libs_raw = json_decode(get_post_meta($id,'sls_libraries',true) ?: '[]', true) ?: [];
        $libs = [];
        foreach ($libs_raw as $lib){
            $ok = Security::sanitize_library($lib, (array)$settings['allowed_domains'], (bool)$settings['require_sri']);
            if ($ok) $libs[] = $ok;
        }

        // Optional compiled cache from admin preview
        $cache = json_decode(get_post_meta($id,'sls_cache',true) ?: '{}', true) ?: [];

        $data = [
            'id'        => $id,
            'settings'  => $meta_settings,
            'languages' => $langs,
            'sources'   => $sources,
            'libraries' => $libs,
            'cache'     => $cache,
        ];

        $attr = [
            'class'   => 'sls-embed',
            'data-sls'=> esc_attr( json_encode_safe($data) ),
            'style'   => 'height:'.(int)$meta_settings['height'].'px'
        ];
        $attrs = '';
        foreach ($attr as $k=>$v){ if ($v==='') continue; $attrs .= ' '.$k.'="'.$v.'"'; }

        return '<div'.$attrs.'><div class="sls-embed__fallback">'.esc_html__('','sls-snippets').'</div></div>';
    }

    /** Register block using block.json; delegate to the shortcode renderer. */
    public function register_block(){
        if (!function_exists('register_block_type')) return;
        register_block_type( SLS_SNIPPETS_PATH . 'blocks/sls-snippet', [
            'render_callback' => function($attributes){
                $atts = [
                    'id' => $attributes['id'] ?? 0,
                    'height' => $attributes['height'] ?? '',
                    'autorun' => isset($attributes['autorun']) ? ($attributes['autorun'] ? 'true':'false') : 'true',
                ];
                return $this->shortcode($atts);
            }
        ]);
    }
}
