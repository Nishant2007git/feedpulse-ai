<?php
// ============================================================
// config/app.php — Application constants & Bootstrap
// ============================================================

// ── Lightweight .env Loader ─────────────────────────────────
(function() {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                $v = trim($v, '"\'');
                if (!array_key_exists($k, $_SERVER) && !array_key_exists($k, $_ENV)) {
                    putenv("{$k}={$v}");
                    $_ENV[$k] = $v;
                    $_SERVER[$k] = $v;
                }
            }
        }
    }
})();

function env(string $key, mixed $default = null): mixed {
    $val = getenv($key);
    if ($val === false) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return $val !== null ? $val : $default;
}

function detectAppUrl(): string {
    // 1. If explicit non-localhost APP_URL is in env, honor it
    $configured = env('APP_URL');
    if ($configured && !str_contains($configured, 'localhost')) {
        return rtrim($configured, '/');
    }

    // 2. Auto-detect from HTTP request
    if (!empty($_SERVER['HTTP_HOST'])) {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
              || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
              ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];

        // Cloud / Vercel deployment (root level)
        if (str_contains($host, 'vercel.app') || isset($_SERVER['VERCEL'])) {
            return $proto . $host;
        }

        // Local environment with subdirectory detection (e.g. /feedpulse-ai)
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = dirname($script);
        $dir = preg_replace('#/(api|admin|student|staff)$#', '', $dir);
        $sub = ($dir && $dir !== '/' && $dir !== '.') ? '/' . trim($dir, '/') : '';

        return $proto . $host . $sub;
    }

    return rtrim($configured ?: 'http://localhost:8080/feedpulse-ai', '/');
}

define('APP_NAME',        env('APP_NAME', 'FeedPulse AI'));
define('APP_VERSION',     '2.0.0');
define('APP_URL',         detectAppUrl());
define('APP_ENV',         env('APP_ENV', 'development'));   // 'production' in prod
define('CRON_SECRET',     env('CRON_SECRET', 'feedpulse_cron_secret'));

date_default_timezone_set('Asia/Kolkata');

// Session configuration
define('SESSION_TIMEOUT', 1800);   // 30 minutes idle timeout

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_TIMEOUT);
    if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_name('FPSESSID');
}

// Error & Exception Handling
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

set_exception_handler(function(Throwable $e) {
    error_log('[FeedPulse Exception] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (defined('APP_ENV') && APP_ENV === 'production') {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>System Error</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></head><body class="bg-dark text-light d-flex align-items-center justify-content-center min-vh-100"><div class="text-center p-5"><h1 class="display-5 fw-bold text-danger">500 — Application Error</h1><p class="text-secondary">An unexpected internal error occurred. Our engineers have been alerted.</p><a href="' . APP_URL . '" class="btn btn-primary mt-3">Return to Home</a></div></body></html>';
        exit;
    }
    echo "<h1>Application Error (Development Mode)</h1>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
});

// Pagination
define('ROWS_PER_PAGE',   15);

// SLA
define('SLA_DEFAULT_HOURS', 72);   // fallback when no category target

// File uploads
define('UPLOAD_MAX_MB',    5);
define('UPLOAD_ALLOWED_TYPES', ['image/jpeg','image/png','image/gif','image/webp','application/pdf','text/plain']);
define('UPLOAD_ALLOWED_EXTS',  ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt']);
define('UPLOAD_DIR',       __DIR__ . '/../uploads/attachments/');
define('AVATAR_DIR',       __DIR__ . '/../uploads/avatars/');

// Ensure runtime directories exist
foreach ([UPLOAD_DIR, AVATAR_DIR, __DIR__ . '/../logs/'] as $targetDir) {
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
}

// Engagement scoring points
define('POINTS_FEEDBACK',        10);
define('POINTS_COMPLAINT',       15);
define('POINTS_SURVEY',           5);

// Tier thresholds
define('TIER_SILVER',    50);
define('TIER_GOLD',     150);
define('TIER_PLATINUM', 400);

// Rate limiting
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW_MINS',  15);

// OTP
define('OTP_EXPIRY_MINUTES', 10);

// Notification polling (seconds)
define('NOTIF_POLL_INTERVAL', 30);
