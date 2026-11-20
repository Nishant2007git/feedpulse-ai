<?php
// ============================================================
// staff/dashboard.php — Staff Department Resolution Desk
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['staff']);

$pdo = getDB();
$staffId = $_SESSION['user_id'];
$deptId  = $_SESSION['department_id'] ?? null;

// Metrics
$assignedStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE assigned_to = ? AND status IN ("open","in_progress","escalated")');
$assignedStmt->execute([$staffId]);
$myActiveCount = (int)$assignedStmt->fetchColumn();

$breachedStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE (assigned_to = ? OR department_id = ?) AND sla_breached = 1 AND status != "resolved"');
$breachedStmt->execute([$staffId, $deptId]);
$breachedCount = (int)$breachedStmt->fetchColumn();

$resolvedStmt = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE assigned_to = ? AND status = "resolved"');
$resolvedStmt->execute([$staffId]);
$resolvedCount = (int)$resolvedStmt->fetchColumn();

// Active tickets assigned to this staff member
$cStmt = $pdo->prepare(
    'SELECT c.*, d.name as dept_name, cat.name as category_name, u.name as student_name
     FROM complaints c
     JOIN departments d ON c.department_id = d.id
     JOIN feedback_categories cat ON c.category_id = cat.id
     JOIN users u ON c.student_id = u.id
     WHERE c.assigned_to = ?
     ORDER BY c.sla_breached DESC, c.sla_deadline ASC
     LIMIT 10'
);
$cStmt->execute([$staffId]);
$myComplaints = $cStmt->fetchAll();

$pageTitle = 'Staff Resolution Desk — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1">Staff Resolution Desk 📋</h2>
    <p class="text-secondary small mb-0">Departmental complaint processing, SLA monitoring, and student communications</p>
  </div>
  <a href="<?= APP_URL ?>/staff/complaints.php" class="fp-btn fp-btn-primary">
    <i class="bi bi-inbox-fill"></i> View All Department Tickets
  </a>
</div>

<!-- Metrics Cards -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-4">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">My Assigned Tickets</span>
        <span class="p-2 rounded-circle" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
          <i class="bi bi-person-workspace"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-info"><?= $myActiveCount ?></div>
      <small class="text-muted">Awaiting your resolution</small>
    </div>
  </div>

  <div class="col-12 col-sm-4">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">SLA Breached / Urgent</span>
        <span class="p-2 rounded-circle" style="background: rgba(220, 38, 38, 0.15); color: #F87171;">
          <i class="bi bi-exclamation-triangle-fill"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-danger"><?= $breachedCount ?></div>
      <small class="text-muted">Requires immediate action</small>
    </div>
  </div>

  <div class="col-12 col-sm-4">
    <div class="fp-glass-card p-3">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <span class="text-secondary small">Resolved by You</span>
        <span class="p-2 rounded-circle" style="background: rgba(16, 185, 129, 0.15); color: #34D399;">
          <i class="bi bi-check-circle-fill"></i>
        </span>
      </div>
      <div class="h3 fw-bold mb-0 text-success"><?= $resolvedCount ?></div>
      <small class="text-muted">Successfully closed</small>
    </div>
  </div>
</div>

<!-- Assigned Tickets Table -->
<div class="fp-glass-card p-4">
  <h5 class="fw-bold mb-3"><i class="bi bi-list-task text-accent me-2"></i>My Priority Action Queue</h5>

  <?php if (empty($myComplaints)): ?>
    <div class="text-center py-5">
      <i class="bi bi-check2-all fs-1 text-success mb-2 d-block"></i>
      <h6 class="fw-bold">All caught up!</h6>
      <p class="text-secondary mb-0">No active complaint tickets currently assigned to you.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
        <thead>
          <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
            <th>Ref</th>
            <th>Subject</th>
            <th>Student</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Status</th>
            <th>SLA Countdown</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($myComplaints as $c): ?>
            <?php 
              $slaStatus = getSlaStatus($c['sla_deadline'], (int)$c['sla_breached']);
              $slaFillClass = match($slaStatus) {
                'breach'  => 'fp-sla-fill--breach',
                'warning' => 'fp-sla-fill--warning',
                default   => 'fp-sla-fill--ok'
              };
              $pct = slaPct($c['created_at'], $c['sla_deadline']);
            ?>
            <tr style="border-bottom: 1px solid var(--fp-border-glass);">
              <td><code class="text-info"><?= escHtml($c['reference_number']) ?></code></td>
              <td class="fw-semibold text-light"><?= escHtml($c['title']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['student_name']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['category_name']) ?></td>
              <td><?= priorityBadge($c['priority']) ?></td>
              <td><?= statusBadge($c['status']) ?></td>
              <td style="min-width: 140px;">
                <div class="small text-muted mb-1"><?= slaRemainingText($c['sla_deadline'], (int)$c['sla_breached']) ?></div>
                <div class="fp-sla-track">
                  <div class="fp-sla-fill <?= $slaFillClass ?>" style="width: <?= $pct ?>%;"></div>
                </div>
              </td>
              <td class="text-end">
                <a href="<?= APP_URL ?>/staff/complaint-details.php?id=<?= $c['id'] ?>" class="fp-btn fp-btn-primary" style="padding: 0.35rem 0.85rem; font-size: 0.8rem;">
                  Manage &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
