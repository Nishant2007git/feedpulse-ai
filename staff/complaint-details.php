<?php
// ============================================================
// staff/complaint-details.php — Staff Resolution & Action Workbench
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logger.php';

requireAuth(['staff']);

$pdo = getDB();
$staffId = (int)$_SESSION['user_id'];
$deptId  = (int)($_SESSION['department_id'] ?? 0);
$complaintId = sanitizeInt($_GET['id'] ?? 0);

if ($complaintId <= 0) {
    redirect(APP_URL . '/staff/complaints.php');
}

// Ensure department id is synchronized if not present in session
if ($deptId <= 0) {
    $uStmt = $pdo->prepare('SELECT department_id FROM users WHERE id = ?');
    $uStmt->execute([$staffId]);
    $deptId = (int) $uStmt->fetchColumn();
    $_SESSION['department_id'] = $deptId;
}

// 1. Fetch Complaint (Restricted to staff's department or direct assignment)
$deptCondition = ($deptId > 0) ? '(c.assigned_to = ? OR c.department_id = ?)' : 'c.assigned_to = ?';
$querySql = "SELECT c.*, d.name as dept_name, cat.name as category_name, 
                    u.name as student_name, u.email as student_email, u.roll_number, u.phone as student_phone,
                    staff.name as assigned_name
             FROM complaints c
             JOIN departments d ON c.department_id = d.id
             JOIN feedback_categories cat ON c.category_id = cat.id
             JOIN users u ON c.student_id = u.id
             LEFT JOIN users staff ON c.assigned_to = staff.id
             WHERE c.id = ? AND {$deptCondition}";

$stmt = $pdo->prepare($querySql);
$execParams = ($deptId > 0) ? [$complaintId, $staffId, $deptId] : [$complaintId, $staffId];
$stmt->execute($execParams);
$c = $stmt->fetch();

if (!$c) {
    http_response_code(404);
    die('Complaint not found or not in your department.');
}

// 2. Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $action = $_POST['action'] ?? '';

    // Assign to me
    if ($action === 'claim') {
        $pdo->prepare("UPDATE complaints SET assigned_to = ?, status = 'in_progress', updated_at = NOW() WHERE id = ?")
            ->execute([$staffId, $complaintId]);

        $pdo->prepare("INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note) VALUES (?, ?, ?, 'in_progress', 'Claimed by staff member')")
            ->execute([$complaintId, $staffId, $c['status']]);

        notifyComplaintUpdate($pdo, (int)$c['student_id'], $c, 'in_progress');
        setFlash('success', 'Ticket successfully claimed and assigned to you.');
        redirect(APP_URL . '/staff/complaint-details.php?id=' . $complaintId);
    }

    // Update Status & Note
    if ($action === 'update_status') {
        $newStatus = sanitizeString($_POST['new_status'] ?? '');
        $note      = sanitizeString($_POST['note'] ?? '', 2000);
        $resNote   = sanitizeString($_POST['resolution_note'] ?? '', 3000);

        if (in_array($newStatus, ['in_progress', 'escalated', 'resolved', 'closed'])) {
            $resolvedAt = ($newStatus === 'resolved') ? date('Y-m-d H:i:s') : $c['resolved_at'];
            $finalResNote = ($newStatus === 'resolved') ? $resNote : $c['resolution_note'];

            $updateStmt = $pdo->prepare(
                'UPDATE complaints 
                 SET status = ?, resolution_note = ?, resolved_at = ?, updated_at = NOW() 
                 WHERE id = ?'
            );
            $updateStmt->execute([$newStatus, $finalResNote, $resolvedAt, $complaintId]);

            // Add to audit trail
            $pdo->prepare(
                'INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note) 
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$complaintId, $staffId, $c['status'], $newStatus, $note ?: ($newStatus === 'resolved' ? $resNote : 'Status updated by staff')]);

            // Notify student
            notifyComplaintUpdate($pdo, (int)$c['student_id'], $c, $newStatus);

            // If resolved, prompt student for survey
            if ($newStatus === 'resolved') {
                notifySurveyRequest($pdo, (int)$c['student_id'], $c);
            }

            logEvent($pdo, 'info', 'complaint', 'Staff updated complaint status', [
                'complaint_id' => $complaintId,
                'old_status'   => $c['status'],
                'new_status'   => $newStatus
            ], $staffId);

            setFlash('success', 'Complaint status has been updated to: ' . ucfirst(str_replace('_', ' ', $newStatus)));
            redirect(APP_URL . '/staff/complaint-details.php?id=' . $complaintId);
        }
    }
}

