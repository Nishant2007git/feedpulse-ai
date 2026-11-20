<?php
// ============================================================
// profile.php — User Profile & Security Settings
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/logger.php';

requireAuth(); // Any logged in user

$pdo = getDB();
$userId = $_SESSION['user_id'];
$error = '';

$stmt = $pdo->prepare('SELECT u.*, d.name as dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $phone = sanitizeString($_POST['phone'] ?? '', 20);
        $pdo->prepare('UPDATE users SET phone = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$phone, $userId]);
        setFlash('success', 'Profile contact information updated.');
        redirect(APP_URL . '/profile.php');
    }

    if ($action === 'change_password') {
        $currentPass = (string)($_POST['current_password'] ?? '');
        $newPass     = (string)($_POST['new_password'] ?? '');
        $confPass    = (string)($_POST['confirm_password'] ?? '');

        if (!password_verify($currentPass, $user['password_hash'])) {
            $error = 'Your current password was entered incorrectly.';
        } elseif (strlen($newPass) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPass !== $confPass) {
            $error = 'New passwords do not match.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$newHash, $userId]);

            logEvent($pdo, 'info', 'auth', 'User changed password', [], $userId);
            setFlash('success', 'Password successfully changed.');
            redirect(APP_URL . '/profile.php');
        }
    }
}

$pageTitle = 'Profile & Security — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
  <div class="col-12 col-lg-4">
    <!-- User Card -->
    <div class="fp-glass-card p-4 text-center">
      <div class="d-inline-flex p-4 rounded-circle mb-3" style="background: rgba(79, 70, 229, 0.15); color: #818CF8;">
        <i class="bi bi-person fs-1"></i>
      </div>
      <h4 class="fw-bold mb-1"><?= escHtml($user['name']) ?></h4>
      <div class="badge bg-secondary mb-3"><?= ucfirst($user['role']) ?></div>

      <div class="text-secondary small text-start border-top pt-3" style="border-color: var(--fp-border-glass) !important;">
        <div class="mb-2"><strong>Email:</strong> <?= escHtml($user['email']) ?></div>
        <?php if (!empty($user['dept_name'])): ?>
          <div class="mb-2"><strong>Department:</strong> <?= escHtml($user['dept_name']) ?></div>
        <?php endif; ?>
        <?php if (!empty($user['roll_number'])): ?>
          <div class="mb-2"><strong>Roll Number:</strong> <?= escHtml($user['roll_number']) ?></div>
        <?php endif; ?>
        <div><strong>Member Since:</strong> <?= fmtDate($user['created_at'], 'M Y') ?></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <?php if (!empty($error)): ?>
      <div class="fp-alert fp-alert--danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><?= escHtml($error) ?></div>
      </div>
    <?php endif; ?>

    <!-- Update Contact Details -->
    <div class="fp-glass-card p-4 mb-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-person-gear text-accent me-2"></i>Contact Details</h5>
      <form method="POST" action="<?= APP_URL ?>/profile.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="mb-3">
          <label class="fp-form-label" for="prof-phone">Phone / WhatsApp Number</label>
          <input type="tel" id="prof-phone" name="phone" class="fp-input" value="<?= escAttr($user['phone'] ?? '') ?>" placeholder="+92 300 0000000">
        </div>

        <button type="submit" class="fp-btn fp-btn-primary">Save Changes</button>
      </form>
    </div>

    <!-- Change Password -->
    <div class="fp-glass-card p-4">
      <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock text-warning me-2"></i>Change Password</h5>
      <form method="POST" action="<?= APP_URL ?>/profile.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="change_password">

        <div class="mb-3">
          <label class="fp-form-label" for="curr-pass">Current Password *</label>
          <input type="password" id="curr-pass" name="current_password" class="fp-input" required>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="new-pass">New Password (Min 8 chars) *</label>
            <input type="password" id="new-pass" name="new_password" class="fp-input" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="conf-pass">Confirm New Password *</label>
            <input type="password" id="conf-pass" name="confirm_password" class="fp-input" required>
          </div>
        </div>

        <button type="submit" class="fp-btn fp-btn-accent">Update Password</button>
      </form>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
