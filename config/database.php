<?php
// ============================================================
// config/database.php — DB credentials & PDO factory
// ============================================================
require_once __DIR__ . '/app.php';

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'feedpulse_ai'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode=(SELECT REPLACE(@@sql_mode,'ANSI_QUOTES',''))",
    ];

    if (filter_var(env('DB_SSL', false), FILTER_VALIDATE_BOOLEAN) || DB_PORT != '3306') {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        // Auto-migrate file_blob column if missing
        static $migrated = false;
        if (!$migrated) {
            try {
                $pdo->query("SELECT file_blob FROM feedback_attachments LIMIT 1");
            } catch (Throwable $e) {
                try {
                    $pdo->exec("ALTER TABLE feedback_attachments ADD COLUMN file_blob LONGTEXT NULL");
                } catch (Throwable $ignored) {}
            }
            $migrated = true;
        }
    } catch (PDOException $e) {
        // Never expose credentials in output
        error_log('[FeedPulse] DB Connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('<h1>Service Unavailable</h1><p>Database connection error. Please try later.</p>');
    }
    return $pdo;
}
