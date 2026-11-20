<?php
// ============================================================
// includes/auth.php — Session guard & role helpers
// ============================================================

function requireAuth(array $allowedRoles = []): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    // Session timeout check
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: ' . APP_URL . '/login.php?expired=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();

    // Role gate
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}

function currentUser(): array {
    return [
        'id'     => (int) ($_SESSION['user_id'] ?? 0),
        'role'   => $_SESSION['role']   ?? '',
        'name'   => $_SESSION['name']   ?? '',
        'avatar' => $_SESSION['avatar'] ?? null,
    ];
}

function isLoggedIn(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']);
}

function redirectIfLoggedIn(): void {
    if (isLoggedIn()) {
        $dashMap = [
            'student'     => APP_URL . '/student/dashboard.php',
            'staff'       => APP_URL . '/staff/dashboard.php',
            'admin'       => APP_URL . '/admin/dashboard.php',
            'super_admin' => APP_URL . '/admin/dashboard.php',
        ];
        $role = $_SESSION['role'] ?? 'student';
        header('Location: ' . ($dashMap[$role] ?? APP_URL . '/student/dashboard.php'));
        exit;
    }
}

function dashboardUrl(string $role): string {
    return match($role) {
        'staff'       => APP_URL . '/staff/dashboard.php',
        'admin',
        'super_admin' => APP_URL . '/admin/dashboard.php',
        default       => APP_URL . '/student/dashboard.php',
    };
}
