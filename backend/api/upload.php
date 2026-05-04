<?php
// ============================================================
// backend/api/upload.php  — Accept 1-3 classroom images
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_check.php';

$user = requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST only'], 405);
}

$classId = (int)($_POST['class_id'] ?? 0);
if (!$classId) jsonResponse(['error' => 'class_id is required'], 400);

// Teachers can only upload for their assigned classes
if ($user['role'] === 'teacher') {
    $pdo = getDB();
    $chk = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $chk->execute([$classId, $user['id']]);
    if (!$chk->fetch()) jsonResponse(['error' => 'You are not assigned to this class'], 403);
}

// Validate files received
$files = $_FILES['images'] ?? null;
if (!$files || empty($files['name'][0])) {
    jsonResponse(['error' => 'No images uploaded. Use field name: images[]'], 400);
}

$fileCount = count($files['name']);
if ($fileCount > 3) {
    jsonResponse(['error' => 'Maximum 3 images allowed'], 400);
}

// Create upload directory for today
$uploadRoot = __DIR__ . '/../uploads/' . date('Y-m-d') . '/';
if (!is_dir($uploadRoot)) {
    if (!mkdir($uploadRoot, 0755, true)) {
        jsonResponse(['error' => 'Could not create upload directory. Check folder permissions.'], 500);
    }
}

$pdo        = getDB();
$insertImg  = $pdo->prepare("INSERT INTO images (class_id, teacher_id, file_path) VALUES (?, ?, ?)");
$savedPaths = [];
$errors     = [];

for ($i = 0; $i < $fileCount; $i++) {
    // Skip empty slots
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "File {$i}: upload error code " . $files['error'][$i];
        continue;
    }

    $tmpName = $files['tmp_name'][$i];
    $origName = $files['name'][$i];
    $ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        $errors[] = "$origName: only JPG and PNG are accepted";
        continue;
    }

    // Validate it's a real image
    $imgInfo = @getimagesize($tmpName);
    if (!$imgInfo) {
        $errors[] = "$origName: not a valid image file";
        continue;
    }

    $newName  = uniqid('img_', true) . '.' . $ext;
    $destPath = $uploadRoot . $newName;

    if (!move_uploaded_file($tmpName, $destPath)) {
        $errors[] = "$origName: failed to save";
        continue;
    }

    $relPath = 'uploads/' . date('Y-m-d') . '/' . $newName;
    $insertImg->execute([$classId, $user['id'], $relPath]);
    $savedPaths[] = $destPath;   // absolute path — needed by AI service
}

if (empty($savedPaths)) {
    jsonResponse([
        'error'  => 'No valid images were saved',
        'detail' => implode('; ', $errors),
    ], 400);
}

jsonResponse([
    'success'  => true,
    'paths'    => $savedPaths,
    'class_id' => $classId,
    'count'    => count($savedPaths),
    'warnings' => $errors,
]);
