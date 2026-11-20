<?php
// ============================================================
// admin/export.php — 1-Click CSV Report Exporter
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth(['admin', 'super_admin', 'staff']);

$pdo = getDB();
$type = sanitizeString($_GET['type'] ?? 'complaints');
$userRole = $_SESSION['role'] ?? 'staff';
$userDept = (int)($_SESSION['department_id'] ?? 0);

// Disable output buffering
if (ob_get_level()) ob_end_clean();

$today = date('Y-m-d');

if ($type === 'feedback') {
    $filename = "feedpulse-feedback-report-{$today}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Write UTF-8 BOM for Microsoft Excel compatibility
    fputs($out, "\xEF\xBB\xBF");

    // Header Row
    fputcsv($out, [
        'Feedback ID', 'Department', 'Category', 'Rating', 
        'Title', 'Comment', 'AI Sentiment', 'AI Confidence', 
        'Moderation Status', 'Anonymous', 'Submitted At'
    ]);

    $sql = "SELECT fb.*, d.name as dept_name, cat.name as category_name
            FROM feedback fb
            JOIN departments d ON fb.department_id = d.id
            JOIN feedback_categories cat ON fb.category_id = cat.id
            ORDER BY fb.created_at DESC";
    $stmt = $pdo->query($sql);

    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['dept_name'],
            $row['category_name'],
            $row['rating'],
            $row['title'],
            $row['comment'],
            $row['ai_sentiment'] ?? 'unclassified',
            $row['ai_confidence'] ? round($row['ai_confidence'] * 100) . '%' : 'N/A',
            $row['moderation_status'],
            $row['is_anonymous'] ? 'Yes' : 'No',
            $row['created_at']
        ]);
    }
    fclose($out);
    exit;
}

// Default: Complaints Report
$filename = "feedpulse-complaints-report-{$today}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

// Header Row
fputcsv($out, [
    'Reference Number', 'Title', 'Department', 'Category', 
    'Student Name', 'Priority', 'Status', 'SLA Breached', 
    'SLA Deadline', 'Escalation Count', 'Created At', 'Resolved At', 'Resolution Note'
]);

$where = "";
$params = [];
if ($userRole === 'staff' && $userDept > 0) {
    $where = "WHERE c.department_id = ?";
    $params = [$userDept];
}

$sql = "SELECT c.*, d.name as dept_name, cat.name as category_name, u.name as student_name
        FROM complaints c
        JOIN departments d ON c.department_id = d.id
        JOIN feedback_categories cat ON c.category_id = cat.id
        JOIN users u ON c.student_id = u.id
        {$where}
        ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['reference_number'],
        $row['title'],
        $row['dept_name'],
        $row['category_name'],
        $row['student_name'],
        strtoupper($row['priority']),
        strtoupper(str_replace('_', ' ', $row['status'])),
        $row['sla_breached'] ? 'YES (BREACHED)' : 'NO (ON-TIME)',
        $row['sla_deadline'],
        $row['escalation_count'] ?? 0,
        $row['created_at'],
        $row['resolved_at'] ?? 'Pending',
        $row['resolution_note'] ?? ''
    ]);
}
fclose($out);
exit;
