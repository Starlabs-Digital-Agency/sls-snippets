# sls-snippets
SLS Snippets is a free, lightweight WordPress plugin for embedding live HTML/CSS/JS anywhere via shortcode or Gutenberg block. It solves the hassle of showing interactive code in WordPress without external playgrounds or theme conflicts—each snippet renders in a sandboxed iframe, loads only when needed, and supports optional external libraries.

# SLS Snippets — Installation & Usage Guide (v1.0.3)

A lightweight, free WordPress plugin to author and embed **HTML / CSS / JS** snippets via **shortcode** or **Gutenberg block**, rendered in an **isolated iframe** for safety and performance.

---

## 1) Requirements

* **WordPress:** 5.8+ (Gutenberg block support recommended)
* **PHP:** 7.4+ (8.x supported)
* **Permissions:**

  * Create/edit snippets → users with `edit_posts`
  * Manage plugin settings/help → users with `manage_options`

> These capabilities are filterable via `sls_snippets_capabilities`.

---

## 2) Download

* **Direct (current build):** SLS Snippets v1.0.3 (ZIP)
* **From your site:** Upload the ZIP to your Downloads area and use that URL with WP Admin or WP‑CLI.

---

## 3) Install

### Method A — WP Admin (Upload ZIP)

1. Go to **Plugins → Add New → Upload Plugin**.
2. Click **Choose File**, select the `sls-snippets-1.0.3.zip`.
3. Click **Install Now**, then **Activate**.

### Method B — WP‑CLI (if your host supports SSH)

```bash
wp plugin install https://YOUR-DOMAIN.TLD/path/to/sls-snippets-1.0.3.zip --activate
```

### Method C — Manual (SFTP)

1. Unzip locally.
2. Upload the folder **sls-snippets** to `/wp-content/plugins/`.
3. In WordPress, go to **Plugins** and click **Activate**.

> After activation, you’ll see **SLS Snippets** in the left admin menu.

---

## 4) First‑time Setup (Global Settings)

Go to **SLS Snippets → Settings**.

| Setting                           | What it does                                                                                      | Recommended                                                          |
| --------------------------------- | ------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| **Disable all embeds**            | Kill switch—turns off all frontend snippet rendering.                                             | Off in production                                                    |
| **Default height (px)**           | Used when an embed doesn’t specify a height.                                                      | 360–560 px                                                           |
| **Allowed library domains**       | One domain per line; external CSS/JS must come from these hosts (leave empty to allow any HTTPS). | Whitelist only what you need (e.g., `cdn.jsdelivr.net`, `unpkg.com`) |
| **Require SRI for external libs** | Forces Subresource Integrity on external CSS/JS. Missing SRI → library is skipped.                | On for production, Off while testing                                 |

> **Security note:** Allowed‑domains + SRI significantly reduce supply‑chain risks from third‑party CDNs.

---

## 5) Create Your First Snippet

1. Go to **SLS Snippets → Add New**.
2. Use the three editors: **HTML**, **CSS**, **JS**.
3. Click **Preview** to render in a sandboxed iframe.
4. Optional: use the **fullscreen preview** button in the editor toolbar.
5. Click **Publish** (or **Update** for changes).

### Per‑Snippet Settings & Libraries

In the **Settings & Libraries** meta box:

* **Height & autorun** (stored in per‑snippet settings):

  ```json
  { "height": 420, "autorun": true }
  ```
* **External libraries JSON** (optional):

  ```json
  [
    { "type": "css", "url": "https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css", "sri": "sha384-..." },
    { "type": "js",  "url": "https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js", "sri": "sha384-..." }
  ]
  ```

**Rules:**

* `type` is `css` or `js` only.
* `url` must be `https://…` and pass the **Allowed library domains** check.
* If **Require SRI** is enabled globally, each library must include a valid `sri` hash.

> Compilers like Pug/SCSS/TypeScript/JSX/TSX are **not supported** in this build—use plain HTML/CSS/JS.

---

## 6) Embed on Pages/Posts

### Option A — Shortcode

Insert anywhere shortcodes are supported:

```text
[sls_snippet id="123" height="420" autorun="true"]
```

**Attributes:**

* `id` *(required)*: snippet post ID.
* `height` *(optional)*: pixels; overrides per‑snippet/global default.
* `autorun` *(optional)*: `true` or `false`. If `false`, visitors get a click‑to‑run overlay.

### Option B — Gutenberg Block

1. Add block → search **“SLS Snippet”**.
2. Set **ID** (snippet post ID).
3. Optionally set **Height** and **Auto‑run**.

---

## 7) Best Practices

* **Performance:** Prefer `autorun: true` with lazy init (default) for demos above the fold; use `autorun: false` for heavy snippets or many embeds on one page.
* **Security:** Keep **Allowed domains** tight; turn on **Require SRI** in production.
* **Styling:** Put snippet‑specific CSS in the snippet’s **CSS** editor; theme CSS won’t leak into the sandboxed iframe.
* **Libraries:** Pin versions (e.g., `@3.13.2`) to avoid unplanned changes.
* **Height:** Set an explicit `height` per embed for predictable layout.

---

## 8) Troubleshooting

* **Embeds don’t show** → Check **Settings → Disable all embeds** is **OFF**.
* **External CSS/JS not loading** → Add the CDN host to **Allowed library domains**; if **Require SRI** is ON, ensure the `sri` hash is present and valid.
* **Layout too small/large** → Adjust `height` in shortcode/block or per‑snippet settings; verify global default.
* **Used to rely on Pug/SCSS/TS/JSX/TSX** → Paste compiled output into the HTML/CSS/JS editors; this build supports **plain** modes only.

---

## 9) Uninstall

* **Plugins → SLS Snippets → Deactivate → Delete** removes plugin **settings**.
* To also purge all snippet posts + meta on uninstall, add to `wp-config.php` **before** deleting:

  ```php
  define('SLS_SNIPPETS_DELETE_CONTENT', true);
  ```

---

## 10) Reference (for Developers)

* **Shortcode:** `sls_snippet` (`id`, `height`, `autorun`)
* **Block:** `sls/snippet` (server‑rendered; delegates to shortcode)
* **Capabilities filter:** `sls_snippets_capabilities`
* **Global Settings option:** `sls_snippets_settings`
* **Helpers:** `SLS\Snippets\get_settings()`, `caps()`, `asset_url()`, `json_encode_safe()`

---

## 11) Changelog (v1.0.3)

* Menu renamed to **SLS Snippets**
* Added **Help** page under plugin menu
* Removed Pug/SCSS/TypeScript/JSX/TSX compilers and related assets
* Enforced plain HTML/CSS/JS at edit & render time (clamping)
* Smaller footprint, improved stability

---

### Need help?

Questions or feature requests? Contact the StarLabs team. We’re happy to help you get the most from SLS Snippets.
