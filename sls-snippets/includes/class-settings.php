<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** Admin settings: kill-switch, allowed domains, defaults. */
final class Settings {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct(){
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_init', [$this,'register_settings']);
    }

    public function menu(){
        add_submenu_page(
            'edit.php?post_type=sls_snippet',
            __('SLS Snippets Settings','sls-snippets'),
            __('Settings','sls-snippets'),
            caps()['manage'],
            'sls-snippets-settings',
            [$this,'page']
        );
    }

    public function register_settings(){
        register_setting('sls_snippets_settings', 'sls_snippets_settings', [$this,'sanitize']);

        add_settings_section('sls_main', __('General','sls-snippets'), function(){
            echo '<p>'.esc_html__('Global controls and security settings for SLS Snippets.','sls-snippets').'</p>';
        }, 'sls_snippets_settings');

        add_settings_field('kill_switch', __('Disable all embeds','sls-snippets'), function(){
            $opt = get_settings();
            echo '<label><input type="checkbox" name="sls_snippets_settings[kill_switch]" value="1" '.checked(!empty($opt['kill_switch']), true, false).' /> '.esc_html__('Temporarily disable all snippet rendering site-wide.','sls-snippets').'</label>';
        }, 'sls_snippets_settings', 'sls_main');

        add_settings_field('default_height', __('Default height (px)','sls-snippets'), function(){
            $opt = get_settings();
            echo '<input type="number" min="100" step="10" name="sls_snippets_settings[default_height]" value="'.esc_attr((int)$opt['default_height']).'" class="small-text" />';
        }, 'sls_snippets_settings', 'sls_main');

        add_settings_field('allowed_domains', __('Allowed library domains','sls-snippets'), function(){
            $opt = get_settings();
            $val = is_array($opt['allowed_domains']) ? implode("\n", $opt['allowed_domains']) : '';
            echo '<textarea name="sls_snippets_settings[allowed_domains]" rows="5" class="large-text code" placeholder="cdn.jsdelivr.net'."\n".'unpkg.com'."\n".'cdnjs.cloudflare.com">'.esc_textarea($val).'</textarea>';
            echo '<p class="description">'.esc_html__('One domain per line. Leave empty to allow any https domain.','sls-snippets').'</p>';
        }, 'sls_snippets_settings', 'sls_main');

        add_settings_field('require_sri', __('Require SRI for external libs','sls-snippets'), function(){
            $opt = get_settings();
            echo '<label><input type="checkbox" name="sls_snippets_settings[require_sri]" value="1" '.checked(!empty($opt['require_sri']), true, false).' /> '.esc_html__('When enabled, external <script> and <link> must include an integrity hash.','sls-snippets').'</label>';
        }, 'sls_snippets_settings', 'sls_main');
    }

    public function sanitize($input){
        $out = [
            'kill_switch'    => !empty($input['kill_switch']),
            'default_height' => isset($input['default_height']) ? max(100, (int)$input['default_height']) : 360,
            'require_sri'    => !empty($input['require_sri']),
            'allowed_domains'=> []
        ];
        if (!empty($input['allowed_domains'])){
            $lines = array_map('trim', explode("\n", (string)$input['allowed_domains']));
            $out['allowed_domains'] = array_values(array_filter($lines));
        }
        return $out;
    }

    public function page(){
        if (!current_user_can(caps()['manage'])) wp_die('forbidden');
        echo '<div class="wrap"><h1>'.esc_html__('SLS Snippets Settings','sls-snippets').'</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('sls_snippets_settings');
        do_settings_sections('sls_snippets_settings');
        submit_button();
        echo '</form></div>';
    }
}
