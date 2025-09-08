# SLS Snippets — MVP Codebase

> Folder: `sls-snippets/`

---

## sls-snippets.php (Plugin Bootstrap)

```php
<?php
/**
 * Plugin Name: SLS Snippets
 * Description: CodePen-style snippets with HTML/CSS/JS editors, client-side transpilers, and shortcode/block embeds.
 * Version: 1.0.0
 * Author: Starlabs
 * Text Domain: sls-snippets
 */

if ( ! defined('ABSPATH') ) exit;

// ---- Constants -------------------------------------------------------------
define('SLS_SNIPPETS_VERSION', '1.0.0');
define('SLS_SNIPPETS_FILE', __FILE__);
define('SLS_SNIPPETS_PATH', plugin_dir_path(__FILE__));
define('SLS_SNIPPETS_URL',  plugin_dir_url(__FILE__));

// ---- Autoload (very small) -------------------------------------------------
spl_autoload_register(function($class){
    // Namespace: SLS\Snippets\
    if (strpos($class, 'SLS\\Snippets\\') !== 0) return;
    $rel = strtolower(str_replace(['SLS\\Snippets\\', '_', '\\'], ['', '-', '/'], $class));
    $file = SLS_SNIPPETS_PATH . 'includes/class-' . basename($rel) . '.php';
    if (is_readable($file)) require_once $file;
});

// Helpers (functions file)
require_once SLS_SNIPPETS_PATH . 'includes/helpers.php';

// ---- Boot ------------------------------------------------------------------
add_action('plugins_loaded', function(){
    load_plugin_textdomain('sls-snippets');

    SLS\Snippets\Assets::instance();
    SLS\Snippets\Cpt::instance();
    SLS\Snippets\Admin_Ui::instance();
    SLS\Snippets\Render::instance();
    SLS\Snippets\Security::instance();
});

// ---- Activation / Deactivation --------------------------------------------
register_activation_hook(__FILE__, function(){
    // Flush CPT rewrite rules by registering once then flushing
    SLS\Snippets\Cpt::instance();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){
    flush_rewrite_rules();
});
```

---

## includes/helpers.php

```php
<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/** Return plugin url for an asset path (relative to plugin root). */
function asset_url(string $rel): string {
    return trailingslashit(\SLS_SNIPPETS_URL) . ltrim($rel, '/');
}

/** Safe JSON encode with defaults. */
function json(object|array|null $data): string {
    return wp_json_encode($data ?? new \stdClass(), JSON_UNESCAPED_SLASHES);
}

/** Capability map (filterable). */
function caps(): array {
    $caps = [
        'manage' => 'manage_options', // who can manage snippets plugin settings
        'edit'   => 'edit_posts',     // who can create/edit snippets
    ];
    return apply_filters('sls_snippets_capabilities', $caps);
}
```

---

## includes/class-assets.php

