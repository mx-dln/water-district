<?php
error_reporting(E_ERROR | E_PARSE);
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

requireLogin();

$token = $_POST[CSRF_TOKEN_NAME] ?? '';
if (!validateCSRFToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'record') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$employeeId = intval($_POST['employee_id'] ?? 0);
$type = $_POST['type'] ?? '';
$lat = floatval($_POST['latitude'] ?? 0);
$lng = floatval($_POST['longitude'] ?? 0);
$photoData = $_FILES['photo'] ?? null;

if (!$employeeId || !$type || !$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$validTypes = ['time-in', 'time-out', 'break-in', 'break-out'];
if (!in_array($type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance type']);
    exit;
}

$typeDb = str_replace('-', '_', $type);

// Validate GPS
if (!validateGPSLocation($lat, $lng)) {
    echo json_encode(['success' => false, 'message' => 'Invalid GPS coordinates']);
    exit;
}

// Get employee
$emp = getEmployeeData($employeeId);
if (!$emp) {
    echo json_encode(['success' => false, 'message' => 'Employee not found']);
    exit;
}

// Check polygon
$activePolygon = getActivePolygon();
$isInside = false;
if ($activePolygon) {
    $coords = json_decode($activePolygon['coordinates'], true);
    $isInside = pointInPolygon($lat, $lng, $coords);
} else {
    $isInside = false;
}

$geofenceStatus = $isInside ? 'verified' : 'outside';

if (!$isInside) {
    // Still save the attempt but mark as outside
}

// Handle photo upload
$photoPath = null;
if ($photoData && $photoData['error'] === UPLOAD_ERR_OK) {
    $validation = validateImageFile($photoData);
    if ($validation['valid']) {
        $year = date('Y');
        $month = date('m');
        $uploadDir = __DIR__ . '/../uploads/attendance/' . $year . '/' . $month . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = $type . '_' . $employeeId . '_' . time() . '.' . $validation['ext'];
        $destPath = $uploadDir . $filename;
        move_uploaded_file($photoData['tmp_name'], $destPath);

        // Watermark
        compressImage($destPath, 1920, 1920, 75);
        watermarkImage(
            $destPath,
            $emp['first_name'] . ' ' . $emp['last_name'],
            $emp['employee_no'],
            date('F j, Y'),
            date('h:i A'),
            $lat,
            $lng,
            ucwords(str_replace('-', ' ', $type)),
            $isInside ? 'Verified Inside Office Boundary' : 'Outside Office Boundary'
        );

        $photoPath = 'uploads/attendance/' . $year . '/' . $month . '/' . $filename;
    }
}

try {
    $db->beginTransaction();

    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');

    // Get or create attendance record
    $stmt = $db->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
    $stmt->execute([$employeeId, $today]);
    $existing = $stmt->fetch();

    if ($existing) {
        $attendanceId = $existing['id'];
        $updateFields = [
            'time_in' => "time_in = ?, time_in_lat = ?, time_in_lng = ?, time_in_status = ?, time_in_photo = ?",
            'time_out' => "time_out = ?, time_out_lat = ?, time_out_lng = ?, time_out_status = ?, time_out_photo = ?",
            'break_in' => "break_in = ?, break_in_lat = ?, break_in_lng = ?, break_in_status = ?, break_in_photo = ?",
            'break_out' => "break_out = ?, break_out_lat = ?, break_out_lng = ?, break_out_status = ?, break_out_photo = ?",
        ];

        if (isset($updateFields[$typeDb])) {
            $sql = "UPDATE attendance SET {$updateFields[$typeDb]}, updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$now, $lat, $lng, $geofenceStatus, $photoPath, $attendanceId]);
        }
    } else {
        $data = [
            'time_in' => [$now, $lat, $lng, $geofenceStatus, $photoPath],
            'time_out' => [null, null, null, 'pending', null],
            'break_in' => [null, null, null, 'pending', null],
            'break_out' => [null, null, null, 'pending', null],
        ];

        $data[$typeDb] = [$now, $lat, $lng, $geofenceStatus, $photoPath];

        $stmt = $db->prepare("INSERT INTO attendance (employee_id, date, time_in, time_in_lat, time_in_lng, time_in_status, time_in_photo, time_out, time_out_lat, time_out_lng, time_out_status, time_out_photo, break_in, break_in_lat, break_in_lng, break_in_status, break_in_photo, break_out, break_out_lat, break_out_lng, break_out_status, break_out_photo, device_info, browser_info) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $employeeId, $today,
            $data['time_in'][0], $data['time_in'][1], $data['time_in'][2], $data['time_in'][3], $data['time_in'][4],
            $data['time_out'][0], $data['time_out'][1], $data['time_out'][2], $data['time_out'][3], $data['time_out'][4],
            $data['break_in'][0], $data['break_in'][1], $data['break_in'][2], $data['break_in'][3], $data['break_in'][4],
            $data['break_out'][0], $data['break_out'][1], $data['break_out'][2], $data['break_out'][3], $data['break_out'][4],
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        $attendanceId = $db->lastInsertId();
    }

    // Log location
    $stmt = $db->prepare("INSERT INTO attendance_locations (attendance_id, type, latitude, longitude, geofence_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$attendanceId, $typeDb, $lat, $lng, $isInside ? 'inside' : 'outside']);

    cscPersistAttendanceComputation((int) $attendanceId);

    $db->commit();

    logAudit("attendance_{$typeDb}", 'attendance', "Employee #$employeeId - $typeDb " . ($isInside ? 'verified' : 'outside'));

    echo json_encode([
        'success' => $isInside,
        'message' => $isInside
            ? ucwords(str_replace('-', ' ', $type)) . ' recorded successfully. Location verified inside office boundary.'
            : 'You are outside the office boundary. Attendance not accepted.',
        'data' => [
            'type' => $type,
            'time' => date('h:i A'),
            'status' => $geofenceStatus,
            'inside' => $isInside
        ]
    ]);
    exit;
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
