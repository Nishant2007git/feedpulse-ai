<?php
// ============================================================
// student/my-feedback.php — Student's Feedback History
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['student']);

$pdo = getDB();
$studentId = $_SESSION['user_id'];
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

$countSql = 'SELECT COUNT(*) FROM feedback WHERE student_id = ?';
$dataSql  = 'SELECT f.*, d.name as dept_name, cat.name as category_name
             FROM feedback f
             JOIN departments d ON f.department_id = d.id
             JOIN feedback_categories cat ON f.category_id = cat.id
             WHERE f.student_id = ?
             ORDER BY f.created_at DESC';

$paged = paginate($pdo, $countSql, $dataSql, [$studentId], $page);

// Fetch any staff responses for these feedbacks
$feedbackIds = array_column($paged['rows'], 'id');
$responses = [];
if (!empty($feedbackIds)) {
    $inClause = implode(',', array_fill(0, count($feedbackIds), '?'));
    $rStmt = $pdo->prepare(
        "SELECT fr.*, u.name as staff_name 
         FROM feedback_responses fr 
         JOIN users u ON fr.responded_by = u.id 
         WHERE fr.feedback_id IN ({$inClause})"
    );
    $rStmt->execute($feedbackIds);
    foreach ($rStmt->fetchAll() as $r) {
        $responses[$r['feedback_id']][] = $r;
    }
}

$pageTitle = 'My Feedback Submissions — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-chat-heart-fill text-accent me-2"></i>My Feedback Submissions</h2>
    <p class="text-secondary small mb-0">Track moderation status, AI sentiment tags, and institutional responses</p>
  </div>
  <a href="<?= APP_URL ?>/student/submit-feedback.php" class="fp-btn fp-btn-primary">
    <i class="bi bi-plus-circle"></i> Give Feedback
  </a>
</div>

<div class="row g-4">
  <?php if (empty($paged['rows'])): ?>
    <div class="col-12">
      <div class="fp-glass-card p-5 text-center">
        <i class="bi bi-chat-dots fs-1 text-muted mb-2 d-block"></i>
        <h5 class="fw-bold">No non-anonymous feedback found</h5>
        <p class="text-secondary mb-3">Note: Feedback submitted under the 100% anonymous toggle is never linked to your user ID.</p>
        <a href="<?= APP_URL ?>/student/submit-feedback.php" class="fp-btn fp-btn-accent">Submit Feedback</a>
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
                <span class="badge <?= $fb['moderation_status'] === 'approved' ? 'bg-success' : ($fb['moderation_status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') ?>">
                  <?= ucfirst($fb['moderation_status']) ?>
                </span>
              </div>
              <span class="text-muted"><?= timeAgo($fb['created_at']) ?></span>
            </div>

            <!-- Department Response if any -->
            <?php if (!empty($responses[$fb['id']])): ?>
              <div class="p-3 rounded" style="background: rgba(6, 182, 212, 0.08); border-left: 3px solid var(--fp-accent);">
                <div class="small fw-bold text-info mb-1"><i class="bi bi-reply-fill"></i> Institutional Reply:</div>
                <?php foreach ($responses[$fb['id']] as $resp): ?>
                  <p class="small text-light mb-1"><?= escHtml($resp['response']) ?></p>
                  <small class="text-muted">— <?= escHtml($resp['staff_name']) ?>, <?= timeAgo($resp['created_at']) ?></small>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    <?php endforeach; ?>

    <div class="col-12 mt-4">
      <?= renderPagination($paged, APP_URL . '/student/my-feedback.php') ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
