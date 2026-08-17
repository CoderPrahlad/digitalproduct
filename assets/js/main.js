/* =============================================
   DEVSTORE — Main JavaScript
   Particles, Animations, Modals, Countdown
   ============================================= */

// ---- PAGE LOADER ----
(function () {
  const loader = document.getElementById('page-loader');
  if (!loader) return;
  let hidden = false;
  const hideLoader = () => {
    if (hidden) return;
    hidden = true;
    loader.classList.add('hidden');
  };
  // Normal path: hide once the page has fully loaded.
  window.addEventListener('load', () => setTimeout(hideLoader, 300));
  // Safety net: never let the loader block clicks (e.g. header nav)
  // if an external resource like Google Fonts is slow/unreachable.
  setTimeout(hideLoader, 1500);
})();

// ---- PARTICLES BACKGROUND ----
(function initParticles() {
  const canvas = document.getElementById('particles-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let particles = [];
  let animId;

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  function createParticle() {
    return {
      x: Math.random() * canvas.width,
      y: Math.random() * canvas.height,
      r: Math.random() * 3 + 0.3,
      vx: (Math.random() - 0.5) * 0.3,
      vy: (Math.random() - 0.5) * 0.3,
      alpha: Math.random() * 0.6 + 0.1,
      color: Math.random() > 0.5 ? '124,58,237' : '37,99,235',
    };
  }

  for (let i = 0; i < 120; i++) particles.push(createParticle());

  function drawLine(a, b, dist) {
    const alpha = (1 - dist / 120) * 0.2;
    ctx.beginPath();
    ctx.strokeStyle = `rgba(124,58,237,${alpha})`;
    ctx.lineWidth = 0.5;
    ctx.moveTo(a.x, a.y);
    ctx.lineTo(b.x, b.y);
    ctx.stroke();
  }

  function animate() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles.forEach((p, i) => {
      p.x += p.vx; p.y += p.vy;
      if (p.x < 0) p.x = canvas.width;
      if (p.x > canvas.width) p.x = 0;
      if (p.y < 0) p.y = canvas.height;
      if (p.y > canvas.height) p.y = 0;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${p.color},${p.alpha})`;
      ctx.fill();
      for (let j = i + 1; j < particles.length; j++) {
        const b = particles[j];
        const dist = Math.hypot(p.x - b.x, p.y - b.y);
        if (dist < 120) drawLine(p, b, dist);
      }
    });
    animId = requestAnimationFrame(animate);
  }
  animate();
})();

// ---- SCROLL REVEAL ----
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); } });
}, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// ---- COUNTDOWN TIMERS ----
function startCountdown(el) {
  const endAttr = el.getAttribute('data-end');
  let target;
  if (endAttr) {
    target = new Date(parseInt(endAttr, 10));
  } else {
    target = new Date();
    target.setHours(target.getHours() + 6);
  }
  const interval = setInterval(() => {
    const diff = target - new Date();
    if (diff <= 0) { el.textContent = '00:00:00'; clearInterval(interval); return; }
    const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
    const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
    const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
    el.textContent = `${h}:${m}:${s}`;
  }, 1000);
}
document.querySelectorAll('.countdown-timer').forEach(startCountdown);

// ---- HEADER HEIGHT SYNC (so the mobile menu sits flush under the header, no gap) ----
function syncHeaderHeight() {
  const header = document.querySelector('.site-header');
  if (header) document.documentElement.style.setProperty('--header-h', header.offsetHeight + 'px');
}
syncHeaderHeight();
window.addEventListener('resize', syncHeaderHeight);
window.addEventListener('load', syncHeaderHeight);

// ---- MOBILE NAV ----
const hamburger      = document.querySelector('.hamburger');
const mainNav        = document.querySelector('.main-nav');
const mobileNavClose = document.getElementById('mobileNavClose');
const mobileBackdrop = document.getElementById('mobileNavBackdrop');

function openMobileNav() {
  syncHeaderHeight();
  if (mainNav) mainNav.classList.add('open');
  if (mobileBackdrop) mobileBackdrop.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeMobileNav() {
  if (mainNav) mainNav.classList.remove('open');
  if (mobileBackdrop) mobileBackdrop.classList.remove('open');
  document.body.style.overflow = '';
}

if (hamburger && mainNav) {
  hamburger.addEventListener('click', () => {
    mainNav.classList.contains('open') ? closeMobileNav() : openMobileNav();
  });
  if (mobileNavClose) mobileNavClose.addEventListener('click', closeMobileNav);
  if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeMobileNav);
  // close menu after tapping a link
  mainNav.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', closeMobileNav);
  });
}

// ---- MODALS ----
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}
document.querySelectorAll('[data-modal-open]').forEach(btn => {
  btn.addEventListener('click', () => openModal(btn.dataset.modalOpen));
});
document.querySelectorAll('[data-modal-close]').forEach(btn => {
  btn.addEventListener('click', () => closeModal(btn.dataset.modalClose));
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(overlay.id); });
});

// ---- TOAST ----
function showToast(msg, type = 'success') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span>${type === 'success' ? '✓' : '✗'}</span> ${msg}`;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// Auto flash messages as toasts
document.querySelectorAll('.flash-toast').forEach(el => {
  showToast(el.dataset.msg, el.dataset.type);
  el.remove();
});

// ---- CONFIRM ----
document.querySelectorAll('.confirm-action').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
  });
});

