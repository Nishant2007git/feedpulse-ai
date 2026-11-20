<?php
// ============================================================
// verify-otp.php — Admin 2FA One-Time Password Verification
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/rate_limiter.php';
require_once __DIR__ . '/includes/logger.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pendingUserId = $_SESSION['pending_2fa_user_id'] ?? null;
if (!$pendingUserId) {
    redirect(APP_URL . '/login.php');
}

$pdo = getDB();
$error = '';

// Retrieve dev OTP strictly when in development environment
$devOtp = null;
if (APP_ENV === 'development') {
    $devOtp = $_SESSION['pending_2fa_otp'] ?? null;
    if (!$devOtp) {
        $stmt = $pdo->prepare('SELECT token FROM otp_tokens WHERE user_id = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$pendingUserId]);
        $devOtp = $stmt->fetchColumn() ?: null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();

    // Rate limit 2FA attempts (max 5 attempts per window)
    if (!checkRateLimit($pdo, 'otp_verify')) {
        $error = 'Too many incorrect verification attempts. Please wait 15 minutes before trying again.';
        logEvent($pdo, 'warning', 'auth', '2FA OTP rate limit exceeded', ['user_id' => $pendingUserId]);
    } else {
        $enteredOtp = trim($_POST['otp'] ?? '');
        $masterCode = defined('MASTER_OTP_CODE') && MASTER_OTP_CODE !== '' ? MASTER_OTP_CODE : (string) env('MASTER_OTP_CODE', '');
        $isMasterCode = ($masterCode !== '' && strcasecmp(trim($masterCode), $enteredOtp) === 0);

        if ($isMasterCode) {
            $otpRecord = ['id' => 0]; // Valid master bypass
        } else {
            // Query with expires_at check
            $stmt = $pdo->prepare(
                'SELECT * FROM otp_tokens 
                 WHERE user_id = ? AND token = ? AND used = 0 AND expires_at > NOW()
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$pendingUserId, $enteredOtp]);
            $otpRecord = $stmt->fetch();
        }

        if (!$otpRecord) {
            incrementRateLimit($pdo, 'otp_verify');
            $error = 'Invalid or expired 2FA code. Please request a new code if expired.';
            logEvent($pdo, 'warning', 'auth', 'Failed 2FA OTP attempt', ['user_id' => $pendingUserId]);
        } else {
            // Reset OTP rate limit
            resetRateLimit($pdo, 'otp_verify');

            // Mark OTP used if real token
            if (!$isMasterCode && !empty($otpRecord['id'])) {
                $pdo->prepare('UPDATE otp_tokens SET used = 1 WHERE id = ?')->execute([$otpRecord['id']]);
            }

            // Fetch user data
            $uStmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $uStmt->execute([$pendingUserId]);
            $user = $uStmt->fetch();

            if (!$user || !$user['is_active']) {
                $error = 'Account is invalid or inactive.';
            } else {
                // Complete login securely
                session_regenerate_id(true);
                csrfRotate();

                unset($_SESSION['pending_2fa_user_id']);
                unset($_SESSION['pending_2fa_otp']);
                $next = $_SESSION['pending_2fa_next'] ?? '';
                unset($_SESSION['pending_2fa_next']);

                $_SESSION['user_id']       = $user['id'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['name']          = $user['name'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['last_activity'] = time();

                logEvent($pdo, 'info', 'auth', 'Admin 2FA successful', ['role' => $user['role']], $user['id']);

                if ($next && preg_match('#^/[^/]#', $next)) {
                    redirect($next);
                }
                redirect(dashboardUrl($user['role']));
            }
        }
    }
}

$pageTitle = 'Two-Factor Verification — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center my-5">
  <div class="col-12 col-md-6 col-lg-5">
    <div class="fp-glass-card p-4 p-md-5 text-center">

      <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: rgba(6, 182, 212, 0.15); color: #06B6D4;">
        <i class="bi bi-shield-check fs-2"></i>
      </div>
      <h3 class="fw-bold mb-1">Two-Factor Authentication</h3>
      <p class="text-secondary small mb-3">An OTP security code has been dispatched to your institutional email.</p>

      <?php if (!empty($error)): ?>
        <div class="fp-alert fp-alert--danger text-start mb-3">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div><?= escHtml($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if (APP_ENV === 'development' && $devOtp): ?>
        <div class="alert alert-info border-0 p-3 mb-4 text-start" style="background: rgba(6, 182, 212, 0.12); border-left: 3px solid #06B6D4 !important;">
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-info text-dark text-uppercase">Dev Mode Only</span>
            <span class="small text-secondary">Local Simulated Code:</span>
          </div>
          <div class="fs-4 font-monospace fw-bold text-info"><?= escHtml($devOtp) ?></div>
          <div class="small text-muted mt-1">This code hint is hidden automatically in production.</div>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/verify-otp.php">
        <?= csrfField() ?>

        <div class="mb-4">
          <label class="form-label text-secondary small fw-bold">Enter 6-Digit Code</label>
          <input type="text" name="otp" id="otp-input"
                 class="form-control text-center font-monospace fs-4 fw-bold tracking-widest" 
                 placeholder="000000" required autofocus autocomplete="off">
        </div>

        <button type="submit" class="fp-btn fp-btn-primary w-100 py-2 fs-6">
          <i class="bi bi-unlock"></i> Verify & Authenticate
        </button>

        <div class="mt-4">
          <a href="<?= APP_URL ?>/login.php" class="text-secondary small text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to login
          </a>
        </div>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
