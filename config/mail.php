<?php
// ============================================================
// config/mail.php — Mail & SMTP configuration
// ============================================================
require_once __DIR__ . '/app.php';

define('MAIL_ENABLED', filter_var(env('MAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN));
define('MAIL_HOST', env('MAIL_HOST', 'smtp.mailtrap.io'));
define('MAIL_PORT', (int) env('MAIL_PORT', 2525));
define('MAIL_USER', env('MAIL_USER', 'your_smtp_user'));
define('MAIL_PASS', env('MAIL_PASS', 'your_smtp_pass'));
define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@feedpulse.edu'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'FeedPulse AI Portal'));

function sendSystemMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (!MAIL_ENABLED) {
        // Log mail locally when SMTP is disabled
        if (function_exists('logMail')) {
            logMail($toEmail, $subject, 'Mail simulated (SMTP disabled)');
        }
        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . MAIL_FROM_ADDRESS,
        'X-Mailer: PHP/' . phpversion()
    ];

    return @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));
}
