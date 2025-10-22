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
    // toggle generic admin-only elements (fallback)
    document.querySelectorAll('[data-admin-only], .admin-only, .nav-users').forEach(el => {
      el.style.display = isAdmin ? '' : 'none';
    });
    // Ensure Users is a top-level sidebar item (not under Settings)
    ensureUsersNav(isAdmin);
    // Clean up Settings submenu (remove deprecated items, ensure structure)
    ensureUsersUnderSettings(isAdmin);
  }

  function ensureUsersUnderSettings(isAdmin){
    // Find the Settings <a>
    let settingsLink = document.querySelector('a[href$="settings.html"]');
    if (!settingsLink) {
      settingsLink = Array.from(document.querySelectorAll('a')).find(x => (x.textContent || '').trim().toLowerCase() === 'settings');
    }
    if (!settingsLink) return;

    // Ensure we reference the proper LI
    const li = settingsLink.closest('li') || settingsLink.parentElement;
    if (!li) return;
    li.classList.add('has-submenu');

    // Ensure submenu UL exists
    let submenu = li.querySelector('ul.submenu');
    if (!submenu) {
      submenu = document.createElement('ul');
      submenu.className = 'submenu';
      li.appendChild(submenu);
    }

    // Helper to ensure a submenu item exists
    function ensureItem(selectorText, href, label, attrs = {}){
      let item = Array.from(submenu.querySelectorAll('a')).find(a => a.textContent.trim() === selectorText || a.getAttribute('href') === href);
      if (!item) {
        const liItem = document.createElement('li');
        item = document.createElement('a');
        item.href = base + href;
        item.textContent = label;
        Object.entries(attrs).forEach(([k,v])=> item.setAttribute(k,v));
        liItem.appendChild(item);
        submenu.appendChild(liItem);
      }
      return item;
    }

    // Remove any deprecated submenu items that are now part of the main Settings page
    Array.from(submenu.querySelectorAll('a')).forEach(a=>{
      const txt=(a.textContent||'').trim().toLowerCase();
      if(txt==='theme preferences'||txt==='account settings'){
        const li=a.closest('li');
        if(li) li.remove();
      }
    });
    // Always remove any Users submenu entries; we want it in the sidebar
    Array.from(submenu.querySelectorAll('a')).forEach(a => {
      if ((a.textContent || '').trim().toLowerCase() === 'users' || /users\.html$/i.test(a.getAttribute('href') || '')) {
        const liItem = a.closest('li');
        if (liItem) liItem.remove();
      }
    });
  }

  function ensureUsersNav(isAdmin){
    // Find the main sidebar nav list
    const navList = document.querySelector('.nav-list') || document.querySelector('.sidebar .nav ul') || document.querySelector('.sidebar ul');
    if (!navList) return;

    // Ensure a standalone Users nav item exists
    let usersLi = document.getElementById('usersNav');
    if (!usersLi) {
      usersLi = document.createElement('li');
      usersLi.id = 'usersNav';
      usersLi.style.display = 'none';
      const a = document.createElement('a');
      a.href = base + 'users.html';
      const icon = document.createElement('span');
      icon.className = 'icon';
      icon.textContent = '👤';
      const label = document.createElement('span');
      label.className = 'label';
      label.textContent = 'Users';
      a.appendChild(icon);
      a.appendChild(label);
      usersLi.appendChild(a);
      navList.appendChild(usersLi);
    }

    // Highlight active state when on users.html
    const usersLink = usersLi.querySelector('a[href$="users.html"]');
    if (usersLink) {
      const isUsersPage = /\/users\.html$/i.test(location.pathname);
      if (isUsersPage) usersLink.classList.add('active');
    }

    // Show for admin, hide otherwise
    usersLi.style.display = isAdmin ? '' : 'none';
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