```php
<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/**
 * Enqueue admin + frontend assets.
 * Admin uses WP Code Editor (CodeMirror) for the 3 textareas.
 */
final class Assets {
    private static $instance; public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct(){
        add_action('admin_enqueue_scripts', [$this, 'admin']);
        add_action('wp_enqueue_scripts',    [$this, 'frontend_register']);
    }

    public function admin($hook){
        global $typenow;
        if ($typenow !== 'sls_snippet') return;

        // Admin styles
        wp_enqueue_style('sls-snippets-admin', asset_url('assets/css/admin.css'), [], SLS_SNIPPETS_VERSION);

        // Ask WP to load CodeMirror + settings for each editor
        $cm_settings = [ 'codemirror' => [ 'indentUnit' => 2, 'tabSize' => 2, 'lineNumbers' => true ] ];
        wp_enqueue_code_editor( array_merge($cm_settings, ['type'=>'text/html']) ); // HTML
        wp_enqueue_code_editor( array_merge($cm_settings, ['type'=>'text/css'])  ); // CSS
        wp_enqueue_code_editor( array_merge($cm_settings, ['type'=>'text/javascript']) ); // JS
        wp_enqueue_script('code-editor'); // ensures wp.codeEditor is present

        // Admin app
        wp_enqueue_script(
            'sls-snippets-admin',
            asset_url('assets/js/admin.js'),
            ['jquery','code-editor'],
            SLS_SNIPPETS_VERSION,
            true
        );

        // Localize config
        wp_localize_script('sls-snippets-admin', 'SLS_SNIPPETS_ADMIN', [
            'url' => SLS_SNIPPETS_URL,
            'version' => SLS_SNIPPETS_VERSION,
            'workers' => [
                'scss'  => asset_url('assets/js/worker-scss.js'),
                'pug'   => asset_url('assets/js/worker-pug.js'),
                'babel' => asset_url('assets/js/worker-babel.js'),
            ],
            'wasm' => [
                'sass' => asset_url('assets/wasm/sass.wasm'),
            ],
            'i18n' => [
                'compiling' => __('Compiling…','sls-snippets'),
                'ready'     => __('Ready','sls-snippets'),
                'error'     => __('Error','sls-snippets'),
            ]
        ]);
    }

    /** Register (not enqueue) frontend assets; actual enqueue happens on render. */
    public function frontend_register(){
        wp_register_style('sls-snippets-frontend', asset_url('assets/css/frontend.css'), [], SLS_SNIPPETS_VERSION);
        wp_register_script('sls-snippets-frontend', asset_url('assets/js/frontend.js'), [], SLS_SNIPPETS_VERSION, true);
        wp_localize_script('sls-snippets-frontend', 'SLS_SNIPPETS_FRONTEND', [
            'url' => SLS_SNIPPETS_URL,
            'workers' => [
                'scss'  => asset_url('assets/js/worker-scss.js'),
                'pug'   => asset_url('assets/js/worker-pug.js'),
                'babel' => asset_url('assets/js/worker-babel.js'),
            ],
            'wasm' => [ 'sass' => asset_url('assets/wasm/sass.wasm') ],
        ]);
    }
}
```

---

## includes/class-cpt.php

```php
<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

final class Cpt {
    private static $instance; public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct(){
        add_action('init', [$this, 'register']);
        add_action('save_post_sls_snippet', [$this, 'save'], 10, 2);
    }

    public function register(){
        $labels = [
            'name' => __('Snippets','sls-snippets'),
            'singular_name' => __('Snippet','sls-snippets'),
            'add_new' => __('Add New','sls-snippets'),
            'add_new_item' => __('Add New Snippet','sls-snippets'),
            'edit_item' => __('Edit Snippet','sls-snippets'),
            'new_item' => __('New Snippet','sls-snippets'),
            'view_item' => __('View Snippet','sls-snippets'),
            'search_items' => __('Search Snippets','sls-snippets'),
        ];
        register_post_type('sls_snippet', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-editor-code',
            'supports' => ['title','revisions','author'],
            'show_in_rest' => true,
        ]);

        // Register meta (exposed to REST)
        $metas = [
            'sls_html' => 'string', 'sls_css' => 'string', 'sls_js' => 'string',
            'sls_lang_html' => 'string', 'sls_lang_css' => 'string', 'sls_lang_js' => 'string',
            'sls_libraries' => 'string', // JSON stringified array
            'sls_settings'  => 'string',
            'sls_cache'     => 'string',
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
```

---

## includes/class-admin-ui.php

