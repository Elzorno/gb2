function confirmAction(msg){ return window.confirm(msg); }

(function(){
  function pad2(n){ n = Math.floor(n); return (n < 10 ? "0" : "") + n; }

  function fmtRemaining(secs){
    secs = Math.max(0, Math.floor(secs));
    var d = Math.floor(secs / 86400); secs -= d * 86400;
    var h = Math.floor(secs / 3600);  secs -= h * 3600;
    var m = Math.floor(secs / 60);    secs -= m * 60;
    var s = secs;

    var parts = [];
    if (d > 0) parts.push(d + "d");
    if (h > 0 || d > 0) parts.push(h + "h");
    parts.push(pad2(m) + "m");
    parts.push(pad2(s) + "s");
    return "(" + parts.join(" ") + " remaining)";
  }

  function tickCountdowns(){
    var now = Math.floor(Date.now() / 1000);
    document.querySelectorAll("[data-gb2-until]").forEach(function(el){
      var until = parseInt(el.getAttribute("data-gb2-until") || "0", 10);
      if (!until || until <= 0) return;

      var left = until - now;
      if (left <= 0) {
        el.textContent = "(expired — refresh)";
        el.classList.add("expired");
      } else {
        el.textContent = fmtRemaining(left);
        el.classList.remove("expired");
      }
    });
  }

  function esc(s){
    return String(s)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#39;");
  }

  function renderPreview(box, data){
    if (!data || !data.ok) {
      box.style.display = 'block';
      box.innerHTML = '<div><strong>Preview:</strong> ' + esc((data && data.error) ? data.error : 'Unknown error') + '</div>';
      return;
    }

    var html = '';
    html += '<div><strong>Preview:</strong></div>';
    html += '<div style="margin-top:6px">';
    html += '<div>Kid: <strong>' + esc(data.kid_name || '') + '</strong></div>';
    html += '<div>Def: <strong>' + esc(data.def_label || '') + '</strong> (' + esc(data.def_code || '') + ')</div>';
    html += '<div>New strike #: <strong>' + esc(data.new_strike_num) + '</strong></div>';
    html += '<div>Days: <strong>' + esc(data.days) + '</strong></div>';
    html += '<div>Mode: <strong>' + esc(data.mode) + '</strong></div>';
    html += '<div>Computed lock-until (UTC): <strong>' + esc(data.lock_until_utc || '') + '</strong></div>';
    if (data.details) {
      html += '<div style="margin-top:6px" class="note">' + esc(data.details) + '</div>';
    }
    html += '</div>';

    box.style.display = 'block';
    box.innerHTML = html;
  }

  async function fetchPreview(kidId, defId){
    var url = '/api/infraction_preview.php?kid_id=' + encodeURIComponent(kidId) + '&def_id=' + encodeURIComponent(defId);
    var r = await fetch(url, { credentials: 'same-origin' });
    var j = await r.json();
    return j;
  }

  function bindInfractionPreviews(){
    document.querySelectorAll('.inf-select').forEach(function(sel){
      var kidId = sel.getAttribute('data-kid');
      var box = document.getElementById('preview_' + kidId);
      if (!kidId || !box) return;

      sel.addEventListener('change', async function(){
        var defId = parseInt(sel.value || '0', 10);
        if (!defId) {
          box.style.display = 'none';
          box.innerHTML = '';
          return;
        }
        box.style.display = 'block';
        box.textContent = 'Loading preview...';

        try {
          var data = await fetchPreview(kidId, defId);
          renderPreview(box, data);
        } catch (e) {
          renderPreview(box, { ok:false, error: String(e) });
        }
      });
    });
  }

  
  function bindNavDrawer(){
  var wrap = document.getElementById('gb2Nav');
  if (!wrap) return;

  var toggles = Array.prototype.slice.call(document.querySelectorAll('[data-nav-toggle]'));
  var lastFocus = null;

  function setExpanded(v){
    toggles.forEach(function(btn){
      try { btn.setAttribute('aria-expanded', v ? 'true' : 'false'); } catch(e){}
    });
  }

  function focusables(){
    return Array.prototype.slice.call(
      wrap.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
    ).filter(function(el){
      return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    });
  }

  function open(){
    if (wrap.classList.contains('open')) return;
    lastFocus = document.activeElement;

    wrap.classList.add('open');
    wrap.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('nav-open');
    setExpanded(true);

    // Focus first meaningful control in the drawer.
    var f = focusables();
    if (f.length) f[0].focus();
  }

  function close(){
    if (!wrap.classList.contains('open')) return;

    wrap.classList.remove('open');
    wrap.setAttribute('aria-hidden','true');
    document.documentElement.classList.remove('nav-open');
    setExpanded(false);

    if (lastFocus && typeof lastFocus.focus === 'function') {
      try { lastFocus.focus(); } catch(e){}
    }
    lastFocus = null;
  }

  toggles.forEach(function(btn){
    btn.addEventListener('click', function(e){ e.preventDefault(); open(); });
  });

  wrap.querySelectorAll('[data-nav-close]').forEach(function(el){
    el.addEventListener('click', function(e){ e.preventDefault(); close(); });
  });

  document.addEventListener('keydown', function(e){
    if (!wrap.classList.contains('open')) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }

    // Basic focus trap when drawer is open.
    if (e.key === 'Tab') {
      var f = focusables();
      if (!f.length) return;

      var first = f[0];
      var last  = f[f.length - 1];

      if (e.shiftKey) {
        if (document.activeElement === first || !wrap.contains(document.activeElement)) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
  });
}

// Kick off common behaviors
  tickCountdowns();
  setInterval(tickCountdowns, 1000);

  bindInfractionPreviews();
  bindNavDrawer();
})();
