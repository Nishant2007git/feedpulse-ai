<?php
// ============================================================
// 403.php — Access Denied Error Screen
// ============================================================
require_once __DIR__ . '/config/app.php';
$pageTitle = '403 Forbidden — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center text-center my-5 py-5">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="fp-glass-card p-5">
      <div class="d-inline-flex p-4 rounded-circle mb-4" style="background: rgba(220, 38, 38, 0.15); color: #F87171;">
        <i class="bi bi-shield-x display-1"></i>
      </div>
      <h1 class="display-6 fw-bold mb-2">Access Denied</h1>
      <p class="text-secondary mb-4">
        You do not possess the required security permissions or role privileges to view this section of FeedPulse AI.
      </p>
      <div class="d-flex justify-content-center gap-3">
        <a href="<?= APP_URL ?>/" class="fp-btn fp-btn-primary">Return to Safety</a>
        <a href="<?= APP_URL ?>/logout.php" class="fp-btn fp-btn-glass">Sign Out</a>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
