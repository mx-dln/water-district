<?php
error_reporting(E_ERROR | E_PARSE);
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../config/app.php';
global $db;
if (!isset($db) || !$db) {
    $db = Database::getInstance()->getConnection();
}

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
$employeeId = intval($_POST['employee_id'] ?? 0);
$type = $_POST['type'] ?? 'auto';

// Fetch employee
$emp = getEmployeeData($employeeId);
if (!$emp || empty($emp['is_active'])) {
    echo json_encode(['success' => false, 'message' => 'Active employee profile not found']);
    exit;
}

$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

// Check existing record for today
$stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$stmt->execute([$employeeId, $today]);
$existing = $stmt->fetch();

// 1-minute cooldown check: user won't in/out within 1 minute
if ($existing) {
    $recordedPunches = array_filter([
        $existing['time_in'],
        $existing['break_out'],
        $existing['break_in'],
        $existing['time_out']
    ]);
    if (!empty($recordedPunches)) {
        $lastPunchTime = max(array_map('strtotime', $recordedPunches));
        $diffSeconds = time() - $lastPunchTime;
        if ($diffSeconds < 60) {
            $waitRemaining = 60 - $diffSeconds;
            echo json_encode([
                'success' => false,
                'cooldown' => true,
                'remaining' => $waitRemaining,
                'message' => "You are already logged in. Please come back after 1 minute ({$waitRemaining}s left)."
            ]);
            exit;
        }
    }
}

// Automatic sequential punch resolution for kiosk or when type='auto':
// 1st scan -> time-in (Morning In)
// 2nd scan -> break-out (Morning Out)
// 3rd scan -> break-in (Afternoon In)
// 4th scan -> time-out (Afternoon Out)
if ($type === 'auto' || empty($type)) {
    if (!$existing || empty($existing['time_in'])) {
        $type = 'time-in';
    } elseif (empty($existing['break_out'])) {
        $type = 'break-out';
    } elseif (empty($existing['break_in'])) {
        $type = 'break-in';
    } elseif (empty($existing['time_out'])) {
        $type = 'time-out';
    } else {
        echo json_encode([
            'success' => false,
            'message' => "All 4 attendance punches (Morning In, Morning Out, Afternoon In, Afternoon Out) for {$emp['first_name']} have already been completed today."
        ]);
        exit;
    }
}

