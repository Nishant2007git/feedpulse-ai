<?php
// ============================================================
// database/setup.php — 1-Click Database Setup & Seeder Tool
// Run from browser: http://localhost/feedpulse-ai/database/setup.php
// Or from CLI: php database/setup.php
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

if (php_sapi_name() !== 'cli' && APP_ENV !== 'development') {
    http_response_code(403);
    die('Forbidden: Database setup script is restricted to CLI or local development mode.');
}

$isCli = (php_sapi_name() === 'cli');

function out(string $msg): void {
    global $isCli;
    if ($isCli) {
        echo $msg . "\n";
    } else {
        echo "<div>" . htmlspecialchars($msg) . "</div>";
    }
}

if (!$isCli) {
    echo '<!DOCTYPE html><html><head><title>FeedPulse AI — Database Setup</title>';
    echo '<style>body{background:#090D16;color:#F8FAFC;font-family:sans-serif;padding:2rem;} .box{max-width:700px;margin:auto;background:#0F172A;border:1px solid rgba(255,255,255,0.1);padding:2rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.5);} .btn{display:inline-block;padding:10px 20px;background:#4F46E5;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;margin-top:1rem;}</style>';
    echo '</head><body><div class="box">';
    echo '<h2>FeedPulse AI — Schema Initializer</h2>';
}

try {
    // 1. Connect without DB name to create it if missing
    $pdoRoot = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    out("Connecting to MySQL host " . DB_HOST . "...");
    $sqlFile = __DIR__ . '/feedpulse.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("feedpulse.sql not found at {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    out("Executing feedpulse.sql schema script...");
    
    // Execute multiple statements
    $pdoRoot->exec($sql);
    
    out("✅ Database 'feedpulse_ai' and all tables/seed data created successfully!");
    
    if (!$isCli) {
        echo '<a href="../index.php" class="btn">Launch FeedPulse AI &rarr;</a>';
    }
} catch (Exception $e) {
    out("❌ Error: " . $e->getMessage());
}

if (!$isCli) {
    echo '</div></body></html>';
}
