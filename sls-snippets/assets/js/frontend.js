/* global SLS_SNIPPETS_FRONTEND */
(function(){
  // Compile via workers if needed. If cache provided, prefer it.
  function compileOrUseCache(data){
    var langs = data.languages || { html:'html', css:'css', js:'js' };
    var isPlain = (langs.html === 'html' && langs.css === 'css' && langs.js === 'js');

    if (data.cache && (data.cache.html || data.cache.css || data.cache.js)){
      return Promise.resolve({ html: data.cache.html||'', css: data.cache.css||'', js: data.cache.js||'' });
    }
    if (isPlain){
      return Promise.resolve({ html: data.sources.html||'', css: data.sources.css||'', js: data.sources.js||'' });
    }

    var tasks = [];

    // HTML / Pug
    tasks.push(new Promise(function(resolve){
      if (langs.html === 'pug'){
        var w = new Worker(SLS_SNIPPETS_FRONTEND.workers.pug);
        w.onmessage = function(e){ resolve({ html: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: data.sources.html || '' });
      } else { resolve({ html: data.sources.html || '' }); }
    }));

    // CSS / SCSS
    tasks.push(new Promise(function(resolve){
      if (langs.css === 'scss'){
        var w = new Worker(SLS_SNIPPETS_FRONTEND.workers.scss);
        w.onmessage = function(e){ resolve({ css: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: data.sources.css || '' });
      } else { resolve({ css: data.sources.css || '' }); }
    }));

    // JS / TS / JSX / TSX
    tasks.push(new Promise(function(resolve){
      if (langs.js !== 'js'){
        var w = new Worker(SLS_SNIPPETS_FRONTEND.workers.babel);
        w.onmessage = function(e){ resolve({ js: e.data.result || '', error: e.data.error || null }); w.terminate(); };
        w.postMessage({ source: data.sources.js || '', lang: langs.js });
      } else { resolve({ js: data.sources.js || '' }); }
    }));

    return Promise.all(tasks).then(function(parts){
      var out = { html:'', css:'', js:'' };
      parts.forEach(function(p){ if(p.html!==undefined) out.html=p.html; if(p.css!==undefined) out.css=p.css; if(p.js!==undefined) out.js=p.js; });
      return out;
    });
  }

  function buildSrcdoc(compiled, libs){
    // CSP keeps things safer. Allow inline for snippets but restrict everything else.
    var csp = '<meta http-equiv="Content-Security-Policy" content="' +
      "default-src 'none'; " +
      "script-src 'unsafe-inline' https:; " +
      "style-src 'unsafe-inline' https:; " +
      "img-src data: https:; " +
      "font-src https:; " +
      "connect-src https:;" + '">';

    var head = [csp];
    (libs||[]).filter(function(l){ return l.type==='css'; }).forEach(function(l){
      var attrs = ' rel="stylesheet" href="'+l.url.replace(/\\"/g,'&quot;')+'"';
      if (l.sri) attrs += ' integrity="'+ l.sri +'" crossorigin="anonymous"';
      head.push('<link'+attrs+'>');
    });
    if (compiled.css) head.push('<style>'+compiled.css+'</style>');

    var body = [];
    if (compiled.html) body.push(compiled.html);
    (libs||[]).filter(function(l){ return l.type==='js'; }).forEach(function(l){
      var attrs = ' src="'+l.url.replace(/\\"/g,'&quot;')+'"';
      if (l.sri) attrs += ' integrity="'+ l.sri +'" crossorigin="anonymous"';
      body.push('<script'+attrs+'></'+'script>');
    });
    if (compiled.js) body.push('<script>'+compiled.js+'\n</'+'script>');

    return '<!doctype html><html><head>'+head.join('')+'</head><body>'+body.join('')+'</body></html>';
  }

  function initOne(el){
    if (!el || el._slsInit) return; 
    el._slsInit = true;

    var data = {}; 
    try{ data = JSON.parse(el.getAttribute('data-sls') || '{}'); }
    catch(e){ data={}; }

    var height = (data.settings && data.settings.height) ? parseInt(data.settings.height,10) : 360;
    el.style.height = height + 'px';

    var iframe = document.createElement('iframe');
    iframe.title = 'SLS Snippet ' + (data.id || '');
    iframe.setAttribute('referrerpolicy','no-referrer');

    // ✅ Safer sandbox that still allows navigation on user click
    var sandbox = [
      'allow-scripts',
      'allow-same-origin',
      'allow-forms',
      'allow-popups',
      'allow-modals',
      'allow-pointer-lock',
      'allow-popups-to-escape-sandbox',
      'allow-top-navigation-by-user-activation'
    ];
    iframe.setAttribute('sandbox', sandbox.join(' '));

    iframe.style.width = '100%';
    iframe.style.height = height + 'px';
    iframe.style.border = '0';
    el.appendChild(iframe);

    compileOrUseCache(data).then(function(compiled){
      iframe.srcdoc = buildSrcdoc(compiled, data.libraries);
    });
  }

  function attachClickToRun(el){
    var overlay = document.createElement('div');
    overlay.className = 'sls-embed__overlay';
    var btn = document.createElement('button');
    btn.type = 'button'; 
    btn.className = 'sls-embed__btn'; 
    btn.textContent = 'Run snippet';
    overlay.appendChild(btn); 
    el.appendChild(overlay);
    overlay.addEventListener('click', function(){ overlay.remove(); initOne(el); }, { once: true });
  }

  function boot(){
    var nodes = document.querySelectorAll('.sls-embed');
    if (!nodes.length) return;

    nodes.forEach(function(el){
      var data = {}; 
      try{ data = JSON.parse(el.getAttribute('data-sls') || '{}'); }
      catch(e){ data={}; }
      var autorun = !data.settings || data.settings.autorun !== false;

      if (autorun){
        var io = 'IntersectionObserver' in window ? new IntersectionObserver(function(entries){
          entries.forEach(function(entry){ 
            if(entry.isIntersecting){ 
              initOne(entry.target); 
              io.unobserve(entry.target); 
            } 
          });
        }) : null;
        if (io) io.observe(el); else initOne(el);
      } else {
        attachClickToRun(el);
      }
    });
  }

  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot, { once: true });
})();
