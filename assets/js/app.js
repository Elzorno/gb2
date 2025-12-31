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

  // Kick off common behaviors
  tickCountdowns();
  setInterval(tickCountdowns, 1000);

  bindInfractionPreviews();
})();