// ---- SUBMIT BUTTON LOADING STATE (login / register / checkout / all forms) ----
(function () {
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      // Respect native validation — don't lock the button if required fields are empty/invalid
      if (form.noValidate !== true && typeof form.checkValidity === 'function' && !form.checkValidity()) return;

      var btn = form.querySelector('button[type="submit"], input[type="submit"]');
      if (btn && !btn.disabled) {
        var isInput = btn.tagName === 'INPUT';
        var label = isInput ? btn.value : btn.innerHTML;
        btn.dataset.loadingReset = '1';
        if (isInput) {
          btn.dataset.originalText = label;
          btn.disabled = true;
          btn.value = 'Please wait...';
        } else {
          btn.dataset.originalHtml = label;
          btn.disabled = true;
          btn.classList.add('btn-loading');
          btn.innerHTML = '<span class="btn-spinner"></span>' + label;
        }
      }

      // Bring back the full-page loader so it's visible until the next page finishes loading
      var pageLoader = document.getElementById('page-loader');
      if (pageLoader) pageLoader.classList.remove('hidden');
    });
  });

  // Safety net: if the page is restored from bfcache (e.g. user hits Back after submit),
  // make sure buttons aren't stuck disabled/loading.
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    document.querySelectorAll('[data-loading-reset]').forEach(function (btn) {
      btn.disabled = false;
      btn.classList.remove('btn-loading');
      if (btn.tagName === 'INPUT' && btn.dataset.originalText) btn.value = btn.dataset.originalText;
      if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
    });
  });
})();

// ---- RAZORPAY CHECKOUT ----
window.initiateRazorpay = function(options) {
  const rzp = new Razorpay(options);
  rzp.on('payment.failed', function(res) {
    showToast('Payment failed: ' + res.error.description, 'error');
  });
  rzp.open();
};

// ---- IMAGE PREVIEW ----
document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
  input.addEventListener('change', function() {
    const preview = document.getElementById(this.dataset.preview);
    if (!preview || !this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(this.files[0]);
  });
});

// ---- PRODUCT IMAGE GALLERY ----
const mainProductImg = document.getElementById('mainProductImg');
document.querySelectorAll('.gallery-thumb').forEach(thumb => {
  thumb.addEventListener('click', () => {
    if (!mainProductImg) return;
    mainProductImg.src = thumb.dataset.img;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
  });
});

