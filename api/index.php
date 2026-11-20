<?php
// Entrypoint for Vercel Serverless
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__);
chdir($root);

// Normalize path
$path = trim($uri, '/');
if ($path === '' || $path === 'index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require $root . '/index.php';
    exit;
}

$target = $root . '/' . $path;

if (is_file($target)) {
    $ext = pathinfo($target, PATHINFO_EXTENSION);
    if ($ext === 'php') {
        $_SERVER['SCRIPT_NAME'] = '/' . $path;
        require $target;
    } else {
        // Static file fallback
        $mimes = [
            'css' => 'text/css',
            'js'  => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile($target);
    }
    exit;
}

if (is_file($target . '.php')) {
    require $target . '.php';
    exit;
}

// Check directory index
if (is_dir($target)) {
    if (is_file($target . '/index.php')) {
        require $target . '/index.php';
        exit;
    }
    if (is_file($target . '/dashboard.php')) {
        require $target . '/dashboard.php';
        exit;
    }
}

http_response_code(404);
echo "404 Not Found";
