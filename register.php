<?php
// ============================================================
// register.php — Student Self-Registration
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/logger.php';

redirectIfLoggedIn();

$pdo = getDB();
$error = '';

// Load active departments
$deptStmt = $pdo->query('SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC');
$departments = $deptStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    $name       = sanitizeString($_POST['name'] ?? '', 120);
    $email      = strtolower(trim($_POST['email'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');
    $confirm    = (string)($_POST['password_confirm'] ?? '');
    $deptId     = sanitizeInt($_POST['department_id'] ?? 0);
    $rollNumber = sanitizeString($_POST['roll_number'] ?? '', 50);
    $phone      = sanitizeString($_POST['phone'] ?? '', 20);

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($deptId)) {
        $error = 'Please complete all required fields marked with an asterisk (*).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
        $error = 'Password must be at least 8 characters long and contain both letters and numbers.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already registered
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with this email address already exists. Please sign in.';
        } else {
            try {
                $pdo->beginTransaction();

                // Hash password using PASSWORD_DEFAULT
                $hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);

                $insert = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, department_id, roll_number, phone, is_active) 
                     VALUES (?, ?, ?, "student", ?, ?, ?, 1)'
                );
                $insert->execute([$name, $email, $hash, $deptId, $rollNumber, $phone]);
                $newUserId = (int)$pdo->lastInsertId();

                // Initialize engagement score record
                $pdo->prepare('INSERT INTO engagement_scores (student_id, score, tier) VALUES (?, 0, "bronze")')
                    ->execute([$newUserId]);

                logEvent($pdo, 'info', 'auth', 'New student registered', ['student_id' => $newUserId, 'dept_id' => $deptId], $newUserId);

                $pdo->commit();
                csrfRotate();

                setFlash('success', 'Registration successful! You may now sign in with your credentials.');
                redirect(APP_URL . '/login.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to create account. Please try again.';
                error_log('[Registration Error] ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Student Registration — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center my-4">
  <div class="col-12 col-md-10 col-lg-7">
    <div class="fp-glass-card p-4 p-md-5">

      <div class="text-center mb-4">
        <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
          <i class="bi bi-mortarboard-fill fs-2"></i>
        </div>
        <h2 class="fw-bold mb-1">Create Student Account</h2>
        <p class="text-secondary small">Access anonymous feedback, complaint resolution tracking, and campus surveys</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="fp-alert fp-alert--danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?= escHtml($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/register.php">
        <?= csrfField() ?>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="reg-name">Full Name *</label>
            <input type="text" id="reg-name" name="name" class="fp-input" placeholder="e.g. Ayesha Khan" required value="<?= escAttr($_POST['name'] ?? '') ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="reg-email">Institutional Email *</label>
            <input type="email" id="reg-email" name="email" class="fp-input" placeholder="rollno@campus.edu" required value="<?= escAttr($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="reg-dept">Academic Department *</label>
            <select id="reg-dept" name="department_id" class="fp-select" required>
              <option value="">Select your department...</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                  <?= escHtml($dept['name']) ?> (<?= escHtml($dept['code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="reg-roll">Roll / Student ID Number</label>
            <input type="text" id="reg-roll" name="roll_number" class="fp-input" placeholder="e.g. 21-CS-104" value="<?= escAttr($_POST['roll_number'] ?? '') ?>">
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="reg-phone">Contact Phone</label>
            <input type="tel" id="reg-phone" name="phone" class="fp-input" placeholder="+92 300 1234567" value="<?= escAttr($_POST['phone'] ?? '') ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="fp-form-label" for="reg-pass">Password (Min 8 chars) *</label>
            <input type="password" id="reg-pass" name="password" class="fp-input" placeholder="••••••••••••" required>
          </div>
          <div class="col-12 col-md-6 offset-md-6">
            <label class="fp-form-label" for="reg-pass-conf">Confirm Password *</label>
            <input type="password" id="reg-pass-conf" name="password_confirm" class="fp-input" placeholder="••••••••••••" required>
          </div>
        </div>

        <button type="submit" class="fp-btn fp-btn-primary w-100 py-2 fs-6 mb-3">
          <i class="bi bi-person-check-fill"></i> Complete Student Registration
        </button>

        <div class="text-center mt-3 text-secondary small">
          Already registered? <a href="<?= APP_URL ?>/login.php" class="text-info fw-semibold text-decoration-none">Sign In here</a>
        </div>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