// ---- CURRENCY TOGGLE (INR <-> USDT) ----
// Persists the chosen mode across page navigation (sessionStorage), uses the
// live USDT rate (same source the real checkout uses), and never touches
// payment buttons/CTAs — those must always show the real ₹ amount that will
// actually be charged, to avoid misleading "Pay X USDT" text on INR-only gateways.
(function initCurrencyToggle() {
  const usdtBtn = document.querySelector('.fab-usdt');
  const MODE_KEY = 'ds_currency_mode';
  let USD_RATE = 93; // overwritten below with the live rate once fetched
  const originalText = new WeakMap();

  fetch((window.SITE_URL || '') + '/api/get_usdt_rate.php')
    .then(r => r.json()).then(d => { if (d && d.rate) USD_RATE = d.rate; })
    .catch(() => {});

  window.dsGetCurrencyMode = function() {
    try { return sessionStorage.getItem(MODE_KEY) || 'inr'; } catch (e) { return 'inr'; }
  };

  function toUSDT(text) {
    return text.replace(/₹\s?([\d,]+(?:\.\d+)?)/g, (match, num) => {
      const val = parseFloat(num.replace(/,/g, ''));
      if (isNaN(val)) return match;
      return (val / USD_RATE).toFixed(2) + ' USDT';
    });
  }

  function collectTextNodes(node, list) {
    if (node.nodeType === 3) { list.push(node); return; }
    if (node.nodeType === 1) {
      const tag = node.tagName;
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT') return;
      // Never mangle payment CTAs / gateway sections — they must keep showing
      // the real ₹ amount that gets charged.
      if (node.classList && node.classList.contains('no-currency-toggle')) return;
      node.childNodes.forEach(child => collectTextNodes(child, list));
    }
  }

  function applyMode(toUsdtMode) {
    const nodes = [];
    collectTextNodes(document.body, nodes);
    nodes.forEach(node => {
      if (toUsdtMode) {
        if (!/₹/.test(node.textContent)) return;
        if (!originalText.has(node)) originalText.set(node, node.textContent);
        node.textContent = toUSDT(originalText.get(node));
      } else {
        if (originalText.has(node)) node.textContent = originalText.get(node);
      }
    });
    if (usdtBtn) usdtBtn.classList.toggle('active', toUsdtMode);
    document.body.classList.toggle('ds-usdt-mode', toUsdtMode);
  }

  // Re-apply the saved mode automatically on every new page load, so the
  // choice survives navigation (home -> product -> checkout etc.)
  if (window.dsGetCurrencyMode() === 'usdt') {
    // Slight delay so the live rate fetch above has a chance to land first.
    setTimeout(() => applyMode(true), 150);
  }

  if (!usdtBtn) return;
  usdtBtn.addEventListener('click', (e) => {
    e.preventDefault();
    const goingUsdt = window.dsGetCurrencyMode() !== 'usdt';
    try { sessionStorage.setItem(MODE_KEY, goingUsdt ? 'usdt' : 'inr'); } catch (err) {}
    applyMode(goingUsdt);
    showToast(goingUsdt ? 'Prices shown in USDT (approx.)' : 'Prices shown in ₹ INR', 'success');
  });
})();

// ---- AUTO-HIDE ALERTS ----
document.querySelectorAll('.alert[data-auto-hide]').forEach(el => {
  setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 4000);
});

