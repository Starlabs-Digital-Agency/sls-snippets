<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** Admin metabox UI: 3 editors + preview + settings. */
final class Admin_Ui {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct(){
        add_action('add_meta_boxes', [$this,'metaboxes']);
    }

    public function metaboxes(){
        add_meta_box('sls_editors', __('SLS Editors','sls-snippets'), [$this,'box_editors'], 'sls_snippet', 'normal', 'high');
        add_meta_box('sls_preview', __('Preview','sls-snippets'), [$this,'box_preview'], 'sls_snippet', 'normal', 'default');
        add_meta_box('sls_settings', __('Settings & Libraries','sls-snippets'), [$this,'box_settings'], 'sls_snippet', 'side');
    }

    public function box_editors($post){
        if (!current_user_can(caps()['edit'], $post->ID)) wp_die('forbidden');
        wp_nonce_field('sls_snippets_save','sls_snippets_nonce');
        $html = get_post_meta($post->ID, 'sls_html', true);
        $css  = get_post_meta($post->ID, 'sls_css',  true);
        $js   = get_post_meta($post->ID, 'sls_js',   true);
        $lh   = get_post_meta($post->ID, 'sls_lang_html', true) ?: 'html';
        $lc   = get_post_meta($post->ID, 'sls_lang_css',  true) ?: 'css';
        $lj   = get_post_meta($post->ID, 'sls_lang_js',   true) ?: 'js';
        // Clamp unsupported languages to plain modes
        if ($lh !== 'html') $lh = 'html';
        if ($lc !== 'css')  $lc = 'css';
        if ($lj !== 'js')   $lj = 'js';
        ?>
        <div class="sls-grid">
            <div class="sls-pane">
                <label for="sls_lang_html" class="sls-label"><?php _e('HTML','sls-snippets'); ?></label>
                <select id="sls_lang_html" name="sls_lang_html" class="sls-select">
                    <option value="html" <?php selected($lh,'html'); ?>>HTML</option>
                    </select>
                <textarea id="sls_html" name="sls_html" rows="14" class="sls-code" data-lang="text/html"><?php echo esc_textarea($html); ?></textarea>
            </div>
            <div class="sls-pane">
                <label for="sls_lang_css" class="sls-label"><?php _e('CSS','sls-snippets'); ?></label>
                <select id="sls_lang_css" name="sls_lang_css" class="sls-select">
                    <option value="css"  <?php selected($lc,'css');  ?>>CSS</option>
                    </select>
                <textarea id="sls_css" name="sls_css" rows="14" class="sls-code" data-lang="text/css"><?php echo esc_textarea($css); ?></textarea>
            </div>
            <div class="sls-pane">
                <label for="sls_lang_js" class="sls-label"><?php _e('JavaScript','sls-snippets'); ?></label>
                <select id="sls_lang_js" name="sls_lang_js" class="sls-select">
                    <option value="js"  <?php selected($lj,'js');  ?>>JS</option>
                    </select>
                <textarea id="sls_js" name="sls_js" rows="14" class="sls-code" data-lang="text/javascript"><?php echo esc_textarea($js); ?></textarea>
            </div>
        </div>
        <?php
    }

    public function box_preview($post){
        $settings = json_decode(get_post_meta($post->ID,'sls_settings',true) ?: '{}', true) ?: [];
        $height = (int) ($settings['height'] ?? get_settings()['default_height']);
        echo '<div id="sls-preview-wrap" style="height:'.esc_attr($height).'px">'
           . '<div class="sls-preview-toolbar">'
           . '<button type="button" class="button button-primary" id="sls-btn-preview">'.esc_html__('Preview','sls-snippets').'</button> '
           . '<label><input type="checkbox" id="sls-auto-run"> '.esc_html__('Auto-run','sls-snippets').'</label>'
           . '<span class="sls-status" id="sls-status"></span>'
           . '</div>'
           . '<iframe id="sls-preview" title="Snippet preview" sandbox="'.esc_attr(Security::sandbox()).'"></iframe>'
           . '</div>';
        echo '<input type="hidden" id="sls_cache" name="sls_cache" value="'.esc_attr(get_post_meta($post->ID,'sls_cache',true)).'">';
    }

    public function box_settings($post){
        $libraries = get_post_meta($post->ID,'sls_libraries',true);
        $settings  = get_post_meta($post->ID,'sls_settings',true);
        ?>
        <p><label class="sls-label"><?php _e('External Libraries (JSON array)','sls-snippets'); ?></label>
        <textarea name="sls_libraries" rows="6" class="widefat code" placeholder='[{"url":"https://cdn.example.com/lib.css","type":"css","sri":"sha384-..."}]'><?php echo esc_textarea($libraries); ?></textarea></p>
        <p><label class="sls-label"><?php _e('Settings (JSON)','sls-snippets'); ?></label>
        <textarea name="sls_settings" rows="6" class="widefat code" placeholder='{"autoRun":true, "height":360, "allowEval":false}'><?php echo esc_textarea($settings); ?></textarea></p>
        <p><?php _e('Shortcode:','sls-snippets'); ?> <code>[sls_snippet id="<?php echo (int)$post->ID; ?>"]</code></p>
        <?php
    }
}
