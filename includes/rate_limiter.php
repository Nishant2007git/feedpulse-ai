<?php
// ============================================================
// includes/rate_limiter.php — IP-based brute force protection
// ============================================================

function checkRateLimit(PDO $pdo, string $action = 'login'): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $stmt = $pdo->prepare(
        'SELECT attempts, blocked_until, updated_at 
         FROM rate_limit_log 
         WHERE ip_address = ? AND action = ?'
    );
    $stmt->execute([$ip, $action]);
    $record = $stmt->fetch();

    if (!$record) {
        return true;
    }

    $now = new DateTime();
    if ($record['blocked_until']) {
        $blockedUntil = new DateTime($record['blocked_until']);
        if ($now < $blockedUntil) {
            return false; // Still blocked
        }
    }

    // Reset window if older than RATE_LIMIT_WINDOW_MINS
    $updatedAt = new DateTime($record['updated_at']);
    $diffMinutes = ($now->getTimestamp() - $updatedAt->getTimestamp()) / 60;
    if ($diffMinutes > RATE_LIMIT_WINDOW_MINS) {
        resetRateLimit($pdo, $action);
        return true;
    }

    return $record['attempts'] < RATE_LIMIT_MAX_ATTEMPTS;
}

function incrementRateLimit(PDO $pdo, string $action = 'login'): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $now = new DateTime();
    
    $stmt = $pdo->prepare(
        'SELECT attempts FROM rate_limit_log WHERE ip_address = ? AND action = ?'
    );
    $stmt->execute([$ip, $action]);
    $current = $stmt->fetch();

    if ($current) {
        $newAttempts = $current['attempts'] + 1;
        $blockedUntil = null;
        if ($newAttempts >= RATE_LIMIT_MAX_ATTEMPTS) {
            $blockedTime = clone $now;
            $blockedTime->modify('+' . RATE_LIMIT_WINDOW_MINS . ' minutes');
            $blockedUntil = $blockedTime->format('Y-m-d H:i:s');
        }

        $update = $pdo->prepare(
            'UPDATE rate_limit_log 
             SET attempts = ?, blocked_until = ?, updated_at = NOW() 
             WHERE ip_address = ? AND action = ?'
        );
        $update->execute([$newAttempts, $blockedUntil, $ip, $action]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO rate_limit_log (ip_address, action, attempts, updated_at) 
             VALUES (?, ?, 1, NOW())'
        );
        $insert->execute([$ip, $action]);
    }
}

function resetRateLimit(PDO $pdo, string $action = 'login'): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $pdo->prepare('DELETE FROM rate_limit_log WHERE ip_address = ? AND action = ?');
    $stmt->execute([$ip, $action]);
}
