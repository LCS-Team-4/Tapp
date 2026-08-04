// app.js — shell interactions ported from
// _design-reference/tapp-php/assets/app.js (switchTab, tickClock), rewritten
// to use data-panel attributes + event delegation instead of inline onclick=
// handlers, matching the current build's pattern (see
// ../Attendance-tracking-system/frontend/employee/src/main.js). Also wires
// the hamburger nav toggle from that same current-build shell chrome.

function switchTab(btn) {
  const shell = btn.closest('.app-shell');
  if (!shell) return;
  shell.querySelectorAll('.nav-item').forEach((n) => n.classList.remove('active'));
  btn.classList.add('active');
  shell.querySelectorAll('.tab-panel').forEach((p) => p.classList.remove('active'));
  const panel = document.getElementById(btn.dataset.panel);
  if (panel) panel.classList.add('active');
  const titleEl = shell.querySelector('.topbar-title h2');
  if (titleEl) titleEl.textContent = btn.textContent.trim();
}

function closeNavPanel() {
  const navPanel = document.getElementById('nav-panel');
  const navToggle = document.getElementById('nav-toggle');
  if (navPanel) navPanel.classList.remove('open');
  if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
}

// live clock tick
function tickClock() {
  const el = document.getElementById('live-clock');
  const dateEl = document.getElementById('live-clock-date');
  if (!el) return;
  const now = new Date();
  let h = now.getHours();
  const m = now.getMinutes();
  const s = now.getSeconds();
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12;
  h = h ? h : 12;
  el.textContent = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')} ${ampm}`;
  if (dateEl) {
    dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  }
}

document.addEventListener('click', (event) => {
  const navToggle = event.target.closest('#nav-toggle');
  if (navToggle) {
    event.stopPropagation();
    const navPanel = document.getElementById('nav-panel');
    if (navPanel) {
      const isOpen = navPanel.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
    }
    return;
  }

  const navItem = event.target.closest('.nav-item');
  if (navItem) {
    switchTab(navItem);
    closeNavPanel();
    return;
  }

  const navPanel = document.getElementById('nav-panel');
  if (navPanel && navPanel.classList.contains('open') && !navPanel.contains(event.target)) {
    closeNavPanel();
  }
});

setInterval(tickClock, 1000);