```php
<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

/**
 * Editor UI: three CodeMirror editors + settings + preview.
 */
final class Admin_Ui {
    private static $instance; public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct(){
        add_action('add_meta_boxes', [$this,'metaboxes']);
    }

    public function metaboxes(){
        add_meta_box('sls_editors', __('SLS Editors','sls-snippets'), [$this,'box_editors'], 'sls_snippet', 'normal', 'high');
        add_meta_box('sls_preview', __('Preview','sls-snippets'), [$this,'box_preview'], 'sls_snippet', 'normal', 'default');
        add_meta_box('sls_settings', __('Settings & Libraries','sls-snippets'), [$this,'box_settings'], 'sls_snippet', 'side');
    }

    public function box_editors($post){
        wp_nonce_field('sls_snippets_save','sls_snippets_nonce');
        $html = get_post_meta($post->ID, 'sls_html', true);
        $css  = get_post_meta($post->ID, 'sls_css',  true);
        $js   = get_post_meta($post->ID, 'sls_js',   true);
        $lh   = get_post_meta($post->ID, 'sls_lang_html', true) ?: 'html';
        $lc   = get_post_meta($post->ID, 'sls_lang_css',  true) ?: 'css';
        $lj   = get_post_meta($post->ID, 'sls_lang_js',   true) ?: 'js';
        ?>
        <div class="sls-grid">
            <div class="sls-pane">
                <label for="sls_lang_html" class="sls-label"><?php _e('HTML','sls-snippets'); ?></label>
                <select id="sls_lang_html" name="sls_lang_html" class="sls-select">
                    <option value="html" <?php selected($lh,'html'); ?>>HTML</option>
                    <option value="pug"  <?php selected($lh,'pug');  ?>>Pug</option>
                </select>
                <textarea id="sls_html" name="sls_html" rows="12" class="sls-code" data-lang="html"><?php echo esc_textarea($html); ?></textarea>
            </div>
            <div class="sls-pane">
                <label for="sls_lang_css" class="sls-label"><?php _e('CSS','sls-snippets'); ?></label>
                <select id="sls_lang_css" name="sls_lang_css" class="sls-select">
                    <option value="css"  <?php selected($lc,'css');  ?>>CSS</option>
                    <option value="scss" <?php selected($lc,'scss'); ?>>SCSS</option>
                </select>
                <textarea id="sls_css" name="sls_css" rows="12" class="sls-code" data-lang="css"><?php echo esc_textarea($css); ?></textarea>
            </div>
            <div class="sls-pane">
                <label for="sls_lang_js" class="sls-label"><?php _e('JavaScript','sls-snippets'); ?></label>
                <select id="sls_lang_js" name="sls_lang_js" class="sls-select">
                    <option value="js"  <?php selected($lj,'js');  ?>>JS</option>
                    <option value="ts"  <?php selected($lj,'ts');  ?>>TypeScript</option>
                    <option value="jsx" <?php selected($lj,'jsx'); ?>>JSX</option>
                    <option value="tsx" <?php selected($lj,'tsx'); ?>>TSX</option>
                </select>
                <textarea id="sls_js" name="sls_js" rows="12" class="sls-code" data-lang="javascript"><?php echo esc_textarea($js); ?></textarea>
            </div>
        </div>
        <?php
    }

    public function box_preview($post){
        $height = (int) ( json_decode(get_post_meta($post->ID,'sls_settings',true) ?: '{}', true)['height'] ?? 360 );
        echo '<div id="sls-preview-wrap" style="height:'.esc_attr($height).'px">'
           . '<div class="sls-preview-toolbar">'
           . '<button type="button" class="button button-primary" id="sls-btn-preview">'.esc_html__('Preview','sls-snippets').'</button> '
           . '<label><input type="checkbox" id="sls-auto-run"> '.esc_html__('Auto-run','sls-snippets').'</label>'
           . '<span class="sls-status" id="sls-status"></span>'
           . '</div>'
           . '<iframe id="sls-preview" title="Snippet preview" sandbox="allow-scripts allow-modals allow-pointer-lock allow-popups-to-escape-sandbox"></iframe>'
           . '</div>';
        // Hidden cache field (optional)
        echo '<input type="hidden" id="sls_cache" name="sls_cache" value="'.esc_attr(get_post_meta($post->ID,'sls_cache',true)).'">';
    }

    public function box_settings($post){
        $libraries = get_post_meta($post->ID,'sls_libraries',true);
        $settings  = get_post_meta($post->ID,'sls_settings',true);
        ?>
        <p><label class="sls-label"><?php _e('External Libraries (JSON array)','sls-snippets'); ?></label>
        <textarea name="sls_libraries" rows="6" class="widefat" placeholder='[{"url":"https://cdn.example.com/lib.css","type":"css"}]'><?php echo esc_textarea($libraries); ?></textarea></p>
        <p><label class="sls-label"><?php _e('Settings (JSON)','sls-snippets'); ?></label>
        <textarea name="sls_settings" rows="6" class="widefat" placeholder='{"autoRun":true, "height":360, "allowEval":false}'><?php echo esc_textarea($settings); ?></textarea></p>
        <p><?php _e('Shortcode:','sls-snippets'); ?> <code>[sls_snippet id="<?php echo (int)$post->ID; ?>"]</code></p>
        <?php
    }
}
```

