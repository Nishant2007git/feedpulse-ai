<?php
// ============================================================
// admin/feedback-moderation.php — Feedback Moderation Queue
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

requireAuth(['admin', 'super_admin']);

$pdo = getDB();
$adminId = $_SESSION['user_id'];
$status = sanitizeString($_GET['status'] ?? 'pending');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

// Handle Moderation Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $fbId = sanitizeInt($_POST['feedback_id'] ?? 0);
    $note = sanitizeString($_POST['moderation_note'] ?? '', 1000);

    if ($fbId > 0 && in_array($action, ['approve', 'reject'])) {
        $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
        
        $pdo->prepare(
            'UPDATE feedback 
             SET moderation_status = ?, moderation_note = ?, moderated_by = ?, moderated_at = NOW() 
             WHERE id = ?'
        )->execute([$newStatus, $note, $adminId, $fbId]);

        logEvent($pdo, 'info', 'moderation', 'Feedback moderation decision', [
            'feedback_id' => $fbId,
            'status'      => $newStatus
        ], $adminId);

        setFlash('success', 'Feedback entry has been ' . $newStatus . '.');
        redirect(APP_URL . '/admin/feedback-moderation.php?status=' . urlencode($status));
    }
}

// Fetch Feedback
$countSql = 'SELECT COUNT(*) FROM feedback WHERE moderation_status = ?';
$dataSql  = 'SELECT f.*, d.name as dept_name, cat.name as category_name, u.name as student_name
             FROM feedback f
             JOIN departments d ON f.department_id = d.id
             JOIN feedback_categories cat ON f.category_id = cat.id
             LEFT JOIN users u ON f.student_id = u.id
             WHERE f.moderation_status = ?
             ORDER BY f.created_at DESC';

$paged = paginate($pdo, $countSql, $dataSql, [$status], $page);

$pageTitle = 'Feedback Moderation — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-funnel-fill text-accent me-2"></i>Campus Feedback Moderation</h2>
    <p class="text-secondary small mb-0">Review student ratings, check AI sentiment classifications, and publish verified reviews</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/admin/feedback-moderation.php?status=pending" class="fp-btn <?= $status === 'pending' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">
      Pending Queue
    </a>
    <a href="<?= APP_URL ?>/admin/feedback-moderation.php?status=approved" class="fp-btn <?= $status === 'approved' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">
      Approved
    </a>
    <a href="<?= APP_URL ?>/admin/feedback-moderation.php?status=rejected" class="fp-btn <?= $status === 'rejected' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">
      Rejected
    </a>
  </div>
</div>

<div class="row g-4">
  <?php if (empty($paged['rows'])): ?>
    <div class="col-12">
      <div class="fp-glass-card p-5 text-center">
        <i class="bi bi-check2-circle fs-1 text-success mb-2 d-block"></i>
        <h5 class="fw-bold">No items in <?= escHtml($status) ?> queue</h5>
        <p class="text-secondary mb-0">Campus submissions are currently up to date.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($paged['rows'] as $fb): ?>
      <div class="col-12 col-lg-6">
        <div class="fp-glass-card p-4 h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <span class="fw-bold text-light"><?= escHtml($fb['dept_name']) ?></span>
                <span class="text-secondary small"> &bull; <?= escHtml($fb['category_name']) ?></span>
              </div>
              <div><?= starRating((int)$fb['rating']) ?></div>
            </div>

            <?php if (!empty($fb['title'])): ?>
              <h6 class="fw-bold text-info mb-2"><?= escHtml($fb['title']) ?></h6>
            <?php endif; ?>

            <p class="text-secondary small mb-3" style="line-height: 1.6; white-space: pre-line;">
              <?= escHtml($fb['comment']) ?>
            </p>
          </div>

          <div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 rounded mb-3" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass); font-size: 0.75rem;">
              <div class="d-flex align-items-center gap-2">
                <span><?= sentimentBadge($fb['ai_sentiment']) ?></span>
                <?php if ($fb['is_anonymous']): ?>
                  <span class="badge bg-secondary"><i class="bi bi-incognito"></i> Anonymous</span>
                <?php else: ?>
                  <span class="text-light fw-semibold"><?= escHtml($fb['student_name'] ?? 'Student') ?></span>
                <?php endif; ?>
              </div>
              <span class="text-muted"><?= timeAgo($fb['created_at']) ?></span>
            </div>

            <?php if ($status === 'pending'): ?>
              <div class="d-flex gap-2">
                <!-- Approve -->
                <form method="POST" action="<?= APP_URL ?>/admin/feedback-moderation.php?status=pending" class="flex-grow-1">
                  <?= csrfField() ?>
                  <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="fp-btn fp-btn-accent w-100" style="padding: 0.4rem;">
                    <i class="bi bi-check-lg"></i> Approve
                  </button>
                </form>

                <!-- Reject -->
                <form method="POST" action="<?= APP_URL ?>/admin/feedback-moderation.php?status=pending" class="flex-grow-1">
                  <?= csrfField() ?>
                  <input type="hidden" name="feedback_id" value="<?= $fb['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button type="submit" class="fp-btn fp-btn-danger w-100" style="padding: 0.4rem;">
                    <i class="bi bi-x-lg"></i> Reject
                  </button>
                </form>
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    <?php endforeach; ?>

    <div class="col-12 mt-4">
      <?= renderPagination($paged, APP_URL . '/admin/feedback-moderation.php?status=' . urlencode($status)) ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
