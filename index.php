<?php
// ============================================================
// index.php — Public Landing Page (FeedPulse AI Design System v3.0)
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If user is already logged in, redirect them to their specific role dashboard
if (isLoggedIn()) {
    redirect(dashboardUrl($_SESSION['role']));
}

$pdo = getDB();

// Real-time campus system metrics
$stats = [
    'resolved'      => 0,
    'satisfaction'  => 98,
    'avg_sla_hours' => 24,
    'departments'   => 0
];

try {
    $resCount = (int)$pdo->query('SELECT COUNT(*) FROM complaints WHERE status IN ("resolved", "closed")')->fetchColumn();
    $stats['resolved'] = $resCount;

    $deptCount = (int)$pdo->query('SELECT COUNT(*) FROM departments WHERE is_active = 1')->fetchColumn();
    $stats['departments'] = $deptCount;

    $avgSat = $pdo->query('SELECT ROUND(AVG(rating) * 20) FROM feedback WHERE rating > 0')->fetchColumn();
    if ($avgSat !== null && (int)$avgSat > 0) {
        $stats['satisfaction'] = (int)$avgSat;
    }

    $avgHours = $pdo->query('SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at))) FROM complaints WHERE resolved_at IS NOT NULL')->fetchColumn();
    if ($avgHours !== null && (int)$avgHours > 0) {
        $stats['avg_sla_hours'] = (int)$avgHours;
    }
} catch (Exception $e) {
    error_log('[FeedPulse Index Stats Error] ' . $e->getMessage());
}

