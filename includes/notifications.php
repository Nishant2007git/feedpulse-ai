<?php
// ============================================================
// includes/notifications.php — Notification helpers
// ============================================================

function createNotification(PDO $pdo, int $userId, string $title, string $message, string $type, ?int $relatedId = null): void {
    $pdo->prepare(
        'INSERT INTO notifications (user_id, title, message, type, related_id)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $title, $message, $type, $relatedId]);
}

function getUnreadCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function getNotifications(PDO $pdo, int $userId, int $limit = 10, bool $unreadOnly = false): array {
    $sql  = 'SELECT * FROM notifications WHERE user_id = ?';
    if ($unreadOnly) $sql .= ' AND is_read = 0';
    $sql .= ' ORDER BY created_at DESC LIMIT ?';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit,  PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function markNotificationRead(PDO $pdo, int $notifId, int $userId): void {
    $pdo->prepare(
        'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
    )->execute([$notifId, $userId]);
}

function markAllRead(PDO $pdo, int $userId): void {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
        ->execute([$userId]);
}

function notifyComplaintAssigned(PDO $pdo, array $complaint, int $staffId): void {
    createNotification(
        $pdo, $staffId,
        'Complaint Assigned',
        "You have been assigned complaint {$complaint['reference_number']}: {$complaint['title']}",
        'complaint',
        $complaint['id']
    );
}

function notifyComplaintUpdate(PDO $pdo, int $studentId, array $complaint, string $newStatus): void {
    $label = ucfirst(str_replace('_', ' ', $newStatus));
    createNotification(
        $pdo, $studentId,
        'Complaint Status Updated',
        "Your complaint {$complaint['reference_number']} status changed to: {$label}",
        'complaint',
        $complaint['id']
    );
}

function notifySlaWarning(PDO $pdo, int $adminId, array $complaint): void {
    createNotification(
        $pdo, $adminId,
        '⚠ SLA Warning',
        "Complaint {$complaint['reference_number']} is approaching its deadline.",
        'sla',
        $complaint['id']
    );
}

function notifySlaBreach(PDO $pdo, int $adminId, array $complaint): void {
    createNotification(
        $pdo, $adminId,
        '🚨 SLA Breached',
        "Complaint {$complaint['reference_number']} has exceeded its SLA deadline!",
        'sla',
        $complaint['id']
    );
}

function notifySurveyRequest(PDO $pdo, int $studentId, array $complaint): void {
    createNotification(
        $pdo, $studentId,
        'Share Your Experience',
        "How was the resolution of {$complaint['reference_number']}? Please take a quick survey.",
        'survey',
        $complaint['id']
    );
}
