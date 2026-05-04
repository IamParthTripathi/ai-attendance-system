<?php
// ============================================================
// backend/api/auth.php  — Login / logout / session check
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── Login ──────────────────────────────────────────────────
if ($method === 'POST') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!$data) {
        jsonResponse(['error' => 'Invalid JSON body'], 400);
    }

    $email    = trim($data['email']    ?? '');
    $password = trim($data['password'] ?? '');

    if (!$email || !$password) {
        jsonResponse(['error' => 'Email and password are required'], 400);
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['error' => 'Invalid email or password'], 401);
    }

    // Regenerate session to prevent fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];

    jsonResponse([
        'success' => true,
        'user'    => [
            'id'   => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
        ],
    ]);
}

// ── Logout ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out']);
}

// ── Session check ──────────────────────────────────────────
if ($method === 'GET') {
    if (!empty($_SESSION['user_id'])) {
        jsonResponse([
            'loggedIn' => true,
            'user'     => [
                'id'   => $_SESSION['user_id'],
                'name' => $_SESSION['name'],
                'role' => $_SESSION['role'],
            ],
        ]);
    }
    jsonResponse(['loggedIn' => false]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