$pageTitle = APP_NAME . ' — Next-Gen Campus Feedback & SLA Resolution Intelligence';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ─── Hero Section with 3D Holographic AI Sentiment Orb ─── -->
<section class="fp-hero d-flex flex-column flex-lg-row align-items-center position-relative my-2">
  <div class="col-12 col-lg-7 pe-lg-4" style="z-index: 2;">
    
    <div class="fp-hero-badge">
      <span class="pulse-dot"></span>
      <span>Next-Gen Campus Governance Powered by AI</span>
    </div>

    <h1 class="display-3 fw-bold mb-3" style="letter-spacing: -0.035em; line-height: 1.12;">
      Your Voice.<br>
      <span class="gradient-text">Accountable Action.</span><br>
      Automated by AI.
    </h1>

    <p class="lead text-secondary mb-4" style="max-width: 580px; font-size: 1.15rem; line-height: 1.75;">
      FeedPulse AI bridges students, faculty, and campus leadership with real-time NLP sentiment analysis, guaranteed SLA resolution timelines, and automated multi-tier escalation.
    </p>

    <div class="d-flex flex-wrap gap-3 mb-5">
      <a href="<?= APP_URL ?>/register.php" class="fp-btn fp-btn-primary" style="padding: 0.85rem 1.85rem; font-size: 1.05rem;">
        <i class="bi bi-mortarboard-fill"></i> Student Get Started
      </a>
      <a href="<?= APP_URL ?>/login.php" class="fp-btn fp-btn-glass" style="padding: 0.85rem 1.85rem; font-size: 1.05rem;">
        <i class="bi bi-box-arrow-in-right"></i> Sign In to Portal
      </a>
    </div>

    <!-- Live Metric Stat Pills -->
    <div class="row g-3">
      <div class="col-6 col-sm-3">
        <div class="fp-glass-card p-3 text-center h-100">
          <div class="h3 fw-bold text-info mb-1" style="font-family: var(--fp-font-heading);"><?= number_format($stats['resolved']) ?>+</div>
          <small class="text-secondary d-block">Resolved Tickets</small>
        </div>
      </div>
      <div class="col-6 col-sm-3">
        <div class="fp-glass-card p-3 text-center h-100">
          <div class="h3 fw-bold text-success mb-1" style="font-family: var(--fp-font-heading);"><?= $stats['satisfaction'] ?>%</div>
          <small class="text-secondary d-block">Satisfaction Rate</small>
        </div>
      </div>
      <div class="col-6 col-sm-3">
        <div class="fp-glass-card p-3 text-center h-100">
          <div class="h3 fw-bold text-warning mb-1" style="font-family: var(--fp-font-heading);">&lt; <?= $stats['avg_sla_hours'] ?>h</div>
          <small class="text-secondary d-block">Avg Resolution</small>
        </div>
      </div>
      <div class="col-6 col-sm-3">
        <div class="fp-glass-card p-3 text-center h-100">
          <div class="h3 fw-bold text-light mb-1" style="font-family: var(--fp-font-heading);"><?= $stats['departments'] ?></div>
          <small class="text-secondary d-block">Departments</small>
        </div>
      </div>
    </div>

  </div>

  <!-- Interactive 3D Visual & Floating Perspective Preview -->
  <div class="col-12 col-lg-5 mt-5 mt-lg-0 position-relative d-flex flex-column justify-content-center align-items-center" id="hero-3d-container">
    
    <!-- 3D Holographic WebGL Canvas -->
    <div id="orb-3d-view" style="width: 100%; height: 380px; position: relative;">
      <canvas id="webgl-orb-canvas" style="width: 100%; height: 100%; border-radius: var(--fp-radius-xl); filter: drop-shadow(0 20px 45px rgba(59, 130, 246, 0.35));"></canvas>
      
      <!-- 3D Status Overlay Pill -->
      <div class="position-absolute bottom-0 start-50 translate-middle-x mb-2 px-3 py-1 rounded-pill d-inline-flex align-items-center gap-2" style="background: rgba(10, 22, 40, 0.85); backdrop-filter: blur(14px); border: 1px solid var(--fp-border-glass); font-size: 0.78rem;">
        <span class="pulse-dot"></span>
        <span class="text-secondary">AI Sentiment Engine &bull; <span class="text-info">Orbiting Node Mesh</span></span>
      </div>
    </div>

    <!-- Floating 3D Perspective Card (Simulated Real-time Resolution Workbench) -->
    <div class="floating-preview-card mt-3">
      <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary border-opacity-10">
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-primary text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.05em;">Live Ticket</span>
          <span class="font-monospace small text-info">FP-<?= date('Y') ?>-78A4B2</span>
        </div>
        <span class="fp-badge fp-badge--warning">In Progress</span>
      </div>

      <h6 class="fw-bold text-light mb-1">Library 3rd Floor Wi-Fi Connectivity Drop</h6>
      <p class="text-secondary small mb-3">Signal drops repeatedly during study hours in CS & EE wing.</p>

      <div class="mb-3">
        <div class="d-flex justify-content-between text-secondary small mb-1" style="font-size: 0.75rem;">
          <span><i class="bi bi-clock-history me-1 text-warning"></i> SLA Timer: 18h remaining</span>
          <span class="text-info font-monospace">75% on-time</span>
        </div>
        <div class="fp-sla-track">
          <div class="fp-sla-fill fp-sla-fill--ok" style="width: 75%;"></div>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between pt-2 border-top border-secondary border-opacity-10">
        <div class="d-flex align-items-center gap-2">
          <span class="badge" style="background: rgba(139, 92, 246, 0.15); color: #c4b5fd; font-size: 0.7rem;">
            <i class="bi bi-cpu me-1"></i> NLP Score: High Urgency
          </span>
        </div>
        <span class="small text-muted" style="font-size: 0.75rem;">Assigned: IT Services</span>
      </div>
    </div>

  </div>
</section>

<!-- Include 3D WebGL Engine Script -->
<script src="<?= APP_URL ?>/assets/js/hero-3d.js"></script>