// 3. Fetch Updates & Attachments
$updates = $pdo->prepare(
    'SELECT cu.*, u.name as user_name, u.role as user_role
     FROM complaint_updates cu
     JOIN users u ON cu.updated_by = u.id
     WHERE cu.complaint_id = ?
     ORDER BY cu.created_at ASC'
);
$updates->execute([$complaintId]);
$updates = $updates->fetchAll();

$attachments = $pdo->prepare('SELECT * FROM feedback_attachments WHERE complaint_id = ?');
$attachments->execute([$complaintId]);
$attachments = $attachments->fetchAll();

$pageTitle = 'Manage Ticket ' . $c['reference_number'] . ' — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <a href="<?= APP_URL ?>/staff/complaints.php" class="small text-info text-decoration-none mb-1 d-inline-block">&larr; Back to Queue</a>
    <h2 class="fw-bold mb-0">Ticket <span class="font-monospace text-accent"><?= escHtml($c['reference_number']) ?></span></h2>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?= statusBadge($c['status']) ?>
    <?= priorityBadge($c['priority']) ?>
  </div>
</div>

<div class="row g-4">
  <!-- Left: Content & Timeline -->
  <div class="col-12 col-lg-8">
    
    <div class="fp-glass-card p-4 mb-4">
      <h4 class="fw-bold text-light mb-3"><?= escHtml($c['title']) ?></h4>
      <div class="p-3 rounded mb-3" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--fp-border-glass); white-space: pre-line; line-height: 1.6;">
        <?= escHtml($c['description']) ?>
      </div>

      <?php if (!empty($attachments)): ?>
        <div class="mb-3">
          <div class="fp-form-label mb-2"><i class="bi bi-paperclip"></i> Student Attachment Evidence:</div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($attachments as $att): ?>
              <?php 
                $attHref = !empty($att['file_blob']) ? 'data:' . escAttr($att['mime_type']) . ';base64,' . $att['file_blob'] : APP_URL . '/uploads/attachments/' . escAttr($att['stored_name']);
              ?>
              <a href="<?= $attHref ?>" download="<?= escAttr($att['original_name']) ?>" target="_blank" class="fp-btn fp-btn-glass" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                <i class="bi bi-file-earmark-arrow-down"></i> <?= escHtml($att['original_name']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Staff Action Card -->
    <div class="fp-glass-card p-4 mb-4" style="border: 1px solid rgba(6, 182, 212, 0.3);">
      <h5 class="fw-bold mb-3"><i class="bi bi-gear-fill text-accent me-2"></i>Investigation & Resolution Actions</h5>

      <?php if (!$c['assigned_to']): ?>
        <div class="fp-alert fp-alert--warning d-flex justify-content-between align-items-center">
          <div><i class="bi bi-person-exclamation me-1"></i> This ticket is currently unassigned.</div>
          <form method="POST" action="<?= APP_URL ?>/staff/complaint-details.php?id=<?= $c['id'] ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="claim">
            <button type="submit" class="fp-btn fp-btn-primary" style="padding: 0.35rem 0.85rem; font-size: 0.85rem;">
              Assign to Me
            </button>
          </form>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/staff/complaint-details.php?id=<?= $c['id'] ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_status">

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="update-status">Transition Status</label>
            <select id="update-status" name="new_status" class="fp-select" required onchange="toggleResolutionBox(this.value)">
              <option value="in_progress" <?= $c['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress (Under Investigation)</option>
              <option value="escalated" <?= $c['status'] === 'escalated' ? 'selected' : '' ?>>Escalate to Higher Authority</option>
              <option value="resolved" <?= $c['status'] === 'resolved' ? 'selected' : '' ?>>Resolved (Provide Solution)</option>
              <option value="closed" <?= $c['status'] === 'closed' ? 'selected' : '' ?>>Closed (Complete)</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="fp-form-label" for="staff-note">Internal Progress Note (Logged in Audit Trail)</label>
          <textarea id="staff-note" name="note" class="fp-textarea" rows="2" placeholder="Describe actions taken, departments contacted, or technician assigned..."></textarea>
        </div>

        <!-- Resolution Note (Shown if status is resolved) -->
        <div id="resolution-box" class="mb-3" style="<?= $c['status'] === 'resolved' ? '' : 'display: none;' ?>">
          <label class="fp-form-label text-success" for="res-note"><i class="bi bi-check2-circle"></i> Formal Resolution Summary (Visible to Student)</label>
          <textarea id="res-note" name="resolution_note" class="fp-textarea" rows="3" placeholder="Explain the exact resolution provided, repairs finished, or policy adjustments made..."><?= escHtml($c['resolution_note'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="fp-btn fp-btn-accent">
          <i class="bi bi-save"></i> Save Status & Notify Student
        </button>
      </form>
    </div>

    <!-- Audit Timeline -->
    <div class="fp-glass-card p-4">
      <h5 class="fw-bold mb-4"><i class="bi bi-clock-history text-secondary me-2"></i>Investigation Audit Trail</h5>
      <div class="fp-timeline">
        <?php foreach ($updates as $u): ?>
          <div class="fp-timeline-item">
            <div class="fp-timeline-dot"></div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <strong class="text-light small"><?= escHtml($u['user_name']) ?> <span class="badge bg-secondary ms-1"><?= ucfirst($u['user_role']) ?></span></strong>
              <small class="text-muted"><?= timeAgo($u['created_at']) ?></small>
            </div>
            <?php if (!empty($u['note'])): ?>
              <div class="p-2 rounded small text-secondary" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--fp-border-glass);">
                <?= escHtml($u['note']) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- Right: Student Metadata & SLA Bar -->
  <div class="col-12 col-lg-4">
    <div class="fp-glass-card p-4 mb-4">
      <h6 class="fw-bold text-secondary text-uppercase small mb-3">Student Information</h6>
      <div class="d-flex flex-column gap-2 small">
        <div><strong>Name:</strong> <?= escHtml($c['student_name']) ?></div>
        <div><strong>Email:</strong> <a href="mailto:<?= escAttr($c['student_email']) ?>" class="text-info"><?= escHtml($c['student_email']) ?></a></div>
        <div><strong>Roll Number:</strong> <?= escHtml($c['roll_number'] ?? 'N/A') ?></div>
        <div><strong>Phone:</strong> <?= escHtml($c['student_phone'] ?? 'N/A') ?></div>
      </div>
    </div>

    <div class="fp-glass-card p-4">
      <h6 class="fw-bold text-secondary text-uppercase small mb-3">SLA Guarantee</h6>
      <div class="small text-muted mb-1">Deadline: <?= fmtDate($c['sla_deadline']) ?></div>
      <div class="small fw-bold mb-2 <?= $c['sla_breached'] ? 'text-danger' : 'text-light' ?>">
        <?= slaRemainingText($c['sla_deadline'], (int)$c['sla_breached']) ?>
      </div>
      <div class="fp-sla-track">
        <div class="fp-sla-fill <?= $c['sla_breached'] ? 'fp-sla-fill--breach' : 'fp-sla-fill--ok' ?>" style="width: <?= slaPct($c['created_at'], $c['sla_deadline']) ?>%;"></div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleResolutionBox(status) {
  const box = document.getElementById('resolution-box');
  if (box) {
    box.style.display = (status === 'resolved') ? 'block' : 'none';
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
