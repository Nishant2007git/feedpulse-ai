<?php
// ============================================================
// student/complaint-details.php — Full Ticket Lifecycle & Survey
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logger.php';

requireAuth(['student']);

$pdo = getDB();
$studentId = $_SESSION['user_id'];
$complaintId = sanitizeInt($_GET['id'] ?? 0);

if ($complaintId <= 0) {
    redirect(APP_URL . '/student/my-complaints.php');
}

// 1. Fetch Complaint
$stmt = $pdo->prepare(
    'SELECT c.*, d.name as dept_name, cat.name as category_name, u.name as assigned_name
     FROM complaints c
     JOIN departments d ON c.department_id = d.id
     JOIN feedback_categories cat ON c.category_id = cat.id
     LEFT JOIN users u ON c.assigned_to = u.id
     WHERE c.id = ? AND c.student_id = ?'
);
$stmt->execute([$complaintId, $studentId]);
$c = $stmt->fetch();

if (!$c) {
    http_response_code(404);
    die('Complaint not found or unauthorized.');
}

// 2. Handle Post-Resolution Survey
$surveyStmt = $pdo->prepare('SELECT * FROM post_resolution_surveys WHERE complaint_id = ?');
$surveyStmt->execute([$complaintId]);
$existingSurvey = $surveyStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_survey') {
    csrfCheck();

    $rating = sanitizeInt($_POST['rating'] ?? 0);
    $comment = sanitizeString($_POST['survey_comment'] ?? '', 1000);

    if ($rating >= 1 && $rating <= 5 && !$existingSurvey) {
        $pdo->prepare(
            'INSERT INTO post_resolution_surveys (complaint_id, student_id, rating, comment)
             VALUES (?, ?, ?, ?)'
        )->execute([$complaintId, $studentId, $rating, $comment]);

        addEngagementPoints($pdo, $studentId, POINTS_SURVEY);
        setFlash('success', 'Thank you for your feedback! +5 engagement points awarded.');
        redirect(APP_URL . '/student/complaint-details.php?id=' . $complaintId);
    }
}

// 3. Handle Student Reply Note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_reply') {
    csrfCheck();

    $note = sanitizeString($_POST['reply_note'] ?? '', 2000);
    if (!empty($note)) {
        $pdo->prepare(
            'INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$complaintId, $studentId, $c['status'], $c['status'], $note]);

        if ($c['assigned_to']) {
            createNotification(
                $pdo, (int)$c['assigned_to'],
                'Student Note Added',
                "Student replied on ticket {$c['reference_number']}",
                'complaint',
                $complaintId
            );
        }

        setFlash('success', 'Your update note has been attached to the ticket.');
        redirect(APP_URL . '/student/complaint-details.php?id=' . $complaintId);
    }
}

// 4. Fetch Audit Timeline
$updatesStmt = $pdo->prepare(
    'SELECT cu.*, u.name as user_name, u.role as user_role
     FROM complaint_updates cu
     JOIN users u ON cu.updated_by = u.id
     WHERE cu.complaint_id = ?
     ORDER BY cu.created_at ASC'
);
$updatesStmt->execute([$complaintId]);
$updates = $updatesStmt->fetchAll();

// 5. Fetch Attachments
$attStmt = $pdo->prepare('SELECT * FROM feedback_attachments WHERE complaint_id = ?');
$attStmt->execute([$complaintId]);
$attachments = $attStmt->fetchAll();

$slaStatus = getSlaStatus($c['sla_deadline'], (int)$c['sla_breached']);
$pct = slaPct($c['created_at'], $c['sla_deadline']);

$pageTitle = 'Ticket ' . $c['reference_number'] . ' — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Breadcrumb & Actions -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <a href="<?= APP_URL ?>/student/my-complaints.php" class="small text-info text-decoration-none mb-1 d-inline-block">&larr; Back to My Complaints</a>
    <h2 class="fw-bold mb-0">Ticket <span class="font-monospace text-accent"><?= escHtml($c['reference_number']) ?></span></h2>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?= statusBadge($c['status']) ?>
    <?= priorityBadge($c['priority']) ?>
  </div>
</div>

