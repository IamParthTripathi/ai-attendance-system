<?php
// ============================================================
// backend/api/process.php
// Calls Python AI → marks attendance in MySQL
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only'], 405);
}

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$classId = (int)($data['class_id']   ?? 0);
$paths   = $data['image_paths']      ?? [];
$date    = $data['date']             ?? date('Y-m-d');

if (!$classId)       jsonResponse(['error' => 'class_id is required'], 400);
if (empty($paths))   jsonResponse(['error' => 'image_paths is required'], 400);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// ── Check cURL is available ─────────────────────────────────
if (!function_exists('curl_init')) {
    jsonResponse(['error' => 'PHP cURL extension is not enabled. Enable it in php.ini: extension=curl'], 500);
}

// ── Build multipart body for Python AI service ──────────────
$boundary   = '----Boundary' . bin2hex(random_bytes(8));
$body       = '';

// class_id field
$body .= "--{$boundary}\r\n";
$body .= "Content-Disposition: form-data; name=\"class_id\"\r\n\r\n{$classId}\r\n";

// image files
$validPaths = [];
foreach ($paths as $absPath) {
    if (!file_exists($absPath)) {
        error_log("[process.php] File not found: $absPath");
        continue;
    }
    $fn      = basename($absPath);
    $ext     = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    $mime    = ($ext === 'png') ? 'image/png' : 'image/jpeg';
    $content = file_get_contents($absPath);

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"images\"; filename=\"{$fn}\"\r\n";
    $body .= "Content-Type: {$mime}\r\n\r\n{$content}\r\n";
    $validPaths[] = $absPath;
}

if (empty($validPaths)) {
    jsonResponse(['error' => 'None of the provided image paths exist on the server'], 400);
}

$body .= "--{$boundary}--\r\n";

// ── Call Python AI service ──────────────────────────────────
$ch = curl_init(AI_SERVICE_URL . '/process');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: multipart/form-data; boundary={$boundary}",
        "Content-Length: " . strlen($body),
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$aiRaw    = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ── Handle AI service errors ────────────────────────────────
if ($curlErr) {
    jsonResponse([
        'error'  => 'Cannot reach the AI service. Make sure it is running.',
        'detail' => "Start it with: uvicorn app:app --host 0.0.0.0 --port 5000 (in ai-service folder with venv active)",
        'curl'   => $curlErr,
    ], 500);
}

if ($httpCode !== 200) {
    jsonResponse([
        'error'     => "AI service returned HTTP $httpCode",
        'detail'    => $aiRaw,
    ], 500);
}

$ai = json_decode($aiRaw, true);
if (!$ai) {
    jsonResponse(['error' => 'AI service returned invalid JSON', 'raw' => $aiRaw], 500);
}

$presentRolls = $ai['present_rolls'] ?? [];

// ── Load all students in this class ─────────────────────────
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id, roll_number, name FROM students WHERE class_id = ? ORDER BY roll_number");
$stmt->execute([$classId]);
$students = $stmt->fetchAll();

if (empty($students)) {
    jsonResponse(['error' => "No students found in class ID $classId. Add students first."], 400);
}

// ── Upsert attendance (INSERT or UPDATE if already marked) ──
$upsert = $pdo->prepare("
    INSERT INTO attendance (student_id, class_id, date, status)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        status    = VALUES(status),
        marked_at = CURRENT_TIMESTAMP
");

$present = [];
$absent  = [];

foreach ($students as $s) {
    $status = in_array($s['roll_number'], $presentRolls, true) ? 'present' : 'absent';
    $upsert->execute([$s['id'], $classId, $date, $status]);

    $entry = ['id' => $s['id'], 'roll' => $s['roll_number'], 'name' => $s['name']];
    if ($status === 'present') {
        $present[] = $entry;
    } else {
        $absent[]  = $entry;
    }
}

jsonResponse([
    'success'       => true,
    'date'          => $date,
    'class_id'      => $classId,
    'total'         => count($students),
    'present_count' => count($present),
    'absent_count'  => count($absent),
    'present'       => $present,
    'absent'        => $absent,
    'face_count'    => (int)($ai['face_count']    ?? 0),
    'unknown_faces' => (int)($ai['unknown_count'] ?? 0),
]);
