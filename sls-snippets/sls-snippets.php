<?php
/**
 * Plugin Name: SLS Snippets
 * Description: CodePen-style snippets with HTML/CSS/JS editors, optional Pug/Babel/SCSS transpilers (client-side), and secure shortcode/block embeds.
 * Version: 1.0.3
 * Author: Starlabs
 * Text Domain: sls-snippets
 */

if ( ! defined('ABSPATH') ) exit; // Safety: block direct access

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
define('SLS_SNIPPETS_VERSION', '1.0.3');
define('SLS_SNIPPETS_FILE', __FILE__);
define('SLS_SNIPPETS_PATH', plugin_dir_path(__FILE__));
define('SLS_SNIPPETS_URL',  plugin_dir_url(__FILE__));

// -----------------------------------------------------------------------------
// Autoload (minimal, PSR-4-like) for SLS\Snippets\* classes
// -----------------------------------------------------------------------------
spl_autoload_register(function($class){
    if (strpos($class, 'SLS\\Snippets\\') !== 0) return;
    $rel = str_replace('SLS\\Snippets\\', '', $class);
    $rel = strtolower(str_replace(['\\', '_'], ['/', '-'], $rel));
    $file = SLS_SNIPPETS_PATH . 'includes/class-' . basename($rel) . '.php';
    if (is_readable($file)) require_once $file;
});

// Helpers
require_once SLS_SNIPPETS_PATH . 'includes/helpers.php';

// -----------------------------------------------------------------------------
// Boot
// -----------------------------------------------------------------------------
add_action('plugins_loaded', function(){
    load_plugin_textdomain('sls-snippets');

    SLS\Snippets\Assets::instance();
    SLS\Snippets\Settings::instance();
    SLS\Snippets\Cpt::instance();
    SLS\Snippets\Admin_Ui::instance();
    SLS\Snippets\Security::instance();
    SLS\Snippets\Render::instance();
    SLS\Snippets\Help::instance();
});

// -----------------------------------------------------------------------------
// Activation / Deactivation
// -----------------------------------------------------------------------------
register_activation_hook(__FILE__, function(){
    SLS\Snippets\Cpt::instance(); // ensure CPT exists
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});
