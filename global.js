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
})();