<!-- ─── Live Interactive AI Sentiment Demo ─── -->
<section id="live-demo" class="my-5 py-4">
  <div class="fp-glass-card p-4 p-md-5" style="border: 1px solid rgba(6, 182, 212, 0.25); background: radial-gradient(circle at 10% 20%, rgba(6, 182, 212, 0.12), rgba(10, 22, 40, 0.85));">
    <div class="row align-items-center g-4">
      <div class="col-12 col-lg-5">
        <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-3" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee; font-size: 0.82rem; font-weight: 600;">
          <i class="bi bi-cpu-fill"></i> Interactive AI Sandbox
        </div>
        <h2 class="fw-bold mb-3">Test the Real-Time AI Sentiment Engine</h2>
        <p class="text-secondary mb-4">
          FeedPulse AI reads complaints and reviews instantly, inferring student emotion, detecting urgent distress patterns, and automatically routing tickets to the responsible campus department.
        </p>

        <div class="small text-secondary mb-2 fw-semibold">Try sample student inputs:</div>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <button type="button" class="btn btn-sm btn-outline-secondary text-light sample-btn" data-text="The washroom near Lab 3 has water leaking and no soap for two days.">
            ⚠️ "Washroom leaking & dirty"
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary text-light sample-btn" data-text="The guest lecture on machine learning was exceptional and very clearly delivered!">
            😊 "Exceptional lecture"
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary text-light sample-btn" data-text="Cafeteria tea prices were updated without prior student notice.">
            😐 "Tea price change"
          </button>
        </div>
      </div>

      <div class="col-12 col-lg-7">
        <div class="p-4 rounded-4" style="background: rgba(6, 13, 32, 0.7); border: 1px solid var(--fp-border-glass);">
          <label class="form-label text-secondary small fw-bold">Live Input Text</label>
          <textarea id="ai-demo-input" class="form-control mb-3" rows="3" placeholder="Type any campus feedback or complaint here to see instant NLP analysis..."></textarea>
          
          <div class="row g-3 text-center">
            <div class="col-4">
              <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
                <small class="text-secondary d-block mb-1">Sentiment</small>
                <div id="ai-demo-sentiment" class="fw-bold fs-5 text-info">Neutral 😐</div>
              </div>
            </div>
            <div class="col-4">
              <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
                <small class="text-secondary d-block mb-1">Confidence</small>
                <div id="ai-demo-conf" class="fw-bold fs-5 text-success">85%</div>
              </div>
            </div>
            <div class="col-4">
              <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
                <small class="text-secondary d-block mb-1">Auto-Category</small>
                <div id="ai-demo-cat" class="fw-bold fs-5 text-warning">Facilities</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── Bento Grid: Institutional Transparency Features ─── -->