---

## includes/class-security.php

```php
<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

final class Security {
    private static $instance; public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct(){}

    /** Build a per-iframe CSP meta tag content string. */
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
        return implode('; ', $parts);
    }

    /** Sandbox attribute value builder. */
    public static function sandbox(array $options = []): string {
        $flags = ['allow-scripts','allow-modals','allow-pointer-lock','allow-popups-to-escape-sandbox'];
        if (!empty($options['allowForms']))      $flags[] = 'allow-forms';
        if (!empty($options['allowSameOrigin'])) $flags[] = 'allow-same-origin';
        return implode(' ', $flags);
    }
}
```

---

## includes/class-render.php

```php
<?php
namespace SLS\Snippets;
if ( ! defined('ABSPATH') ) exit;

final class Render {
    private static $instance; public static function instance(): self { return self::$instance ??= new self(); }
    private function __construct(){
        add_shortcode('sls_snippet', [$this,'shortcode']);
        add_action('init', [$this,'register_block']);
    }

    public function shortcode($atts = []): string {
        $a = shortcode_atts([
            'id' => 0,
            'height' => '',
            'theme' => 'light',
            'autorun' => 'true',
        ], $atts, 'sls_snippet');
        $id = (int)$a['id'];
        if (!$id) return '';

        // Enqueue only when used
        wp_enqueue_style('sls-snippets-frontend');
        wp_enqueue_script('sls-snippets-frontend');

        $settings = json_decode(get_post_meta($id,'sls_settings',true) ?: '{}', true) ?: [];
        if ($a['height'] !== '') $settings['height'] = (int)$a['height'];
        $autorun = filter_var($a['autorun'], FILTER_VALIDATE_BOOLEAN);

        $data = [
            'id'        => $id,
            'settings'  => $settings,
            'languages' => [
                'html' => get_post_meta($id,'sls_lang_html',true) ?: 'html',
                'css'  => get_post_meta($id,'sls_lang_css', true) ?: 'css',
                'js'   => get_post_meta($id,'sls_lang_js',  true) ?: 'js',
            ],
            'sources'   => [
                'html' => get_post_meta($id,'sls_html',true) ?: '',
                'css'  => get_post_meta($id,'sls_css', true) ?: '',
                'js'   => get_post_meta($id,'sls_js',  true) ?: '',
            ],
            'libraries' => json_decode(get_post_meta($id,'sls_libraries',true) ?: '[]', true) ?: [],
        ];

        $attr = [
            'class' => 'sls-embed',
            'data-sls' => esc_attr( json($data) ),
            'style' => isset($settings['height']) ? 'height:'.(int)$settings['height'].'px' : ''
        ];
        $attrs = '';
        foreach ($attr as $k=>$v){ if ($v==='') continue; $attrs .= ' '.$k.'="'.$v.'"'; }

        $html = '<div'.$attrs.'>'
              . '<div class="sls-embed__fallback">'.esc_html__('Loading snippet…','sls-snippets').'</div>'
              . '</div>';
        return $html;
    }

    /** Register basic block wrapper that renders the shortcode server-side. */
    public function register_block(){
        if (!function_exists('register_block_type')) return;
        register_block_type('sls/snippet', [
            'render_callback' => function($attributes){
                $atts = [
                    'id' => $attributes['id'] ?? 0,
                    'height' => $attributes['height'] ?? '',
                    'autorun' => $attributes['autorun'] ?? 'true',
                ];
                return $this->shortcode($atts);
            },
            'attributes' => [
                'id'      => ['type'=>'integer'],
                'height'  => ['type'=>'string'],
                'autorun' => ['type'=>'boolean','default'=>true],
            ]
        ]);
    }
}
```

---

## assets/css/admin.css

