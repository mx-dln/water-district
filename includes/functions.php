<?php
function baseUrl($path = '') {
    return rtrim(APP_URL, '/') . ($path ? '/' . ltrim($path, '/') : '');
}

function redirect($url) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    $base = rtrim(APP_URL, '/');
    if (strpos($url, 'http') === 0 && strpos($url, $base) !== 0) {
        $url = $base . '/index.php?page=login';
    }
    $url = preg_replace('#(https?://[^/]+)/+#', '$1/', $url);
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_message'] = 'Please login to continue';
        $_SESSION['flash_type'] = 'warning';
        redirect(APP_URL . '/index.php?page=login');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        $_SESSION['flash_message'] = 'Access denied. Insufficient permissions.';
        $_SESSION['flash_type'] = 'danger';
        redirect(APP_URL . '/index.php');
    }
}

function hasRole($role) {
    if (!isset($_SESSION['role'])) return false;
    if (is_array($role)) {
        return in_array($_SESSION['role'], $role);
    }
    return $_SESSION['role'] === $role;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Administrator';
}

function isEmployee() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Employee';
}

function formatDate($date, $format = 'F j, Y') {
    if (empty($date)) return 'N/A';
    $dt = new DateTime($date);
    return $dt->format($format);
}

function formatTime($date, $format = 'h:i A') {
    if (empty($date)) return 'N/A';
    $dt = new DateTime($date);
    return $dt->format($format);
}

function formatDateTime($date, $format = 'F j, Y h:i A') {
    if (empty($date)) return 'N/A';
    $dt = new DateTime($date);
    return $dt->format($format);
}

function timeAgo($datetime) {
    if (empty($datetime)) return 'N/A';
    $tz = new DateTimeZone(TIMEZONE);
    $now = new DateTime('now', $tz);
    $ago = new DateTime($datetime, $tz);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' year(s) ago';
    if ($diff->m > 0) return $diff->m . ' month(s) ago';
    if ($diff->d > 0) return $diff->d . ' day(s) ago';
    if ($diff->h > 0) return $diff->h . ' hour(s) ago';
    if ($diff->i > 0) return $diff->i . ' minute(s) ago';
    return 'just now';
}

function getStatusBadge($status) {
    $map = [
        'verified'  => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Verified</span>',
        'inside'    => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Inside</span>',
        'outside'   => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Outside</span>',
        'pending'   => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>',
        'approved'  => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Approved</span>',
        'rejected'  => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Rejected</span>',
        'cancelled' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Cancelled</span>',
        'active'    => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>',
        'inactive'  => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>',
    ];
    return $map[strtolower($status)] ?? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">' . escapeOutput($status) . '</span>';
}

function paginate($page, $totalPages) {
    if ($totalPages <= 1) return '';
    $html = '<div class="flex items-center justify-between mt-6">';
    $html .= '<p class="text-sm text-gray-600">Page ' . $page . ' of ' . $totalPages . '</p>';
    $html .= '<div class="flex space-x-2">';
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    $query = $_GET;
    if ($page > 1) {
        $query['p'] = $page - 1;
        $html .= '<a href="?' . http_build_query($query) . '" class="px-3 py-1 text-sm border rounded hover:bg-gray-100">&laquo; Prev</a>';
    }
    for ($i = 1; $i <= $totalPages; $i++) {
        $query['p'] = $i;
        $active = $i === $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100';
        $html .= '<a href="?' . http_build_query($query) . '" class="px-3 py-1 text-sm border rounded ' . $active . '">' . $i . '</a>';
    }
    if ($page < $totalPages) {
        $query['p'] = $page + 1;
        $html .= '<a href="?' . http_build_query($query) . '" class="px-3 py-1 text-sm border rounded hover:bg-gray-100">Next &raquo;</a>';
    }
    $html .= '</div></div>';
    return $html;
}

function setFlash($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function getFlash() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $msg, 'type' => $type];
    }
    return null;
}

function getEmployeeIdFromUser($userId) {
    global $db;
    $stmt = $db->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? $row['id'] : null;
}

function getEmployeeData($employeeId) {
    global $db;
    $stmt = $db->prepare("
        SELECT e.*, d.name as department_name, d.code as department_code
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.id = ?
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetch();
}

function logAudit($action, $module, $description = null) {
    global $db;
    $userId = $_SESSION['user_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, module, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $module, $description, $ip, $ua]);
}

function createNotification($userId, $title, $message, $type = 'info', $link = null) {
    global $db;
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $type, $link]);
}

