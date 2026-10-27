<?php
error_reporting(E_ERROR | E_PARSE);
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../config/app.php';

requireLogin();

global $db;
if (!isset($db) || !$db) {
    $db = Database::getInstance()->getConnection();
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'get_enrolled':
        // Return active employees with their face descriptors for the kiosk matcher
        try {
            $stmt = $db->query("
                SELECT 
                    e.id as employee_id,
                    e.employee_no,
                    e.first_name,
                    e.last_name,
                    e.profile_pic,
                    d.name as department_name,
                    ef.id as face_id,
                    ef.descriptor,
                    ef.photo_path
                FROM employees e
                JOIN employee_faces ef ON e.id = ef.employee_id
                LEFT JOIN departments d ON e.department_id = d.id
                WHERE e.is_active = 1
                ORDER BY e.last_name, e.first_name, ef.id ASC
            ");
            $rows = $stmt->fetchAll();

            $employeesMap = [];
            foreach ($rows as $row) {
                $empId = (int) $row['employee_id'];
                if (!isset($employeesMap[$empId])) {
                    $employeesMap[$empId] = [
                        'employee_id' => $empId,
                        'employee_no' => $row['employee_no'],
                        'name' => $row['first_name'] . ' ' . $row['last_name'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'department' => $row['department_name'] ?? 'General',
                        'profile_pic' => $row['profile_pic'] ? baseUrl('/' . $row['profile_pic']) : null,
                        'descriptors' => [],
                        'photos' => []
                    ];
                }
                $desc = json_decode($row['descriptor'], true);
                if (is_array($desc) && count($desc) === 128) {
                    $employeesMap[$empId]['descriptors'][] = $desc;
                    if ($row['photo_path']) {
                        $employeesMap[$empId]['photos'][] = baseUrl('/' . $row['photo_path']);
                    }
                }
            }

            // Fetch today's attendance to track recent punches and cooldown
            $todayStr = date('Y-m-d');
            $attStmt = $db->prepare("SELECT employee_id, time_in, break_out, break_in, time_out FROM attendance WHERE date = ?");
            $attStmt->execute([$todayStr]);
            $attRows = $attStmt->fetchAll();
            $todayPunchesMap = [];
            foreach ($attRows as $ar) {
                $pList = array_filter([$ar['time_in'], $ar['break_out'], $ar['break_in'], $ar['time_out']]);
                if (!empty($pList)) {
                    $latestTs = max(array_map('strtotime', $pList));
                    $todayPunchesMap[(int)$ar['employee_id']] = [
                        'last_punch_ts' => $latestTs * 1000,
                        'seconds_ago' => time() - $latestTs
                    ];
                }
            }

            foreach ($employeesMap as $empId => &$empData) {
                if (isset($todayPunchesMap[$empId])) {
                    $empData['last_punch_ts'] = $todayPunchesMap[$empId]['last_punch_ts'];
                    $empData['last_punch_seconds_ago'] = $todayPunchesMap[$empId]['seconds_ago'];
                } else {
                    $empData['last_punch_ts'] = null;
                    $empData['last_punch_seconds_ago'] = null;
                }
            }
            unset($empData);

            echo json_encode([
                'success' => true,
                'count' => count($employeesMap),
                'employees' => array_values($employeesMap)
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load face database: ' . $e->getMessage()]);
        }
        exit;

    case 'get_employee_faces':
        requireRole('Administrator');
        $employeeId = intval($_GET['employee_id'] ?? 0);
        if (!$employeeId) {
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
            exit;
        }

        try {
            $stmt = $db->prepare("SELECT id, photo_path, created_at FROM employee_faces WHERE employee_id = ? ORDER BY created_at DESC");
            $stmt->execute([$employeeId]);
            $faces = $stmt->fetchAll();
            foreach ($faces as &$f) {
                $f['photo_url'] = $f['photo_path'] ? baseUrl('/' . $f['photo_path']) : null;
            }
            echo json_encode(['success' => true, 'faces' => $faces]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;

    case 'get_employee_attendance':
    case 'get_today_attendance':
        $empId = intval($_GET['employee_id'] ?? ($_POST['employee_id'] ?? 0));
        $emp = getEmployeeData($empId);
        if (!$emp) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }

        $todayStr = date('Y-m-d');
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

        // Fetch this week's records
        $stmt = $db->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? 
              AND date BETWEEN ? AND ? 
            ORDER BY date ASC
        ");
        $stmt->execute([$empId, $startOfWeek, $endOfWeek]);
        $weekRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $recordsByDate = [];
        foreach ($weekRows as $r) {
            $recordsByDate[$r['date']] = $r;
        }

        // Build Mon - Sun week days array
        $weekDays = [];
        $cur = strtotime($startOfWeek);
        while ($cur <= strtotime($endOfWeek)) {
            $dStr = date('Y-m-d', $cur);
            $rec = $recordsByDate[$dStr] ?? null;

            $pCount = 0;
            if (!empty($rec['time_in'])) $pCount++;
            if (!empty($rec['break_out'])) $pCount++;
            if (!empty($rec['break_in'])) $pCount++;
            if (!empty($rec['time_out'])) $pCount++;

            $weekDays[] = [
                'date' => $dStr,
                'day_short' => date('D', $cur),
                'day_full' => date('l', $cur),
                'formatted' => date('M j', $cur),
                'is_today' => ($dStr === $todayStr),
                'is_past' => ($dStr < $todayStr),
                'is_future' => ($dStr > $todayStr),
                'has_record' => !empty($rec),
                'punch_count' => $pCount,
                'time_in' => !empty($rec['time_in']) ? date('h:i A', strtotime($rec['time_in'])) : null,
                'break_out' => !empty($rec['break_out']) ? date('h:i A', strtotime($rec['break_out'])) : null,
                'break_in' => !empty($rec['break_in']) ? date('h:i A', strtotime($rec['break_in'])) : null,
                'time_out' => !empty($rec['time_out']) ? date('h:i A', strtotime($rec['time_out'])) : null,
                'status' => $rec ? ($pCount >= 4 ? 'Complete' : ($pCount > 0 ? "In Progress ({$pCount}/4)" : 'No Punches')) : ($dStr <= $todayStr ? 'No Record' : 'Upcoming')
            ];
            $cur = strtotime('+1 day', $cur);
        }

        // Also fetch past 7 active attendance records for previous week / history
        $stmtPast = $db->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? 
            ORDER BY date DESC 
            LIMIT 7
        ");
        $stmtPast->execute([$empId]);
        $pastRows = $stmtPast->fetchAll(PDO::FETCH_ASSOC);

        $pastHistory = [];
        foreach ($pastRows as $p) {
            $pCount = 0;
            if (!empty($p['time_in'])) $pCount++;
            if (!empty($p['break_out'])) $pCount++;
            if (!empty($p['break_in'])) $pCount++;
            if (!empty($p['time_out'])) $pCount++;

            $pastHistory[] = [
                'date' => $p['date'],
                'day_short' => date('D', strtotime($p['date'])),
                'formatted' => date('M j, Y', strtotime($p['date'])),
                'is_today' => ($p['date'] === $todayStr),
                'time_in' => !empty($p['time_in']) ? date('h:i A', strtotime($p['time_in'])) : null,
                'break_out' => !empty($p['break_out']) ? date('h:i A', strtotime($p['break_out'])) : null,
                'break_in' => !empty($p['break_in']) ? date('h:i A', strtotime($p['break_in'])) : null,
                'time_out' => !empty($p['time_out']) ? date('h:i A', strtotime($p['time_out'])) : null,
                'punch_count' => $pCount,
                'status' => $pCount >= 4 ? 'Complete' : "In Progress ({$pCount}/4)"
            ];
        }

        // Today's details
        $todayRec = $recordsByDate[$todayStr] ?? null;
        $todayPunches = [
            'time_in' => !empty($todayRec['time_in']) ? date('h:i A', strtotime($todayRec['time_in'])) : null,
            'break_out' => !empty($todayRec['break_out']) ? date('h:i A', strtotime($todayRec['break_out'])) : null,
            'break_in' => !empty($todayRec['break_in']) ? date('h:i A', strtotime($todayRec['break_in'])) : null,
            'time_out' => !empty($todayRec['time_out']) ? date('h:i A', strtotime($todayRec['time_out'])) : null,
        ];

        $latestPunch = null;
        if (!empty($todayRec['time_out'])) {
            $latestPunch = ['label' => 'Afternoon Out', 'time' => $todayPunches['time_out'], 'key' => 'time_out', 'status' => $todayRec['time_out_status'] ?? 'verified'];
        } elseif (!empty($todayRec['break_in'])) {
            $latestPunch = ['label' => 'Afternoon In', 'time' => $todayPunches['break_in'], 'key' => 'break_in', 'status' => $todayRec['break_in_status'] ?? 'verified'];
        } elseif (!empty($todayRec['break_out'])) {
            $latestPunch = ['label' => 'Morning Out', 'time' => $todayPunches['break_out'], 'key' => 'break_out', 'status' => $todayRec['break_out_status'] ?? 'verified'];
        } elseif (!empty($todayRec['time_in'])) {
            $latestPunch = ['label' => 'Morning In', 'time' => $todayPunches['time_in'], 'key' => 'time_in', 'status' => $todayRec['time_in_status'] ?? 'verified'];
        }

        $latestPunchTs = null;
        $secondsSinceLastPunch = null;
        if ($todayRec) {
            $pList = array_filter([$todayRec['time_in'], $todayRec['break_out'], $todayRec['break_in'], $todayRec['time_out']]);
            if (!empty($pList)) {
                $latestPunchTs = max(array_map('strtotime', $pList));
                $secondsSinceLastPunch = time() - $latestPunchTs;
            }
        }

        echo json_encode([
            'success' => true,
            'employee' => [
                'id' => $emp['id'],
                'employee_no' => $emp['employee_no'],
                'name' => $emp['first_name'] . ' ' . $emp['last_name'],
                'department' => $emp['department_name'] ?? 'General Administration',
                'profile_pic' => $emp['profile_pic'] ? baseUrl('/' . $emp['profile_pic']) : null,
            ],
            'today_punches' => $todayPunches,
            'latest_punch' => $latestPunch,
            'last_punch_ts' => $latestPunchTs ? ($latestPunchTs * 1000) : null,
            'seconds_since_last_punch' => $secondsSinceLastPunch,
            'has_punched_today' => !empty($latestPunch),
            'week_days' => $weekDays,
            'past_history' => $pastHistory
        ]);
        exit;

    case 'enroll':
        requireRole('Administrator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST method required']);
            exit;
        }

        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        $employeeId = intval($_POST['employee_id'] ?? 0);
        $descriptorJson = $_POST['descriptor'] ?? '';
        $descriptor = json_decode($descriptorJson, true);

        if (!$employeeId) {
            echo json_encode(['success' => false, 'message' => 'Employee is required']);
            exit;
        }

        if (!is_array($descriptor) || count($descriptor) !== 128) {
            echo json_encode(['success' => false, 'message' => 'Invalid face descriptor format (must be 128-dimensional vector)']);
            exit;
        }

        $emp = getEmployeeData($employeeId);
        if (!$emp) {
            echo json_encode(['success' => false, 'message' => 'Employee not found']);
            exit;
        }

        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $validation = validateImageFile($_FILES['photo']);
            if ($validation['valid']) {
                $dir = __DIR__ . '/../uploads/faces/' . date('Y/m') . '/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = 'face_' . $employeeId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $validation['ext'];
                $dest = $dir . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    compressImage($dest, 600, 600, 85);
                    $photoPath = 'uploads/faces/' . date('Y/m') . '/' . $filename;
                }
            }
        }

        try {
            $stmt = $db->prepare("INSERT INTO employee_faces (employee_id, descriptor, photo_path) VALUES (?, ?, ?)");
            $stmt->execute([$employeeId, json_encode($descriptor), $photoPath]);
            $faceId = $db->lastInsertId();

            logAudit('enroll_face', 'employees', "Face enrolled for {$emp['first_name']} {$emp['last_name']} (#{$emp['employee_no']})");

            echo json_encode([
                'success' => true,
                'message' => 'Face registered successfully for ' . $emp['first_name'] . ' ' . $emp['last_name'],
                'face_id' => $faceId,
                'photo_url' => $photoPath ? baseUrl('/' . $photoPath) : null
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;

    case 'delete_face':
        requireRole('Administrator');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST method required']);
            exit;
        }

        $token = $_POST[CSRF_TOKEN_NAME] ?? '';
        if (!validateCSRFToken($token)) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit;
        }

        $faceId = intval($_POST['face_id'] ?? 0);
        $employeeId = intval($_POST['employee_id'] ?? 0);

        try {
            if ($faceId > 0) {
                $stmt = $db->prepare("SELECT photo_path FROM employee_faces WHERE id = ?");
                $stmt->execute([$faceId]);
                $face = $stmt->fetch();
                if ($face && $face['photo_path']) {
                    $file = __DIR__ . '/../' . $face['photo_path'];
                    if (file_exists($file)) @unlink($file);
                }
                $del = $db->prepare("DELETE FROM employee_faces WHERE id = ?");
                $del->execute([$faceId]);
                logAudit('delete_face', 'employees', "Deleted face template ID #$faceId");
            } elseif ($employeeId > 0) {
                $stmt = $db->prepare("SELECT photo_path FROM employee_faces WHERE employee_id = ?");
                $stmt->execute([$employeeId]);
                while ($face = $stmt->fetch()) {
                    if ($face['photo_path']) {
                        $file = __DIR__ . '/../' . $face['photo_path'];
                        if (file_exists($file)) @unlink($file);
                    }
                }
                $del = $db->prepare("DELETE FROM employee_faces WHERE employee_id = ?");
                $del->execute([$employeeId]);
                logAudit('delete_face', 'employees', "Deleted all face templates for employee ID #$employeeId");
            } else {
                echo json_encode(['success' => false, 'message' => 'face_id or employee_id is required']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Face template removed successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}
