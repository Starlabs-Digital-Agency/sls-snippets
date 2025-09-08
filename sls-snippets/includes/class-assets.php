<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/**
 * Registers/enqueues admin + frontend assets and block assets.
 * - Admin: WordPress Code Editor (CodeMirror) for the 3 editors.
 * - Frontend: registered and enqueued only when the shortcode/block renders.
 */
final class Assets {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct(){
        add_action('admin_enqueue_scripts', [$this, 'admin']);
        add_action('wp_enqueue_scripts',    [$this, 'frontend_register']);
        add_action('init',                  [$this, 'register_block_assets']);
    }

    /** Enqueue admin editor assets on our CPT screen. */
    public function admin($hook){
        global $typenow, $post_type;
        $type = $typenow ?: $post_type;
        if ($type !== 'sls_snippet') return;

        // Styles
        wp_enqueue_style('sls-snippets-admin', asset_url('assets/css/admin.css'), [], SLS_SNIPPETS_VERSION);

        // WP Code Editor (CodeMirror)
        $cm = [ 'codemirror' => [ 'indentUnit'=>2, 'tabSize'=>2, 'lineNumbers'=>true ] ];
        wp_enqueue_code_editor( array_merge($cm, ['type'=>'text/html']) );
        wp_enqueue_code_editor( array_merge($cm, ['type'=>'text/css'])  );
        wp_enqueue_code_editor( array_merge($cm, ['type'=>'text/javascript']) );
        wp_enqueue_script('code-editor');

        // Admin app
        wp_enqueue_script(
            'sls-snippets-admin',
            asset_url('assets/js/admin.js'),
            ['jquery','code-editor','underscore'],
            SLS_SNIPPETS_VERSION,
            true
        );

        $settings = get_settings();
        wp_localize_script('sls-snippets-admin', 'SLS_SNIPPETS_ADMIN', [
            'url'     => SLS_SNIPPETS_URL,
            'version' => SLS_SNIPPETS_VERSION,
            'i18n' => [
                'compiling' => __('Compiling…','sls-snippets'),
                'ready'     => __('Ready','sls-snippets'),
                'error'     => __('Error','sls-snippets'),
            ],
            'defaults' => [ 'height' => (int) $settings['default_height'] ],
        ]);
    }

    /** Register (not enqueue) frontend assets; enqueue during render. */
    public function frontend_register(){
        wp_register_style('sls-snippets-frontend', asset_url('assets/css/frontend.css'), [], SLS_SNIPPETS_VERSION);
        wp_register_script('sls-snippets-frontend', asset_url('assets/js/frontend.js'), [], SLS_SNIPPETS_VERSION, true);
        wp_localize_script('sls-snippets-frontend', 'SLS_SNIPPETS_FRONTEND', [
            'url' => SLS_SNIPPETS_URL,
            ]);
    }

    /** Register block handles referenced by block.json */
    public function register_block_assets(){
        wp_register_script(
            'sls-snippets-block-edit',
            asset_url('blocks/sls-snippet/edit.js'),
            ['wp-blocks','wp-element','wp-components','wp-block-editor','wp-editor'],
            SLS_SNIPPETS_VERSION,
            true
        );
        wp_register_style(
            'sls-snippets-block-style',
            asset_url('blocks/sls-snippet/style.css'),
            [],
            SLS_SNIPPETS_VERSION
        );
    }
}
