<?php
// ============================================================
// notifications.php — User Notification Inbox
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notifications.php';

requireAuth(); // Any authenticated user

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    csrfCheck();
    markAllRead($pdo, $userId);
    setFlash('success', 'All notifications marked as read.');
    redirect(APP_URL . '/notifications.php');
}

// Handle single mark read
if (isset($_GET['read'])) {
    if (verifyCsrfToken($_GET['csrf'] ?? '')) {
        $notifId = sanitizeInt($_GET['read']);
        markNotificationRead($pdo, $notifId, $userId);
        csrfRotate();
    }
    redirect(APP_URL . '/notifications.php');
}

$notifications = getNotifications($pdo, $userId, 50);

$pageTitle = 'Notifications — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-bell-fill text-accent me-2"></i>Notifications Inbox</h2>
    <p class="text-secondary small mb-0">System updates, complaint tracking alerts, and SLA escalations</p>
  </div>
  <?php if (!empty($notifications)): ?>
    <form method="POST" action="<?= APP_URL ?>/notifications.php">
      <?= csrfField() ?>
      <button type="submit" name="mark_all_read" value="1" class="fp-btn fp-btn-glass">
        <i class="bi bi-check2-all"></i> Mark All as Read
      </button>
    </form>
  <?php endif; ?>
</div>

<div class="row">
  <div class="col-12">
    <?php if (empty($notifications)): ?>
      <div class="fp-glass-card p-5 text-center">
        <i class="bi bi-bell-slash fs-1 text-muted mb-3 d-block"></i>
        <h4 class="fw-bold">No notifications yet</h4>
        <p class="text-secondary mb-0">You're all caught up with your campus alerts!</p>
      </div>
    <?php else: ?>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($notifications as $n): ?>
          <div class="fp-glass-card p-3 d-flex align-items-start gap-3 <?= $n['is_read'] ? 'opacity-75' : '' ?>" style="border-left: 4px solid <?= $n['is_read'] ? 'var(--fp-border-glass)' : 'var(--fp-accent)' ?>;">
            <div class="p-2 rounded-circle" style="background: rgba(255, 255, 255, 0.05);">
              <?php
                $icon = match($n['type']) {
                  'complaint' => 'bi-ticket-detailed text-info',
                  'sla'       => 'bi-exclamation-triangle-fill text-warning',
                  'feedback'  => 'bi-chat-heart text-success',
                  'survey'    => 'bi-star-fill text-primary',
                  default     => 'bi-info-circle text-secondary',
                };
              ?>
              <i class="bi <?= $icon ?> fs-4"></i>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="fw-bold mb-0 text-light"><?= escHtml($n['title']) ?></h6>
                <small class="text-muted"><?= timeAgo($n['created_at']) ?></small>
              </div>
              <p class="text-secondary mb-2 small"><?= escHtml($n['message']) ?></p>
              
              <?php if (!$n['is_read']): ?>
                <a href="<?= APP_URL ?>/notifications.php?read=<?= $n['id'] ?>&csrf=<?= generateCsrfToken() ?>" class="small text-info text-decoration-none">
                  <i class="bi bi-check"></i> Mark as read
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
