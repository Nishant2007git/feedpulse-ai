<?php
// ============================================================
// admin/users.php — User Directory & Role Management
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logger.php';

requireAuth(['admin', 'super_admin']);

$pdo = getDB();
$adminId = $_SESSION['user_id'];
$page = max(1, sanitizeInt($_GET['page'] ?? 1));
$roleFilter = sanitizeString($_GET['role'] ?? '');

// Handle status toggling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $targetUserId = sanitizeInt($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($targetUserId > 0 && $targetUserId !== $adminId) {
        $tStmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $tStmt->execute([$targetUserId]);
        $targetUser = $tStmt->fetch();

        if ($targetUser) {
            $targetRole = $targetUser['role'];
            $currentRole = $_SESSION['role'] ?? 'admin';

            // Only super_admin can modify admin or super_admin accounts
            if (in_array($targetRole, ['admin', 'super_admin'], true) && $currentRole !== 'super_admin') {
                setFlash('danger', 'Permission denied: Only Super Administrators can alter administrator accounts.');
            } else {
                if ($action === 'toggle_status') {
                    $pdo->prepare('UPDATE users SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?')
                        ->execute([$targetUserId]);
                    setFlash('success', 'User account status updated.');
                    csrfRotate();
                } elseif ($action === 'change_role') {
                    $newRole = sanitizeString($_POST['new_role'] ?? 'student');
                    $allowedRoles = ($currentRole === 'super_admin') ? ['student', 'staff', 'admin', 'super_admin'] : ['student', 'staff'];
                    if (in_array($newRole, $allowedRoles, true)) {
                        $pdo->prepare('UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?')
                            ->execute([$newRole, $targetUserId]);
                        setFlash('success', 'User role updated.');
                        csrfRotate();
                    } else {
                        setFlash('danger', 'Insufficient permissions to assign the specified role.');
                    }
                }
            }
        }
        redirect(APP_URL . '/admin/users.php' . ($roleFilter ? '?role=' . urlencode($roleFilter) : ''));
    }
}

$where = 'WHERE 1=1';
$params = [];
if (!empty($roleFilter)) {
    $where .= ' AND u.role = ?';
    $params[] = $roleFilter;
}

$countSql = "SELECT COUNT(*) FROM users u {$where}";
$dataSql  = "SELECT u.*, d.name as dept_name 
             FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             {$where} 
             ORDER BY u.created_at DESC";

$paged = paginate($pdo, $countSql, $dataSql, $params, $page);

$pageTitle = 'User Management — ' . APP_NAME;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h2 class="fw-bold mb-1"><i class="bi bi-people-fill text-accent me-2"></i>Campus User Directory</h2>
    <p class="text-secondary small mb-0">Role provisioning, account status activation, and departmental mapping</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/admin/users.php" class="fp-btn <?= empty($roleFilter) ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">All</a>
    <a href="<?= APP_URL ?>/admin/users.php?role=student" class="fp-btn <?= $roleFilter === 'student' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">Students</a>
    <a href="<?= APP_URL ?>/admin/users.php?role=staff" class="fp-btn <?= $roleFilter === 'staff' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">Staff</a>
    <a href="<?= APP_URL ?>/admin/users.php?role=admin" class="fp-btn <?= $roleFilter === 'admin' ? 'fp-btn-primary' : 'fp-btn-glass' ?>" style="padding: 0.4rem 0.85rem; font-size: 0.85rem;">Admins</a>
  </div>
</div>

<div class="fp-glass-card p-4">
  <div class="table-responsive">
    <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
      <thead>
        <tr class="text-secondary" style="border-bottom: 1px solid var(--fp-border-glass); font-size: 0.8rem; text-transform: uppercase;">
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Department</th>
          <th>Status</th>
          <th>Registered</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($paged['rows'] as $u): ?>
          <tr style="border-bottom: 1px solid var(--fp-border-glass);">
            <td class="fw-semibold text-light"><?= escHtml($u['name']) ?></td>
            <td class="text-secondary small"><?= escHtml($u['email']) ?></td>
            <td>
              <form method="POST" action="<?= APP_URL ?>/admin/users.php" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <select name="new_role" class="fp-select" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; width: 110px;" onchange="this.form.submit()" <?= $u['id'] === $adminId ? 'disabled' : '' ?>>
                  <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                  <option value="staff" <?= $u['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                  <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                  <option value="super_admin" <?= $u['role'] === 'super_admin' ? 'selected' : '' ?> disabled>Super Admin</option>
                </select>
              </form>
            </td>
            <td class="text-secondary small"><?= escHtml($u['dept_name'] ?? '—') ?></td>
            <td>
              <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td class="text-secondary small"><?= fmtDate($u['created_at'], 'd M Y') ?></td>
            <td class="text-end">
              <?php if ($u['id'] !== $adminId): ?>
                <form method="POST" action="<?= APP_URL ?>/admin/users.php" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="fp-btn fp-btn-glass" style="padding: 0.25rem 0.65rem; font-size: 0.75rem;">
                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
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
    <?= renderPagination($paged, APP_URL . '/admin/users.php' . ($roleFilter ? '?role=' . urlencode($roleFilter) : '')) ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
