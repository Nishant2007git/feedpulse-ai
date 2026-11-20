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
});

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

// ── Notification Bell Polling ───────────────────────────────
function initNotificationBell() {
  const bellBadge = document.getElementById('notif-badge');
  const notifDropdown = document.getElementById('notif-dropdown-content');

  if (!bellBadge) return;

  const baseUrl = document.body.dataset.baseUrl || '';

  async function checkNotifications() {
    try {
      const res = await fetch(`${baseUrl}/api/notifications.php?action=count`);
      if (res.ok) {
        const data = await res.json();
        if (data.unread_count > 0) {
          bellBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
          bellBadge.style.display = 'inline-flex';
        } else {
          bellBadge.style.display = 'none';
        }
      }
    } catch (e) {
      console.warn('Notifications poll paused');
    }
  }

  // Initial check on load
  checkNotifications();

  // Poll every 30 seconds
  setInterval(checkNotifications, 30000);
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
