<?php
// ============================================================
// includes/logger.php — Application logging & audit trail
// ============================================================

function logEvent(PDO $pdo, string $level, string $category, string $message, ?array $context = null, ?int $userId = null): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ctxJson = $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO app_logs (level, category, message, context, user_id, ip_address) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$level, $category, $message, $ctxJson, $userId, $ip]);
    } catch (Exception $e) {
        // Fallback to local flat file if DB table write fails
        $file = __DIR__ . '/../logs/app.log';
        $entry = sprintf("[%s] [%s] [%s] %s %s (IP: %s)\n", date('Y-m-d H:i:s'), strtoupper($level), $category, $message, $ctxJson ?? '', $ip);
        @file_put_contents($file, $entry, FILE_APPEND);
    }
}

function logError(PDO $pdo, string $category, string $message, ?array $context = null): void {
    $userId = $_SESSION['user_id'] ?? null;
    logEvent($pdo, 'error', $category, $message, $context, $userId ? (int)$userId : null);
}

function logMail(string $toEmail, string $subject, string $note = ''): void {
    $file = __DIR__ . '/../logs/mail.log';
    $entry = sprintf("[%s] TO: %s | SUBJ: %s | NOTE: %s\n", date('Y-m-d H:i:s'), $toEmail, $subject, $note);
    @file_put_contents($file, $entry, FILE_APPEND);
}
