/* global wp, SLS_SNIPPETS_ADMIN */
(function () {
  if (!window.wp || !wp.codeEditor) return;

  // -------------------------------------------------------------
  // Registry for active CodeMirror editors
  // -------------------------------------------------------------
  const editors = {};
  let activeSidebar = null;
  let activeFullscreenPreview = null;

  // -------------------------------------------------------------
  // Utility helpers
  // -------------------------------------------------------------
  function byId(id) {
    return document.getElementById(id);
  }

  function setStatus(text) {
    const s = byId("sls-status");
    if (s) s.textContent = text || "";
  }

  // -------------------------------------------------------------
  // Editor initialization
  // -------------------------------------------------------------
  function initEditor(id, mode) {
    const el = byId(id);
    if (!el) return null;

    const settings = wp.codeEditor.defaultSettings
      ? window._.clone(wp.codeEditor.defaultSettings)
      : {};
    settings.codemirror = settings.codemirror || {};
    settings.codemirror.mode = mode;

    const ed = wp.codeEditor.initialize(el, settings);
    editors[id] = ed;
    return ed;
  }

  function getVal(id) {
    const ed = editors[id];
    if (ed && ed.codemirror) return ed.codemirror.getValue();
    const el = byId(id);
    return el ? el.value : "";
  }

  // -------------------------------------------------------------
  // Compilation logic (HTML/Pug, CSS/SCSS, JS/TS/JSX/TSX)
  // -------------------------------------------------------------
  function compileAll(langs, src) {
    setStatus(SLS_SNIPPETS_ADMIN.i18n.compiling);

    const tasks = [
      // HTML / Pug
      new Promise((resolve) => {
        if (langs.html === "pug") {
          const w = new Worker(SLS_SNIPPETS_ADMIN.workers.pug);
          w.onmessage = (e) => {
            resolve({ html: e.data.result || "", error: e.data.error || null });
            w.terminate();
          };
          w.postMessage({ source: src.html });
        } else resolve({ html: src.html, error: null });
      }),

      // CSS / SCSS
      new Promise((resolve) => {
        if (langs.css === "scss") {
          const w = new Worker(SLS_SNIPPETS_ADMIN.workers.scss);
          w.onmessage = (e) => {
            resolve({ css: e.data.result || "", error: e.data.error || null });
            w.terminate();
          };
          w.postMessage({ source: src.css });
        } else resolve({ css: src.css, error: null });
      }),

      // JS / TS / JSX / TSX
      new Promise((resolve) => {
        if (langs.js !== "js") {
          const w = new Worker(SLS_SNIPPETS_ADMIN.workers.babel);
          w.onmessage = (e) => {
            resolve({ js: e.data.result || "", error: e.data.error || null });
            w.terminate();
          };
          w.postMessage({ source: src.js, lang: langs.js });
        } else resolve({ js: src.js, error: null });
      }),
    ];

    return Promise.all(tasks).then((parts) => {
      const out = { html: "", css: "", js: "" };
      const errors = [];

      parts.forEach((p) => {
        if (p.html !== undefined) out.html = p.html;
        if (p.css !== undefined) out.css = p.css;
        if (p.js !== undefined) out.js = p.js;
        if (p.error) errors.push(p.error);
      });

      return { out, errors };
    });
  }

  // -------------------------------------------------------------
  // Build secure-ish srcdoc with inline CSP
  // -------------------------------------------------------------
  function buildIframeSrcdoc(compiled, libs) {
    const csp =
      "<meta http-equiv=\"Content-Security-Policy\" content=\"" +
      "default-src 'none'; " +
      "script-src 'unsafe-inline' https:; " +
      "style-src 'unsafe-inline' https:; " +
      "img-src data: https:; " +
      "font-src https:; " +
      "connect-src https:;" +
      '">';

    const head = [csp];
    (libs || [])
      .filter((l) => l.type === "css")
      .forEach((l) => {
        let attrs = ` rel="stylesheet" href="${l.url.replace(/\\"/g, "&quot;")}"`;
        if (l.sri) attrs += ` integrity="${l.sri}" crossorigin="anonymous"`;
        head.push("<link" + attrs + ">");
      });
    if (compiled.css) head.push("<style>" + compiled.css + "</style>");

    const body = [];
    if (compiled.html) body.push(compiled.html);

    (libs || [])
      .filter((l) => l.type === "js")
      .forEach((l) => {
        let attrs = ` src="${l.url.replace(/\\"/g, "&quot;")}"`;
        if (l.sri) attrs += ` integrity="${l.sri}" crossorigin="anonymous"`;
        body.push("<script" + attrs + "></" + "script>");
      });
    if (compiled.js) body.push("<script>" + compiled.js + "\n</" + "script>");

    return "<!doctype html><html><head>" + head.join("") + "</head><body>" + body.join("") + "</body></html>";
  }

  // -------------------------------------------------------------
  // Preview rendering
  // -------------------------------------------------------------
  function doPreview() {
    const langs = {
      html: byId("sls_lang_html").value || "html",
      css: byId("sls_lang_css").value || "css",
      js: byId("sls_lang_js").value || "js",
    };
    const src = {
      html: getVal("sls_html"),
      css: getVal("sls_css"),
      js: getVal("sls_js"),
    };

    let libs = [];
    try {
      libs = JSON.parse(document.querySelector('[name="sls_libraries"]').value || "[]");
    } catch (e) {}
    let settings = {};
    try {
      settings = JSON.parse(document.querySelector('[name="sls_settings"]').value || "{}");
    } catch (e) {}

    compileAll(langs, src).then((r) => {
      setStatus(r.errors.length ? SLS_SNIPPETS_ADMIN.i18n.error : SLS_SNIPPETS_ADMIN.i18n.ready);

      const iframe = byId("sls-preview");
      const html = buildIframeSrcdoc(r.out, libs);
      iframe.setAttribute(
        "sandbox",
        "allow-scripts allow-modals allow-pointer-lock allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"
      );
      iframe.srcdoc = html;

      byId("sls_cache").value = JSON.stringify(r.out);
    });
  }

  // -------------------------------------------------------------
  // Sidebar editor logic
  // -------------------------------------------------------------
  function openEditorSidebar(id) {
    const wrap = byId(id)?.closest(".sls-pane");
    if (!wrap) return;

    wrap.classList.add("sls-sidebar-open");
    activeSidebar = wrap;

    // Add close button inside sidebar (top-right)
    if (!wrap.querySelector(".sls-close-btn")) {
      const close = document.createElement("button");
      close.className = "sls-close-btn";
      close.innerHTML = "&times;";
      close.title = "Close editor";
      close.addEventListener("click", (e) => {
        e.stopPropagation();
        closeEditorSidebar();
      });
      wrap.appendChild(close);
    }

    // Outside click handler
    setTimeout(() => document.addEventListener("click", outsideClickHandler));

    editors[id].codemirror.refresh();
  }

  function closeEditorSidebar() {
    if (!activeSidebar) return;
    activeSidebar.classList.remove("sls-sidebar-open");
    activeSidebar = null;
    document.removeEventListener("click", outsideClickHandler);
  }

  function outsideClickHandler(e) {
    if (activeSidebar && !activeSidebar.contains(e.target)) {
      closeEditorSidebar();
    }
  }

  // -------------------------------------------------------------
  // Fullscreen preview logic
  // -------------------------------------------------------------
  function openPreviewFullscreen() {
    const wrap = byId("sls-preview-wrap");
    if (!wrap) return;
    wrap.classList.add("sls-fullscreen");
    activeFullscreenPreview = wrap;

    if (!wrap.querySelector(".sls-close-btn")) {
      const close = document.createElement("button");
      close.className = "sls-close-btn";
      close.innerHTML = "&times;";
      close.title = "Close preview";
      close.addEventListener("click", (e) => {
        e.stopPropagation();
        closePreviewFullscreen();
      });
      wrap.appendChild(close);
    }
  }

  function closePreviewFullscreen() {
    if (!activeFullscreenPreview) return;
    activeFullscreenPreview.classList.remove("sls-fullscreen");
    activeFullscreenPreview = null;
  }

  // -------------------------------------------------------------
  // Global keyboard shortcuts
  // -------------------------------------------------------------
  function handleGlobalKey(e) {
    if (e.key === "Escape") {
      if (activeSidebar) closeEditorSidebar();
      if (activeFullscreenPreview) closePreviewFullscreen();
    }
  }

  // -------------------------------------------------------------
  // Boot
  // -------------------------------------------------------------
  document.addEventListener("DOMContentLoaded", function () {
    // Initialize editors
    initEditor("sls_html", "text/html");
    initEditor("sls_css", "text/css");
    initEditor("sls_js", "text/javascript");

    // Add sidebar open buttons
    ["sls_html", "sls_css", "sls_js"].forEach((id) => {
      const pane = byId(id)?.closest(".sls-pane");
      if (pane) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "sls-btn-icon";
        btn.innerHTML = '<span class="dashicons dashicons-fullscreen-alt"></span>';
        btn.title = "Open editor sidebar";
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          openEditorSidebar(id);
        });
        pane.insertBefore(btn, pane.firstChild);
      }
    });

    // Add fullscreen preview button
    const previewWrap = byId("sls-preview-wrap");
    if (previewWrap) {
      let toolbar = previewWrap.querySelector(".sls-preview-toolbar");
      if (!toolbar) {
        toolbar = document.createElement("div");
        toolbar.className = "sls-preview-toolbar";
        previewWrap.appendChild(toolbar);
      }

      const fsBtn = document.createElement("button");
      fsBtn.type = "button";
      fsBtn.className = "sls-btn-icon";
      fsBtn.innerHTML = '<span class="dashicons dashicons-fullscreen-alt"></span>';
      fsBtn.title = "Open fullscreen preview";
      fsBtn.addEventListener("click", openPreviewFullscreen);
      toolbar.appendChild(fsBtn);
    }

    // Preview button
    const btn = byId("sls-btn-preview");
    if (btn) btn.addEventListener("click", doPreview);

    // ESC key support
    document.addEventListener("keydown", handleGlobalKey);
  });
})();
