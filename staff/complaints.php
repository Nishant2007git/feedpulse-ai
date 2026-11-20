<?php
// ============================================================
// staff/complaints.php — Department Complaints Queue
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['staff']);

$pdo = getDB();
$staffId = $_SESSION['user_id'];
$deptId  = $_SESSION['department_id'] ?? 0;
$page    = max(1, sanitizeInt($_GET['page'] ?? 1));
$filter  = sanitizeString($_GET['filter'] ?? 'assigned');

$where = 'WHERE (c.assigned_to = ? OR (c.department_id = ? AND c.assigned_to IS NULL))';
$params = [$staffId, $deptId];

if ($filter === 'assigned') {
    $where = 'WHERE c.assigned_to = ?';
    $params = [$staffId];
} elseif ($filter === 'unassigned') {
    $where = 'WHERE c.department_id = ? AND c.assigned_to IS NULL';
    $params = [$deptId];
}

$countSql = "SELECT COUNT(*) FROM complaints c {$where}";
$dataSql  = "SELECT c.*, d.name as dept_name, cat.name as category_name, u.name as student_name
             FROM complaints c
             JOIN departments d ON c.department_id = d.id
             JOIN feedback_categories cat ON c.category_id = cat.id
             JOIN users u ON c.student_id = u.id
             {$where}
             ORDER BY c.sla_breached DESC, c.created_at DESC";

$paged = paginate($pdo, $countSql, $dataSql, $params, $page);

$pageTitle = 'Department Tickets — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-inbox-fill text-accent me-2"></i>Department Complaints Queue</h2>
    <p class="text-secondary small mb-0">Tickets under your jurisdiction requiring investigation, status transition, or resolution</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/staff/complaints.php?filter=assigned" class="fp-btn <?= $filter === 'assigned' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">My Assigned</a>
    <a href="<?= APP_URL ?>/staff/complaints.php?filter=unassigned" class="fp-btn <?= $filter === 'unassigned' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">Unassigned in Dept</a>
  </div>
</div>

<div class="fp-glass-card p-4">
  <?php if (empty($paged['rows'])): ?>
    <div class="text-center py-5">
      <i class="bi bi-folder-check fs-1 text-muted mb-2 d-block"></i>
      <p class="text-secondary mb-0">No complaints in this queue.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
        <thead>
          <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
            <th>Reference</th>
            <th>Title</th>
            <th>Student</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Status</th>
            <th>SLA Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paged['rows'] as $c): ?>
            <tr style="border-bottom: 1px solid var(--fp-border-glass);">
              <td><code class="text-info fw-bold"><?= escHtml($c['reference_number']) ?></code></td>
              <td class="fw-semibold text-light"><?= escHtml($c['title']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['student_name']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['category_name']) ?></td>
              <td><?= priorityBadge($c['priority']) ?></td>
              <td><?= statusBadge($c['status']) ?></td>
              <td>
                <span class="fp-badge <?= $c['sla_breached'] ? 'fp-badge--breach' : 'fp-badge--resolved' ?>">
                  <?= $c['sla_breached'] ? 'Breached' : 'Active' ?>
                </span>
              </td>
              <td class="text-end">
                <a href="<?= APP_URL ?>/staff/complaint-details.php?id=<?= $c['id'] ?>" class="fp-btn fp-btn-primary" style="padding: 0.35rem 0.8rem; font-size: 0.8rem;">
                  Open &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      <?= renderPagination($paged, APP_URL . '/staff/complaints.php?filter=' . urlencode($filter)) ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
