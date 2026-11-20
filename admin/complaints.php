<?php
// ============================================================
// admin/complaints.php — Master Triage & Ticket Management
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logger.php';

requireAuth(['admin', 'super_admin']);

$pdo = getDB();
$adminId = $_SESSION['user_id'];
$page = max(1, sanitizeInt($_GET['page'] ?? 1));

// Filter parameters
$deptFilter   = sanitizeInt($_GET['department_id'] ?? 0);
$statusFilter = sanitizeString($_GET['status'] ?? '');
$breachFilter = isset($_GET['breach']) ? (int)$_GET['breach'] : null;

// Handle Administrative Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $cid = sanitizeInt($_POST['complaint_id'] ?? 0);

    if ($cid > 0) {
        $cStmt = $pdo->prepare('SELECT * FROM complaints WHERE id = ?');
        $cStmt->execute([$cid]);
        $c = $cStmt->fetch();

        if ($c) {
            // 1. Reassign Staff
            if ($action === 'reassign') {
                $newStaffId = sanitizeInt($_POST['staff_id'] ?? 0);
                if ($newStaffId > 0) {
                    $pdo->prepare('UPDATE complaints SET assigned_to = ?, updated_at = NOW() WHERE id = ?')
                        ->execute([$newStaffId, $cid]);

                    $pdo->prepare('INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note) VALUES (?, ?, ?, ?, "Assigned to staff by Administrator")')
                        ->execute([$cid, $adminId, $c['status'], $c['status']]);

                    notifyComplaintAssigned($pdo, $c, $newStaffId);
                    setFlash('success', 'Ticket successfully assigned to staff.');
                    csrfRotate();
                }
            }

            // 2. Manual Escalation
            if ($action === 'escalate') {
                $pdo->prepare("UPDATE complaints SET status = 'escalated', priority = 'critical', escalated_at = NOW(), escalation_count = escalation_count + 1 WHERE id = ?")
                    ->execute([$cid]);

                $pdo->prepare("INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note) VALUES (?, ?, ?, 'escalated', 'Manually escalated to CRITICAL priority by Admin')")
                    ->execute([$cid, $adminId, $c['status']]);

                $pdo->prepare("INSERT INTO complaint_escalations (complaint_id, escalated_from, escalated_to, reason, notified_user) VALUES (?, ?, 'critical', 'Admin manual escalation', ?)")
                    ->execute([$cid, $c['priority'], $adminId]);

                setFlash('warning', 'Ticket escalated to CRITICAL with priority flag.');
                csrfRotate();
            }
        }

        redirect(APP_URL . '/admin/complaints.php');
    }
}

// Build query
$where = 'WHERE 1=1';
$params = [];

if ($deptFilter > 0) {
    $where .= ' AND c.department_id = ?';
    $params[] = $deptFilter;
}
if (!empty($statusFilter)) {
    $where .= ' AND c.status = ?';
    $params[] = $statusFilter;
}
if ($breachFilter !== null) {
    $where .= ' AND c.sla_breached = ?';
    $params[] = $breachFilter;
}

$countSql = "SELECT COUNT(*) FROM complaints c {$where}";
$dataSql  = "SELECT c.*, d.name as dept_name, cat.name as category_name, 
                    u.name as student_name, staff.name as staff_name
             FROM complaints c
             JOIN departments d ON c.department_id = d.id
             JOIN feedback_categories cat ON c.category_id = cat.id
             JOIN users u ON c.student_id = u.id
             LEFT JOIN users staff ON c.assigned_to = staff.id
             {$where}
             ORDER BY c.sla_breached DESC, c.created_at DESC";

$paged = paginate($pdo, $countSql, $dataSql, $params, $page);

// Department & Staff lookups
$depts = $pdo->query('SELECT id, name, code FROM departments WHERE is_active = 1')->fetchAll();
$staffMembers = $pdo->query("SELECT id, name, department_id FROM users WHERE role = 'staff' AND is_active = 1")->fetchAll();

$pageTitle = 'Manage All Complaints — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-ticket-detailed-fill text-accent me-2"></i>Institutional Complaints Triage</h2>
    <p class="text-secondary small mb-0">System-wide complaint monitoring, staff assignment, and manual escalations</p>
  </div>
</div>

<!-- Filters Bar -->
<div class="fp-glass-card p-3 mb-4">
  <form method="GET" action="<?= APP_URL ?>/admin/complaints.php" class="row g-2 align-items-center">
    <div class="col-12 col-md-4">
      <select name="department_id" class="fp-select" onchange="this.form.submit()">
        <option value="">All Campus Departments</option>
        <?php foreach ($depts as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>>
            <?= escHtml($d['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <select name="status" class="fp-select" onchange="this.form.submit()">
        <option value="">All Lifecycle States</option>
        <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
        <option value="escalated" <?= $statusFilter === 'escalated' ? 'selected' : '' ?>>Escalated</option>
        <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
        <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <select name="breach" class="fp-select" onchange="this.form.submit()">
        <option value="">All SLA Statuses</option>
        <option value="1" <?= $breachFilter === 1 ? 'selected' : '' ?>>Only Breached SLA</option>
        <option value="0" <?= $breachFilter === 0 ? 'selected' : '' ?>>Within SLA Limits</option>
      </select>
    </div>
    <div class="col-12 col-md-2">
      <a href="<?= APP_URL ?>/admin/complaints.php" class="fp-btn fp-btn-glass w-100 text-center">Reset</a>
    </div>
  </form>
</div>

<!-- Master Table -->
<div class="fp-glass-card p-4">
  <?php if (empty($paged['rows'])): ?>
    <div class="text-center py-5">
      <i class="bi bi-inbox fs-1 text-muted mb-2 d-block"></i>
      <p class="text-secondary mb-0">No tickets matching the specified filters.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
        <thead>
          <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
            <th>Ref</th>
            <th>Title</th>
            <th>Department</th>
            <th>Student</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Assigned Staff</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($paged['rows'] as $c): ?>
            <tr style="border-bottom: 1px solid var(--fp-border-glass);">
              <td><code class="text-info fw-bold"><?= escHtml($c['reference_number']) ?></code></td>
              <td class="fw-semibold text-light"><?= escHtml($c['title']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['dept_name']) ?></td>
              <td class="text-secondary small"><?= escHtml($c['student_name']) ?></td>
              <td><?= priorityBadge($c['priority']) ?></td>
              <td><?= statusBadge($c['status']) ?></td>
              <td>
                <!-- Reassignment Form -->
                <form method="POST" action="<?= APP_URL ?>/admin/complaints.php" class="d-inline-flex gap-1 align-items-center">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="reassign">
                  <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                  <select name="staff_id" class="fp-select" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; width: 140px;" onchange="this.form.submit()">
                    <option value="">-- Assign Staff --</option>
                    <?php foreach ($staffMembers as $s): ?>
                      <option value="<?= $s['id'] ?>" <?= $c['assigned_to'] == $s['id'] ? 'selected' : '' ?>>
                        <?= escHtml($s['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
              <td class="text-end">
                <?php if ($c['status'] !== 'escalated' && $c['status'] !== 'resolved'): ?>
                  <form method="POST" action="<?= APP_URL ?>/admin/complaints.php" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="escalate">
                    <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="fp-btn fp-btn-danger" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;" title="Escalate to Critical">
                      <i class="bi bi-lightning-charge"></i> Escalate
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mt-4">
      <?= renderPagination($paged, APP_URL . '/admin/complaints.php') ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
