<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** Registers CPT + meta; persists fields on save. */
final class Cpt {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct(){
        add_action('init', [$this, 'register']);
        add_action('save_post_sls_snippet', [$this, 'save'], 10, 2);
    }

    public function register(){
        $labels = [
            'name' => __('SLS Snippets','sls-snippets'),
            'singular_name'      => __('Snippet','sls-snippets'),
            'menu_name'          => __('SLS Snippets','sls-snippets'),
            'add_new'            => __('Add New','sls-snippets'),
            'add_new_item'       => __('Add New Snippet','sls-snippets'),
            'edit_item'          => __('Edit Snippet','sls-snippets'),
            'new_item'           => __('New Snippet','sls-snippets'),
            'view_item'          => __('View Snippet','sls-snippets'),
            'search_items'       => __('Search Snippets','sls-snippets'),
            'not_found'          => __('No snippets found','sls-snippets'),
            'not_found_in_trash' => __('No snippets found in Trash','sls-snippets'),
        ];
        register_post_type('sls_snippet', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-editor-code',
            'supports' => ['title','revisions','author'],
            'show_in_rest' => true,
        ]);

        // REST-exposed meta for the three panes + settings/libs/cache
        $metas = [
            'sls_html'       => 'string',
            'sls_css'        => 'string',
            'sls_js'         => 'string',
            'sls_lang_html'  => 'string',
            'sls_lang_css'   => 'string',
            'sls_lang_js'    => 'string',
            'sls_libraries'  => 'string', // JSON array string
            'sls_settings'   => 'string', // JSON object string
            'sls_cache'      => 'string', // JSON object string (compiled)
        ];
        foreach ($metas as $key => $type){
            register_post_meta('sls_snippet', $key, [
                'type' => $type,
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => function($v){ return is_string($v) ? $v : ''; }
            ]);
        }
    }

    public function save($post_id, $post){
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can(caps()['edit'], $post_id)) return;
        if (!isset($_POST['sls_snippets_nonce']) || !wp_verify_nonce($_POST['sls_snippets_nonce'], 'sls_snippets_save')) return;

        $fields = ['sls_html','sls_css','sls_js','sls_lang_html','sls_lang_css','sls_lang_js','sls_libraries','sls_settings','sls_cache'];
        foreach ($fields as $f){
            if (isset($_POST[$f])) update_post_meta($post_id, $f, wp_unslash($_POST[$f]));
        }
    }
}
