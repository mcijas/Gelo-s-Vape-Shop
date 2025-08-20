document.addEventListener('DOMContentLoaded', function () {
  const signInButton = document.querySelector('.button-2');
  const usernameInput = document.getElementById('input-1');
  const passInput = document.getElementById('input-2');
  const rememberContainer = document.getElementById('remember-container');
  const rememberCheckbox = document.getElementById('remember-me');
  const togglePass = document.getElementById('toggle-pass');

  async function doLogin() {
    const base = location.pathname.includes('/Pages/') ? '../' : '';
    const username = (usernameInput?.value || '').trim();
    const password = passInput?.value || '';
    if (!username || !password) {
      alert('Please enter username and password');
      return;
    }
    try {
      const body = { username, password, remember: !!rememberCheckbox?.checked };
      const res = await fetch(base + 'api/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(body)
      });
      const ct = res.headers.get('content-type') || '';
      const text = await res.text();
      let data = null;
      if (ct.includes('application/json')) {
        try { data = JSON.parse(text); } catch (_) {}
      }
      if (!data) {
        throw new Error('Server returned non-JSON: ' + text.slice(0, 120));
      }
      if (!res.ok || !data.ok) throw new Error(data.error || 'Login failed');
      sessionStorage.setItem('userName', data.user?.name || 'Admin');
      // persist remember
      if (rememberCheckbox?.checked) {
        localStorage.setItem('rememberUsername', username);
        localStorage.setItem('rememberChecked', '1');
      } else {
        localStorage.removeItem('rememberUsername');
        localStorage.removeItem('rememberChecked');
      }
      window.location.href = base + 'Pages/dashboard.html';
    } catch (e) {
      alert(e.message || 'Login error');
    }
  }

  if (signInButton) {
    signInButton.addEventListener('click', function (e) {
      e.preventDefault();
      doLogin();
    });
  }
  passInput?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') doLogin();
  });

  // show/hide password toggle
  if (togglePass && passInput) {
    const toggle = () => {
      const isPassword = passInput.getAttribute('type') === 'password';
      passInput.setAttribute('type', isPassword ? 'text' : 'password');
    };
    togglePass.addEventListener('click', (e) => {
      e.preventDefault();
      toggle();
    });
  }

  // remember me widget behavior and prefill
  if (rememberContainer && rememberCheckbox) {
    const applyAria = () => {
      rememberContainer.setAttribute('aria-checked', rememberCheckbox.checked ? 'true' : 'false');
    };
    rememberContainer.addEventListener('click', (e) => {
      e.preventDefault();
      rememberCheckbox.checked = !rememberCheckbox.checked;
      applyAria();
    });
    rememberContainer.addEventListener('keydown', (e) => {
      if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        rememberCheckbox.checked = !rememberCheckbox.checked;
        applyAria();
      }
    });
    // prefill from localStorage
    const remembered = localStorage.getItem('rememberUsername') || '';
    const rememberedChecked = localStorage.getItem('rememberChecked') === '1';
    if (remembered) usernameInput && (usernameInput.value = remembered);
    if (rememberedChecked) rememberCheckbox.checked = true;
    applyAria();
  }

  // if already authenticated, redirect from login page
  (async function maybeRedirectIfAuthed(){
    try {
      const base = location.pathname.includes('/Pages/') ? '../' : '';
      const res = await fetch(base + 'api/auth/me.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (data && data.ok) {
        window.location.href = base + 'Pages/dashboard.html';
      }
    } catch (_) { /* ignore */ }
  })();
});