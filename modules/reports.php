<?php
requireRole('Administrator');

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$dateFrom = $month . '-01';
$dateTo = date('Y-m-t', strtotime($dateFrom));
$employeeId = intval($_GET['employee_id'] ?? 0);
$format = $_GET['format'] ?? 'html';
$agencyName = getSetting('company_name', 'Cauayan City Water District');
$agencyAddress = getSetting('company_address', '');

function reportTimeCell($value): string {
    return $value ? date('h:i A', strtotime($value)) : '';
}

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

function reportWorkBetween($start, $end): string {
    if (!$start || !$end) return '00:00';
    $minutes = max(0, (int) floor((strtotime($end) - strtotime($start)) / 60));
    return reportFormatWorkMinutes($minutes);
}

function reportTimetable(): string {
    $amStart = getSetting('csc_am_start', '08:00');
    $pmEnd = getSetting('csc_pm_end', '17:00');
    return 'Office 8-5(' . $amStart . '-' . $pmEnd . ')';
}

function reportTimeCardRows(array $record): array {
    $rows = [];
    $hasMorning = !empty($record['time_in']) || !empty($record['break_out']);
    $hasAfternoon = !empty($record['break_in']) || !empty($record['time_out']);

    if ($hasMorning) {
        $rows[] = [
            'clock_in' => $record['time_in'] ?? null,
            'clock_out' => $record['break_out'] ?? null,
            'round_in' => $record['time_in'] ?? null,
            'round_out' => $record['break_out'] ?? null,
            'work' => reportWorkBetween($record['time_in'] ?? null, $record['break_out'] ?? null),
        ];
    }

    if ($hasAfternoon) {
        $rows[] = [
            'clock_in' => $record['break_in'] ?? null,
            'clock_out' => $record['time_out'] ?? null,
            'round_in' => $record['break_in'] ?? null,
            'round_out' => $record['time_out'] ?? null,
            'work' => reportWorkBetween($record['break_in'] ?? null, $record['time_out'] ?? null),
        ];
    }

    if (!$rows) {
        $rows[] = [
            'clock_in' => null,
            'clock_out' => null,
            'round_in' => null,
            'round_out' => null,
            'work' => '00:00',
        ];
    }

    return $rows;
}

$employees = $db->query("
    SELECT e.id, e.first_name, e.last_name, e.employee_no, d.name AS department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.is_active = 1
    ORDER BY e.last_name, e.first_name
")->fetchAll();

$selectedEmployee = null;
if ($employeeId) {
    foreach ($employees as $employee) {
        if ((int) $employee['id'] === $employeeId) {
            $selectedEmployee = $employee;
            break;
        }
    }
}

if ($format !== 'html' && (!$employeeId || !$selectedEmployee)) {
    setFlash('Select an employee before generating a report.', 'warning');
    redirect(APP_URL . '/index.php?page=reports&month=' . urlencode($month));
}

if ($format !== 'html') {
    $records = cscAttachComputations(generateCompleteAttendanceReport($employeeId, $dateFrom, $dateTo));
    $employeeName = $selectedEmployee['first_name'] . ' ' . $selectedEmployee['last_name'];

    if ($format === 'csv') {
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

    if ($format === 'print') {
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
                td.left { text-align: left; }
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
}

$records = $selectedEmployee ? cscAttachComputations(generateCompleteAttendanceReport($employeeId, $dateFrom, $dateTo)) : [];
$totalPresent = $records ? count(array_filter($records, fn($r) => $r['time_in'] !== null)) : 0;
$totalLate = $records ? array_sum(array_column(array_column($records, 'csc'), 'tardiness_minutes')) : 0;
$totalUndertime = $records ? array_sum(array_column(array_column($records, 'csc'), 'undertime_minutes')) : 0;
$totalDeducted = $records ? array_sum(array_column(array_column($records, 'csc'), 'deficiency_minutes')) : 0;
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Reports</h1>
            <p class="text-sm text-gray-500">View monthly DTR records per employee.</p>
        </div>
        <?php if ($selectedEmployee): ?>
        <a href="<?= APP_URL ?>/index.php?page=reports&month=<?= urlencode($month) ?>" class="text-sm text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i>Back to Employees</a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <input type="hidden" name="page" value="reports">
            <?php if ($selectedEmployee): ?>
            <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
            <?php endif; ?>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Month</label>
                <input type="month" name="month" value="<?= escapeOutput($month) ?>" class="px-3 py-2 border rounded-lg text-sm">
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800"><i class="fas fa-search mr-2"></i>View Month</button>
            <?php if ($selectedEmployee): ?>
            <a href="?page=reports&month=<?= urlencode($month) ?>&employee_id=<?= $employeeId ?>&format=csv" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-file-csv mr-2"></i>CSV</a>
            <a href="?page=reports&month=<?= urlencode($month) ?>&employee_id=<?= $employeeId ?>&format=print" target="_blank" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-print mr-2"></i>Printable DTR</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$selectedEmployee): ?>
    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b">
            <h3 class="font-semibold text-gray-800">Employees</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Employee No</th>
                        <th class="text-left px-4 py-3 font-medium">Name</th>
                        <th class="text-left px-4 py-3 font-medium">Department</th>
                        <th class="text-right px-4 py-3 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($employees)): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No employees found</td></tr>
                    <?php else: foreach ($employees as $employee): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($employee['employee_no']) ?></td>
                        <td class="px-4 py-3 font-medium"><?= escapeOutput($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                        <td class="px-4 py-3"><?= escapeOutput($employee['department_name'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3 text-right">
                            <a href="<?= APP_URL ?>/index.php?page=reports&month=<?= urlencode($month) ?>&employee_id=<?= $employee['id'] ?>" class="inline-flex items-center px-3 py-1.5 bg-blue-700 text-white rounded-lg text-xs hover:bg-blue-800"><i class="fas fa-eye mr-1"></i>View</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-sm text-gray-500">Present Days</p>
            <p class="text-2xl font-bold text-green-600"><?= $totalPresent ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-sm text-gray-500">Tardiness</p>
            <p class="text-2xl font-bold text-orange-600"><?= cscFormatMinutes($totalLate) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-sm text-gray-500">Undertime</p>
            <p class="text-2xl font-bold text-red-600"><?= cscFormatMinutes($totalUndertime) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-sm text-gray-500">Deducted</p>
            <p class="text-2xl font-bold text-red-600"><?= cscFormatMinutes($totalDeducted) ?></p>
        </div>
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
    <?php endif; ?>
</div>
