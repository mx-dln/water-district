<?php
requireLogin();

$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
if (isEmployee() && !$employeeId) {
    setFlash('Employee profile not found. Contact administrator.', 'danger');
    redirect(APP_URL . '/index.php?page=dashboard');
}

$action = $_GET['action'] ?? '';
if (in_array($action, ['time-in', 'time-out', 'break-in', 'break-out'])) {
    $type = str_replace('-', '_', $action);
    require __DIR__ . '/attendance/capture.php';
    return;
}

$subpage = $_GET['subpage'] ?? 'today';
if ($subpage === 'history') {
    require __DIR__ . '/attendance/history.php';
    return;
}
if ($subpage === 'evidence') {
    require __DIR__ . '/attendance/evidence.php';
    return;
}

$today = date('Y-m-d');
$isAdmin = isAdmin();
$empId = $isAdmin ? ($_GET['employee_id'] ?? null) : $employeeId;

$pageTitle = isAdmin() ? 'Attendance Management' : 'My Attendance';
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800"><?= $pageTitle ?></h1>
            <p class="text-sm text-gray-500"><?= formatDate($today) ?></p>
        </div>
        <?php if (isEmployee()): ?>
        <div>
            <a href="<?= baseUrl() ?>/index.php?page=attendance-history" class="inline-block px-4 py-2 border rounded-lg text-sm hover:bg-gray-50 text-center"><i class="fas fa-history mr-2"></i>My History</a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (isEmployee()): ?>
    <?php
    $todayAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
    $todayAtt->execute([$employeeId, $today]);
    $todayAtt = $todayAtt->fetch();
    $emp = getEmployeeData($employeeId);
    ?>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-camera text-blue-600 text-2xl"></i>
            </div>
            <h2 class="text-lg font-semibold text-gray-800"><?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?></h2>
            <p class="text-sm text-gray-500"><?= escapeOutput($emp['employee_no']) ?> | <?= escapeOutput($emp['department_name'] ?? 'N/A') ?></p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            $attTypes = [
                ['type' => 'time-in', 'label' => 'Time In', 'icon' => 'fa-sign-in-alt', 'color' => 'green', 'done' => $todayAtt && $todayAtt['time_in'], 'time' => $todayAtt['time_in'] ?? null],
                ['type' => 'break-out', 'label' => 'Break Out', 'icon' => 'fa-coffee', 'color' => 'yellow', 'done' => $todayAtt && $todayAtt['break_out'], 'time' => $todayAtt['break_out'] ?? null],
                ['type' => 'break-in', 'label' => 'Break In', 'icon' => 'fa-mug-hot', 'color' => 'orange', 'done' => $todayAtt && $todayAtt['break_in'], 'time' => $todayAtt['break_in'] ?? null],
                ['type' => 'time-out', 'label' => 'Time Out', 'icon' => 'fa-sign-out-alt', 'color' => 'red', 'done' => $todayAtt && $todayAtt['time_out'], 'time' => $todayAtt['time_out'] ?? null],
            ];
            foreach ($attTypes as $at):
            ?>
            <a href="<?= !$at['done'] ? baseUrl('/index.php?page=attendance&action=' . $at['type']) : '#' ?>"
               class="flex flex-col items-center justify-center p-3 sm:p-5 border-2 rounded-xl transition-all <?= $at['done'] ? 'border-green-300 bg-green-50 cursor-default' : 'border-dashed border-gray-300 hover:border-' . $at['color'] . '-400 hover:bg-' . $at['color'] . '-50' ?>">
                <i class="fas <?= $at['icon'] ?> text-2xl sm:text-3xl text-<?= $at['color'] ?>-600 mb-1 sm:mb-2"></i>
                <span class="text-xs sm:text-sm font-medium text-gray-700"><?= $at['label'] ?></span>
                <?php if ($at['done']): ?>
                <span class="text-xs text-green-600 mt-1"><?= formatTime($at['time']) ?></span>
                <?php else: ?>
                <span class="text-[10px] sm:text-xs text-gray-400 mt-1">Tap to start</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php else: ?>
    <?php
    $calMonth = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : (int)date('m');
    $calYear = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : (int)date('Y');
    $selDate = $_GET['date'] ?? date('Y-m-d');
    $totalEmp = $db->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();

    $firstDay = mktime(0,0,1,$calMonth,1,$calYear);
    $daysInMonth = date('t', $firstDay);
    $startDow = (int)date('w', $firstDay);
    $monthName = date('F Y', $firstDay);

    $monthData = $db->prepare("
        SELECT a.date, COUNT(a.id) as present,
               SUM(CASE WHEN a.time_in_status = 'verified' THEN 1 ELSE 0 END) as verified,
               SUM(CASE WHEN a.time_in IS NOT NULL AND TIME(a.time_in) > '08:15:00' THEN 1 ELSE 0 END) as late
        FROM attendance a
        WHERE DATE_FORMAT(a.date, '%Y-%m') = ?
        GROUP BY a.date
    ");
    $monthData->execute([sprintf('%04d-%02d', $calYear, $calMonth)]);
    $dayStats = [];
    foreach ($monthData as $d) { $dayStats[$d['date']] = $d; }

    $selAtt = $db->prepare("
        SELECT a.*, e.first_name, e.last_name, e.employee_no, d.name as dept
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE a.date = ?
        ORDER BY e.last_name ASC
    ");
    $selAtt->execute([$selDate]);
    $selAtt = $selAtt->fetchAll();
    ?>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Attendance Calendar</h1>
            <p class="text-sm text-gray-500">Click a day to view attendance details</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="?page=attendance&cal_month=<?= $calMonth-1 < 1 ? 12 : $calMonth-1 ?>&cal_year=<?= $calMonth-1 < 1 ? $calYear-1 : $calYear ?>&date=<?= $selDate ?>" class="px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50">&larr;</a>
            <span class="text-sm font-semibold px-2"><?= $monthName ?></span>
            <a href="?page=attendance&cal_month=<?= $calMonth+1 > 12 ? 1 : $calMonth+1 ?>&cal_year=<?= $calMonth+1 > 12 ? $calYear+1 : $calYear ?>&date=<?= $selDate ?>" class="px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50">&rarr;</a>
            <a href="?page=reports" class="px-3 py-1.5 border rounded-lg text-sm hover:bg-gray-50 ml-2">Reports</a>
        </div>
    </div>

    <div class="flex items-center gap-4 text-xs text-gray-500 mb-2">
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-500"></span> Present</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-orange-400"></span> Late</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-400"></span> Absent</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-gray-300"></span> No data</span>
    </div>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="grid grid-cols-7 text-center text-xs font-semibold text-gray-500 uppercase border-b bg-gray-50">
            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
            <div class="py-2"><?= $d ?></div>
            <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-7">
            <?php for ($i=0; $i<$startDow; $i++): ?>
            <div class="min-h-[72px] sm:min-h-[90px] p-1 border-b border-r bg-gray-50/50"></div>
            <?php endfor; ?>
            <?php for ($day=1; $day<=$daysInMonth; $day++):
                $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
                $today = $dateStr === date('Y-m-d');
                $selected = $dateStr === $selDate;
                $stats = $dayStats[$dateStr] ?? null;
                $absent = $totalEmp - ($stats ? $stats['present'] : 0);
                $rate = $stats ? round(($stats['present'] / $totalEmp) * 100) : 0;
            ?>
            <a href="?page=attendance&cal_month=<?= $calMonth ?>&cal_year=<?= $calYear ?>&date=<?= $dateStr ?>"
               class="min-h-[72px] sm:min-h-[90px] p-1.5 border-b border-r hover:bg-blue-50 transition-colors flex flex-col relative <?= $selected ? 'bg-blue-50 ring-2 ring-blue-500 ring-inset' : '' ?> <?= $today && !$selected ? 'bg-yellow-50/50' : '' ?>">
                <span class="text-xs sm:text-sm font-medium leading-tight <?= $today ? 'text-blue-700' : ($selected ? 'text-blue-700' : 'text-gray-700') ?>"><?= $day ?></span>
                <?php if ($stats): ?>
                <div class="mt-1 flex flex-wrap gap-0.5">
                    <?php for ($p=0; $p<min($stats['present'], 6); $p++): ?>
                    <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-green-500"></span>
                    <?php endfor; ?>
                    <?php for ($l=0; $l<min($stats['late'], 3); $l++): ?>
                    <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-orange-400"></span>
                    <?php endfor; ?>
                </div>
                <div class="mt-auto w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                    <div class="h-full rounded-full <?= $rate >= 80 ? 'bg-green-500' : ($rate >= 50 ? 'bg-yellow-500' : 'bg-red-500') ?>" style="width:<?= $rate ?>%"></div>
                </div>
                <span class="text-[10px] text-gray-400 leading-tight"><?= $stats['present'] ?>/<?= $totalEmp ?></span>
                <?php else: ?>
                <div class="mt-auto flex items-center justify-center">
                    <span class="w-2 h-2 rounded-full bg-gray-300"></span>
                </div>
                <?php endif; ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><?= formatDate($selDate) ?></h3>
            <div class="flex items-center gap-3 text-xs text-gray-500">
                <span class="text-green-700 font-medium"><?= count($selAtt) ?> present</span>
                <span class="text-gray-300">|</span>
                <span class="text-red-500 font-medium"><?= $totalEmp - count($selAtt) ?> absent</span>
            </div>
        </div>
        <?php if (empty($selAtt)): ?>
        <div class="p-8 text-center text-gray-500 text-sm">No attendance records for this date</div>
        <?php else: ?>
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Employee</th>
                        <th class="text-left px-4 py-3 font-medium">Time In</th>
                        <th class="text-left px-4 py-3 font-medium">Break Out</th>
                        <th class="text-left px-4 py-3 font-medium">Break In</th>
                        <th class="text-left px-4 py-3 font-medium">Time Out</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-center px-4 py-3 font-medium">Evidence</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($selAtt as $a): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($a['first_name'] . ' ' . $a['last_name']) ?><br><span class="text-xs text-gray-500"><?= escapeOutput($a['employee_no']) ?></span></td>
                        <td class="px-4 py-3"><?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?></td>
                        <td class="px-4 py-3"><?= $a['break_out'] ? formatTime($a['break_out']) : '--' ?></td>
                        <td class="px-4 py-3"><?= $a['break_in'] ? formatTime($a['break_in']) : '--' ?></td>
                        <td class="px-4 py-3"><?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></td>
                        <td class="px-4 py-3"><?= getStatusBadge($a['time_in_status'] ?? 'pending') ?></td>
                        <td class="px-4 py-3 text-center">
                            <a href="<?= baseUrl() ?>/index.php?page=attendance-evidence&id=<?= $a['id'] ?>" class="text-blue-600 hover:text-blue-800"><i class="fas fa-image"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="sm:hidden divide-y">
            <?php foreach ($selAtt as $a): ?>
            <div class="p-3 space-y-1 text-sm">
                <div class="font-medium"><?= escapeOutput($a['first_name'] . ' ' . $a['last_name']) ?> <span class="text-xs text-gray-500"><?= escapeOutput($a['employee_no']) ?></span></div>
                <div class="grid grid-cols-2 gap-1 text-xs text-gray-600">
                    <span>In: <?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?></span>
                    <span>Out: <?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></span>
                    <span>Break: <?= $a['break_out'] ? formatTime($a['break_out']) : '--' ?> / <?= $a['break_in'] ? formatTime($a['break_in']) : '--' ?></span>
                    <span class="text-right"><?= getStatusBadge($a['time_in_status'] ?? 'pending') ?></span>
                </div>
                <a href="<?= baseUrl() ?>/index.php?page=attendance-evidence&id=<?= $a['id'] ?>" class="text-blue-600 text-xs hover:underline">View Evidence</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
