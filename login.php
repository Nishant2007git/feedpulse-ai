<?php
// ============================================================
// login.php — User Authentication with Rate Limiting & 2FA Gate
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/rate_limiter.php';
require_once __DIR__ . '/includes/logger.php';

redirectIfLoggedIn();

$pdo = getDB();
$error = '';
$next = sanitizeString($_GET['next'] ?? '');

if (isset($_GET['expired'])) {
    $error = 'Your session has expired due to inactivity. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    // 1. Check Rate Limit
    if (!checkRateLimit($pdo, 'login')) {
        $error = 'Too many failed login attempts. Please wait 15 minutes before trying again.';
        logEvent($pdo, 'warning', 'auth', 'Login rate limit triggered', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your institutional email and password.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                incrementRateLimit($pdo, 'login');
                $error = 'Invalid email credentials or password.';
                logEvent($pdo, 'warning', 'auth', 'Failed login attempt', ['email' => $email]);
            } elseif (!$user['is_active']) {
                $error = 'This account has been deactivated. Please contact campus administration.';
            } else {
                // Successful verification
                resetRateLimit($pdo, 'login');

                // Check if password hash needs upgrade
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT, ['cost' => 12])) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, $user['id']]);
                }

                // Check if Admin requires 2FA OTP
                if (in_array($user['role'], ['admin', 'super_admin'])) {
                    $otp = (string) random_int(100000, 999999);

                    $pdo->prepare('INSERT INTO otp_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))')
                        ->execute([$user['id'], $otp, OTP_EXPIRY_MINUTES]);

                    // Store pending user id in session
                    $_SESSION['pending_2fa_user_id'] = $user['id'];
                    $_SESSION['pending_2fa_next']    = $next;

                    // Only in local development mode for frictionless testing
                    if (APP_ENV === 'development') {
                        $_SESSION['pending_2fa_otp'] = $otp;
                    } else {
                        unset($_SESSION['pending_2fa_otp']);
                    }

                    // Dispatch OTP via email
                    $mailBody = "<p>Hello " . escHtml($user['name']) . ",</p>"
                              . "<p>Your 2FA verification code for FeedPulse AI is: <strong style='font-size: 1.4rem; letter-spacing: 2px;'>" . $otp . "</strong></p>"
                              . "<p>This code will expire in " . OTP_EXPIRY_MINUTES . " minutes. If you did not request this login, please secure your account immediately.</p>";
                    sendSystemMail($user['email'], $user['name'], 'FeedPulse AI Security Code', $mailBody);

                    // Never log plaintext OTP in security audit logs
                    logEvent($pdo, 'info', 'auth', '2FA OTP generated for admin', ['user_id' => $user['id'], 'role' => $user['role']]);

                    redirect(APP_URL . '/verify-otp.php');
                }

                // Normal login (Student / Staff)
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['name']          = $user['name'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['last_activity'] = time();

                // Rotate CSRF token on login
                csrfRotate();

                logEvent($pdo, 'info', 'auth', 'User logged in', ['role' => $user['role']], $user['id']);

                if ($next && preg_match('#^/[^/]#', $next)) {
                    redirect($next);
                }
                redirect(dashboardUrl($user['role']));
            }
        }
    }
}

$pageTitle = 'Sign In — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center my-5">
  <div class="col-12 col-md-8 col-lg-5">
    <div class="fp-glass-card p-4 p-md-5">
      
      <div class="text-center mb-4">
        <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: rgba(79, 70, 229, 0.15); color: #818CF8;">
          <i class="bi bi-shield-lock-fill fs-2"></i>
        </div>
        <h2 class="fw-bold mb-1">Campus Sign In</h2>
        <p class="text-secondary small">Authenticate with your campus institutional credentials</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="fp-alert fp-alert--danger mb-4">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?= escHtml($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/login.php<?= $next ? '?next=' . urlencode($next) : '' ?>">
        <?= csrfField() ?>

        <div class="mb-3">
          <label class="form-label text-secondary small fw-bold">Institutional Email</label>
          <div class="input-group">
            <span class="input-group-text bg-transparent border-secondary text-secondary"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" class="form-control" placeholder="username@feedpulse.edu" required autofocus value="<?= escAttr($_POST['email'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center">
            <label class="form-label text-secondary small fw-bold">Password</label>
          </div>
          <div class="input-group">
            <span class="input-group-text bg-transparent border-secondary text-secondary"><i class="bi bi-key"></i></span>
            <input type="password" name="password" class="form-control" placeholder="••••••••••••" required>
          </div>
        </div>

        <button type="submit" class="fp-btn fp-btn-primary w-100 py-2 fs-6">
          <i class="bi bi-box-arrow-in-right"></i> Sign In to Portal
        </button>
      </form>

      <?php if (APP_ENV === 'development'): ?>
      <div class="mt-4 pt-4 border-top border-secondary border-opacity-25">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="badge bg-secondary text-uppercase fw-semibold" style="font-size: 0.65rem;">Dev Quick Fill</span>
          <span class="text-muted small">Default Password: <code>password</code></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickFill('student@feedpulse.edu')">Student</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickFill('staff.cs@feedpulse.edu')">Staff</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="quickFill('admin@feedpulse.edu')">Admin (2FA)</button>
        </div>
      </div>
      <script>
        function quickFill(email) {
          document.querySelector('input[name="email"]').value = email;
          document.querySelector('input[name="password"]').value = 'password';
        }
      </script>
      <?php endif; ?>

      <div class="text-center mt-4 pt-3 border-top border-secondary border-opacity-10 text-secondary small">
        New student without an account? <a href="<?= APP_URL ?>/register.php" class="text-accent text-decoration-none fw-semibold">Register here</a>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
