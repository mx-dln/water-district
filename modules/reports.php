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
    ")->fetchAll(PDO::FETCH_ASSOC);
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
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
        <title>Time Card Report - <?= escapeOutput($employeeName) ?></title>
        <style>
            body { font-family: "Times New Roman", Arial, sans-serif; color: #000; margin: 24px; }
            .report { width: 760px; max-width: 100%; margin: 0 auto; }
            .title { text-align: center; margin-bottom: 10px; }
            .title h1 { font-family: Arial, sans-serif; font-size: 26px; line-height: 1.15; margin: 0; font-weight: 700; }
            .title h2 { font-size: 17px; margin: 6px 0 8px; }
            .title p { font-family: Arial, sans-serif; font-size: 13px; margin: 0; font-weight: 700; }
            .rule { border-top: 2px solid #000; margin: 8px 0 6px; }
            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th, td { border: 1px solid #000; padding: 3px 4px; text-align: center; vertical-align: middle; }
            th { font-weight: 700; background: #f0f0f0; }
            td.label { text-align: left; font-weight: 700; width: 110px; background: #fafafa; }
            .timetable { line-height: 1.2; }
            .no-print { margin-bottom: 14px; text-align: right; }
            .btn { background: #1d4ed8; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; }
            @media print { .no-print { display: none; } body { margin: 10mm; } }
        </style>
    </head>
    <body>
        <div class="no-print"><button class="btn" onclick="window.print()">Print Official Time Card</button></div>
        <div class="report">
            <div class="title">
                <h1><?= escapeOutput($agencyName) ?></h1>
                <h2>Official Time Card Report (CSC DTR)</h2>
                <p><?= escapeOutput(reportDateCell($dateFrom)) ?> &nbsp; &mdash; &nbsp; <?= escapeOutput(reportDateCell($dateTo)) ?></p>
            </div>
            <div class="rule"></div>
            <table>
                <tbody>
                    <tr>
                        <td class="label">Department</td>
                        <td colspan="7" style="text-align:left;padding-left:8px;"><?= escapeOutput($selectedEmployee['department_name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <td class="label">Full Name</td>
                        <td colspan="2" style="text-align:left;padding-left:8px;"><?= escapeOutput($employeeName) ?></td>
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
    'attendance' => ['label' => 'Attendance DTR', 'icon' => 'fa-clipboard-check'],
    'leave'      => ['label' => 'Leave Records',  'icon' => 'fa-calendar-minus'],
    'credits'    => ['label' => 'CSC Leave Credits', 'icon' => 'fa-scale-balanced'],
];
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Reports & DTR</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Executive Reports & CSC DTR</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Official Civil Service Commission Daily Time Records, Master Turnout, and Leave Credits
            </p>
        </div>
        <?php if ($selectedEmployee): ?>
        <div>
            <a href="<?= baseUrl() ?>/index.php?page=reports&type=<?= urlencode($reportType) ?>&month=<?= urlencode($month) ?>&year=<?= $year ?>"
               class="inline-flex items-center space-x-2 px-3.5 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-arrow-left text-slate-400"></i>
                <span>Back to Master List</span>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab Strip -->
    <div class="app-card p-1.5 flex flex-wrap gap-1.5 bg-slate-100/80">
        <?php foreach ($tabs as $key => $tab):
            $isActive = ($reportType === $key);
        ?>
        <a href="<?= baseUrl() ?>/index.php?page=reports&type=<?= $key ?>&month=<?= urlencode($month) ?>&year=<?= $year ?>"
           class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all
           <?= $isActive ? 'bg-white text-navy-950 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' ?>">
            <i class="fas <?= $tab['icon'] ?> text-xs <?= $isActive ? 'text-blue-600' : 'text-slate-400' ?>"></i>
            <span><?= $tab['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter & Action Controls -->
    <div class="app-card p-5">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="type" value="<?= escapeOutput($reportType) ?>">
            <?php if ($selectedEmployee): ?>
            <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
            <?php endif; ?>

            <?php if ($reportType === 'credits'): ?>
            <div class="min-w-[130px]">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-1.5">Calendar Year</label>
                <input type="number" min="2000" max="2100" name="year" value="<?= $year ?>"
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm font-mono focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <?php else: ?>
            <div class="min-w-[160px]">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-1.5">Coverage Month</label>
                <input type="month" name="month" value="<?= escapeOutput($month) ?>"
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <?php endif; ?>

            <button type="submit"
                    class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center space-x-2">
                <i class="fas fa-search text-xs"></i>
                <span>Load Report</span>
            </button>

            <div class="flex items-center space-x-2 ml-auto">
                <?php if ($reportType === 'attendance' && $selectedEmployee): ?>
                <a href="?page=reports&type=attendance&month=<?= urlencode($month) ?>&employee_id=<?= $employeeId ?>&format=csv"
                   class="inline-flex items-center space-x-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors">
                    <i class="fas fa-file-csv text-emerald-600"></i>
                    <span>Export CSV</span>
                </a>
                <a href="?page=reports&type=attendance&month=<?= urlencode($month) ?>&employee_id=<?= $employeeId ?>&format=print" target="_blank"
                   class="inline-flex items-center space-x-1.5 px-3.5 py-2 rounded-xl bg-navy-950 hover:bg-navy-900 text-white text-xs font-bold shadow-xs transition-colors">
                    <i class="fas fa-print text-blue-300"></i>
                    <span>Printable DTR</span>
                </a>
                <?php elseif ($reportType === 'attendance'): ?>
                <a href="?page=reports&type=attendance&month=<?= urlencode($month) ?>&format=csv"
                   class="inline-flex items-center space-x-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors">
                    <i class="fas fa-file-csv text-emerald-600"></i>
                    <span>Master Turnout CSV</span>
                </a>
                <?php elseif ($reportType === 'leave'): ?>
                <a href="?page=reports&type=leave&month=<?= urlencode($month) ?><?= $selectedEmployee ? '&employee_id=' . $employeeId : '' ?>&format=csv"
                   class="inline-flex items-center space-x-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors">
                    <i class="fas fa-file-csv text-emerald-600"></i>
                    <span>Export Leaves CSV</span>
                </a>
                <?php elseif ($reportType === 'credits'): ?>
                <a href="?page=reports&type=credits&year=<?= $year ?>&format=csv"
                   class="inline-flex items-center space-x-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors">
                    <i class="fas fa-file-csv text-emerald-600"></i>
                    <span>Credits Ledger CSV</span>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Attendance Master View -->
    <?php if ($reportType === 'attendance' && !$selectedEmployee): ?>
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading">Master Attendance Summary</h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= escapeOutput(reportDateCell($dateFrom)) ?> &mdash; <?= escapeOutput(reportDateCell($dateTo)) ?></p>
            </div>
            <span class="text-xs font-bold text-blue-700 font-mono"><?= count($attendanceSummary) ?> Active Personnel</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Employee No</th>
                        <th class="px-4 py-3">Employee Name</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3 text-center">Present</th>
                        <th class="px-4 py-3 text-center">Absent</th>
                        <th class="px-4 py-3">Tardy</th>
                        <th class="px-4 py-3">Undertime</th>
                        <th class="px-4 py-3">Deducted</th>
                        <th class="px-6 py-3 text-right">DTR Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($attendanceSummary as $row): $employee = $row['employee']; ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-bold font-mono text-slate-800"><?= escapeOutput($employee['employee_no']) ?></td>
                        <td class="px-4 py-3.5 font-bold font-heading text-slate-800">
                            <?= escapeOutput($employee['first_name'] . ' ' . $employee['last_name']) ?>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                <?= escapeOutput($employee['department_name'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center font-bold text-emerald-600 font-mono"><?= $row['present'] ?></td>
                        <td class="px-4 py-3.5 text-center font-bold text-rose-600 font-mono"><?= $row['absent'] ?></td>
                        <td class="px-4 py-3.5 font-mono text-amber-700"><?= escapeOutput(reportFormatWorkMinutes($row['tardy'])) ?></td>
                        <td class="px-4 py-3.5 font-mono text-rose-700"><?= escapeOutput(reportFormatWorkMinutes($row['undertime'])) ?></td>
                        <td class="px-4 py-3.5 font-mono font-bold text-slate-800"><?= escapeOutput(reportFormatWorkMinutes($row['deducted'])) ?></td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="<?= baseUrl() ?>/index.php?page=reports&type=attendance&month=<?= urlencode($month) ?>&employee_id=<?= $employee['id'] ?>"
                               class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-2xs transition-colors">
                                <i class="fas fa-file-lines text-[10px]"></i>
                                <span>Inspect DTR</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Attendance Individual Time Card View -->
    <?php elseif ($reportType === 'attendance' && $selectedEmployee): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="app-card kpi-green p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Present Days</span>
            <p class="text-3xl font-extrabold text-emerald-600 font-heading mt-2"><?= $totalPresent ?></p>
            <p class="text-[11px] text-slate-400 mt-1">Days with verified punch</p>
        </div>
        <div class="app-card kpi-amber p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Total Tardiness</span>
            <p class="text-3xl font-extrabold text-amber-600 font-heading mt-2"><?= cscFormatMinutes($totalLate) ?></p>
            <p class="text-[11px] text-slate-400 mt-1">Late arrivals</p>
        </div>
        <div class="app-card kpi-red p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Undertime</span>
            <p class="text-3xl font-extrabold text-rose-600 font-heading mt-2"><?= cscFormatMinutes($totalUndertime) ?></p>
            <p class="text-[11px] text-slate-400 mt-1">Early departures</p>
        </div>
        <div class="app-card kpi-indigo p-5">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Total Deducted</span>
            <p class="text-3xl font-extrabold text-indigo-600 font-heading mt-2"><?= cscFormatMinutes($totalDeducted) ?></p>
            <p class="text-[11px] text-slate-400 mt-1">Charged against VL balance</p>
        </div>
    </div>

    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-slate-50/50">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading">Civil Service Time Card Log</h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= escapeOutput($agencyName) ?> &bull; <?= escapeOutput(reportDateCell($dateFrom)) ?> &mdash; <?= escapeOutput(reportDateCell($dateTo)) ?></p>
            </div>
            <div class="text-xs font-semibold text-slate-700 bg-white px-3 py-1.5 rounded-lg border border-slate-200">
                Staff: <strong class="text-blue-700"><?= escapeOutput($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?></strong> (<?= escapeOutput($selectedEmployee['employee_no']) ?>)
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-4 py-3">Schedule</th>
                        <th class="px-4 py-3">Clock-In</th>
                        <th class="px-4 py-3">Clock-Out</th>
                        <th class="px-4 py-3">Round-In</th>
                        <th class="px-4 py-3">Round-Out</th>
                        <th class="px-4 py-3">Worked</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($records as $r): foreach (reportTimeCardRows($r) as $timeRow): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3 font-bold font-heading text-slate-800"><?= escapeOutput(reportDateCell($r['date'])) ?></td>
                        <td class="px-4 py-3 text-slate-500 text-xs"><?= ($timeRow['clock_in'] || $timeRow['clock_out']) ? escapeOutput(reportTimetable()) : '--' ?></td>
                        <td class="px-4 py-3 font-mono text-slate-800"><?= $timeRow['clock_in'] ? escapeOutput(reportDateTimeCell($timeRow['clock_in'])) : '--' ?></td>
                        <td class="px-4 py-3 font-mono text-slate-800"><?= $timeRow['clock_out'] ? escapeOutput(reportDateTimeCell($timeRow['clock_out'])) : '--' ?></td>
                        <td class="px-4 py-3 font-mono text-slate-600"><?= $timeRow['round_in'] ? escapeOutput(reportDateTimeCell($timeRow['round_in'])) : '--' ?></td>
                        <td class="px-4 py-3 font-mono text-slate-600"><?= $timeRow['round_out'] ? escapeOutput(reportDateTimeCell($timeRow['round_out'])) : '--' ?></td>
                        <td class="px-4 py-3 font-mono font-bold text-slate-900"><?= escapeOutput($timeRow['work']) ?></td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Leave Report -->
    <?php elseif ($reportType === 'leave' && !$selectedEmployee): ?>
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading">Master Monthly Leave Summary</h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= escapeOutput(reportDateCell($dateFrom)) ?> &mdash; <?= escapeOutput(reportDateCell($dateTo)) ?></p>
            </div>
            <span class="text-xs font-bold text-blue-700 font-mono"><?= count($leaveSummary) ?> Staff Records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Employee No</th>
                        <th class="px-4 py-3">Employee Name</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3 text-center">Requests</th>
                        <th class="px-4 py-3 text-center">Approved</th>
                        <th class="px-4 py-3 text-center">Pending</th>
                        <th class="px-4 py-3 text-center">Approved Days</th>
                        <th class="px-6 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($leaveSummary as $row): $employee = $row['employee']; ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-mono font-bold text-slate-800"><?= escapeOutput($employee['employee_no']) ?></td>
                        <td class="px-4 py-3.5 font-bold font-heading text-slate-800">
                            <?= escapeOutput($employee['first_name'] . ' ' . $employee['last_name']) ?>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                <?= escapeOutput($employee['department_name'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center font-mono"><?= $row['requests'] ?></td>
                        <td class="px-4 py-3.5 text-center font-mono font-bold text-emerald-600"><?= $row['approved'] ?></td>
                        <td class="px-4 py-3.5 text-center font-mono font-bold text-amber-600"><?= $row['pending'] ?></td>
                        <td class="px-4 py-3.5 text-center font-mono font-bold text-blue-700"><?= $row['approved_days'] ?></td>
                        <td class="px-6 py-3.5 text-right">
                            <a href="<?= baseUrl() ?>/index.php?page=reports&type=leave&month=<?= urlencode($month) ?>&employee_id=<?= $employee['id'] ?>"
                               class="inline-flex items-center space-x-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition-colors">
                                <i class="fas fa-eye text-[10px]"></i>
                                <span>Inspect</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Individual Leave Report -->
    <?php elseif ($reportType === 'leave' && $selectedEmployee): ?>
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading">Individual Leave Applications</h3>
                <p class="text-xs text-slate-500 mt-0.5"><?= escapeOutput($selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name']) ?> &bull; <?= escapeOutput(reportDateCell($dateFrom)) ?> &mdash; <?= escapeOutput(reportDateCell($dateTo)) ?></p>
            </div>
            <span class="text-xs font-bold text-slate-500"><?= count($leaveRows) ?> Records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Leave Classification</th>
                        <th class="px-4 py-3">Date From</th>
                        <th class="px-4 py-3">Date To</th>
                        <th class="px-4 py-3 text-center">Days</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-6 py-3">Reason / Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($leaveRows)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                            No leave applications filed for this month.
                        </td>
                    </tr>
                    <?php else: foreach ($leaveRows as $leave): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-bold font-heading text-slate-800"><?= escapeOutput($leave['leave_type_name']) ?></td>
                        <td class="px-4 py-3.5 font-mono text-slate-700"><?= escapeOutput(reportDateCell($leave['date_from'])) ?></td>
                        <td class="px-4 py-3.5 font-mono text-slate-700"><?= escapeOutput(reportDateCell($leave['date_to'])) ?></td>
                        <td class="px-4 py-3.5 text-center font-bold font-mono text-blue-700"><?= (int) $leave['days'] ?></td>
                        <td class="px-4 py-3.5"><?= getStatusBadge($leave['status']) ?></td>
                        <td class="px-6 py-3.5 text-slate-600"><?= escapeOutput($leave['reason'] ?? '--') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CSC Leave Credits Ledger -->
    <?php elseif ($reportType === 'credits'): ?>
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-slate-50/50">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading">Civil Service Leave Credits Ledger (CY <?= $year ?>)</h3>
                <p class="text-xs text-slate-500 mt-0.5">Vacation Leave & Sick Leave earned under CSC MC No. 41 s. 1998 (1.25 days / month)</p>
            </div>
            <span class="text-xs font-bold text-blue-800 bg-blue-100 px-2.5 py-1 rounded-full font-mono">15.000 VL &bull; 15.000 SL Annual</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Employee No</th>
                        <th class="px-4 py-3">Full Name</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3 text-right">VL Earned</th>
                        <th class="px-4 py-3 text-right">VL Used</th>
                        <th class="px-4 py-3 text-right">VL Deductions</th>
                        <th class="px-4 py-3 text-right text-blue-800">VL Balance</th>
                        <th class="px-4 py-3 text-right">SL Earned</th>
                        <th class="px-4 py-3 text-right">SL Used</th>
                        <th class="px-6 py-3 text-right text-emerald-800">SL Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($creditRows as $row): $employee = $row['employee']; ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-mono font-bold text-slate-800"><?= escapeOutput($employee['employee_no']) ?></td>
                        <td class="px-4 py-3.5 font-bold font-heading text-slate-800">
                            <?= escapeOutput($employee['first_name'] . ' ' . $employee['last_name']) ?>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                <?= escapeOutput($employee['department_name'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right font-mono text-slate-600"><?= escapeOutput(reportFormatDays($row['vl_earned'])) ?></td>
                        <td class="px-4 py-3.5 text-right font-mono text-slate-600"><?= escapeOutput(reportFormatDays($row['vl_used'])) ?></td>
                        <td class="px-4 py-3.5 text-right font-mono font-bold text-rose-600"><?= escapeOutput(reportFormatDays($row['vl_deductions'])) ?></td>
                        <td class="px-4 py-3.5 text-right font-mono font-extrabold text-blue-700 bg-blue-50/40"><?= escapeOutput(reportFormatDays($row['vl_balance'])) ?></td>
                        <td class="px-4 py-3.5 text-right font-mono text-slate-600"><?= escapeOutput(reportFormatDays($row['sl_earned'])) ?></td>
                        <td class="px-4 py-3.5 text-right font-mono text-slate-600"><?= escapeOutput(reportFormatDays($row['sl_used'])) ?></td>
                        <td class="px-6 py-3.5 text-right font-mono font-extrabold text-emerald-700 bg-emerald-50/40"><?= escapeOutput(reportFormatDays($row['sl_balance'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3.5 border-t border-slate-100 text-xs text-slate-500 bg-slate-50/50 flex items-center justify-between">
            <span><i class="fas fa-gavel text-blue-600 mr-1.5"></i>Civil Service Commission MC No. 41 s. 1998 &amp; MC No. 14 s. 1999 Compliance</span>
            <span class="font-mono text-slate-400">1 Working Day = 8 Hours = 480 Minutes</span>
        </div>
    </div>
    <?php endif; ?>
</div>
