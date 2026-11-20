<?php
// ============================================================
// student/my-complaints.php — View All Student Complaints
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['student']);

$pdo = getDB();
$studentId = $_SESSION['user_id'];
$statusFilter = sanitizeString($_GET['status'] ?? '');
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

$where = 'WHERE c.student_id = ?';
$params = [$studentId];

if (!empty($statusFilter)) {
    $where .= ' AND c.status = ?';
    $params[] = $statusFilter;
}

$countSql = "SELECT COUNT(*) FROM complaints c {$where}";
$dataSql  = "SELECT c.*, d.name as dept_name, cat.name as category_name 
             FROM complaints c
             JOIN departments d ON c.department_id = d.id
             JOIN feedback_categories cat ON c.category_id = cat.id
             {$where}
             ORDER BY c.created_at DESC";

$paged = paginate($pdo, $countSql, $dataSql, $params, $page);

$pageTitle = 'My Complaints — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-card-checklist text-accent me-2"></i>My Complaint History</h2>
    <p class="text-secondary small mb-0">Track lifecycle states, audit notes, and post-resolution satisfaction</p>
  </div>
  <a href="<?= APP_URL ?>/student/new-complaint.php" class="fp-btn fp-btn-primary">
    <i class="bi bi-plus-circle"></i> Log New Ticket
  </a>
</div>

<!-- Filter Tabs -->
<div class="d-flex flex-wrap gap-2 mb-4">
  <a href="<?= APP_URL ?>/student/my-complaints.php" class="fp-btn <?= empty($statusFilter) ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">All Tickets</a>
  <a href="<?= APP_URL ?>/student/my-complaints.php?status=open" class="fp-btn <?= $statusFilter === 'open' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">Open</a>
  <a href="<?= APP_URL ?>/student/my-complaints.php?status=in_progress" class="fp-btn <?= $statusFilter === 'in_progress' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">In Progress</a>
  <a href="<?= APP_URL ?>/student/my-complaints.php?status=escalated" class="fp-btn <?= $statusFilter === 'escalated' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">Escalated</a>
  <a href="<?= APP_URL ?>/student/my-complaints.php?status=resolved" class="fp-btn <?= $statusFilter === 'resolved' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;">Resolved</a>
</div>

<!-- Table Card -->
<div class="fp-glass-card p-4">
  <?php if (empty($paged['rows'])): ?>
    <div class="text-center py-5">
      <i class="bi bi-folder2-open fs-1 text-muted mb-2 d-block"></i>
      <p class="text-secondary mb-0">No tickets found for this filter criteria.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
        <thead>
          <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
            <th>Reference</th>
            <th>Title</th>
            <th>Department</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Status</th>
            <th>SLA Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paged['rows'] as $c): ?>
            <?php 
              $slaStatus = getSlaStatus($c['sla_deadline'], (int)$c['sla_breached']);
              $slaBadge = match($slaStatus) {
                'breach'  => '<span class="fp-badge fp-badge--breach">Breached</span>',
                'warning' => '<span class="fp-badge fp-badge--warning">Warning</span>',
                default   => '<span class="fp-badge fp-badge--resolved">On Track</span>'
              };
            ?>
            <tr style="border-bottom: 1px solid var(--fp-border-glass);">
              <td><code class="text-info fw-bold"><?= escHtml($c['reference_number']) ?></code></td>
              <td class="fw-semibold text-light"><?= escHtml($c['title']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['dept_name']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['category_name']) ?></td>
              <td><?= priorityBadge($c['priority']) ?></td>
              <td><?= statusBadge($c['status']) ?></td>
              <td><?= $slaBadge ?></td>
              <td class="text-end">
                <a href="<?= APP_URL ?>/student/complaint-details.php?id=<?= $c['id'] ?>" class="fp-btn fp-btn-glass" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                  View &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
      <?= renderPagination($paged, APP_URL . '/student/my-complaints.php' . ($statusFilter ? '?status=' . urlencode($statusFilter) : '')) ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
