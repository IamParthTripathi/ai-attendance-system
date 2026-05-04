<?php
// ============================================================
// backend/api/teachers.php  — list teachers (for dropdowns)
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$user = requireAuth();
$pdo  = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT id, name, email, role FROM users ORDER BY name");
    jsonResponse(['teachers' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    $data     = json_decode(file_get_contents('php://input'), true) ?? [];
    $name     = trim($data['name']     ?? '');
    $email    = trim($data['email']    ?? '');
    $password = trim($data['password'] ?? '');
    $role     = in_array($data['role'] ?? '', ['admin','teacher']) ? $data['role'] : 'teacher';

    if (!$name || !$email || !$password) {
        jsonResponse(['error' => 'name, email, and password are required'], 400);
    }

    $dup = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $dup->execute([$email]);
    if ($dup->fetch()) jsonResponse(['error' => "Email '$email' already in use"], 409);

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hash, $role]);
    jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