```css
.sls-grid{ display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.sls-pane{ display:flex; flex-direction: column; }
.sls-label{ font-weight:600; margin-bottom:6px; display:block; }
.sls-select{ margin:0 0 8px; }
.sls-code{ width:100%; min-height:260px; }

#sls-preview-wrap{ border:1px solid #c3c4c7; background:#fff; margin-top:8px; position:relative; }
.sls-preview-toolbar{ position:absolute; top:8px; right:8px; display:flex; align-items:center; gap:10px; z-index:2; }
#sls-preview{ width:100%; height:100%; border:0; }
.sls-status{ font-size:12px; color:#666; }
```

---

## assets/css/frontend.css

```css
.sls-embed{ position:relative; width:100%; background:transparent; }
.sls-embed iframe{ position:absolute; inset:0; width:100%; height:100%; border:0; }
.sls-embed__fallback{ padding:12px; font-size:14px; color:#666; }
```

---

## assets/js/admin.js

```js
/* global wp, SLS_SNIPPETS_ADMIN */
(function(){
  if (!window.wp || !wp.codeEditor) return;

  // Initialize three editors against our textareas
  var editors = {};
  function initEditor(id, type){
    var el = document.getElementById(id);
    if (!el) return null;
    var settings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
    settings.codemirror = settings.codemirror || {};
    settings.codemirror.mode = type;
    return (editors[id] = wp.codeEditor.initialize(el, settings));
  }

  function getVal(id){
    var ed = editors[id];
    return ed && ed.codemirror ? ed.codemirror.getValue() : (document.getElementById(id)?.value || '');
  }

  function setStatus(text){
    var s = document.getElementById('sls-status');
    if (s) s.textContent = text || '';
  }

  function byId(id){ return document.getElementById(id); }

  function compileAll(langs, src){
    // Returns Promise<{ html, css, js }>
    setStatus(SLS_SNIPPETS_ADMIN.i18n.compiling);

    var tasks = [];

    // HTML / Pug
    tasks.push(new Promise(function(resolve){
      if (langs.html === 'pug'){
        var w = new Worker(SLS_SNIPPETS_ADMIN.workers.pug);
        w.onmessage = function(e){ resolve({ html: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: src.html });
      } else {
        resolve({ html: src.html, error: null });
      }
    }));

    // CSS / SCSS
    tasks.push(new Promise(function(resolve){
      if (langs.css === 'scss'){
        var w = new Worker(SLS_SNIPPETS_ADMIN.workers.scss);
        w.onmessage = function(e){ resolve({ css: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: src.css, wasmUrl: SLS_SNIPPETS_ADMIN.wasm.sass });
      } else {
        resolve({ css: src.css, error: null });
      }
    }));

    // JS / TS / JSX / TSX
    tasks.push(new Promise(function(resolve){
      if (langs.js !== 'js'){
        var w = new Worker(SLS_SNIPPETS_ADMIN.workers.babel);
        w.onmessage = function(e){ resolve({ js: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: src.js, lang: langs.js });
      } else {
        resolve({ js: src.js, error: null });
      }
    }));

    return Promise.all(tasks).then(function(parts){
      var out = { html:'', css:'', js:'' }, errors = [];
      parts.forEach(function(p){
        if (p.html !== undefined) out.html = p.html;
        if (p.css  !== undefined) out.css  = p.css;
        if (p.js   !== undefined) out.js   = p.js;
        if (p.error) errors.push(p.error);
      });
      return { out: out, errors: errors };
    });
  }

  function buildIframeSrcdoc(compiled, libs, settings){
    var csp = "<meta http-equiv=\"Content-Security-Policy\" content=\""+
      "default-src 'none'; script-src 'unsafe-inline' https:; style-src 'unsafe-inline' https:; img-src data: https:; font-src https:; connect-src https:;\">";

    var head = [csp];
    // CSS libs
    (libs||[]).filter(l=>l.type==='css').forEach(function(l){
      var attr = ' rel="stylesheet" href="'+ l.url.replace(/"/g,'&quot;') +'"';
      head.push('<link'+attr+'>');
    });
    // Inline CSS
    if (compiled.css) head.push('<style>'+compiled.css+'</style>');

    var body = [];
    if (compiled.html) body.push(compiled.html);

    // JS libs
    (libs||[]).filter(l=>l.type==='js').forEach(function(l){
      body.push('<script src="'+ l.url.replace(/"/g,'&quot;') +'"><\/script>');
    });
    // Inline JS
    if (compiled.js) body.push('<script>'+compiled.js+'\n<\/script>');

    return '<!doctype html><html><head>'+ head.join('') +'</head><body>'+ body.join('') +'</body></html>';
  }

  function doPreview(){
    var langs = {
      html: byId('sls_lang_html').value || 'html',
      css:  byId('sls_lang_css').value  || 'css',
      js:   byId('sls_lang_js').value   || 'js'
    };
    var src = {
      html: getVal('sls_html'),
      css:  getVal('sls_css'),
      js:   getVal('sls_js')
    };
    var libs = [];
    try{ libs = JSON.parse( (document.querySelector('[name="sls_libraries"]').value || '[]') ); }catch(e){ libs=[]; }
    var settings = {};
    try{ settings = JSON.parse( (document.querySelector('[name="sls_settings"]').value || '{}') ); }catch(e){ settings={}; }

    compileAll(langs, src).then(function(r){
      setStatus(r.errors.length ? SLS_SNIPPETS_ADMIN.i18n.error : SLS_SNIPPETS_ADMIN.i18n.ready);
      var iframe = byId('sls-preview');
      var html = buildIframeSrcdoc(r.out, libs, settings);
      iframe.setAttribute('sandbox','allow-scripts allow-modals allow-pointer-lock allow-popups-to-escape-sandbox');
      iframe.srcdoc = html;
      // Save compiled cache for optional server store
      byId('sls_cache').value = JSON.stringify(r.out);
    });
  }

  // Boot
  document.addEventListener('DOMContentLoaded', function(){
    initEditor('sls_html', 'text/html');
    initEditor('sls_css',  'text/css');
    initEditor('sls_js',   'text/javascript');

    var btn = byId('sls-btn-preview');
    if (btn) btn.addEventListener('click', doPreview);

    var auto = byId('sls-auto-run');
    if (auto) {
      var deb; ['sls_html','sls_css','sls_js','sls_lang_html','sls_lang_css','sls_lang_js'].forEach(function(id){
        var el = byId(id);
        if (!el) return;
        el.addEventListener('input', function(){
          if (!auto.checked) return;
          clearTimeout(deb); deb = setTimeout(doPreview, 500);
        });
        el.addEventListener('change', function(){ if (auto.checked) doPreview(); });
      });
    }
  });
})();
```

