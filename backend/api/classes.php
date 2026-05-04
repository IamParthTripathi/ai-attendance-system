<?php
// ============================================================
// backend/api/classes.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$pdo    = getDB();

// ── GET: list classes ───────────────────────────────────────
if ($method === 'GET') {
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.section, c.teacher_id, u.name AS teacher_name,
                   (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS total_students
            FROM classes c
            JOIN users u ON u.id = c.teacher_id
            ORDER BY c.name, c.section
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT c.id, c.name, c.section, c.teacher_id,
                   (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS total_students
            FROM classes c
            WHERE c.teacher_id = ?
            ORDER BY c.name, c.section
        ");
        $stmt->execute([$user['id']]);
    }
    jsonResponse(['classes' => $stmt->fetchAll()]);
}

// ── POST: create class (admin only) ────────────────────────
if ($method === 'POST') {
    requireAdmin();
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $name      = trim($data['name']       ?? '');
    $section   = trim($data['section']    ?? '');
    $teacherId = (int)($data['teacher_id'] ?? 0);

    if (!$name || !$teacherId) {
        jsonResponse(['error' => 'name and teacher_id are required'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO classes (name, section, teacher_id) VALUES (?, ?, ?)");
    $stmt->execute([$name, $section ?: null, $teacherId]);
    jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

// ── DELETE: remove class (admin only) ──────────────────────
if ($method === 'DELETE') {
    requireAdmin();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'id required'], 400);
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
