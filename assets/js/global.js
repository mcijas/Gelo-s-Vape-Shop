// global.js - shared auth utilities and logout handler
(function(){
  const isIndex = /(?:^|\/)index\.html$/.test(location.pathname) || /\/$/.test(location.pathname);
  const base = location.pathname.includes('/Pages/') ? '../' : '';

  async function ensureAuth() {
    try {
      const res = await fetch(base + 'api/auth/me.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error('unauthorized');
      if (data.user) {
        const name = data.user.full_name || data.user.name || 'Admin';
        sessionStorage.setItem('userName', name);
        if (data.user.role) sessionStorage.setItem('userRole', data.user.role);
        if (data.user.phone !== undefined) sessionStorage.setItem('userPhone', data.user.phone || '');
        if (data.user.employee_id !== undefined) sessionStorage.setItem('userEmployeeId', data.user.employee_id || '');
      }
      return data.user || null;
    } catch (_) {
      // allow index.html
      if (!isIndex) window.location.href = base + 'index.html';
      return null;
    }
  }

  function hookLogout() {
    document.querySelectorAll('a.logout').forEach(a => {
      a.addEventListener('click', async (e) => {
        e.preventDefault();
        try {
          await fetch(base + 'api/auth/logout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '',
            credentials: 'same-origin',
            keepalive: true,
            cache: 'no-store'
          });
        } catch(_) {}
        sessionStorage.removeItem('userName');
        sessionStorage.removeItem('userRole');
        sessionStorage.removeItem('userPhone');
        sessionStorage.removeItem('userEmployeeId');
        window.location.href = base + 'index.html';
      });
    });
  }

  function applyRoleUI(role){
    const isAdmin = role === 'admin';
    document.querySelectorAll('[data-admin-only], .admin-only').forEach(el => {
      el.style.display = isAdmin ? '' : 'none';
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    hookLogout();
    if (!isIndex) {
      ensureAuth().then(user => {
        applyRoleUI(user && user.role);
      });
    }
  });

  window.ensureAuth = ensureAuth;
  window.getCurrentUserName = function(){ return sessionStorage.getItem('userName') || 'Admin'; };
  window.getCurrentUserRole = function(){ return sessionStorage.getItem('userRole') || 'employee'; };
  window.getCurrentUserPhone = function(){ return sessionStorage.getItem('userPhone') || ''; };
  window.getCurrentEmployeeId = function(){ return sessionStorage.getItem('userEmployeeId') || ''; };

  // Shared fuzzy search + autocomplete utility
  // Usage:
  // attachAutocomplete(inputEl, {
  //   getItems: async () => [...strings],
  //   onSelect: (value) => { /* optional: trigger filter */ },
  //   minChars: 1
  // })
  function normalize(s){ return String(s||'').toLowerCase().trim(); }
  function levenshtein(a,b){
    a=normalize(a); b=normalize(b);
    const m=[]; for(let i=0;i<=a.length;i++){ m[i]=[i]; }
    for(let j=1;j<=b.length;j++){ m[0][j]=j; }
    for(let i=1;i<=a.length;i++){
      for(let j=1;j<=b.length;j++){
        const cost = a[i-1]===b[j-1] ? 0 : 1;
        m[i][j] = Math.min(m[i-1][j]+1, m[i][j-1]+1, m[i-1][j-1]+cost);
      }
    }
    return m[a.length][b.length];
  }
  function score(query, candidate){
    const q=normalize(query), c=normalize(candidate);
    if (!q) return 0;
    if (c.startsWith(q)) return 3; // strong prefix
    if (c.includes(q)) return 2;   // contains
    const dist = levenshtein(q,c);
    return dist<=Math.max(1, Math.floor(q.length/3)) ? 1 : 0; // autocorrect tolerance
  }
  function attachAutocomplete(inputEl, opts={}){
    const getItems = opts.getItems || (async()=>[]);
    const onSelect = typeof opts.onSelect==='function' ? opts.onSelect : (v=>{ inputEl.value=v; inputEl.dispatchEvent(new Event('input')); });
    const minChars = Number(opts.minChars||1);
    if (!inputEl) return;

    const box = document.createElement('div');
    box.className='autocomplete-box';
    box.style.cssText='position:absolute; z-index:9999; background:#1f2223; border:1px solid #404040; border-radius:8px; min-width:200px; max-height:220px; overflow:auto; display:none; color:#e7e7e7; box-shadow:0 6px 20px rgba(0,0,0,.4);';
    document.body.appendChild(box);

    function positionBox(){
      const r = inputEl.getBoundingClientRect();
      box.style.left = `${window.scrollX + r.left}px`;
      box.style.top = `${window.scrollY + r.bottom + 4}px`;
      box.style.width = `${r.width}px`;
    }
    window.addEventListener('resize', positionBox);
    window.addEventListener('scroll', positionBox, true);
    inputEl.addEventListener('focus', positionBox);

    let itemsCache = [];
    let selectedIdx = -1;

    async function refresh(){
      const q = inputEl.value || '';
      if (normalize(q).length < minChars) { box.style.display='none'; return; }
      try { itemsCache = await getItems(); } catch(_) { itemsCache=[]; }
      const ranked = itemsCache
        .map(v=>({ v, s: score(q,v) }))
        .filter(x=>x.s>0)
        .sort((a,b)=> b.s - a.s)
        .slice(0, 8);
      if (!ranked.length){ box.style.display='none'; return; }
      box.innerHTML = ranked.map((x,i)=>`<div class="ac-item" data-i="${i}" style="padding:8px 10px; cursor:pointer; border-bottom:1px solid #2a2a2a;">${x.v}</div>`).join('');
      Array.from(box.querySelectorAll('.ac-item')).forEach(el=>{
        el.addEventListener('mouseenter', ()=>{ selectedIdx = Number(el.dataset.i)||0; highlight(); });
        el.addEventListener('click', ()=>{ const idx=Number(el.dataset.i)||0; const val=ranked[idx]?.v; if(val){ onSelect(val); box.style.display='none'; } });
      });
      selectedIdx = 0;
      positionBox();
      box.style.display='block';
      highlight();
    }
    function highlight(){
      Array.from(box.querySelectorAll('.ac-item')).forEach((el,i)=>{ el.style.background = (i===selectedIdx) ? '#2a2a2a' : 'transparent'; });
    }
    inputEl.addEventListener('input', refresh);
    inputEl.addEventListener('keydown', (e)=>{
      if (box.style.display==='none') return;
      if (e.key==='ArrowDown'){ selectedIdx = Math.min(selectedIdx+1, box.querySelectorAll('.ac-item').length-1); highlight(); e.preventDefault(); }
      else if (e.key==='ArrowUp'){ selectedIdx = Math.max(selectedIdx-1, 0); highlight(); e.preventDefault(); }
      else if (e.key==='Enter'){ const el=box.querySelectorAll('.ac-item')[selectedIdx]; if(el){ el.click(); e.preventDefault(); } }
      else if (e.key==='Escape'){ box.style.display='none'; }
    });
    document.addEventListener('click', (e)=>{ if(!box.contains(e.target) && e.target!==inputEl){ box.style.display='none'; } });
  }

  window.attachAutocomplete = attachAutocomplete;
})();