---

## assets/js/frontend.js

```js
/* global SLS_SNIPPETS_FRONTEND */
(function(){
  function compile(langs, src){
    // returns Promise<{html,css,js}>
    var tasks = [];

    tasks.push(new Promise(function(resolve){
      if (langs.html === 'pug'){
        var w = new Worker(SLS_SNIPPETS_FRONTEND.workers.pug);
        w.onmessage = function(e){ resolve({ html: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: src.html });
      } else { resolve({ html: src.html }); }
    }));

    tasks.push(new Promise(function(resolve){
      if (langs.css === 'scss'){
        var w = new Worker(SLS_SNIPPETS_FRONTEND.workers.scss);
        w.onmessage = function(e){ resolve({ css: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: src.css, wasmUrl: SLS_SNIPPETS_FRONTEND.wasm.sass });
      } else { resolve({ css: src.css }); }
    }));

    tasks.push(new Promise(function(resolve){
      if (langs.js !== 'js'){
        var w = new Worker(SLS_SNIPPETS_FRONTEND.workers.babel);
        w.onmessage = function(e){ resolve({ js: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: src.js, lang: langs.js });
      } else { resolve({ js: src.js }); }
    }));

    return Promise.all(tasks).then(function(parts){
      var out = { html:'', css:'', js:'' };
      parts.forEach(function(p){ if(p.html!==undefined) out.html=p.html; if(p.css!==undefined) out.css=p.css; if(p.js!==undefined) out.js=p.js; });
      return out;
    });
  }

  function buildSrcdoc(compiled, libs){
    var csp = "<meta http-equiv=\"Content-Security-Policy\" content=\""+
      "default-src 'none'; script-src 'unsafe-inline' https:; style-src 'unsafe-inline' https:; img-src data: https:; font-src https:; connect-src https:;\">";

    var head = [csp];
    (libs||[]).filter(l=>l.type==='css').forEach(function(l){ head.push('<link rel="stylesheet" href="'+l.url.replace(/"/g,'&quot;')+'">'); });
    if (compiled.css) head.push('<style>'+compiled.css+'</style>');

    var body = [];
    if (compiled.html) body.push(compiled.html);
    (libs||[]).filter(l=>l.type==='js').forEach(function(l){ body.push('<script src="'+l.url.replace(/"/g,'&quot;')+'"><\/script>'); });
    if (compiled.js) body.push('<script>'+compiled.js+'\n<\/script>');

    return '<!doctype html><html><head>'+head.join('')+'</head><body>'+body.join('')+'</body></html>';
  }

  function initOne(el){
    if (!el || el._slsInit) return; el._slsInit = true;
    var data = {}; try{ data = JSON.parse(el.getAttribute('data-sls') || '{}'); }catch(e){ data={}; }
    var height = (data.settings && data.settings.height) ? parseInt(data.settings.height,10) : 360;
    el.style.height = height + 'px';

    // Build iframe
    var iframe = document.createElement('iframe');
    iframe.title = 'SLS Snippet';
    iframe.setAttribute('sandbox','allow-scripts allow-modals allow-pointer-lock allow-popups-to-escape-sandbox');
    el.appendChild(iframe);

    // Compile and inject
    compile(data.languages, data.sources).then(function(compiled){
      iframe.srcdoc = buildSrcdoc(compiled, data.libraries);
    });
  }

  function boot(){
    var nodes = document.querySelectorAll('.sls-embed');
    if (!nodes.length) return;

    // Lazy init when visible
    var io = 'IntersectionObserver' in window ? new IntersectionObserver(function(entries){
      entries.forEach(function(entry){ if(entry.isIntersecting){ initOne(entry.target); io.unobserve(entry.target); } });
    }) : null;

    nodes.forEach(function(n){ if(io) io.observe(n); else initOne(n); });
  }

  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot, { once: true });
})();
```

