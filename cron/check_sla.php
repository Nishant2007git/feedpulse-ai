<?php
// ============================================================
// cron/check_sla.php — Automated SLA Escalation Engine
// Run via CLI or Task Scheduler: php cron/check_sla.php
// ============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/logger.php';

// Prevent unauthorized web access
$providedKey = (string) ($_GET['key'] ?? '');
if (php_sapi_name() !== 'cli' && (!hash_equals(CRON_SECRET, $providedKey))) {
    http_response_code(403);
    die("Access denied. CLI only or valid key required.\n");
}

$pdo = getDB();
$now = date('Y-m-d H:i:s');
echo "[{$now}] Running FeedPulse AI SLA Escalation Engine...\n";

// Lookup active administrative system account
$sysUserStmt = $pdo->query('SELECT id FROM users WHERE role = "super_admin" AND is_active = 1 ORDER BY id ASC LIMIT 1');
$systemUserId = (int) ($sysUserStmt->fetchColumn() ?: 1);

// ── 1. Detect & Escalate SLA Breaches ───────────────────────
$breachStmt = $pdo->prepare(
    'SELECT c.*, d.name as dept_name 
     FROM complaints c
     JOIN departments d ON c.department_id = d.id
     WHERE c.status IN ("open", "in_progress")
       AND c.sla_deadline IS NOT NULL
       AND c.sla_deadline < NOW()
       AND c.sla_breached = 0'
);
$breachStmt->execute();
$breachedTickets = $breachStmt->fetchAll();

echo "Found " . count($breachedTickets) . " breached tickets.\n";

// Fetch Super Admins for notification
$admins = $pdo->query('SELECT id FROM users WHERE role IN ("admin", "super_admin") AND is_active = 1')->fetchAll();

foreach ($breachedTickets as $ticket) {
    echo "  -> Escalating {$ticket['reference_number']}...\n";

    // Update complaint record
    $pdo->prepare(
        'UPDATE complaints 
         SET sla_breached = 1, status = "escalated", priority = "critical", 
             escalated_at = NOW(), escalation_count = escalation_count + 1, updated_at = NOW() 
         WHERE id = ?'
    )->execute([$ticket['id']]);

    // Insert into audit trail
    $pdo->prepare(
        'INSERT INTO complaint_updates (complaint_id, updated_by, old_status, new_status, note) 
         VALUES (?, ?, ?, "escalated", "Automated cron SLA breach escalation to CRITICAL")'
    )->execute([$ticket['id'], $systemUserId, $ticket['status']]);

    // Insert into escalations log
    $pdo->prepare(
        'INSERT INTO complaint_escalations (complaint_id, escalated_from, escalated_to, reason, notified_user) 
         VALUES (?, ?, "critical", "SLA deadline exceeded", ?)'
    )->execute([$ticket['id'], $ticket['priority'], $systemUserId]);

    // Notify administrators
    foreach ($admins as $admin) {
        notifySlaBreach($pdo, (int)$admin['id'], $ticket);
    }

    // Notify student that ticket has been escalated for senior review
    createNotification(
        $pdo, (int)$ticket['student_id'],
        'Ticket Escalated to Senior Administration',
        "Your ticket {$ticket['reference_number']} has been escalated due to SLA urgency.",
        'sla',
        $ticket['id']
    );

    logEvent($pdo, 'warning', 'sla', "Cron escalated ticket {$ticket['reference_number']}", [
        'complaint_id' => $ticket['id'],
        'deadline'     => $ticket['sla_deadline']
    ]);
}

// ── 2. Detect SLA Approaching Warnings ──────────────────────
// Tickets within 8 hours of deadline not yet breached
$warnStmt = $pdo->prepare(
    'SELECT c.* 
     FROM complaints c
     WHERE c.status IN ("open", "in_progress")
       AND c.sla_breached = 0
       AND c.sla_deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 8 HOUR)'
);
$warnStmt->execute();
$warningTickets = $warnStmt->fetchAll();

echo "Found " . count($warningTickets) . " tickets in warning threshold.\n";
foreach ($warningTickets as $wt) {
    if ($wt['assigned_to']) {
        notifySlaWarning($pdo, (int)$wt['assigned_to'], $wt);
    }
}

echo "SLA Engine completed successfully.\n";
