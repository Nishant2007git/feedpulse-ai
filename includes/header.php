<?php
// ============================================================
// includes/header.php — Shared layout header & navigation
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

// Security Headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['name'] ?? 'User';
$pageTitle = $pageTitle ?? APP_NAME . ' — Campus Intelligence';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#06b6d4">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="FeedPulse AI">
  <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
  <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/icon-192.png">
  <?php if ($isLoggedIn): ?>
  <meta name="robots" content="noindex, nofollow">
  <?php endif; ?>
  <title><?= escHtml($pageTitle) ?></title>
  
  <!-- Modern Typography Preconnect -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Three.js for 3D Interactive WebGL Experiences -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

  <!-- Spline 3D Viewer Web Component -->
  <script type="module" src="https://unpkg.com/@splinetool/viewer@1.9.28/build/spline-viewer.js"></script>

  <!-- FeedPulse Master Design System v3.0 -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/feedpulse.css">

  <style>
    /* Spline 3D Integration Skill — Watermark suppression & containment */
    spline-viewer::part(logo) { display: none !important; }
    spline-viewer { --spline-viewer-logo-display: none; }
  </style>
</head>
<body data-base-url="<?= APP_URL ?>">

  <!-- Ambient Floating Orbs -->
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <!-- Top Glass Navigation -->
  <header class="fp-navbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-4">
      <a href="<?= APP_URL ?>/" class="fp-brand">
        <span class="fp-brand-icon">
          <i class="bi bi-soundwave" style="font-size: 1.35rem;"></i>
        </span>
        <span>Feed<span style="color: var(--fp-blue-400);">Pulse</span> <span style="font-size: 0.72rem; vertical-align: super; background: linear-gradient(135deg, #06b6d4, #3b82f6); color: #fff; padding: 2px 7px; border-radius: 6px; font-weight: 800; letter-spacing: 0.05em;">AI</span></span>
      </a>

      <?php if ($isLoggedIn): ?>
        <nav class="d-none d-md-flex align-items-center gap-2 ms-3">
          <?php if ($userRole === 'student'): ?>
            <a href="<?= APP_URL ?>/student/dashboard.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-grid-1x2"></i> Dashboard</a>
            <a href="<?= APP_URL ?>/student/submit-feedback.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-chat-heart"></i> Give Feedback</a>
            <a href="<?= APP_URL ?>/student/new-complaint.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-exclamation-octagon"></i> Log Complaint</a>
            <a href="<?= APP_URL ?>/student/my-complaints.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-card-checklist"></i> My Tickets</a>
          <?php elseif ($userRole === 'staff'): ?>
            <a href="<?= APP_URL ?>/staff/dashboard.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-speedometer2"></i> Staff Desk</a>
            <a href="<?= APP_URL ?>/staff/complaints.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-inbox-fill"></i> Assigned Complaints</a>
          <?php elseif (in_array($userRole, ['admin', 'super_admin'])): ?>
            <a href="<?= APP_URL ?>/admin/dashboard.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-shield-check"></i> Command Center</a>
            <a href="<?= APP_URL ?>/admin/complaints.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-ticket-detailed"></i> All Tickets</a>
            <a href="<?= APP_URL ?>/admin/feedback-moderation.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-funnel"></i> Moderation</a>
            <a href="<?= APP_URL ?>/admin/analytics.php" class="fp-btn fp-btn-glass" style="padding: 0.4rem 0.85rem;"><i class="bi bi-graph-up-arrow"></i> AI Analytics</a>
          <?php endif; ?>
        </nav>
      <?php else: ?>
        <nav class="d-none d-lg-flex align-items-center gap-4 ms-4">
          <a href="<?= APP_URL ?>/#features" class="text-secondary text-decoration-none small fw-semibold hover-light">Features</a>
          <a href="<?= APP_URL ?>/#how-it-works" class="text-secondary text-decoration-none small fw-semibold hover-light">Workflow</a>
          <a href="<?= APP_URL ?>/#roles" class="text-secondary text-decoration-none small fw-semibold hover-light">Campus Roles</a>
          <a href="<?= APP_URL ?>/#live-demo" class="text-secondary text-decoration-none small fw-semibold hover-light d-flex align-items-center gap-1"><span class="pulse-dot"></span> Live AI Demo</a>
        </nav>
      <?php endif; ?>
    </div>

    <!-- Right Controls -->
    <div class="d-flex align-items-center gap-3">
      <!-- Theme Toggle -->
      <button id="fp-theme-toggle" class="fp-btn fp-btn-glass" style="padding: 0.5rem; width: 40px; height: 40px; border-radius: 12px;" title="Toggle Theme" aria-label="Toggle Theme">
        <i class="bi bi-moon-stars"></i>
      </button>

      <?php if ($isLoggedIn): ?>
        <!-- Notification Bell -->
        <div class="position-relative">
          <a href="<?= APP_URL ?>/notifications.php" class="fp-btn fp-btn-glass" style="padding: 0.5rem; width: 40px; height: 40px; border-radius: 12px; position: relative;" title="Notifications">
            <i class="bi bi-bell"></i>
            <span id="notif-badge" class="badge rounded-pill bg-danger position-absolute" style="top: -4px; right: -4px; font-size: 0.65rem; display: none;">0</span>
          </a>
        </div>

        <!-- User Profile Dropdown -->
        <div class="dropdown">
          <button class="fp-btn fp-btn-glass dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 0.45rem 0.95rem; border-radius: 12px;">
            <i class="bi bi-person-circle text-info"></i>
            <span class="d-none d-sm-inline fw-semibold"><?= escHtml($userName) ?></span>
            <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;"><?= ucfirst($userRole) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark" style="background: var(--fp-bg-surface-elevated); border: 1px solid var(--fp-border-glass); border-radius: var(--fp-radius-md); box-shadow: var(--fp-shadow-card);">
            <li><a class="dropdown-item" href="<?= APP_URL ?>/profile.php"><i class="bi bi-person me-2"></i> Profile & Security</a></li>
            <li><hr class="dropdown-divider" style="border-color: var(--fp-border-glass);"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a href="<?= APP_URL ?>/login.php" class="fp-btn fp-btn-glass" style="padding: 0.55rem 1.15rem;">Sign In</a>
        <a href="<?= APP_URL ?>/register.php" class="fp-btn fp-btn-primary" style="padding: 0.55rem 1.25rem;">Register</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Main Container -->
  <main class="flex-grow-1 position-relative" style="z-index: 1;">
    <div class="container-xl py-4">
      <?= renderFlash() ?>
