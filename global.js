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
    // toggle generic admin-only elements
    document.querySelectorAll('[data-admin-only], .admin-only, .nav-users').forEach(el => {
      el.style.display = isAdmin ? '' : 'none';
    });
    // Also toggle any static Users links that may already exist; ensure they have icon+label markup
    document.querySelectorAll('a[href$="users.html"]').forEach(el => {
      el.style.display = isAdmin ? '' : 'none';
      if (isAdmin) {
        el.classList.add('nav-users');
        if (!el.querySelector('.icon')) {
          const labelText = (el.textContent || '').trim() || 'Users';
          el.innerHTML = '<span class="icon">👤</span><span class="label">' + labelText + '</span>';
        }
      }
    });
    ensureUsersLink(isAdmin);
  }

  function ensureUsersLink(isAdmin){
    const existing = document.querySelector('[data-users-link], a#navUsers, a.nav-users[href$="users.html"]');
    if (!isAdmin) {
      // remove if exists for non-admin
      document.querySelectorAll('[data-users-link], a#navUsers, a.nav-users[href$="users.html"]').forEach(el => el.remove());
      return;
    }
    if (existing) return;
    // Try to find a sidebar/nav list to append to
    const candidates = [
      document.querySelector('.sidebar ul'),
      document.querySelector('aside ul'),
      document.querySelector('nav ul'),
      document.querySelector('.menu ul'),
      document.querySelector('ul.sidebar-menu')
    ].filter(Boolean);
    if (candidates.length === 0) return;
    const ul = candidates[0];

    const li = document.createElement('li');
    const a = document.createElement('a');
    a.href = base + 'users.html';
    a.setAttribute('data-users-link', 'true');
    a.className = 'nav-users';
    a.innerHTML = '<span class="icon">👤</span><span class="label">Users</span>';

    // Insert after Settings if possible
    let settingsLink = ul.querySelector('a[href$="settings.html"]');
    if (!settingsLink) {
      settingsLink = Array.from(ul.querySelectorAll('a')).find(x => x.textContent.trim().toLowerCase() === 'settings');
    }
    if (settingsLink && settingsLink.parentElement && settingsLink.parentElement.tagName === 'LI') {
      settingsLink.parentElement.insertAdjacentElement('afterend', li);
    } else {
      ul.appendChild(li);
    }
    li.appendChild(a);
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


