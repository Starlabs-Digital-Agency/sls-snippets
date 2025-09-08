<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** Adds a Help page under SLS Snippets menu. */
final class Help {
    private static $instance;
    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct(){
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu(){
        add_submenu_page(
            'edit.php?post_type=sls_snippet',
            __('SLS Snippets Help','sls-snippets'),
            __('Help','sls-snippets'),
            caps()['manage'],
            'sls-snippets-help',
            [$this, 'page']
        );
    }

    public function page(){
        if (!current_user_can(caps()['manage'])) wp_die('forbidden');
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('SLS Snippets — Help & Usage','sls-snippets') . '</h1>';
        echo '<p>' . esc_html__('Create code snippets with plain HTML, CSS, and JavaScript, then embed them via shortcode or block. This build removes experimental compilers (Pug/SCSS/TypeScript/JSX/TSX) for stability.','sls-snippets') . '</p>';

        echo '<h2>' . esc_html__('1) Create a Snippet','sls-snippets') . '</h2>';
        echo '<ol><li>' . esc_html__('Go to SLS Snippets → Add New.','sls-snippets') . '</li>';
        echo '<li>' . esc_html__('Use the three editors (HTML, CSS, JS). The language selectors are fixed to plain modes.','sls-snippets') . '</li>';
        echo '<li>' . esc_html__('Click Preview to render in a sandboxed iframe. Toggle “Auto-run” to update automatically.','sls-snippets') . '</li></ol>';

        echo '<h2>' . esc_html__('2) Embed on a Page/Post','sls-snippets') . '</h2>';
        echo '<p><code>[sls_snippet id="123" height="420" autorun="true"]</code></p>';
        echo '<p>' . esc_html__('Or add the “SLS Snippet” block in the editor and fill in the snippet ID and height.','sls-snippets') . '</p>';

        echo '<h2>' . esc_html__('3) External Libraries (Optional)','sls-snippets') . '</h2>';
        echo '<p>' . esc_html__('You may include external CSS/JS via the “Settings & Libraries” meta box using a JSON array, e.g.:','sls-snippets') . '</p>';
        echo '<pre>[\n  {"type":"css","url":"https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css","sri":"..."},\n  {"type":"js","url":"https://cdn.jsdelivr.net/npm/alpinejs","sri":"..."}\n]</pre>';
        echo '<p>' . esc_html__('Admin → SLS Snippets → Settings lets you restrict allowed domains and require SRI.','sls-snippets') . '</p>';

        echo '<h2>' . esc_html__('4) Troubleshooting','sls-snippets') . '</h2>';
        echo '<ul>';
        echo '<li>' . esc_html__('If preview does not update, ensure “Disable all embeds” is OFF in Settings.','sls-snippets') . '</li>';
        echo '<li>' . esc_html__('If external libraries are blocked, add their hostnames in “Allowed library domains” or remove the SRI requirement for testing.','sls-snippets') . '</li>';
        echo '<li>' . esc_html__('This version only supports plain HTML/CSS/JS. Re-save snippets that used Pug/SCSS/TS/JSX/TSX as plain code.','sls-snippets') . '</li>';
        echo '</ul>';
        echo '</div>';
    }
}
