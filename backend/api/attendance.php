<?php
// ============================================================
// backend/api/attendance.php
// GET ?dashboard=1&date=YYYY-MM-DD  → summary for all classes
// GET ?class_id=N&date=YYYY-MM-DD   → records for one class
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$user = requireAuth();
$pdo  = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'GET only'], 405);
}

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// ── Dashboard mode ──────────────────────────────────────────
if (isset($_GET['dashboard'])) {
    // Get class IDs this user can see
    if ($user['role'] === 'admin') {
        $cStmt = $pdo->query("SELECT id FROM classes");
    } else {
        $cStmt = $pdo->prepare("SELECT id FROM classes WHERE teacher_id = ?");
        $cStmt->execute([$user['id']]);
    }
    $classIds = array_column($cStmt->fetchAll(), 'id');

    if (empty($classIds)) {
        jsonResponse([
            'total_students' => 0,
            'total_present'  => 0,
            'total_absent'   => 0,
            'classes'        => [],
        ]);
    }

    $in = implode(',', array_fill(0, count($classIds), '?'));

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id IN ($in)");
    $totalStmt->execute($classIds);
    $totalStudents = (int)$totalStmt->fetchColumn();

    $presStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM attendance WHERE class_id IN ($in) AND date = ? AND status = 'present'"
    );
    $presStmt->execute([...$classIds, $date]);
    $totalPresent = (int)$presStmt->fetchColumn();

    // Per-class breakdown
    $classes = [];
    foreach ($classIds as $cid) {
        $infoStmt = $pdo->prepare("SELECT id, name, section FROM classes WHERE id = ?");
        $infoStmt->execute([$cid]);
        $info = $infoStmt->fetch();

        $tsStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ?");
        $tsStmt->execute([$cid]);
        $ts = (int)$tsStmt->fetchColumn();

        $tpStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM attendance WHERE class_id = ? AND date = ? AND status = 'present'"
        );
        $tpStmt->execute([$cid, $date]);
        $tp = (int)$tpStmt->fetchColumn();

        $classes[] = [
            'id'             => $cid,
            'name'           => $info['name'],
            'section'        => $info['section'] ?? '',
            'total_students' => $ts,
            'present'        => $tp,
            'absent'         => $ts - $tp,
        ];
    }

    jsonResponse([
        'total_students' => $totalStudents,
        'total_present'  => $totalPresent,
        'total_absent'   => $totalStudents - $totalPresent,
        'classes'        => $classes,
    ]);
}

// ── Class + date mode ───────────────────────────────────────
$classId = (int)($_GET['class_id'] ?? 0);
if (!$classId) jsonResponse(['error' => 'class_id is required'], 400);

// Teacher access guard
if ($user['role'] === 'teacher') {
    $chk = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $chk->execute([$classId, $user['id']]);
    if (!$chk->fetch()) jsonResponse(['error' => 'Access denied to this class'], 403);
}

$stmt = $pdo->prepare("
    SELECT
        s.id,
        s.roll_number,
        s.name,
        COALESCE(a.status, 'not marked') AS status,
        a.marked_at
    FROM students s
    LEFT JOIN attendance a
           ON a.student_id = s.id
          AND a.class_id   = ?
          AND a.date       = ?
    WHERE s.class_id = ?
    ORDER BY s.roll_number
");
$stmt->execute([$classId, $date, $classId]);
$records = $stmt->fetchAll();

$presentCount = count(array_filter($records, fn($r) => $r['status'] === 'present'));
$absentCount  = count(array_filter($records, fn($r) => $r['status'] === 'absent'));

jsonResponse([
    'date'          => $date,
    'class_id'      => $classId,
    'total'         => count($records),
    'present_count' => $presentCount,
    'absent_count'  => $absentCount,
    'records'       => $records,
]);
