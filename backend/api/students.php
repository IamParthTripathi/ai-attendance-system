<?php
// ============================================================
// backend/api/students.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDB();

// ── GET ────────────────────────────────────────────────────
if ($method === 'GET') {
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) jsonResponse(['error' => 'class_id required'], 400);

    // Teachers: only their own class
    if ($user['role'] === 'teacher') {
        $chk = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
        $chk->execute([$classId, $user['id']]);
        if (!$chk->fetch()) jsonResponse(['error' => 'Access denied'], 403);
    }

    $stmt = $pdo->prepare("
        SELECT id, roll_number, name, photo_path, created_at
        FROM students WHERE class_id = ?
        ORDER BY roll_number
    ");
    $stmt->execute([$classId]);
    jsonResponse(['students' => $stmt->fetchAll()]);
}

// ── POST: add student (admin only) ─────────────────────────
if ($method === 'POST') {
    requireAdmin();
    $data    = json_decode(file_get_contents('php://input'), true) ?? [];
    $roll    = trim($data['roll_number'] ?? '');
    $name    = trim($data['name']        ?? '');
    $classId = (int)($data['class_id']   ?? 0);

    if (!$roll || !$name || !$classId) {
        jsonResponse(['error' => 'roll_number, name, and class_id are required'], 400);
    }

    // Check duplicate roll number
    $dup = $pdo->prepare("SELECT id FROM students WHERE roll_number = ?");
    $dup->execute([$roll]);
    if ($dup->fetch()) {
        jsonResponse(['error' => "Roll number '$roll' already exists"], 409);
    }

    $stmt = $pdo->prepare("INSERT INTO students (roll_number, name, class_id) VALUES (?, ?, ?)");
    $stmt->execute([$roll, $name, $classId]);
    jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

// ── DELETE (admin only) ─────────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 400);
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
