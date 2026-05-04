<?php
// ============================================================
// backend/middleware/auth_check.php
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function requireAuth(): array {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized. Please login.']);
        exit;
    }
    return [
        'id'   => (int)$_SESSION['user_id'],
        'role' => $_SESSION['role'],
        'name' => $_SESSION['name'],
    ];
}

function requireAdmin(): void {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Admin access required.']);
        exit;
    }
}
