<?php
requireRole('Administrator');

$reportType = $_GET['type'] ?? 'attendance';
if (!in_array($reportType, ['attendance', 'leave', 'credits'], true)) {
    $reportType = 'attendance';
}

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$year = (int) ($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$dateFrom = $month . '-01';
$dateTo = date('Y-m-t', strtotime($dateFrom));
$employeeId = intval($_GET['employee_id'] ?? 0);
$format = $_GET['format'] ?? 'html';
$agencyName = getSetting('company_name', 'Cauayan City Water District');

function reportDateCell($value): string {
    return $value ? date('m/d/Y', strtotime($value)) : '';
}

function reportDateTimeCell($value): string {
    return $value ? date('m/d/Y H:i:s', strtotime($value)) : '';
}

function reportDateTimeHtml($value): string {
    return $value ? escapeOutput(date('m/d/Y', strtotime($value))) . '<br>' . escapeOutput(date('H:i:s', strtotime($value))) : '';
}

function reportFormatWorkMinutes(int $minutes): string {
    $minutes = max(0, $minutes);
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function reportFormatDays(float $days): string {
    return number_format($days, 3, '.', '');
}

function reportTimetable(): string {
    $amStart = getSetting('csc_am_start', '08:00');
    $pmEnd = getSetting('csc_pm_end', '17:00');
    return 'Office 8-5(' . $amStart . '-' . $pmEnd . ')';
}

function reportTimeCardRows(array $record): array {
    $hasAnyPunch = !empty($record['time_in'])
        || !empty($record['break_out'])
        || !empty($record['break_in'])
        || !empty($record['time_out']);

    return [[
        'clock_in' => $record['time_in'] ?? null,
        'clock_out' => $record['time_out'] ?? null,
        'round_in' => $record['time_in'] ?? null,
        'round_out' => $record['time_out'] ?? null,
        'work' => $hasAnyPunch ? reportFormatWorkMinutes((int) ($record['csc']['worked_minutes'] ?? 0)) : '00:00',
    ]];
}

function reportEmployees(): array {
    global $db;
    return $db->query("
        SELECT e.id, e.first_name, e.last_name, e.employee_no, d.name AS department_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.is_active = 1
        ORDER BY e.last_name, e.first_name
    ")->fetchAll();
}

function reportFindEmployee(array $employees, int $employeeId): ?array {
    foreach ($employees as $employee) {
        if ((int) $employee['id'] === $employeeId) {
            return $employee;
        }
    }
    return null;
}

function reportAttendanceSummary(array $employees, string $dateFrom, string $dateTo): array {
    $records = cscAttachComputations(generateCompleteAttendanceReport(0, $dateFrom, $dateTo));
    $summary = [];
    foreach ($employees as $employee) {
        $summary[(int) $employee['id']] = [
            'employee' => $employee,
            'present' => 0,
            'absent' => 0,
            'worked' => 0,
            'tardy' => 0,
            'undertime' => 0,
            'deducted' => 0,
        ];
    }

    foreach ($records as $record) {
        $id = (int) $record['employee_id'];
        if (!isset($summary[$id])) continue;

        $isRestDay = !empty($record['csc']['is_rest_day']);
        $isHoliday = !empty($record['csc']['is_holiday']);
        $hasTime = !empty($record['time_in']);

        if ($hasTime) {
            $summary[$id]['present']++;
        } elseif (!$isRestDay && !$isHoliday) {
            $summary[$id]['absent']++;
        }

        $summary[$id]['worked'] += (int) ($record['csc']['worked_minutes'] ?? 0);
        $summary[$id]['tardy'] += (int) ($record['csc']['tardiness_minutes'] ?? 0);
        $summary[$id]['undertime'] += (int) ($record['csc']['undertime_minutes'] ?? 0);
        $summary[$id]['deducted'] += (int) ($record['csc']['deficiency_minutes'] ?? 0);
    }

    return array_values($summary);
}

function reportLeaveRows(?int $employeeId, string $dateFrom, string $dateTo): array {
    global $db;
    $sql = "
        SELECT lr.*, lt.name AS leave_type_name, lt.code AS leave_type_code,
               e.employee_no, e.first_name, e.last_name, d.name AS department_name
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        JOIN employees e ON lr.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE lr.date_from <= ? AND lr.date_to >= ?
    ";
    $params = [$dateTo, $dateFrom];
    if ($employeeId) {
        $sql .= " AND lr.employee_id = ?";
        $params[] = $employeeId;
    }
    $sql .= " ORDER BY e.last_name, e.first_name, lr.date_from";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function reportLeaveSummary(array $employees, string $dateFrom, string $dateTo): array {
    $summary = [];
    foreach ($employees as $employee) {
        $summary[(int) $employee['id']] = [
            'employee' => $employee,
            'requests' => 0,
            'approved' => 0,
            'pending' => 0,
            'approved_days' => 0,
        ];
    }

    foreach (reportLeaveRows(null, $dateFrom, $dateTo) as $leave) {
        $id = (int) $leave['employee_id'];
        if (!isset($summary[$id])) continue;

        $status = strtolower($leave['status'] ?? '');
        $summary[$id]['requests']++;
        if (isset($summary[$id][$status])) {
            $summary[$id][$status]++;
        }
        if ($status === 'approved') {
            $summary[$id]['approved_days'] += (int) $leave['days'];
        }
    }

    return array_values($summary);
}

function reportLeaveCredits(array $employees, int $year): array {
    global $db;

    $yearFrom = $year . '-01-01';
    $yearTo = $year . '-12-31';
    $credits = [];
    foreach ($employees as $employee) {
        $credits[(int) $employee['id']] = [
            'employee' => $employee,
            'vl_earned' => 15.0,
            'sl_earned' => 15.0,
            'vl_used' => 0.0,
            'sl_used' => 0.0,
            'vl_deductions' => 0.0,
            'vl_balance' => 15.0,
            'sl_balance' => 15.0,
        ];
    }

    $stmt = $db->prepare("
        SELECT lr.employee_id, lt.code, SUM(lr.days) AS days_used
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.id
        WHERE lr.status = 'approved'
          AND lr.date_from <= ?
          AND lr.date_to >= ?
          AND lt.code IN ('VL', 'SL')
        GROUP BY lr.employee_id, lt.code
    ");
    $stmt->execute([$yearTo, $yearFrom]);
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['employee_id'];
        if (!isset($credits[$id])) continue;
        if (strtoupper($row['code']) === 'VL') {
            $credits[$id]['vl_used'] = (float) $row['days_used'];
        } elseif (strtoupper($row['code']) === 'SL') {
            $credits[$id]['sl_used'] = (float) $row['days_used'];
        }
    }

    $stmt = $db->prepare("
        SELECT employee_id, SUM(deficiency_minutes) AS deducted_minutes
        FROM attendance
        WHERE date BETWEEN ? AND ?
        GROUP BY employee_id
    ");
    $stmt->execute([$yearFrom, $yearTo]);
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['employee_id'];
        if (!isset($credits[$id])) continue;
        $credits[$id]['vl_deductions'] = round(((int) $row['deducted_minutes']) / 480, 3);
    }

    foreach ($credits as &$row) {
        $row['vl_balance'] = $row['vl_earned'] - $row['vl_used'] - $row['vl_deductions'];
        $row['sl_balance'] = $row['sl_earned'] - $row['sl_used'];
    }
    unset($row);

    return array_values($credits);
}

$employees = reportEmployees();
$selectedEmployee = $employeeId ? reportFindEmployee($employees, $employeeId) : null;

if ($format === 'csv') {
    if ($reportType === 'attendance' && $selectedEmployee) {
        $records = cscAttachComputations(generateCompleteAttendanceReport($employeeId, $dateFrom, $dateTo));
        $employeeName = $selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=time_card_' . $selectedEmployee['employee_no'] . '_' . $month . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, [$agencyName]);
        fputcsv($output, ['Time Card Report']);
        fputcsv($output, [reportDateCell($dateFrom), '--', reportDateCell($dateTo)]);
        fputcsv($output, []);
        fputcsv($output, ['Department', $selectedEmployee['department_name'] ?? 'N/A']);
        fputcsv($output, ['Full Name', $employeeName, 'Card Number', '0']);
        fputcsv($output, ['Employee ID', 'Date', 'Timetable', 'Clock-In', 'Clock-Out', 'Round-In', 'Round-Out', 'Round Work']);
        foreach ($records as $r) {
            foreach (reportTimeCardRows($r) as $timeRow) {
                fputcsv($output, [
                    $selectedEmployee['id'],
                    reportDateCell($r['date']),
                    ($timeRow['clock_in'] || $timeRow['clock_out']) ? reportTimetable() : '',
                    reportDateTimeCell($timeRow['clock_in']),
                    reportDateTimeCell($timeRow['clock_out']),
                    reportDateTimeCell($timeRow['round_in']),
                    reportDateTimeCell($timeRow['round_out']),
                    $timeRow['work'],
                ]);
            }
        }
        fclose($output);
        exit;
    }

    if ($reportType === 'attendance') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=attendance_master_' . $month . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, [$agencyName]);
        fputcsv($output, ['Master Attendance Report', reportDateCell($dateFrom) . ' - ' . reportDateCell($dateTo)]);
        fputcsv($output, ['Employee No', 'Name', 'Department', 'Present', 'Absent', 'Worked', 'Tardy', 'Undertime', 'Deducted']);
        foreach (reportAttendanceSummary($employees, $dateFrom, $dateTo) as $row) {
            $employee = $row['employee'];
            fputcsv($output, [
                $employee['employee_no'],
                $employee['first_name'] . ' ' . $employee['last_name'],
                $employee['department_name'] ?? 'N/A',
                $row['present'],
                $row['absent'],
                reportFormatWorkMinutes($row['worked']),
                reportFormatWorkMinutes($row['tardy']),
                reportFormatWorkMinutes($row['undertime']),
                reportFormatWorkMinutes($row['deducted']),
            ]);
        }
        fclose($output);
        exit;
    }

    if ($reportType === 'leave') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leave_report_' . $month . ($selectedEmployee ? '_' . $selectedEmployee['employee_no'] : '') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, [$agencyName]);
        fputcsv($output, ['Monthly Leave Report', reportDateCell($dateFrom) . ' - ' . reportDateCell($dateTo)]);
        fputcsv($output, ['Employee No', 'Name', 'Department', 'Leave Type', 'From', 'To', 'Days', 'Status', 'Reason']);
        foreach (reportLeaveRows($selectedEmployee ? $employeeId : null, $dateFrom, $dateTo) as $leave) {
            fputcsv($output, [
                $leave['employee_no'],
                $leave['first_name'] . ' ' . $leave['last_name'],
                $leave['department_name'] ?? 'N/A',
                $leave['leave_type_name'],
                reportDateCell($leave['date_from']),
                reportDateCell($leave['date_to']),
                $leave['days'],
                $leave['status'],
                $leave['reason'],
            ]);
        }
        fclose($output);
        exit;
    }

    if ($reportType === 'credits') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leave_credits_' . $year . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, [$agencyName]);
        fputcsv($output, ['Civil Service Leave Credits', $year]);
        fputcsv($output, ['Employee No', 'Name', 'Department', 'VL Earned', 'VL Used', 'VL Tardy/Undertime Deductions', 'VL Balance', 'SL Earned', 'SL Used', 'SL Balance']);
        foreach (reportLeaveCredits($employees, $year) as $row) {
            $employee = $row['employee'];
            fputcsv($output, [
                $employee['employee_no'],
                $employee['first_name'] . ' ' . $employee['last_name'],
                $employee['department_name'] ?? 'N/A',
                reportFormatDays($row['vl_earned']),
                reportFormatDays($row['vl_used']),
                reportFormatDays($row['vl_deductions']),
                reportFormatDays($row['vl_balance']),
                reportFormatDays($row['sl_earned']),
                reportFormatDays($row['sl_used']),
                reportFormatDays($row['sl_balance']),
            ]);
        }
        fclose($output);
        exit;
    }
}

if ($format === 'print' && $reportType === 'attendance' && $selectedEmployee) {
    $records = cscAttachComputations(generateCompleteAttendanceReport($employeeId, $dateFrom, $dateTo));
    $employeeName = $selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name'];
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Time Card Report</title>
        <style>
            body { font-family: "Times New Roman", Arial, sans-serif; color: #000; margin: 24px; }
            .report { width: 760px; max-width: 100%; }
            .title { text-align: center; margin-bottom: 10px; }
            .title h1 { font-family: Arial, sans-serif; font-size: 28px; line-height: 1.15; margin: 0; font-weight: 700; white-space: pre-line; }
            .title h2 { font-size: 18px; margin: 8px 0 10px; }
            .title p { font-family: Arial, sans-serif; font-size: 14px; margin: 0; font-weight: 700; }
            .rule { border-top: 2px solid #000; margin: 10px 0 6px; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th, td { border: 1px solid #000; padding: 3px 4px; text-align: center; vertical-align: middle; }
            th { font-weight: 700; }
            td.label { text-align: left; font-weight: 700; width: 110px; }
            .timetable { line-height: 1.2; }
            .no-print { margin-bottom: 14px; text-align: right; }
            @media print { .no-print { display: none; } body { margin: 10mm; } }
        </style>
    </head>
    <body>
        <div class="no-print"><button onclick="window.print()">Print</button></div>
        <div class="report">
            <div class="title">
                <h1><?= escapeOutput(str_replace(' Water District', " Water\nDistrict", $agencyName)) ?></h1>
                <h2>Time Card Report</h2>
                <p><?= escapeOutput(reportDateCell($dateFrom)) ?> &nbsp; -- &nbsp; <?= escapeOutput(reportDateCell($dateTo)) ?></p>
            </div>
            <div class="rule"></div>
            <table>
                <tbody>
                    <tr>
                        <td class="label">Department</td>
                        <td colspan="7"><?= escapeOutput($selectedEmployee['department_name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Full Name</td>
                        <td colspan="2"><?= escapeOutput($employeeName) ?></td>
                        <td colspan="2" class="label">Card Number</td>
                        <td colspan="3">0</td>
                    </tr>
                    <tr>
                        <th>Employee ID</th>
                        <th>Date</th>
                        <th>Timetable</th>
                        <th>Clock-In</th>
                        <th>Clock-Out</th>
                        <th>Round-In</th>
                        <th>Round-Out</th>
                        <th>Round Work</th>
                    </tr>
                <?php foreach ($records as $r): foreach (reportTimeCardRows($r) as $timeRow): ?>
                <tr>
                    <td><?= escapeOutput($selectedEmployee['id']) ?></td>
                    <td><?= escapeOutput(reportDateCell($r['date'])) ?></td>
                    <td class="timetable"><?= ($timeRow['clock_in'] || $timeRow['clock_out']) ? escapeOutput(reportTimetable()) : '' ?></td>
                    <td><?= reportDateTimeHtml($timeRow['clock_in']) ?></td>
                    <td><?= reportDateTimeHtml($timeRow['clock_out']) ?></td>
                    <td><?= reportDateTimeHtml($timeRow['round_in']) ?></td>
                    <td><?= reportDateTimeHtml($timeRow['round_out']) ?></td>
                    <td><?= escapeOutput($timeRow['work']) ?></td>
                </tr>
                <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$records = $selectedEmployee ? cscAttachComputations(generateCompleteAttendanceReport($employeeId, $dateFrom, $dateTo)) : [];
$attendanceSummary = $reportType === 'attendance' && !$selectedEmployee ? reportAttendanceSummary($employees, $dateFrom, $dateTo) : [];
$leaveRows = $reportType === 'leave' && $selectedEmployee ? reportLeaveRows($employeeId, $dateFrom, $dateTo) : [];
$leaveSummary = $reportType === 'leave' && !$selectedEmployee ? reportLeaveSummary($employees, $dateFrom, $dateTo) : [];
$creditRows = $reportType === 'credits' ? reportLeaveCredits($employees, $year) : [];

$totalPresent = $records ? count(array_filter($records, fn($r) => $r['time_in'] !== null)) : 0;
$totalLate = $records ? array_sum(array_column(array_column($records, 'csc'), 'tardiness_minutes')) : 0;
$totalUndertime = $records ? array_sum(array_column(array_column($records, 'csc'), 'undertime_minutes')) : 0;
$totalDeducted = $records ? array_sum(array_column(array_column($records, 'csc'), 'deficiency_minutes')) : 0;

$tabs = [
    'attendance' => ['label' => 'Attendance', 'icon' => 'fa-clipboard-check'],
    'leave' => ['label' => 'Leave', 'icon' => 'fa-calendar-minus'],
    'credits' => ['label' => 'Leave Credits', 'icon' => 'fa-scale-balanced'],
];
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Reports</h1>
            <p class="text-sm text-gray-500">Monthly individual reports, master lists, leave reports, and CSC leave credits.</p>
        </div>
        <?php if ($selectedEmployee): ?>
        <a href="<?= APP_URL ?>/index.php?page=reports&type=<?= urlencode($reportType) ?>&month=<?= urlencode($month) ?>&year=<?= $year ?>" class="text-sm text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i>Back to Employees</a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-2 flex flex-wrap gap-2">
        <?php foreach ($tabs as $key => $tab): ?>
        <a href="<?= APP_URL ?>/index.php?page=reports&type=<?= $key ?>&month=<?= urlencode($month) ?>&year=<?= $year ?>" class="px-4 py-2 rounded-lg text-sm <?= $reportType === $key ? 'bg-blue-700 text-white' : 'text-gray-600 hover:bg-gray-50' ?>">
            <i class="fas <?= $tab['icon'] ?> mr-2"></i><?= $tab['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="type" value="<?= escapeOutput($reportType) ?>">
            <?php if ($selectedEmployee): ?>
            <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
            <?php endif; ?>
            <?php if ($reportType === 'credits'): ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Year</label>
                <input type="number" min="2000" max="2100" name="year" value="<?= $year ?>" class="px-3 py-2 border rounded-lg text-sm w-28">
            </div>
            <?php else: ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Month</label>
                <input type="month" name="month" value="<?= escapeOutput($month) ?>" class="px-3 py-2 border rounded-lg text-sm">
            </div>
            <?php endif; ?>
            <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800"><i class="fas fa-search mr-2"></i>View</button>

            <?php if ($reportType === 'attendance' && $selectedEmployee): ?>
            <a href="?page=reports&type=attendance&month=<?= urlencode($month) ?>&employee_id=<?= $employeeId ?>&format=csv" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-file-csv mr-2"></i>CSV</a>
            <a href="?page=reports&type=attendance&month=<?= urlencode($month) ?>&employee_id=<?= $employeeId ?>&format=print" target="_blank" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-print mr-2"></i>Printable DTR</a>
            <?php elseif ($reportType === 'attendance'): ?>
            <a href="?page=reports&type=attendance&month=<?= urlencode($month) ?>&format=csv" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-file-csv mr-2"></i>Master CSV</a>
            <?php elseif ($reportType === 'leave'): ?>
            <a href="?page=reports&type=leave&month=<?= urlencode($month) ?><?= $selectedEmployee ? '&employee_id=' . $employeeId : '' ?>&format=csv" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-file-csv mr-2"></i>CSV</a>
            <?php elseif ($reportType === 'credits'): ?>
            <a href="?page=reports&type=credits&year=<?= $year ?>&format=csv" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-file-csv mr-2"></i>CSV</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($reportType === 'attendance' && !$selectedEmployee): ?>
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b">
            <h3 class="font-semibold text-gray-800">Master Attendance Report</h3>
            <p class="text-sm text-gray-500"><?= escapeOutput(reportDateCell($dateFrom)) ?> -- <?= escapeOutput(reportDateCell($dateTo)) ?></p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Employee No</th>
                        <th class="text-left px-4 py-3 font-medium">Name</th>
                        <th class="text-left px-4 py-3 font-medium">Department</th>
                        <th class="text-center px-4 py-3 font-medium">Present</th>
                        <th class="text-center px-4 py-3 font-medium">Absent</th>
                        <th class="text-left px-4 py-3 font-medium">Tardy</th>
                        <th class="text-left px-4 py-3 font-medium">Undertime</th>
                        <th class="text-left px-4 py-3 font-medium">Deducted</th>
                        <th class="text-right px-4 py-3 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($attendanceSummary as $row): $employee = $row['employee']; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($employee['employee_no']) ?></td>
                        <td class="px-4 py-3 font-medium"><?= escapeOutput($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput($employee['department_name'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 text-center"><?= $row['present'] ?></td>
                        <td class="px-4 py-3 text-center"><?= $row['absent'] ?></td>
                        <td class="px-4 py-3"><?= escapeOutput(reportFormatWorkMinutes($row['tardy'])) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput(reportFormatWorkMinutes($row['undertime'])) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput(reportFormatWorkMinutes($row['deducted'])) ?></td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= APP_URL ?>/index.php?page=reports&type=attendance&month=<?= urlencode($month) ?>&employee_id=<?= $employee['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-700 text-white rounded-lg text-xs hover:bg-blue-800"><i class="fas fa-eye mr-1"></i>View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($reportType === 'attendance' && $selectedEmployee): ?>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-4"><p class="text-sm text-gray-500">Present Days</p><p class="text-2xl font-bold text-green-600"><?= $totalPresent ?></p></div>
        <div class="bg-white rounded-xl shadow-sm border p-4"><p class="text-sm text-gray-500">Tardiness</p><p class="text-2xl font-bold text-orange-600"><?= cscFormatMinutes($totalLate) ?></p></div>
        <div class="bg-white rounded-xl shadow-sm border p-4"><p class="text-sm text-gray-500">Undertime</p><p class="text-2xl font-bold text-red-600"><?= cscFormatMinutes($totalUndertime) ?></p></div>
        <div class="bg-white rounded-xl shadow-sm border p-4"><p class="text-sm text-gray-500">Deducted</p><p class="text-2xl font-bold text-red-600"><?= cscFormatMinutes($totalDeducted) ?></p></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <div>
                <h3 class="font-semibold text-gray-800">Time Card Report</h3>
                <p class="text-sm text-gray-500"><?= escapeOutput($agencyName) ?> · <?= escapeOutput(reportDateCell($dateFrom)) ?> -- <?= escapeOutput(reportDateCell($dateTo)) ?></p>
            </div>
            <span class="text-sm text-gray-500"><?= escapeOutput($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?></span>
        </div>
        <div class="px-5 py-3 border-b grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <div><span class="font-semibold">Department:</span> <?= escapeOutput($selectedEmployee['department_name'] ?? 'N/A') ?></div>
            <div><span class="font-semibold">Full Name:</span> <?= escapeOutput($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?></div>
            <div><span class="font-semibold">Employee No:</span> <?= escapeOutput($selectedEmployee['employee_no']) ?></div>
            <div><span class="font-semibold">Card Number:</span> 0</div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Employee ID</th>
                        <th class="text-left px-4 py-3 font-medium">Date</th>
                        <th class="text-left px-4 py-3 font-medium">Timetable</th>
                        <th class="text-left px-4 py-3 font-medium">Clock-In</th>
                        <th class="text-left px-4 py-3 font-medium">Clock-Out</th>
                        <th class="text-left px-4 py-3 font-medium">Round-In</th>
                        <th class="text-left px-4 py-3 font-medium">Round-Out</th>
                        <th class="text-left px-4 py-3 font-medium">Round Work</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($records as $r): foreach (reportTimeCardRows($r) as $timeRow): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($selectedEmployee['id']) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput(reportDateCell($r['date'])) ?></td>
                        <td class="px-4 py-3"><?= ($timeRow['clock_in'] || $timeRow['clock_out']) ? escapeOutput(reportTimetable()) : '--' ?></td>
                        <td class="px-4 py-3"><?= $timeRow['clock_in'] ? escapeOutput(reportDateTimeCell($timeRow['clock_in'])) : '--' ?></td>
                        <td class="px-4 py-3"><?= $timeRow['clock_out'] ? escapeOutput(reportDateTimeCell($timeRow['clock_out'])) : '--' ?></td>
                        <td class="px-4 py-3"><?= $timeRow['round_in'] ? escapeOutput(reportDateTimeCell($timeRow['round_in'])) : '--' ?></td>
                        <td class="px-4 py-3"><?= $timeRow['round_out'] ? escapeOutput(reportDateTimeCell($timeRow['round_out'])) : '--' ?></td>
                        <td class="px-4 py-3 font-medium"><?= escapeOutput($timeRow['work']) ?></td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($reportType === 'leave' && !$selectedEmployee): ?>
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-800">Monthly Leave Report</h3><p class="text-sm text-gray-500"><?= escapeOutput(reportDateCell($dateFrom)) ?> -- <?= escapeOutput(reportDateCell($dateTo)) ?></p></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600"><tr><th class="text-left px-4 py-3 font-medium">Employee No</th><th class="text-left px-4 py-3 font-medium">Name</th><th class="text-left px-4 py-3 font-medium">Department</th><th class="text-center px-4 py-3 font-medium">Requests</th><th class="text-center px-4 py-3 font-medium">Approved</th><th class="text-center px-4 py-3 font-medium">Pending</th><th class="text-center px-4 py-3 font-medium">Approved Days</th><th class="text-right px-4 py-3 font-medium">Action</th></tr></thead>
                <tbody class="divide-y">
                    <?php foreach ($leaveSummary as $row): $employee = $row['employee']; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($employee['employee_no']) ?></td>
                        <td class="px-4 py-3 font-medium"><?= escapeOutput($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput($employee['department_name'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 text-center"><?= $row['requests'] ?></td>
                        <td class="px-4 py-3 text-center"><?= $row['approved'] ?></td>
                        <td class="px-4 py-3 text-center"><?= $row['pending'] ?></td>
                        <td class="px-4 py-3 text-center"><?= $row['approved_days'] ?></td>
                        <td class="px-4 py-3 text-right"><a href="<?= APP_URL ?>/index.php?page=reports&type=leave&month=<?= urlencode($month) ?>&employee_id=<?= $employee['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-700 text-white rounded-lg text-xs hover:bg-blue-800"><i class="fas fa-eye mr-1"></i>View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($reportType === 'leave' && $selectedEmployee): ?>
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-800">Individual Leave Report</h3><p class="text-sm text-gray-500"><?= escapeOutput($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?> · <?= escapeOutput(reportDateCell($dateFrom)) ?> -- <?= escapeOutput(reportDateCell($dateTo)) ?></p></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600"><tr><th class="text-left px-4 py-3 font-medium">Leave Type</th><th class="text-left px-4 py-3 font-medium">From</th><th class="text-left px-4 py-3 font-medium">To</th><th class="text-center px-4 py-3 font-medium">Days</th><th class="text-left px-4 py-3 font-medium">Status</th><th class="text-left px-4 py-3 font-medium">Reason</th></tr></thead>
                <tbody class="divide-y">
                    <?php if (empty($leaveRows)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No leave records for this month</td></tr>
                    <?php else: foreach ($leaveRows as $leave): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($leave['leave_type_name']) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput(reportDateCell($leave['date_from'])) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput(reportDateCell($leave['date_to'])) ?></td>
                        <td class="px-4 py-3 text-center"><?= (int) $leave['days'] ?></td>
                        <td class="px-4 py-3"><?= getStatusBadge($leave['status']) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput($leave['reason']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($reportType === 'credits'): ?>
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b">
            <h3 class="font-semibold text-gray-800">Civil Service Leave Credits</h3>
            <p class="text-sm text-gray-500">VL and SL earn 15 days each per full year, computed as 1.25 days per month or one day per 24 days of actual service. Tardiness and undertime deductions are charged against vacation leave.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600"><tr><th class="text-left px-4 py-3 font-medium">Employee No</th><th class="text-left px-4 py-3 font-medium">Name</th><th class="text-left px-4 py-3 font-medium">Department</th><th class="text-right px-4 py-3 font-medium">VL Earned</th><th class="text-right px-4 py-3 font-medium">VL Used</th><th class="text-right px-4 py-3 font-medium">VL Deductions</th><th class="text-right px-4 py-3 font-medium">VL Balance</th><th class="text-right px-4 py-3 font-medium">SL Earned</th><th class="text-right px-4 py-3 font-medium">SL Used</th><th class="text-right px-4 py-3 font-medium">SL Balance</th></tr></thead>
                <tbody class="divide-y">
                    <?php foreach ($creditRows as $row): $employee = $row['employee']; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($employee['employee_no']) ?></td>
                        <td class="px-4 py-3 font-medium"><?= escapeOutput($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput($employee['department_name'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 text-right"><?= escapeOutput(reportFormatDays($row['vl_earned'])) ?></td>
                        <td class="px-4 py-3 text-right"><?= escapeOutput(reportFormatDays($row['vl_used'])) ?></td>
                        <td class="px-4 py-3 text-right text-red-600"><?= escapeOutput(reportFormatDays($row['vl_deductions'])) ?></td>
                        <td class="px-4 py-3 text-right font-semibold"><?= escapeOutput(reportFormatDays($row['vl_balance'])) ?></td>
                        <td class="px-4 py-3 text-right"><?= escapeOutput(reportFormatDays($row['sl_earned'])) ?></td>
                        <td class="px-4 py-3 text-right"><?= escapeOutput(reportFormatDays($row['sl_used'])) ?></td>
                        <td class="px-4 py-3 text-right font-semibold"><?= escapeOutput(reportFormatDays($row['sl_balance'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 border-t text-xs text-gray-500">Source basis checked against CSC MC No. 41 s. 1998 / CSC MC No. 14 s. 1999 leave-credit computation rules.</div>
    </div>
    <?php endif; ?>
</div>