---

## assets/js/worker-scss.js

```js
// SCSS worker — requires vendor/sass.sync.min.js and assets/wasm/sass.wasm
// Replace the vendor placeholder with the real Sass JS (sync) build.
self.onmessage = function(e){
  var source = e.data && e.data.source || '';
  var wasmUrl = e.data && e.data.wasmUrl;
  try {
    importScripts('vendor/sass.sync.min.js'); // <-- provide this file
    if (typeof Sass === 'undefined') throw new Error('Sass runtime missing');
    Sass.setWasmUrl && Sass.setWasmUrl(wasmUrl);
    var result = Sass.compile(source);
    postMessage({ result: result.css || result.text || '' });
  } catch(err){
    postMessage({ error: (err && err.message) ? err.message : String(err) });
  }
};
```

---

## assets/js/worker-pug.js

```js
// Pug worker — requires vendor/pug.min.js
self.onmessage = function(e){
  var source = e.data && e.data.source || '';
  try {
    importScripts('vendor/pug.min.js'); // <-- provide this file
    if (typeof pug === 'undefined') throw new Error('Pug runtime missing');
    var fn = pug.compile(source, { doctype: 'html' });
    var html = fn({});
    postMessage({ result: html });
  } catch(err){
    postMessage({ error: (err && err.message) ? err.message : String(err) });
  }
};
```

---

## assets/js/worker-babel.js

```js
// Babel/TypeScript/JSX worker — requires vendor/babel.min.js (@babel/standalone)
self.onmessage = function(e){
  var source = (e.data && e.data.source) || '';
  var lang   = (e.data && e.data.lang) || 'js';
  try {
    importScripts('vendor/babel.min.js'); // <-- provide this file
    if (typeof Babel === 'undefined') throw new Error('Babel runtime missing');

    var presets = ['env'];
    if (lang === 'ts' || lang === 'tsx') presets.push('typescript');
    if (lang === 'jsx' || lang === 'tsx') presets.push('react');

    var res = Babel.transform(source, { presets: presets });
    postMessage({ result: res.code || '' });
  } catch(err){
    postMessage({ error: (err && err.message) ? err.message : String(err) });
  }
};
```

---

## assets/js/sandbox-runtime.js (optional placeholder)