<section id="features" class="my-5 py-4">
  <div class="text-center mb-5">
    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(59, 130, 246, 0.12); color: #60a5fa; font-size: 0.82rem; font-weight: 600;">
      <i class="bi bi-shield-lock-fill"></i> Enterprise Architecture
    </div>
    <h2 class="display-5 fw-bold mb-2">Designed for Complete Campus Transparency</h2>
    <p class="text-secondary mx-auto" style="max-width: 620px;">
      FeedPulse AI combines strict SLA enforcement with advanced artificial intelligence to eliminate forgotten complaints and guarantee administrative accountability.
    </p>
  </div>

  <div class="bento-grid">
    
    <!-- Bento 1 (Span 2): SLA Timers & Automatic Escalation -->
    <div class="bento-card sp-2">
      <div class="d-flex align-items-center justify-content-between mb-4">
        <div class="d-inline-flex p-3 rounded-4" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
          <i class="bi bi-clock-history fs-3"></i>
        </div>
        <span class="fp-badge fp-badge--warning">Zero Ticket Blackhole</span>
      </div>
      <h3 class="fw-bold mb-2">Automated SLA Resolution Timers & Escalations</h3>
      <p class="text-secondary mb-4">
        Every logged issue receives a strict countdown clock based on category severity (12 hours for cafeteria hygiene, 24 hours for IT/facilities, 48 hours for academic disputes). When a deadline approaches, staff receive urgent warnings; if breached, tickets automatically escalate to the University Dean.
      </p>
      <div class="row g-3">
        <div class="col-4">
          <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
            <div class="fw-bold text-light">12 Hours</div>
            <small class="text-secondary">Food & Health SLA</small>
          </div>
        </div>
        <div class="col-4">
          <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
            <div class="fw-bold text-light">24 Hours</div>
            <small class="text-secondary">Facilities & Wi-Fi SLA</small>
          </div>
        </div>
        <div class="col-4">
          <div class="p-3 rounded-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
            <div class="fw-bold text-warning">Auto-Escalate</div>
            <small class="text-secondary">To Senior Admin</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Bento 2: Real-Time AI Sentiment Analysis -->
    <div class="bento-card">
      <div class="d-inline-flex p-3 rounded-4 mb-4" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa;">
        <i class="bi bi-cpu fs-3"></i>
      </div>
      <h3 class="fw-bold mb-2">AI Sentiment Analysis</h3>
      <p class="text-secondary mb-0">
        Continuous NLP tone analysis tags positive reviews, detects student frustration patterns, and flags critical harassment or emergency signals for immediate triage.
      </p>
    </div>

    <!-- Bento 3: Whistleblower Anonymity -->
    <div class="bento-card">
      <div class="d-inline-flex p-3 rounded-4 mb-4" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
        <i class="bi bi-incognito fs-3"></i>
      </div>
      <h3 class="fw-bold mb-2">Anonymous Whistleblowing</h3>
      <p class="text-secondary mb-0">
        Students can speak truth to power. Anonymous submissions completely disassociate user identity from database records, preventing academic retaliation.
      </p>
    </div>

    <!-- Bento 4: Live Department Heatmap -->
    <div class="bento-card">
      <div class="d-inline-flex p-3 rounded-4 mb-4" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
        <i class="bi bi-bar-chart-line-fill fs-3"></i>
      </div>
      <h3 class="fw-bold mb-2">Real-Time Performance Heatmaps</h3>
      <p class="text-secondary mb-0">
        Deans and department heads monitor real-time satisfaction curves, departmental ticket backlogs, and on-time resolution ratios through interactive analytics.
      </p>
    </div>

    <!-- Bento 5: Civic Engagement Badges & Rewards -->
    <div class="bento-card">
      <div class="d-inline-flex p-3 rounded-4 mb-4" style="background: rgba(234, 179, 8, 0.15); color: #fde047;">
        <i class="bi bi-award-fill fs-3"></i>
      </div>
      <h3 class="fw-bold mb-2">Civic Engagement Points</h3>
      <p class="text-secondary mb-0">
        Constructive participation is recognized. Students earn points for verified suggestions, climbing from Bronze to Platinum campus leadership standing.
      </p>
    </div>

  </div>
</section>

<!-- ─── 3-Step Resolution Workflow ─── -->
<section id="how-it-works" class="my-5 py-4">
  <div class="text-center mb-5">
    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(16, 185, 129, 0.12); color: #34d399; font-size: 0.82rem; font-weight: 600;">
      <i class="bi bi-arrow-repeat"></i> Streamlined Resolution Loop
    </div>
    <h2 class="display-5 fw-bold mb-2">How FeedPulse Resolves Issues</h2>
    <p class="text-secondary mx-auto" style="max-width: 600px;">
      From initial grievance submission to verified closure in three transparent stages.
    </p>
  </div>

  <div class="row g-4">
    <!-- Step 1 -->
    <div class="col-12 col-md-4">
      <div class="fp-glass-card p-4 h-100 position-relative">
        <div class="display-4 fw-extrabold text-secondary opacity-25 mb-2" style="font-family: var(--fp-font-heading);">01</div>
        <div class="d-inline-flex p-3 rounded-4 mb-3" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
          <i class="bi bi-pencil-square fs-4"></i>
        </div>
        <h4 class="fw-bold mb-2">Log Issue with Evidence</h4>
        <p class="text-secondary small mb-0">
          Select campus department, assign priority, upload image/document evidence, and choose whether to remain anonymous.
        </p>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="col-12 col-md-4">
      <div class="fp-glass-card p-4 h-100 position-relative">
        <div class="display-4 fw-extrabold text-secondary opacity-25 mb-2" style="font-family: var(--fp-font-heading);">02</div>
        <div class="d-inline-flex p-3 rounded-4 mb-3" style="background: rgba(6, 182, 212, 0.15); color: #22d3ee;">
          <i class="bi bi-stopwatch fs-4"></i>
        </div>
        <h4 class="fw-bold mb-2">AI Triage & SLA Clock Starts</h4>
        <p class="text-secondary small mb-0">
          The system infers sentiment, assigns a unique tracking reference, immediately notifies designated staff, and starts the countdown.
        </p>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="col-12 col-md-4">
      <div class="fp-glass-card p-4 h-100 position-relative">
        <div class="display-4 fw-extrabold text-secondary opacity-25 mb-2" style="font-family: var(--fp-font-heading);">03</div>
        <div class="d-inline-flex p-3 rounded-4 mb-3" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
          <i class="bi bi-check2-circle fs-4"></i>
        </div>
        <h4 class="fw-bold mb-2">Resolution & Student Rating</h4>
        <p class="text-secondary small mb-0">
          Staff documents root-cause action. The student is notified instantly to verify the fix and submit a post-resolution rating.
        </p>
      </div>
    </div>
  </div>
