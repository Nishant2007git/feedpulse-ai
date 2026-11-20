<?php
// ============================================================
// api/ai-sentiment.php — Real-time AI Sentiment & Triage Engine
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Authentication required'], 401);
}

$pdo = getDB();
if (!checkRateLimit($pdo, 'ai_sentiment')) {
    jsonResponse(['error' => 'Rate limit exceeded. Please try again shortly.'], 429);
}
incrementRateLimit($pdo, 'ai_sentiment');

$text = trim($_POST['text'] ?? '');
if (empty($text)) {
    jsonResponse(['error' => 'No text provided'], 400);
}

// Intelligent sentiment classification heuristics
$textLower = strtolower($text);

$positiveWords = ['excellent', 'great', 'awesome', 'helpful', 'prompt', 'good', 'improved', 'satisfied', 'friendly', 'clear', 'fast', 'love', 'clean', 'polite'];
$negativeWords = ['terrible', 'broken', 'worst', 'horrible', 'delayed', 'rude', 'unacceptable', 'slow', 'leak', 'dirty', 'failed', 'issue', 'problem', 'stink', 'smell', 'breach', 'harass', 'unfair', 'corrupt'];

$posScore = 0;
$negScore = 0;

foreach ($positiveWords as $pw) {
    if (str_contains($textLower, $pw)) $posScore += 1.5;
}
foreach ($negativeWords as $nw) {
    if (str_contains($textLower, $nw)) $negScore += 1.5;
}

$sentiment = 'neutral';
$confidence = 0.75;
$badgeClass = 'muted';
$icon = '😐';

if ($posScore > $negScore) {
    $sentiment = 'positive';
    $confidence = min(0.98, 0.70 + ($posScore * 0.08));
    $badgeClass = 'resolved';
    $icon = '😊';
} elseif ($negScore > $posScore) {
    $sentiment = 'negative';
    $confidence = min(0.99, 0.72 + ($negScore * 0.08));
    $badgeClass = 'breach';
    $icon = '⚠️';
}

// Category suggestion inference
$categoryMap = [
    'Academic Quality'   => ['lecture', 'teacher', 'professor', 'exam', 'grade', 'syllabus', 'quiz', 'course', 'assignment'],
    'Facilities'         => ['washroom', 'toilet', 'ac', 'fan', 'chair', 'desk', 'light', 'water', 'building', 'elevator'],
    'Food Services'      => ['cafeteria', 'canteen', 'food', 'lunch', 'tea', 'hygiene', 'taste', 'price', 'menu'],
    'IT & Technology'    => ['wifi', 'internet', 'portal', 'computer', 'lab', 'software', 'network', 'login', 'server'],
    'Staff & Admin'      => ['clerk', 'fee', 'account', 'registrar', 'staff', 'admin', 'rude', 'delay', 'officer'],
    'Health & Safety'    => ['doctor', 'clinic', 'first aid', 'ambulance', 'guard', 'security', 'theft', 'harassment'],
    'Library'            => ['book', 'library', 'librarian', 'journal', 'study area', 'borrow'],
    'Transportation'     => ['bus', 'shuttle', 'route', 'driver', 'parking', 'transport'],
];

$suggestedCategory = null;
foreach ($categoryMap as $cat => $keywords) {
    foreach ($keywords as $kw) {
        if (str_contains($textLower, $kw)) {
            $suggestedCategory = $cat;
            break 2;
        }
    }
}

jsonResponse([
    'sentiment'          => $sentiment,
    'confidence'         => round($confidence, 2),
    'badge_class'        => $badgeClass,
    'icon'               => $icon,
    'suggested_category' => $suggestedCategory
]);
