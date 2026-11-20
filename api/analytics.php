<?php
// ============================================================
// api/analytics.php — Chart.js Data Aggregation Endpoint
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$pdo = getDB();
$type = $_GET['type'] ?? 'overview';

if ($type === 'sentiment_distribution') {
    $stmt = $pdo->query(
        'SELECT 
           COALESCE(ai_sentiment, "neutral") as sentiment, 
           COUNT(*) as total 
         FROM feedback 
         GROUP BY sentiment'
    );
    $data = $stmt->fetchAll();
    
    $labels = ['Positive', 'Neutral', 'Negative'];
    $counts = [0, 0, 0];
    foreach ($data as $row) {
        if ($row['sentiment'] === 'positive') $counts[0] = (int)$row['total'];
        if ($row['sentiment'] === 'neutral')  $counts[1] = (int)$row['total'];
        if ($row['sentiment'] === 'negative') $counts[2] = (int)$row['total'];
    }
    jsonResponse(['labels' => $labels, 'data' => $counts]);
}

if ($type === 'dept_complaints') {
    $stmt = $pdo->query(
        'SELECT d.code, COUNT(c.id) as total 
         FROM departments d 
         LEFT JOIN complaints c ON c.department_id = d.id 
         GROUP BY d.id, d.code 
         ORDER BY total DESC 
         LIMIT 8'
    );
    $rows = $stmt->fetchAll();
    $labels = array_column($rows, 'code');
    $data   = array_map('intval', array_column($rows, 'total'));
    jsonResponse(['labels' => $labels, 'data' => $data]);
}

if ($type === 'sla_compliance') {
    $stmt = $pdo->query(
        'SELECT 
           SUM(CASE WHEN sla_breached = 0 THEN 1 ELSE 0 END) as on_time,
           SUM(CASE WHEN sla_breached = 1 THEN 1 ELSE 0 END) as breached
         FROM complaints 
         WHERE status IN ("resolved", "closed")'
    );
    $row = $stmt->fetch();
    $onTime = (int)($row['on_time'] ?? 0);
    $breached = (int)($row['breached'] ?? 0);
    jsonResponse([
        'labels' => ['Within SLA', 'Breached SLA'],
        'data'   => [$onTime, $breached]
    ]);
}

jsonResponse(['error' => 'Unknown analytics query type'], 400);