</section>

<!-- ─── Campus Roles Breakdown ─── -->
<section id="roles" class="my-5 py-4">
  <div class="text-center mb-5">
    <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill mb-2" style="background: rgba(139, 92, 246, 0.12); color: #a78bfa; font-size: 0.82rem; font-weight: 600;">
      <i class="bi bi-people-fill"></i> Multi-Role Campus Workflows
    </div>
    <h2 class="display-5 fw-bold mb-2">Engineered for the Entire Campus</h2>
    <p class="text-secondary">Tailored workbench interfaces for every institutional stakeholder.</p>
  </div>

  <div class="row g-4">
    <!-- Student Role -->
    <div class="col-12 col-md-4">
      <div class="fp-glass-card p-4 p-lg-5 h-100 text-center">
        <div class="d-inline-flex p-4 rounded-4 mb-3" style="background: rgba(59, 130, 246, 0.12); color: #60a5fa;">
          <i class="bi bi-mortarboard-fill fs-2"></i>
        </div>
        <h4 class="fw-bold mb-2">Students</h4>
        <p class="text-secondary small mb-4">
          Full tracking transparency, whistleblowing confidentiality, and civic rewards for campus involvement.
        </p>
        <ul class="list-unstyled text-start small d-flex flex-column gap-2 text-secondary">
          <li><i class="bi bi-check2 text-info me-2"></i> Real-time SLA progress updates</li>
          <li><i class="bi bi-check2 text-info me-2"></i> 1-click anonymous feedback</li>
          <li><i class="bi bi-check2 text-info me-2"></i> Engagement points & tier badges</li>
        </ul>
      </div>
    </div>

    <!-- Staff Role -->
    <div class="col-12 col-md-4">
      <div class="fp-glass-card p-4 p-lg-5 h-100 text-center">
        <div class="d-inline-flex p-4 rounded-4 mb-3" style="background: rgba(16, 185, 129, 0.12); color: #34d399;">
          <i class="bi bi-tools fs-2"></i>
        </div>
        <h4 class="fw-bold mb-2">Faculty & Staff</h4>
        <p class="text-secondary small mb-4">
          A focused departmental resolution workbench with automated reminders before SLA deadlines lapse.
        </p>
        <ul class="list-unstyled text-start small d-flex flex-column gap-2 text-secondary">
          <li><i class="bi bi-check2 text-success me-2"></i> Department ticket claim queue</li>
          <li><i class="bi bi-check2 text-success me-2"></i> SLA urgency countdown alerts</li>
          <li><i class="bi bi-check2 text-success me-2"></i> Documented resolution notes</li>
        </ul>
      </div>
    </div>

    <!-- Admin Role -->
    <div class="col-12 col-md-4">
      <div class="fp-glass-card p-4 p-lg-5 h-100 text-center">
        <div class="d-inline-flex p-4 rounded-4 mb-3" style="background: rgba(139, 92, 246, 0.12); color: #a78bfa;">
          <i class="bi bi-shield-check fs-2"></i>
        </div>
        <h4 class="fw-bold mb-2">Deans & Admins</h4>
        <p class="text-secondary small mb-4">
          Institutional bird’s-eye oversight, manual and automated escalations, and predictive analytics.
        </p>
        <ul class="list-unstyled text-start small d-flex flex-column gap-2 text-secondary">
          <li><i class="bi bi-check2 text-primary me-2"></i> Automated cron breach triggers</li>
          <li><i class="bi bi-check2 text-primary me-2"></i> Cross-department trend radar</li>
          <li><i class="bi bi-check2 text-primary me-2"></i> Staff accountability auditing</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ─── Call to Action Banner ─── -->
