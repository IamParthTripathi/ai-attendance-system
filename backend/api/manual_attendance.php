<?php
// ============================================================
// backend/api/manual_attendance.php
// Mark a single student's attendance manually (present/absent)
// Called from the Manual Attendance page per student toggle
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$user = requireAuth();

// Only POST is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$studentId = (int)($data['student_id'] ?? 0);
$classId   = (int)($data['class_id']   ?? 0);
$status    = $data['status']            ?? '';
$date      = $data['date']             ?? date('Y-m-d');

// ── Validate inputs ───────────────────────────────────────────
if (!$studentId) jsonResponse(['error' => 'student_id is required'], 400);
if (!$classId)   jsonResponse(['error' => 'class_id is required'],   400);
if (!in_array($status, ['present', 'absent'], true)) {
    jsonResponse(['error' => 'status must be "present" or "absent"'], 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$pdo = getDB();

// ── Teacher access guard ──────────────────────────────────────
if ($user['role'] === 'teacher') {
    $chk = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $chk->execute([$classId, $user['id']]);
    if (!$chk->fetch()) {
        jsonResponse(['error' => 'Access denied to this class'], 403);
    }
}

// ── Verify student belongs to this class ──────────────────────
$verify = $pdo->prepare("SELECT id, name, roll_number FROM students WHERE id = ? AND class_id = ?");
$verify->execute([$studentId, $classId]);
$student = $verify->fetch();
if (!$student) {
    jsonResponse(['error' => 'Student not found in this class'], 404);
}

// ── Upsert attendance record ──────────────────────────────────
$upsert = $pdo->prepare("
    INSERT INTO attendance (student_id, class_id, date, status)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        status    = VALUES(status),
        marked_at = CURRENT_TIMESTAMP
");
$upsert->execute([$studentId, $classId, $date, $status]);

// Return updated record with timestamp
$rec = $pdo->prepare("SELECT status, marked_at FROM attendance WHERE student_id = ? AND class_id = ? AND date = ?");
$rec->execute([$studentId, $classId, $date]);
$record = $rec->fetch();

jsonResponse([
    'success'    => true,
    'student_id' => $studentId,
    'class_id'   => $classId,
    'date'       => $date,
    'status'     => $status,
    'marked_at'  => $record['marked_at'] ?? null,
    'student'    => [
        'name'        => $student['name'],
        'roll_number' => $student['roll_number'],
    ],
]);