function getUnreadNotifications($userId) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getSetting($key, $default = null) {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function updateSetting($key, $value) {
    global $db;
    $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    return $stmt->execute([$key, $value]);
}

function getActivePolygon() {
    global $db;
    $stmt = $db->prepare("SELECT * FROM office_polygons WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

function pointInPolygon($lat, $lng, $polygon) {
    if (!is_array($polygon) || count($polygon) < 3) return false;
    $buf = 0.000045;
    $offsets = [
        [$lat, $lng],
        [$lat + $buf, $lng],
        [$lat - $buf, $lng],
        [$lat, $lng + $buf],
        [$lat, $lng - $buf],
        [$lat + $buf, $lng + $buf],
        [$lat - $buf, $lng - $buf],
    ];
    foreach ($offsets as $pt) {
        $clat = $pt[0];
        $clng = $pt[1];
        $inside = false;
        $j = count($polygon) - 1;
        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lng'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lng'];
            if ((($yi > $clng) !== ($yj > $clng)) && ($clat < ($xj - $xi) * ($clng - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
            $j = $i;
        }
        if ($inside) return true;
    }
    return false;
}

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function watermarkImage($sourcePath, $employeeName, $employeeNo, $date, $time, $lat, $lng, $attendanceType, $status) {
    $info = getimagesize($sourcePath);
    if ($info === false) return false;
    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $image = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $image = imagecreatefrompng($sourcePath); break;
        case 'image/webp': $image = imagecreatefromwebp($sourcePath); break;
        default: return false;
    }

    $imgW = imagesx($image);
    $imgH = imagesy($image);

    $textLines = [
        $employeeName,
        $employeeNo,
        $date . ' ' . $time,
        'Lat: ' . $lat . '  Lng: ' . $lng,
        $attendanceType . ' | ' . $status,
    ];

    $fontFile = null;
    $ttfPaths = [
        __DIR__ . '/../assets/fonts/DejaVuSans.ttf',
        __DIR__ . '/../assets/fonts/OpenSans-Regular.ttf',
        __DIR__ . '/../assets/fonts/Roboto-Regular.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
    ];
    foreach ($ttfPaths as $p) {
        if (file_exists($p)) { $fontFile = $p; break; }
    }

    if ($fontFile && function_exists('imagettftext')) {
        $fontSize = 22;
        $lineH = $fontSize + 10;
        $padding = 14;
        $bannerH = count($textLines) * $lineH + ($padding * 2);

        $banner = imagecreatetruecolor($imgW, $bannerH);
        $bgColor = imagecolorallocatealpha($banner, 0, 0, 0, 55);
        imagefill($banner, 0, 0, $bgColor);
        imagesavealpha($banner, true);

        $textColor = imagecolorallocate($banner, 255, 255, 255);
        $y = $padding + $fontSize;
        foreach ($textLines as $line) {
            imagettftext($banner, $fontSize, 0, $padding, $y, $textColor, $fontFile, $line);
            $y += $lineH;
        }
        imagecopy($image, $banner, 0, $imgH - $bannerH, 0, 0, $imgW, $bannerH);
        imagedestroy($banner);
    } else {
        $fontSize = 5;
        $charH = imagefontheight($fontSize);
        $lineH = $charH + 8;
        $padding = 12;
        $bannerH = count($textLines) * $lineH + ($padding * 2);

        $banner = imagecreatetruecolor($imgW, $bannerH);
        $bgColor = imagecolorallocatealpha($banner, 0, 0, 0, 60);
        imagefill($banner, 0, 0, $bgColor);
        imagesavealpha($banner, true);

        $textColor = imagecolorallocate($banner, 255, 255, 255);
        $shadowColor = imagecolorallocatealpha($banner, 0, 0, 0, 40);
        $y = $padding + $charH;
        foreach ($textLines as $line) {
            imagestring($image, $fontSize, $padding + 1, $imgH - $bannerH + $y - $charH + 1, $line, $shadowColor);
            imagestring($image, $fontSize, $padding, $imgH - $bannerH + $y - $charH, $line, $textColor);
            $y += $lineH;
        }
        imagecopy($image, $banner, 0, $imgH - $bannerH, 0, 0, $imgW, $bannerH);
        imagedestroy($banner);
    }

    switch ($mime) {
        case 'image/jpeg': imagejpeg($image, $sourcePath, 90); break;
        case 'image/png':  imagepng($image, $sourcePath, 9); break;
        case 'image/webp': imagewebp($image, $sourcePath, 90); break;
    }
    imagedestroy($image);
    return true;
}

function generateAttendanceReport($employeeId, $dateFrom, $dateTo) {
    global $db;
    $sql = "SELECT a.*, e.employee_no, e.first_name, e.last_name, d.name as department_name
            FROM attendance a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE a.date BETWEEN ? AND ?";
    $params = [$dateFrom, $dateTo];
    if ($employeeId) {
        $sql .= " AND a.employee_id = ?";
        $params[] = $employeeId;
    }
    $sql .= " ORDER BY a.date DESC, a.time_in DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function generateCompleteAttendanceReport($employeeId, $dateFrom, $dateTo) {
    global $db;

    $employeeSql = "SELECT e.id, e.employee_no, e.first_name, e.last_name, d.name as department_name
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    WHERE e.is_active = 1";
    $employeeParams = [];
    if ($employeeId) {
        $employeeSql .= " AND e.id = ?";
        $employeeParams[] = $employeeId;
    }
    $employeeSql .= " ORDER BY e.last_name, e.first_name";
    $stmt = $db->prepare($employeeSql);
    $stmt->execute($employeeParams);
    $employees = $stmt->fetchAll();

    $attendanceSql = "SELECT * FROM attendance WHERE date BETWEEN ? AND ?";
    $attendanceParams = [$dateFrom, $dateTo];
    if ($employeeId) {
        $attendanceSql .= " AND employee_id = ?";
        $attendanceParams[] = $employeeId;
    }
    $stmt = $db->prepare($attendanceSql);
    $stmt->execute($attendanceParams);
    $attendanceRows = [];
    foreach ($stmt->fetchAll() as $row) {
        $attendanceRows[$row['employee_id'] . '|' . $row['date']] = $row;
    }

    $records = [];
    $start = new DateTime($dateFrom);
    $end = (new DateTime($dateTo))->modify('+1 day');
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);

    foreach ($employees as $employee) {
        foreach ($period as $day) {
            $date = $day->format('Y-m-d');
            $key = $employee['id'] . '|' . $date;
            $attendance = $attendanceRows[$key] ?? [];
            $records[] = array_merge([
                'id' => $attendance['id'] ?? null,
                'employee_id' => $employee['id'],
                'date' => $date,
                'time_in' => null,
                'time_out' => null,
                'break_in' => null,
                'break_out' => null,
                'remarks' => null,
                'employee_no' => $employee['employee_no'],
                'first_name' => $employee['first_name'],
                'last_name' => $employee['last_name'],
                'department_name' => $employee['department_name'],
            ], $attendance, [
                'employee_no' => $employee['employee_no'],
                'first_name' => $employee['first_name'],
                'last_name' => $employee['last_name'],
                'department_name' => $employee['department_name'],
            ]);
        }
    }

    return $records;
}

function compressImage($sourcePath, $maxWidth = 1920, $maxHeight = 1920, $quality = 75) {
    $info = getimagesize($sourcePath);
    if ($info === false) return false;
    $mime = $info['mime'];
    list($w, $h) = $info;

    if ($w <= $maxWidth && $h <= $maxHeight) return true;

    $ratio = min($maxWidth / $w, $maxHeight / $h);
    $newW = round($w * $ratio);
    $newH = round($h * $ratio);

    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $src = imagecreatefrompng($sourcePath); break;
        case 'image/webp': $src = imagecreatefromwebp($sourcePath); break;
        default: return false;
    }

    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($src);

    switch ($mime) {
        case 'image/jpeg': imagejpeg($dst, $sourcePath, $quality); break;
        case 'image/png':  imagepng($dst, $sourcePath, round(9 - ($quality / 10))); break;
        case 'image/webp': imagewebp($dst, $sourcePath, $quality); break;
    }
    imagedestroy($dst);
    return true;
}

function getDateRange($period) {
    $now = new DateTime();
    switch ($period) {
        case 'today':
            return ['start' => $now->format('Y-m-d'), 'end' => $now->format('Y-m-d')];
        case 'week':
            $start = (clone $now)->modify('monday this week');
            $end = (clone $now)->modify('sunday this week');
            return ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
        case 'month':
            return ['start' => $now->format('Y-m-01'), 'end' => $now->format('Y-m-t')];
        case 'year':
            return ['start' => $now->format('Y-01-01'), 'end' => $now->format('Y-12-31')];
        default:
            return ['start' => $now->format('Y-m-d'), 'end' => $now->format('Y-m-d')];
    }
}