```js
// If you need to bootstrap inside the iframe, place helpers here.
```

---

## assets/js/vendor/ (placeholders)

Create these files (replace contents with official vendor builds):

### assets/js/vendor/sass.sync.min.js
```js
/* PLACEHOLDER. Download the official Sass WASM JS runtime and save here. */
throw new Error('sass.sync.min.js placeholder: replace with real file.');
```

### assets/js/vendor/pug.min.js
```js
/* PLACEHOLDER. Download Pug runtime (browser build) and save here. */
throw new Error('pug.min.js placeholder: replace with real file.');
```

### assets/js/vendor/babel.min.js
```js
/* PLACEHOLDER. Download @babel/standalone and save here. */
throw new Error('babel.min.js placeholder: replace with real file.');
```

---

## assets/wasm/sass.wasm

> Binary file. Place official `sass.wasm` here and ensure the URL matches what we pass from PHP.

---

## blocks/sls-snippet/block.json

```json
{
  "apiVersion": 3,
  "name": "sls/snippet",
  "title": "SLS Snippet",
  "category": "widgets",
  "icon": "editor-code",
  "description": "Embed an SLS Snippet by ID.",
  "attributes": {
    "id": { "type": "integer" },
    "height": { "type": "string" },
    "autorun": { "type": "boolean", "default": true }
  },
  "editorScript": "sls-snippets-block-edit",
  "style": "sls-snippets-block-style"
}
```

---

## blocks/sls-snippet/edit.js

```js
( function(wp){
  const { registerPlugin } = wp.plugins || {};
  const { useSelect } = wp.data;
  const { PanelBody, TextControl, ToggleControl } = wp.components;
  const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;

  const Edit = (props) => {
    const { attributes, setAttributes } = props;
    const blockProps = useBlockProps({ className: 'sls-snippet-block' });

    return (
      wp.element.createElement('div', blockProps,
        wp.element.createElement(InspectorControls, {},
          wp.element.createElement(PanelBody, { title: 'Snippet Settings' },
            wp.element.createElement(TextControl, {
              label: 'Snippet ID',
              value: attributes.id || '',
              onChange: (v)=> setAttributes({ id: parseInt(v||'0',10) })
            }),
            wp.element.createElement(TextControl, {
              label: 'Height (px)',
              value: attributes.height || '',
              onChange: (v)=> setAttributes({ height: v })
            }),
            wp.element.createElement(ToggleControl, {
              label: 'Auto-run',
              checked: !!attributes.autorun,
              onChange: (v)=> setAttributes({ autorun: v })
            })
          )
        ),
        wp.element.createElement('p', {}, 'SLS Snippet #', attributes.id || '—')
      )
    );
  };

  wp.blocks.registerBlockType('sls/snippet', {
    edit: Edit,
    save: () => null // server-rendered
  });
})(window.wp);
```

---

## blocks/sls-snippet/style.css

```css
.sls-snippet-block{ border:1px dashed #ccc; padding:10px; }
```

---

## templates/embed.php (optional server template)

```php
<?php
// Not used in this MVP since we render via shortcode callback directly.
```

---

## readme.txt (brief)

```text
=== SLS Snippets ===
Contributors: starlabs
Requires at least: 6.0
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later

CodePen-style snippets inside WordPress. Compose HTML/CSS/JS with optional SCSS, Pug, and Babel (TS/JSX) via client-side compilers. Embed with a shortcode or block.

== Description ==
- Three editors (HTML, CSS, JS) powered by WordPress Code Editor (CodeMirror).
- Optional client-side compilers in Web Workers (SCSS, Pug, Babel/TypeScript/JSX).
- Safe sandboxed iframe preview and embed with CSP.
- Shortcode: [sls_snippet id="123" height="420"].

== Installation ==
1. Upload `sls-snippets` to `/wp-content/plugins/`.
2. Place vendor files:
   - `assets/js/vendor/sass.sync.min.js`
   - `assets/wasm/sass.wasm`
   - `assets/js/vendor/pug.min.js`
   - `assets/js/vendor/babel.min.js`
3. Activate the plugin.

== Changelog ==
= 1.0.0 =
* Initial release.
```