// ---- DARK / LIGHT THEME TOGGLE ----
(function() {
  const toggleBtn = document.getElementById('themeToggle');
  if (!toggleBtn) return;
  function applyTheme(theme) {
    if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    else document.documentElement.removeAttribute('data-theme');
    try { localStorage.setItem('theme', theme); } catch(e) {}
  }
  toggleBtn.addEventListener('click', function() {
    const isLight = document.documentElement.getAttribute('data-theme') === 'light';
    applyTheme(isLight ? 'dark' : 'light');
  });
})();
/* ---- 3D TILT + ZOOM ON PRODUCT CARDS ---- */
(function() {
  function initTilt() {
    document.querySelectorAll('.product-card').forEach(card => {
      card.addEventListener('mouseenter', () => {
        card.style.transition = 'transform .15s ease, box-shadow .4s ease, border-color .3s ease';
      });

      card.addEventListener('mousemove', e => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const cx = rect.width  / 2;
        const cy = rect.height / 2;
        const rotateX = ((y - cy) / cy) * -8;
        const rotateY = ((x - cx) / cx) *  8;
        card.style.transform =
          `perspective(900px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.07) translateY(-6px)`;
        card.style.boxShadow = `0 28px 55px rgba(124,58,237,.45)`;
        card.style.borderColor = `rgba(124,58,237,.6)`;
      });

      card.addEventListener('mouseleave', () => {
        card.style.transition = 'transform .5s cubic-bezier(.34,1.1,.4,1), box-shadow .4s ease, border-color .3s ease';
        card.style.transform  = 'perspective(900px) rotateX(0deg) rotateY(0deg) scale(1) translateY(0)';
        card.style.boxShadow  = '';
        card.style.borderColor = '';
      });
    });
  }

  // DOM ready hone ke baad run karo
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTilt);
  } else {
    initTilt();
  }
})();
/* ---- MOBILE FILTER TOGGLE ---- */
const mobileFilterBtn = document.getElementById('mobileFilterBtn');
const searchSidebar   = document.getElementById('searchSidebar');
if (mobileFilterBtn && searchSidebar) {
  mobileFilterBtn.addEventListener('click', () => {
    searchSidebar.classList.toggle('open');
    mobileFilterBtn.textContent = searchSidebar.classList.contains('open') 
      ? '🔼 Hide Filters' 
      : '🔽 Filters';
  });
}
// --- Digital Circuit Animation ---
document.addEventListener("DOMContentLoaded", function() {
    const canvas = document.getElementById('circuit-canvas');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    let width, height;

    function resize() {
        width = canvas.width = window.innerWidth;
        height = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    const particles = [];
    // Colors matching your video: Orange, Ice Blue, White, Dark Grey
    const colors = ['#ff7a00', '#00f0ff', '#ffffff', '#333344'];
    const maxParticles = 70; // Lines ki ginti
    const gridSize = 20;

    class Particle {
        constructor() {
            this.reset();
        }
        reset() {
            this.x = Math.floor((Math.random() * width) / gridSize) * gridSize;
            this.y = Math.floor((Math.random() * height) / gridSize) * gridSize;
            this.speed = 2; // Speed (gridSize se divide hona chahiye)
            this.direction = Math.floor(Math.random() * 4); // 0: up, 1: right, 2: down, 3: left
            this.color = colors[Math.floor(Math.random() * colors.length)];
            this.history = [];
            this.maxHistory = Math.floor(Math.random() * 60) + 20; // Trail ki length
        }
        update() {
            this.history.push({x: this.x, y: this.y});
            if(this.history.length > this.maxHistory) {
                this.history.shift();
            }

            // Move
            if (this.direction === 0) this.y -= this.speed;
            else if (this.direction === 1) this.x += this.speed;
            else if (this.direction === 2) this.y += this.speed;
            else if (this.direction === 3) this.x -= this.speed;

            // Turn logic (90 degree turns only on grid intersections)
            if (this.x % gridSize === 0 && this.y % gridSize === 0) {
                if (Math.random() > 0.75) { // 25% chance to turn
                    this.direction = (this.direction + (Math.random() > 0.5 ? 1 : 3)) % 4;
                }
            }

            // Reset if goes off screen
            if (this.x < 0 || this.x > width || this.y < 0 || this.y > height) {
                this.reset();
            }
        }
        draw() {
            if(this.history.length < 2) return;
            
            // Draw Line (Trail)
            ctx.beginPath();
            ctx.moveTo(this.history[0].x, this.history[0].y);
            for (let i = 1; i < this.history.length; i++) {
                ctx.lineTo(this.history[i].x, this.history[i].y);
            }
            ctx.strokeStyle = this.color;
            ctx.lineWidth = 1.2;
            ctx.globalAlpha = 0.4;
            ctx.stroke();

           // Draw Glowing Dot (Head)
const isDarkMode = document.documentElement.getAttribute('data-theme') !== 'light';
ctx.beginPath();
ctx.arc(this.x, this.y, 2.5, 0, Math.PI * 2);
ctx.fillStyle = isDarkMode ? this.color : '#64748b';
ctx.globalAlpha = 1;
ctx.shadowBlur = isDarkMode ? 12 : 0; // Light mode me glow off
ctx.shadowColor = isDarkMode ? this.color : 'transparent';
ctx.fill();
ctx.shadowBlur = 0;
        }
    }

    for (let i = 0; i < maxParticles; i++) {
        particles.push(new Particle());
    }

    function animate() {
    // Theme check
    const isDarkMode = document.documentElement.getAttribute('data-theme') !== 'light';

    // Light mode me background clear hoga aur dark mode me dark trail rahegi
    ctx.fillStyle = isDarkMode ? 'rgba(9, 9, 11, 0.15)' : 'rgba(255, 255, 255, 0.25)'; 
    ctx.fillRect(0, 0, width, height);

    particles.forEach(p => {
        p.update();
        p.draw();
    });

    requestAnimationFrame(animate);
}
    animate();
});