<div class="row g-4">
  <!-- Left Column: Details & Timeline -->
  <div class="col-12 col-lg-8">
    
    <!-- Main Ticket Card -->
    <div class="fp-glass-card p-4 mb-4">
      <h4 class="fw-bold text-light mb-3"><?= escHtml($c['title']) ?></h4>
      <div class="p-3 rounded mb-4" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--fp-border-glass); line-height: 1.6; white-space: pre-line;">
        <?= escHtml($c['description']) ?>
      </div>

      <!-- Attachments if any -->
      <?php if (!empty($attachments)): ?>
        <div class="mb-3">
          <div class="fp-form-label mb-2"><i class="bi bi-paperclip"></i> Uploaded Evidence:</div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($attachments as $att): ?>
              <?php 
                $attHref = !empty($att['file_blob']) ? 'data:' . escAttr($att['mime_type']) . ';base64,' . $att['file_blob'] : APP_URL . '/uploads/attachments/' . escAttr($att['stored_name']);
              ?>
              <a href="<?= $attHref ?>" download="<?= escAttr($att['original_name']) ?>" target="_blank" class="fp-btn fp-btn-glass" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                <i class="bi bi-file-earmark-arrow-down"></i> <?= escHtml($att['original_name']) ?> (<?= round($att['size_bytes'] / 1024) ?> KB)
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Resolution Note if Resolved -->
      <?php if ($c['status'] === 'resolved' && !empty($c['resolution_note'])): ?>
        <div class="fp-alert fp-alert--success">
          <i class="bi bi-check-circle-fill fs-4"></i>
          <div>
            <strong>Staff Resolution Summary:</strong><br>
            <?= escHtml($c['resolution_note']) ?>
            <div class="small text-muted mt-1">Resolved at <?= fmtDate($c['resolved_at']) ?></div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Post-Resolution Survey Card -->
    <?php if ($c['status'] === 'resolved'): ?>
      <div class="fp-glass-card p-4 mb-4" style="border-color: rgba(5, 150, 105, 0.4);">
        <div class="d-flex align-items-center gap-2 mb-3">
          <i class="bi bi-star-fill text-warning fs-4"></i>
          <h5 class="fw-bold mb-0">Post-Resolution Satisfaction Survey</h5>
        </div>

        <?php if ($existingSurvey): ?>
          <div class="text-secondary small">
            You rated this resolution: <?= starRating((int)$existingSurvey['rating']) ?>
            <?php if (!empty($existingSurvey['comment'])): ?>
              <p class="mt-2 text-light fst-italic">"<?= escHtml($existingSurvey['comment']) ?>"</p>
            <?php endif; ?>
            <div class="text-muted small mt-1">Submitted on <?= fmtDate($existingSurvey['submitted_at']) ?></div>
          </div>
        <?php else: ?>
          <p class="text-secondary small">Are you satisfied with how campus staff handled your issue? Complete this 30-second survey to award +5 civic points.</p>
          <form method="POST" action="<?= APP_URL ?>/student/complaint-details.php?id=<?= $c['id'] ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="submit_survey">

            <div class="mb-3">
              <label class="fp-form-label">How satisfied are you with the outcome? *</label>
              <div class="fp-rating-group">
                <input type="radio" id="s5" name="rating" value="5" required><label for="s5">★</label>
                <input type="radio" id="s4" name="rating" value="4"><label for="s4">★</label>
                <input type="radio" id="s3" name="rating" value="3"><label for="s3">★</label>
                <input type="radio" id="s2" name="rating" value="2"><label for="s2">★</label>
                <input type="radio" id="s1" name="rating" value="1"><label for="s1">★</label>
              </div>
            </div>

            <div class="mb-3">
              <textarea name="survey_comment" class="fp-textarea" rows="2" placeholder="Optional comments on staff promptness or quality of solution..."></textarea>
            </div>

            <button type="submit" class="fp-btn fp-btn-accent">Submit Survey (+5 Points)</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Audit Lifecycle Timeline -->
    <div class="fp-glass-card p-4">
      <h5 class="fw-bold mb-4"><i class="bi bi-clock-history text-accent me-2"></i>Investigation & Status Audit Trail</h5>
      
      <div class="fp-timeline">
        <?php foreach ($updates as $u): ?>
          <div class="fp-timeline-item">
            <div class="fp-timeline-dot"></div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <strong class="text-light small"><?= escHtml($u['user_name']) ?> <span class="badge bg-secondary ms-1" style="font-size: 0.65rem;"><?= ucfirst($u['user_role']) ?></span></strong>
              <small class="text-muted"><?= timeAgo($u['created_at']) ?></small>
            </div>
            <?php if ($u['old_status'] !== $u['new_status']): ?>
              <div class="small mb-1 text-info">
                Status transitioned from <span class="text-secondary"><?= escHtml($u['old_status'] ?? 'none') ?></span> to <strong><?= escHtml($u['new_status']) ?></strong>
              </div>
            <?php endif; ?>
            <?php if (!empty($u['note'])): ?>
              <div class="p-2 rounded small text-secondary" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
                <?= escHtml($u['note']) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Add Student Follow-up Note -->
      <?php if (!in_array($c['status'], ['closed'])): ?>
        <hr style="border-color: var(--fp-border-glass);">
        <form method="POST" action="<?= APP_URL ?>/student/complaint-details.php?id=<?= $c['id'] ?>">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="add_reply">
          <div class="mb-3">
            <label class="fp-form-label" for="reply-box">Add Additional Note or Follow-Up to Assigned Staff</label>
            <textarea id="reply-box" name="reply_note" class="fp-textarea" rows="2" placeholder="Have additional context or updates regarding this issue? Post here..." required></textarea>
          </div>
          <button type="submit" class="fp-btn fp-btn-glass py-2">
            <i class="bi bi-chat-left-text"></i> Post Follow-Up Note
          </button>
        </form>
      <?php endif; ?>

    </div>
  </div>

  <!-- Right Column: SLA & Ticket Metadata Sidebar -->
  <div class="col-12 col-lg-4">
    
    <!-- SLA Card -->
    <div class="fp-glass-card p-4 mb-4">
      <h6 class="fw-bold text-secondary text-uppercase small mb-3">SLA Compliance Guarantee</h6>

      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1 small">
          <span class="text-muted">Deadline:</span>
          <span class="fw-bold text-light"><?= fmtDate($c['sla_deadline']) ?></span>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2 small">
          <span class="text-muted">Status:</span>
          <span class="fw-bold"><?= slaRemainingText($c['sla_deadline'], (int)$c['sla_breached']) ?></span>
        </div>
        <div class="fp-sla-track">
          <div class="fp-sla-fill fp-sla-fill--<?= $slaStatus === 'breach' ? 'breach' : ($slaStatus === 'warning' ? 'warning' : 'ok') ?>" style="width: <?= $pct ?>%;"></div>
        </div>
      </div>

      <?php if ($c['sla_breached']): ?>
        <div class="fp-alert fp-alert--danger p-2 small mb-0">
          <i class="bi bi-exclamation-octagon-fill"></i>
          <div>This ticket has breached standard SLA and has been flagged for administrative escalation.</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Ticket Specs Metadata -->
    <div class="fp-glass-card p-4">
      <h6 class="fw-bold text-secondary text-uppercase small mb-3">Ticket Information</h6>

      <div class="d-flex flex-column gap-3 small">
        <div>
          <div class="text-muted">Assigned Department:</div>
          <div class="text-light fw-semibold"><?= escHtml($c['dept_name']) ?></div>
        </div>
        <div>
          <div class="text-muted">Category:</div>
          <div class="text-light fw-semibold"><?= escHtml($c['category_name']) ?></div>
        </div>
        <div>
          <div class="text-muted">Assigned Officer:</div>
          <div class="text-light fw-semibold"><?= $c['assigned_name'] ? escHtml($c['assigned_name']) : '<span class="text-muted fst-italic">Pending Department Assignment</span>' ?></div>
        </div>
        <div>
          <div class="text-muted">Submitted At:</div>
          <div class="text-light"><?= fmtDate($c['created_at']) ?></div>
        </div>
        <div>
          <div class="text-muted">Auto-Escalation Count:</div>
          <div class="text-light"><?= (int)$c['escalation_count'] ?> escalations</div>
        </div>
      </div>

    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
