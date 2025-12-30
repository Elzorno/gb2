(function(){
  const q=(s,r)=> (r||document).querySelector(s);
  const qa=(s,r)=> Array.from((r||document).querySelectorAll(s));

  // Toasts (Phase 8)
  const ensureToastHost = () => {
    let host = document.getElementById('gbToastHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'gbToastHost';
      document.body.appendChild(host);
    }
    return host;
  };

  const gbToast = (msg, type='info', meta='') => {
    if (!msg) return;
    const host = ensureToastHost();
    const t = document.createElement('div');
    t.className = `gb-toast ${type}`;
    t.setAttribute('role','status');
    t.setAttribute('aria-live','polite');
    const wrap = document.createElement('div');
    wrap.style.flex = '1';
    const m = document.createElement('div');
    m.className = 'tmsg';
    m.textContent = msg;
    wrap.appendChild(m);
    if (meta) {
      const mm = document.createElement('div');
      mm.className = 'tmeta';
      mm.textContent = meta;
      wrap.appendChild(mm);
    }
    const close = document.createElement('button');
    close.className = 'tclose';
    close.type = 'button';
    close.setAttribute('aria-label','Dismiss');
    close.textContent = '×';
    close.addEventListener('click', ()=>{
      t.classList.remove('show');
      setTimeout(()=> t.remove(), 180);
    });

    t.appendChild(wrap);
    t.appendChild(close);
    host.appendChild(t);
    // animate in
    requestAnimationFrame(()=> t.classList.add('show'));
    // auto dismiss
    setTimeout(()=>{
      if (!t.isConnected) return;
      t.classList.remove('show');
      setTimeout(()=> t.remove(), 180);
    }, 3800);
  };

  // Expose for inline-less usage
  window.gbToast = gbToast;



  // Theme (Phase 6)
  const THEME_KEY = 'gb_theme';
  const applyTheme = (v) => {
    const val = (v==='light' || v==='dark' || v==='auto') ? v : 'auto';
    // "auto" should follow system theme; do that by removing the override.
    if (val === 'auto') {
      document.documentElement.removeAttribute('data-theme');
    } else {
      document.documentElement.setAttribute('data-theme', val);
    }
    try { localStorage.setItem(THEME_KEY, val); } catch(e) {}
  };
  let saved = 'auto';
  try { saved = localStorage.getItem(THEME_KEY) || 'auto'; } catch(e) {}
  applyTheme(saved);
  const themeSel = q('[data-gb-theme]');
  if (themeSel){
    themeSel.value = (saved==='light' || saved==='dark' || saved==='auto') ? saved : 'auto';
    themeSel.addEventListener('change', ()=> applyTheme(themeSel.value));
  }

  // Phase 7: Quick Add modal (Board)
  const modal = q('[data-gb-modal="quickadd"]');
  if (modal){
    const title = q('[data-gb-qa-title]', modal);
    const search = q('[data-gb-qa-search]', modal);
    const favs = q('[data-gb-qa-favs]', modal);
    const recent = q('[data-gb-qa-recent]', modal);
    const all = q('[data-gb-qa-all]', modal);
    const preview = q('[data-gb-qa-preview]', modal);
    const form = q('[data-gb-qa-form]', modal);
    const kidInput = q('[data-gb-qa-kid]', modal);
    const infInput = q('[data-gb-qa-inf]', modal);
    const applyBtn = q('[data-gb-qa-apply]', modal);
    const hint = q('[data-gb-qa-hint]', modal);

    const RECENT_KEY = 'gb_recent_infractions';
    const readRecent = () => {
      try {
        const raw = localStorage.getItem(RECENT_KEY) || '[]';
        const arr = JSON.parse(raw);
        return Array.isArray(arr) ? arr.filter(x=> typeof x==='string') : [];
      } catch(e) { return []; }
    };
    const writeRecent = (arr) => {
      try { localStorage.setItem(RECENT_KEY, JSON.stringify(arr.slice(0, 10))); } catch(e) {}
    };

    const state = {
      kidIndex: null,
      kidName: '',
      infractions: [],
      favorites: [],
      selectedId: '',
      loaded: false,
      loadPromise: null,
    };

    const open = () => {
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      window.setTimeout(()=>{ try { search && search.focus(); } catch(e){} }, 50);
    };
    const close = () => {
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      state.selectedId = '';
      if (infInput) infInput.value = '';
      if (applyBtn) applyBtn.disabled = true;
      if (hint) hint.textContent = 'Select an infraction.';
      if (preview) preview.textContent = 'Pick an infraction to see the outcome.';
      qa('.qa-pill', modal).forEach(p=> p.classList.remove('active'));
      if (search) search.value = '';
      renderAll();
    };

    const pill = (inf) => {
      const d = document.createElement('div');
      d.className = 'qa-pill';
      d.setAttribute('role', 'button');
      d.setAttribute('tabindex', '0');
      d.dataset.id = inf.id;
      d.dataset.label = inf.label;
      d.innerHTML = `<span>${escapeHtml(inf.label)}</span> <small>${escapeHtml(inf.id)}</small>`;
      const sel = () => selectInf(inf.id);
      d.addEventListener('click', sel);
      d.addEventListener('keydown', (e)=>{ if (e.key==='Enter' || e.key===' ') { e.preventDefault(); sel(); }});
      return d;
    };

    const escapeHtml = (s) => String(s)
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');

    const loadData = () => {
      if (state.loaded) return Promise.resolve();
      if (state.loadPromise) return state.loadPromise;
      state.loadPromise = fetch('api.php?action=infractions', {credentials:'same-origin'})
        .then(r => r.ok ? r.json() : Promise.reject(new Error('Failed to load')))
        .then(j => {
          state.infractions = Array.isArray(j.infractions) ? j.infractions : [];
          state.favorites = Array.isArray(j.favorites) ? j.favorites : [];
          state.loaded = true;
          renderAll();
        })
        .catch(()=>{
          state.infractions = [];
          state.favorites = [];
          state.loaded = true;
          renderAll();
        });
      return state.loadPromise;
    };

    const renderList = (host, list) => {
      if (!host) return;
      host.innerHTML = '';
      list.forEach(inf => host.appendChild(pill(inf)));
      if (!list.length) {
        const em = document.createElement('div');
        em.className = 'small';
        em.style.marginTop = '6px';
        em.textContent = 'None.';
        host.appendChild(em);
      }
    };

    const byId = (id) => state.infractions.find(x=> x.id===id) || null;

    const renderAll = () => {
      const qv = (search && search.value ? search.value : '').trim().toLowerCase();
      const filtered = state.infractions
        .filter(x => !qv || (x.label||'').toLowerCase().includes(qv) || (x.id||'').toLowerCase().includes(qv));

      const favSet = new Set(state.favorites);
      const favList = state.infractions.filter(x=> favSet.has(x.id));

      const recIds = readRecent();
      const recList = recIds.map(id=> byId(id)).filter(Boolean);

      renderList(favs, favList);
      renderList(recent, recList);
      renderList(all, filtered);

      // Keep active state
      qa('.qa-pill', modal).forEach(p=>{
        if (p.dataset.id === state.selectedId) p.classList.add('active');
      });
    };

    const selectInf = (id) => {
      state.selectedId = String(id || '');
      if (infInput) infInput.value = state.selectedId;
      qa('.qa-pill', modal).forEach(p=> p.classList.toggle('active', p.dataset.id===state.selectedId));
      if (applyBtn) applyBtn.disabled = !state.selectedId;
      if (hint) hint.textContent = state.selectedId ? 'Ready to apply.' : 'Select an infraction.';

      if (!state.selectedId) return;
      // Preview outcome
      preview.textContent = 'Loading preview…';
      const url = `api.php?action=preview&kid_index=${encodeURIComponent(state.kidIndex)}&infraction_id=${encodeURIComponent(state.selectedId)}`;
      fetch(url, {credentials:'same-origin'})
        .then(r=> r.ok ? r.json() : Promise.reject(new Error('bad')))
        .then(j=>{
          if (!j || !j.ok) throw new Error('bad');
          const lines = [];
          lines.push(`${j.label} (${j.id})`);
          lines.push(`Strike: ${j.strike_after}`);
          lines.push(`Days: ${j.days} (${j.mode === 'add' ? 'adds' : 'sets'})`);
          if (j.ends) lines.push(`Ends: ${j.ends}`);
          if (j.review_on) lines.push(`Review: ${j.review_on}`);
          preview.textContent = lines.join(' • ');
        })
        .catch(()=>{
          preview.textContent = 'Preview unavailable.';
        });
    };

    // Open modal buttons
    qa('[data-gb-quick-add]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        state.kidIndex = btn.getAttribute('data-kid-index');
        state.kidName = btn.getAttribute('data-kid-name') || 'Kid';
        if (title) title.textContent = state.kidName;
        if (kidInput) kidInput.value = state.kidIndex;
        loadData().then(()=> open());
      });
    });

    // Close handlers
    qa('[data-gb-modal-close]', modal).forEach(el=> el.addEventListener('click', close));
    document.addEventListener('keydown', (e)=>{ if (modal.getAttribute('aria-hidden')==='false' && e.key==='Escape') close(); });

    if (search){
      search.addEventListener('input', renderAll);
    }

    // Before submit, remember recent
    if (form){
      form.addEventListener('submit', ()=>{
        const id = (infInput && infInput.value) ? String(infInput.value) : '';
        if (!id) return;
        const cur = readRecent();
        const next = [id, ...cur.filter(x=> x!==id)];
        writeRecent(next);
      });
    }
  }

  // CSRF token injection: add hidden input to all POST forms.
  const metaCsrf = q('meta[name="gb-csrf"]');
  const csrf = metaCsrf ? (metaCsrf.getAttribute('content')||'') : '';
  if (csrf){
    qa('form[method="post"]').forEach(f=>{
      if (!q('input[name="gb_csrf"]', f)){
        const i=document.createElement('input');
        i.type='hidden';
        i.name='gb_csrf';
        i.value=csrf;
        f.appendChild(i);
      }
    });
  }

  // Confirm dialogs without inline JS (CSP-safe)
  qa('form[data-gb-confirm]').forEach(f=>{
    f.addEventListener('submit', (e)=>{
      const msg = f.getAttribute('data-gb-confirm') || 'Are you sure?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  });

  // Fullscreen button (kiosk)
  const fsBtn = q('[data-gb-fullscreen]');
  if (fsBtn && document.documentElement.requestFullscreen){
    fsBtn.addEventListener('click', ()=>{
      try { document.documentElement.requestFullscreen(); } catch(e) {}
    });
  }

  // ===== Phase 8.1: Tabs + Sheets (CSP-safe) =====

  // Simple tab system
  qa('.tabs[data-gb-tabs]').forEach(tabs=>{
    const name = tabs.getAttribute('data-gb-tabs') || 'tabs';
    const key = 'gb_tab_' + name;
    const btns = qa('[data-gb-tab]', tabs);
    const panels = qa('[data-gb-tabpanel]');

    const show = (tab) => {
      btns.forEach(b=> b.classList.toggle('active', b.getAttribute('data-gb-tab')===tab));
      panels.forEach(p=>{
        const match = p.getAttribute('data-gb-tabpanel') === tab;
        // Only affect panels that belong to this tab group (same page).
        // If multiple tab groups exist in the future, they should namespace their panels.
        p.classList.toggle('active', match);
      });
      try { localStorage.setItem(key, tab); } catch(e) {}
    };

    let initial = btns[0] ? (btns[0].getAttribute('data-gb-tab')||'') : '';
    try {
      const saved = localStorage.getItem(key);
      if (saved) initial = saved;
    } catch(e) {}
    if (!btns.some(b=> (b.getAttribute('data-gb-tab')||'')===initial)){
      initial = btns[0] ? (btns[0].getAttribute('data-gb-tab')||'') : initial;
    }
    if (initial) show(initial);

    btns.forEach(b=> b.addEventListener('click', ()=> show(b.getAttribute('data-gb-tab')||'')));
  });

  // Generic modal open/close
  const openModal = (m) => {
    if (!m) return;
    m.setAttribute('aria-hidden','false');
    document.body.style.overflow='hidden';
  };
  const closeModal = (m) => {
    if (!m) return;
    m.setAttribute('aria-hidden','true');
    document.body.style.overflow='';
  };

  qa('.modal').forEach(m=>{
    qa('[data-gb-modal-close]', m).forEach(x=> x.addEventListener('click', ()=> closeModal(m)));
  });
  document.addEventListener('keydown', (e)=>{
    if (e.key !== 'Escape') return;
    const open = qa('.modal[aria-hidden="false"]');
    const top = open.length ? open[open.length-1] : null;
    if (top) closeModal(top);
  });

  // Kids: Add/Edit sheet
  const kidModal = q('[data-gb-modal="kid"]');
  if (kidModal){
    const title = q('[data-gb-kid-modal-title]', kidModal);
    const subtitle = q('[data-gb-kid-modal-subtitle]', kidModal);
    const addForm = q('#gbKidAddForm', kidModal);
    const editForm = q('#gbKidEditForm', kidModal);
    const editIndex = q('#gbKidEditIndex', kidModal);
    const editName = q('#gbKidEditName', kidModal);

    const showAdd = () => {
      if (title) title.textContent = 'New kid';
      if (subtitle) subtitle.textContent = 'Add a kid to the rotation.';
      if (addForm) addForm.style.display = '';
      if (editForm) editForm.style.display = 'none';
      const i = q('input[name="name"]', addForm);
      if (i) { i.value=''; setTimeout(()=>{ try{i.focus()}catch(e){} }, 30); }
      openModal(kidModal);
    };
    const showEdit = (idx, name) => {
      if (title) title.textContent = 'Edit kid';
      if (subtitle) subtitle.textContent = 'Rename a kid.';
      if (addForm) addForm.style.display = 'none';
      if (editForm) editForm.style.display = '';
      if (editIndex) editIndex.value = String(idx);
      if (editName) editName.value = String(name||'');
      setTimeout(()=>{ try{ editName && editName.focus(); }catch(e){} }, 30);
      openModal(kidModal);
    };

    qa('[data-gb-kid-add]').forEach(b=> b.addEventListener('click', showAdd));
    qa('[data-gb-kid-edit]').forEach(b=> b.addEventListener('click', ()=>{
      showEdit(b.getAttribute('data-kid-index'), b.getAttribute('data-kid-name'));
    }));
  }

  // Settings: Infraction sheet + filter
  const infModal = q('[data-gb-modal="infraction"]');
  if (infModal){
    const title = q('[data-gb-inf-modal-title]', infModal);
    const subtitle = q('[data-gb-inf-modal-subtitle]', infModal);
    const form = q('#gbInfForm', infModal);
    const action = q('#gbInfAction', infModal);
    const id = q('#gbInfId', infModal);
    const label = q('#gbInfLabel', infModal);
    const days = q('#gbInfDays', infModal);
    const mode = q('#gbInfMode', infModal);
    const phone = q('#gbInfPhone', infModal);
    const games = q('#gbInfGames', infModal);
    const other = q('#gbInfOther', infModal);
    const ladder = q('#gbInfLadder', infModal);
    const repairs = q('#gbInfRepairs', infModal);
    const review = q('#gbInfReview', infModal);
    const submit = q('#gbInfSubmit', infModal);

    const reset = () => {
      if (action) action.value = 'add_infraction';
      if (id){ id.readOnly = false; id.value=''; }
      if (label) label.value='';
      if (days) days.value='1';
      if (mode) mode.value='set';
      if (phone) phone.checked = true;
      if (games) games.checked = true;
      if (other) other.checked = true;
      if (ladder) ladder.value='';
      if (repairs) repairs.value='';
      if (review) review.value='0';
      if (title) title.textContent = 'New infraction';
      if (subtitle) subtitle.textContent = 'Create a new infraction.';
      if (submit) submit.textContent = 'Add';
    };

    const openAdd = () => {
      reset();
      openModal(infModal);
      setTimeout(()=>{ try{ id && id.focus(); }catch(e){} }, 30);
    };

    const openEdit = (d) => {
      if (title) title.textContent = 'Edit infraction';
      if (subtitle) subtitle.textContent = 'Update settings for this infraction.';
      if (action) action.value = 'update_infraction';
      if (id){ id.value = String(d.id||''); id.readOnly = true; }
      if (label) label.value = String(d.label||'');
      if (days) days.value = String(d.days||'0');
      if (mode) mode.value = String(d.mode||'set');
      if (phone) phone.checked = String(d.phone||'0') === '1';
      if (games) games.checked = String(d.games||'0') === '1';
      if (other) other.checked = String(d.other||'0') === '1';
      if (ladder) ladder.value = String(d.ladder||'');
      if (review) review.value = String(d.review||'0');
      let rep = '';
      if (d.repairsB64){
        try { rep = atob(String(d.repairsB64||'')); } catch(e) { rep=''; }
      }
      if (repairs) repairs.value = rep;
      if (submit) submit.textContent = 'Save';
      openModal(infModal);
      setTimeout(()=>{ try{ label && label.focus(); }catch(e){} }, 30);
    };

    qa('[data-gb-inf-add]').forEach(b=> b.addEventListener('click', openAdd));
    qa('[data-gb-inf-edit]').forEach(btn=>{
      btn.addEventListener('click', ()=> openEdit({
        id: btn.dataset.id,
        label: btn.dataset.label,
        days: btn.dataset.days,
        mode: btn.dataset.mode,
        phone: btn.dataset.phone,
        games: btn.dataset.games,
        other: btn.dataset.other,
        ladder: btn.dataset.ladder,
        repairsB64: btn.dataset.repairsB64,
        review: btn.dataset.review
      }));
    });

    const filter = q('[data-gb-inf-filter]');
    if (filter){
      const rows = qa('[data-gb-inf-row]');
      const apply = () => {
        const v = (filter.value||'').trim().toLowerCase();
        rows.forEach(r=>{
          const idv = (r.getAttribute('data-id')||'').toLowerCase();
          const lab = (r.getAttribute('data-label')||'').toLowerCase();
          r.style.display = (!v || idv.includes(v) || lab.includes(v)) ? '' : 'none';
        });
      };
      filter.addEventListener('input', apply);
      apply();
    }
  }

  const search = q('[data-gb-search]');
  if (search){
    const items = qa('[data-gb-kid-item]');
    const apply = () => {
      const v=(search.value||'').trim().toLowerCase();
      items.forEach(el=>{
        const name=(el.getAttribute('data-name')||'').toLowerCase();
        el.style.display = (v==='' || name.includes(v)) ? '' : 'none';
      });
    };
    search.addEventListener('input', apply);
    apply();
  }

  const cmd = q('[data-gb-cmd]');
  if (cmd){
    cmd.addEventListener('keydown', (e)=>{
      if (e.key !== 'Enter') return;
      const v=(cmd.value||'').trim();
      if (!v) return;
      const form=q('#gb-command-form');
      q('input[name="cmd"]', form).value=v;
      form.submit();
    });
  }


  // Settings: click-to-edit infractions (no inline JS; keeps CSP strict)
  const infEditForm = q('#gb-inf-edit');
  if (infEditForm){
    const setForm = (d) => {
      const safe = (v)=> (v===undefined || v===null) ? '' : String(v);
      const byId = (id)=> document.getElementById(id);

      const elId = byId('gb_edit_id');
      if (!elId) return;

      elId.value = safe(d.id);
      byId('gb_edit_label').value = safe(d.label);
      byId('gb_edit_days').value = safe(d.days ?? '0');
      byId('gb_edit_mode').value = safe(d.mode || 'set');
      byId('gb_edit_phone').checked = (safe(d.phone) === '1');
      byId('gb_edit_games').checked = (safe(d.games) === '1');
      byId('gb_edit_other').checked = (safe(d.other) === '1');
      byId('gb_edit_ladder').value = safe(d.ladder);
      byId('gb_edit_review').value = safe(d.review ?? '0');

      let rep = '';
      if (d.repairsB64){
        try { rep = atob(safe(d.repairsB64)); } catch(e) { rep = ''; }
      }
      byId('gb_edit_repairs').value = rep;
    };

    qa('[data-gb-edit="1"]').forEach(btn => {
      btn.addEventListener('click', () => {
        setForm({
          id: btn.dataset.id,
          label: btn.dataset.label,
          days: btn.dataset.days,
          mode: btn.dataset.mode,
          phone: btn.dataset.phone,
          games: btn.dataset.games,
          other: btn.dataset.other,
          ladder: btn.dataset.ladder,
          repairsB64: btn.dataset.repairsB64,
          review: btn.dataset.review
        });
        const anchor = document.getElementById('gb-edit-anchor');
        if (anchor) anchor.scrollIntoView({behavior:'smooth', block:'start'});
      });
    });

    const cancel = document.getElementById('gb_edit_cancel');
    if (cancel){
      cancel.addEventListener('click', () => {
        setForm({id:'',label:'',days:'1',mode:'set',phone:'1',games:'1',other:'1',ladder:'',repairsB64:'',review:'0'});
      });
    }
  }

  // Events filter
  const evFilter = q('[data-gb-events-filter]');
  if (evFilter){
    const rows = qa('[data-gb-event-row]');
    const apply = () => {
      const v = (evFilter.value||'').trim().toLowerCase();
      rows.forEach(tr=>{
        const kid = (tr.getAttribute('data-kid')||'').toLowerCase();
        const act = (tr.getAttribute('data-action')||'').toLowerCase();
        const det = (tr.getAttribute('data-details')||'').toLowerCase();
        const show = (v==='' || kid.includes(v) || act.includes(v) || det.includes(v));
        tr.style.display = show ? '' : 'none';
      });
    };
    evFilter.addEventListener('input', apply);
    apply();
  }

  // Convert server "badge" messages to toasts (Phase 8)
  const badgeType = (el) => {
    const c = el.className || '';
    if (c.includes('bad') || c.includes('err')) return 'bad';
    if (c.includes('warn')) return 'warn';
    if (c.includes('ok') || c.includes('success')) return 'ok';
    return 'info';
  };
  qa('.badge').forEach(b=>{
    // Only convert badges that are in their own small message section.
    const p = b.closest('.section');
    const txt = (b.textContent || '').trim();
    if (!txt) return;
    // If section contains more than just this badge, leave it alone.
    const clean = (p ? p.textContent.trim() : txt);
    if (p && clean === txt) {
      gbToast(txt, badgeType(b));
      p.remove();
    }
  });

  // Convert explicit flash nodes if present
  qa('[data-gb-flash]').forEach(n=>{
    const txt = (n.getAttribute('data-gb-flash')||'').trim();
    const type = (n.getAttribute('data-gb-flash-type')||'info').trim();
    if (txt) gbToast(txt, type);
    n.remove();
  });

})();
