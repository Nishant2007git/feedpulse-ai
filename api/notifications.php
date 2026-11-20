<?php
// ============================================================
// api/notifications.php — AJAX Notification Controller
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

if ($action === 'count') {
    $count = getUnreadCount($pdo, $userId);
    jsonResponse(['unread_count' => $count]);
}

if ($action === 'list') {
    $notifications = getNotifications($pdo, $userId, 10, true);
    jsonResponse(['notifications' => $notifications]);
}

jsonResponse(['error' => 'Invalid action'], 400);
