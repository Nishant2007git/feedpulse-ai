/**
 * FeedPulse AI — Main Frontend Script
 * Notification polling, Theme toggling, Spline 3D fallback, Realtime AI feedback
 */

document.addEventListener('DOMContentLoaded', () => {
  initThemeToggle();
  initNotificationBell();
  initSplineFallback();
  initStarRatings();
  initAiSentimentAnalyzer();
  initPwaServiceWorker();
});

// ── PWA Service Worker Registration ────────────────────────
function initPwaServiceWorker() {
  if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
  }
}

// ── Theme Switcher ─────────────────────────────────────────
function initThemeToggle() {
  const toggleBtn = document.getElementById('fp-theme-toggle');
  const savedTheme = localStorage.getItem('fp-theme') || 'dark';
  document.documentElement.setAttribute('data-theme', savedTheme);

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme');
      const next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('fp-theme', next);
    });
  }
}

// ── Notification Bell Polling & Realtime Toasts ────────────
function initNotificationBell() {
  const bellBadge = document.getElementById('notif-badge');
  if (!bellBadge) return;

  const baseUrl = document.body.dataset.baseUrl || '';
  let lastUnreadCount = null;

  async function checkNotifications() {
    try {
      const res = await fetch(`${baseUrl}/api/notifications.php?action=count`);
      if (res.ok) {
        const data = await res.json();
        const currentCount = parseInt(data.unread_count || 0, 10);

        if (currentCount > 0) {
          bellBadge.textContent = currentCount > 99 ? '99+' : currentCount;
          bellBadge.style.display = 'inline-flex';
        } else {
          bellBadge.style.display = 'none';
        }

        // If new notifications arrived while viewing the portal, trigger a live toast
        if (lastUnreadCount !== null && currentCount > lastUnreadCount) {
          fetchLatestNotificationToast(baseUrl);
        }
        lastUnreadCount = currentCount;
      }
    } catch (e) {
      console.warn('Notifications poll paused');
    }
  }

  async function fetchLatestNotificationToast(baseUrl) {
    try {
      const res = await fetch(`${baseUrl}/api/notifications.php?action=list`);
      if (res.ok) {
        const data = await res.json();
        if (data.notifications && data.notifications.length > 0) {
          const top = data.notifications[0];
          showNotificationToast(top.title || 'FeedPulse Update', top.message || 'You have a new update.', `${baseUrl}/notifications.php`);
        }
      }
    } catch (e) {}
  }

  // Initial check on load
  checkNotifications();

  // Poll every 20 seconds
  setInterval(checkNotifications, 20000);
}

// ── Floating Real-time Toast System ─────────────────────────
function showNotificationToast(title, message, link) {
  let container = document.getElementById('fp-toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'fp-toast-container';
    container.className = 'position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '10500';
    document.body.appendChild(container);
  }

  const toastId = 'fp-toast-' + Date.now();
  const toastHtml = `
    <div id="${toastId}" class="fp-live-toast p-3 mb-2 rounded-3 shadow-lg" style="background: rgba(10, 22, 40, 0.95); border: 1px solid rgba(6, 182, 212, 0.45); backdrop-filter: blur(16px); color: #fff; max-width: 360px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); animation: fpSlideUp 0.35s ease;">
      <div class="d-flex align-items-start justify-content-between gap-2">
        <div class="d-flex gap-2">
          <i class="bi bi-bell-fill text-info fs-5 mt-1"></i>
          <div>
            <div class="fw-bold small text-info">${escapeHtml(title)}</div>
            <div class="small text-secondary" style="line-height: 1.4;">${escapeHtml(message)}</div>
            ${link ? `<a href="${link}" class="small text-accent text-decoration-none fw-semibold mt-1 d-inline-block">Open Notifications &rarr;</a>` : ''}
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" style="font-size: 0.65rem;" onclick="document.getElementById('${toastId}').remove()"></button>
      </div>
    </div>
  `;
  container.insertAdjacentHTML('beforeend', toastHtml);

  // Auto-dismiss after 6 seconds
  setTimeout(() => {
    const el = document.getElementById(toastId);
    if (el) {
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.4s ease';
      setTimeout(() => el.remove(), 400);
    }
  }, 6000);
}

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// ── Spline 3D Canvas Fallback ────────────────────────────────
function initSplineFallback() {
  const splineViewer = document.querySelector('spline-viewer');
  const fallbackContainer = document.getElementById('spline-fallback-canvas');

  if (!fallbackContainer) return;

  // Check if WebGL is supported
  const canvas = document.createElement('canvas');
  const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');

  if (!gl && splineViewer) {
    splineViewer.style.display = 'none';
    fallbackContainer.style.display = 'block';
    renderFallback3DOrb(fallbackContainer);
  }
}

function renderFallback3DOrb(container) {
  container.innerHTML = `
    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
      <div style="width: 260px; height: 260px; border-radius: 50%; background: radial-gradient(circle at 35% 35%, #06B6D4, #4F46E5 60%, #1E40AF); box-shadow: 0 0 50px rgba(79, 70, 229, 0.6); animation: fpPulse 4s infinite ease-in-out;"></div>
    </div>
    <style>
      @keyframes fpPulse {
        0%, 100% { transform: scale(1) rotate(0deg); filter: hue-rotate(0deg); }
        50% { transform: scale(1.08) rotate(180deg); filter: hue-rotate(45deg); }
      }
    </style>
  `;
}

// ── Star Ratings ────────────────────────────────────────────
function initStarRatings() {
  const ratingGroups = document.querySelectorAll('.fp-rating-group input');
  ratingGroups.forEach(radio => {
    radio.addEventListener('change', (e) => {
      const targetLabel = document.getElementById('selected-rating-label');
      if (targetLabel) {
        const labels = ['', 'Poor (1/5)', 'Fair (2/5)', 'Good (3/5)', 'Very Good (4/5)', 'Excellent (5/5)'];
        targetLabel.textContent = labels[parseInt(e.target.value, 10)] || '';
      }
    });
  });
}

// ── Realtime AI Sentiment Analyzer ──────────────────────────
function initAiSentimentAnalyzer() {
  const commentBox = document.getElementById('feedback-comment-input');
  const sentimentDisplay = document.getElementById('ai-sentiment-preview');

  if (!commentBox || !sentimentDisplay) return;

  let debounceTimer = null;
  commentBox.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    const text = commentBox.value.trim();

    if (text.length < 15) {
      sentimentDisplay.style.display = 'none';
      return;
    }

    debounceTimer = setTimeout(async () => {
      try {
        const formData = new FormData();
        formData.append('text', text);

        const res = await fetch('/feedpulse-ai/api/ai-sentiment.php', {
          method: 'POST',
          body: formData
        });

        if (res.ok) {
          const data = await res.json();
          sentimentDisplay.style.display = 'inline-flex';
          sentimentDisplay.className = `fp-badge fp-badge--ai fp-badge--${data.badge_class}`;
          sentimentDisplay.innerHTML = `${data.icon} AI Sentiment: <strong>${data.sentiment}</strong> (${Math.round(data.confidence * 100)}%)`;
        }
      } catch (err) {
        // Silently skip if network or endpoint unreached
      }
    }, 400);
  });
}