$validTypes = ['time-in', 'time-out', 'break-in', 'break-out'];
if (!in_array($type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance punch type']);
    exit;
}

$typeDb = str_replace('-', '_', $type);

// Label map for friendly output
$punchLabels = [
    'time_in' => 'Morning In',
    'break_out' => 'Morning Out',
    'break_in' => 'Afternoon In',
    'time_out' => 'Afternoon Out'
];
$currentPunchLabel = $punchLabels[$typeDb] ?? ucwords(str_replace('_', ' ', $typeDb));

// If specific punch was already recorded today
if ($existing && !empty($existing[$typeDb])) {
    $existingTime = date('h:i A', strtotime($existing[$typeDb]));
    echo json_encode([
        'success' => false,
        'message' => "{$emp['first_name']} has already recorded {$currentPunchLabel} today at {$existingTime}."
    ]);
    exit;
}

// Logical sequence check for manual submissions
if ($typeDb === 'time_out' && (!$existing || empty($existing['time_in']))) {
    echo json_encode(['success' => false, 'message' => "Cannot Time Out: {$emp['first_name']} has not timed in today."]);
    exit;
}
if ($typeDb === 'break_in' && (!$existing || empty($existing['break_out']))) {
    echo json_encode(['success' => false, 'message' => "Cannot Break In: Break Out was not recorded."]);
    exit;
}

$photoData = $_FILES['photo'] ?? null;
$photoPath = null;
$mode = $_POST['mode'] ?? ($action === 'face_record' ? 'office_face' : 'field_polygon');
$isFacePunch = ($action === 'face_record' || $mode === 'office_face');

$lat = floatval($_POST['latitude'] ?? 0);
$lng = floatval($_POST['longitude'] ?? 0);

if ($isFacePunch) {
    // Office Face Recognition Punch
    $method = 'face';
    $status = 'verified';
    $isAccepted = true;
    $locationLabel = 'Office Attendance (Face Verified)';
    $remarksText = 'Verified via Office Face Recognition Kiosk';
} else {
    // Field / Outside Attendance with Polygon
    $method = 'polygon';
    if (!validateGPSLocation($lat, $lng)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing GPS coordinates for field attendance']);
        exit;
    }

    $activePolygon = getActivePolygon();
    $isInside = false;
    if ($activePolygon) {
        $coords = json_decode($activePolygon['coordinates'], true);
        $isInside = pointInPolygon($lat, $lng, $coords);
    }

    $status = $isInside ? 'verified' : 'outside';
    $isAccepted = $isInside;
    $locationLabel = $isInside ? 'Verified Inside Geofence' : 'Outside Boundary';
    $remarksText = $isInside ? 'Verified via Field Polygon GPS' : 'Outside boundary GPS punch';
}

// Handle selfie photo upload and watermarking
if ($photoData && $photoData['error'] === UPLOAD_ERR_OK) {
    $validation = validateImageFile($photoData);
    if ($validation['valid']) {
        $year = date('Y');
        $month = date('m');
        $uploadDir = __DIR__ . '/../uploads/attendance/' . $year . '/' . $month . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = $typeDb . '_' . ($isFacePunch ? 'face_' : 'poly_') . $employeeId . '_' . time() . '.' . $validation['ext'];
        $destPath = $uploadDir . $filename;
        if (move_uploaded_file($photoData['tmp_name'], $destPath)) {
            compressImage($destPath, 1280, 1280, 80);
            watermarkImage(
                $destPath,
                $emp['first_name'] . ' ' . $emp['last_name'],
                $emp['employee_no'],
                date('F j, Y'),
                date('h:i A'),
                $lat,
                $lng,
                $currentPunchLabel,
                $locationLabel
            );
            $photoPath = 'uploads/attendance/' . $year . '/' . $month . '/' . $filename;
        }
    }
}

try {
    $db->beginTransaction();

    if ($existing) {
        $attendanceId = $existing['id'];
        $fieldSql = "{$typeDb} = ?, {$typeDb}_lat = ?, {$typeDb}_lng = ?, {$typeDb}_status = ?, {$typeDb}_photo = ?, {$typeDb}_method = ?, updated_at = NOW()";
        $sql = "UPDATE attendance SET {$fieldSql} WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$now, $lat, $lng, $status, $photoPath, $method, $attendanceId]);
    } else {
        $data = [
            'time_in' => [null, null, null, 'pending', null, 'polygon'],
            'time_out' => [null, null, null, 'pending', null, 'polygon'],
            'break_in' => [null, null, null, 'pending', null, 'polygon'],
            'break_out' => [null, null, null, 'pending', null, 'polygon'],
        ];

        $data[$typeDb] = [$now, $lat, $lng, $status, $photoPath, $method];

        $stmt = $db->prepare("
            INSERT INTO attendance (
                employee_id, date,
                time_in, time_in_lat, time_in_lng, time_in_status, time_in_photo, time_in_method,
                time_out, time_out_lat, time_out_lng, time_out_status, time_out_photo, time_out_method,
                break_in, break_in_lat, break_in_lng, break_in_status, break_in_photo, break_in_method,
                break_out, break_out_lat, break_out_lng, break_out_status, break_out_photo, break_out_method,
                device_info, browser_info, remarks
            ) VALUES (
                ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?
            )
        ");
        $stmt->execute([
            $employeeId, $today,
            $data['time_in'][0], $data['time_in'][1], $data['time_in'][2], $data['time_in'][3], $data['time_in'][4], $data['time_in'][5],
            $data['time_out'][0], $data['time_out'][1], $data['time_out'][2], $data['time_out'][3], $data['time_out'][4], $data['time_out'][5],
            $data['break_in'][0], $data['break_in'][1], $data['break_in'][2], $data['break_in'][3], $data['break_in'][4], $data['break_in'][5],
            $data['break_out'][0], $data['break_out'][1], $data['break_out'][2], $data['break_out'][3], $data['break_out'][4], $data['break_out'][5],
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $remarksText
        ]);
        $attendanceId = $db->lastInsertId();
    }

    // Record attendance photo log
    if ($photoPath) {
        $filesize = file_exists(__DIR__ . '/../' . $photoPath) ? filesize(__DIR__ . '/../' . $photoPath) : 0;
        $stmt = $db->prepare("INSERT INTO attendance_photos (attendance_id, type, filename, filepath, filesize, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$attendanceId, $typeDb, basename($photoPath), $photoPath, $filesize, $lat, $lng]);
    }

    // Record location log
    $stmt = $db->prepare("INSERT INTO attendance_locations (attendance_id, type, latitude, longitude, geofence_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$attendanceId, $typeDb, $lat, $lng, $status === 'verified' ? 'inside' : 'outside']);

    // Recompute CSC daily calculations
    cscPersistAttendanceComputation((int) $attendanceId);

    $db->commit();

    logAudit("attendance_{$typeDb}", 'attendance', "Employee #{$employeeId} ({$emp['first_name']} {$emp['last_name']}) - {$typeDb} ({$currentPunchLabel}) via {$method} ({$status})");

    $updatedRow = $db->query("SELECT time_in, break_out, break_in, time_out FROM attendance WHERE id = {$attendanceId}")->fetch(PDO::FETCH_ASSOC);
    $todayPunches = [
        'time_in' => !empty($updatedRow['time_in']) ? date('h:i A', strtotime($updatedRow['time_in'])) : null,
        'break_out' => !empty($updatedRow['break_out']) ? date('h:i A', strtotime($updatedRow['break_out'])) : null,
        'break_in' => !empty($updatedRow['break_in']) ? date('h:i A', strtotime($updatedRow['break_in'])) : null,
        'time_out' => !empty($updatedRow['time_out']) ? date('h:i A', strtotime($updatedRow['time_out'])) : null,
    ];

    echo json_encode([
        'success' => true,
        'message' => "{$currentPunchLabel} recorded successfully for {$emp['first_name']} {$emp['last_name']}.",
        'data' => [
            'employee_id' => $emp['id'],
            'employee_no' => $emp['employee_no'],
            'employee_name' => $emp['first_name'] . ' ' . $emp['last_name'],
            'department' => $emp['department_name'] ?? 'General Administration',
            'profile_pic' => $emp['profile_pic'] ? baseUrl('/' . $emp['profile_pic']) : null,
            'type' => $type,
            'punch_label' => $currentPunchLabel,
            'time' => date('h:i A'),
            'date' => date('F j, Y'),
            'status' => $status,
            'method' => $method,
            'photo' => $photoPath ? baseUrl('/' . $photoPath) : null,
            'today_punches' => $todayPunches
        ]
    ]);
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