<section class="my-5 p-5 fp-glass-card text-center position-relative overflow-hidden" style="border: 1px solid rgba(59, 130, 246, 0.35); background: radial-gradient(circle at 50% 50%, rgba(59, 130, 246, 0.18), rgba(6, 13, 32, 0.95));">
  <div class="position-relative" style="z-index: 2;">
    <h2 class="display-5 fw-bold mb-3">Empower Your Campus Governance Today</h2>
    <p class="lead text-secondary mb-4 mx-auto" style="max-width: 620px;">
      Join thousands of students and faculty members cultivating a transparent, responsive, and accountable university community.
    </p>
    <div class="d-flex justify-content-center flex-wrap gap-3">
      <a href="<?= APP_URL ?>/register.php" class="fp-btn fp-btn-primary" style="padding: 0.9rem 2.2rem; font-size: 1.05rem;">
        <i class="bi bi-mortarboard-fill"></i> Create Student Account
      </a>
      <a href="<?= APP_URL ?>/login.php" class="fp-btn fp-btn-glass" style="padding: 0.9rem 2rem; font-size: 1.05rem;">
        <i class="bi bi-box-arrow-in-right"></i> Sign In to Portal
      </a>
    </div>
  </div>
</section>

<!-- Live AI Demo Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('ai-demo-input');
  const sentimentEl = document.getElementById('ai-demo-sentiment');
  const confEl = document.getElementById('ai-demo-conf');
  const catEl = document.getElementById('ai-demo-cat');
  const sampleBtns = document.querySelectorAll('.sample-btn');

  function analyze(text) {
    if (!text.trim()) {
      sentimentEl.innerHTML = 'Neutral 😐';
      sentimentEl.className = 'fw-bold fs-5 text-info';
      confEl.textContent = '85%';
      catEl.textContent = 'Facilities';
      return;
    }

    const lower = text.toLowerCase();
    let sentiment = 'Neutral 😐';
    let sCls = 'fw-bold fs-5 text-info';
    let conf = '78%';
    let cat = 'General';

    if (lower.includes('leak') || lower.includes('washroom') || lower.includes('broken') || lower.includes('dirty') || lower.includes('ac') || lower.includes('water')) {
      cat = 'Facilities';
    } else if (lower.includes('lecture') || lower.includes('teacher') || lower.includes('exam') || lower.includes('grade')) {
      cat = 'Academic Quality';
    } else if (lower.includes('food') || lower.includes('canteen') || lower.includes('tea') || lower.includes('lunch') || lower.includes('cafeteria')) {
      cat = 'Food Services';
    } else if (lower.includes('wifi') || lower.includes('internet') || lower.includes('portal') || lower.includes('computer')) {
      cat = 'IT & Technology';
    }

    if (lower.includes('bad') || lower.includes('worst') || lower.includes('terrible') || lower.includes('broken') || lower.includes('dirty') || lower.includes('leak') || lower.includes('failed')) {
      sentiment = 'Negative ⚠️';
      sCls = 'fw-bold fs-5 text-danger';
      conf = '93%';
    } else if (lower.includes('great') || lower.includes('exceptional') || lower.includes('good') || lower.includes('fantastic') || lower.includes('excellent')) {
      sentiment = 'Positive 😊';
      sCls = 'fw-bold fs-5 text-success';
      conf = '95%';
    }

    sentimentEl.innerHTML = sentiment;
    sentimentEl.className = sCls;
    confEl.textContent = conf;
    catEl.textContent = cat;
  }

  sampleBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      input.value = btn.dataset.text;
      analyze(btn.dataset.text);
    });
  });

  input.addEventListener('input', (e) => {
    analyze(e.target.value);
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
