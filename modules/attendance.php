<?php
requireLogin();

$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
if (isEmployee() && !$employeeId) {
    setFlash('Employee profile not linked. Please contact the system administrator.', 'danger');
    redirect(baseUrl() . '/index.php?page=dashboard');
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
$pageTitle = $isAdmin ? 'Attendance Management & Calendar' : 'Field Attendance';
?>

<div class="space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="hover:text-blue-600">Home</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold"><?= $pageTitle ?></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight"><?= $pageTitle ?></h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Cauayan City Water District &bull; Geofenced Attendance Monitoring & Verification
            </p>
        </div>
        <div class="flex items-center space-x-2">
            <?php if (isEmployee()): ?>
            <a href="<?= baseUrl() ?>/index.php?page=attendance-history"
               class="inline-flex items-center space-x-2 px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-clock-rotate-left text-blue-600"></i>
                <span>My Attendance Logs</span>
            </a>
            <?php else: ?>
            <a href="<?= baseUrl() ?>/index.php?page=kiosk" target="_blank"
               class="inline-flex items-center space-x-2 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-camera"></i>
                <span>Face Kiosk</span>
            </a>
            <a href="<?= baseUrl() ?>/index.php?page=reports"
               class="inline-flex items-center space-x-2 px-3.5 py-2 bg-navy-950 hover:bg-navy-900 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-file-export text-blue-300"></i>
                <span>Generate CSC DTR</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isEmployee()): ?>
    <?php
    $todayAttStmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
    $todayAttStmt->execute([$employeeId, $today]);
    $todayAtt = $todayAttStmt->fetch(PDO::FETCH_ASSOC);
    $emp = getEmployeeData($employeeId);
    ?>
    <div class="app-card p-6 sm:p-8">
        <div class="text-center max-w-md mx-auto mb-8">
            <div class="w-16 h-16 rounded-xl bg-blue-700 text-white flex items-center justify-center mx-auto mb-4 shadow-sm text-2xl font-bold font-heading">
                <?= strtoupper(substr($emp['first_name'] ?? 'E', 0, 1)) ?>
            </div>
            <h2 class="text-xl font-extrabold text-navy-950 font-heading">
                <?= escapeOutput($emp['first_name'] . ' ' . $emp['last_name']) ?>
            </h2>
            <div class="flex items-center justify-center space-x-2 mt-1 text-xs text-slate-500">
                <span class="font-mono font-bold text-slate-700"><?= escapeOutput($emp['employee_no']) ?></span>
                <span>&bull;</span>
                <span class="font-semibold text-blue-700"><?= escapeOutput($emp['department_name'] ?? 'CCWD Staff') ?></span>
            </div>
            <p class="text-xs text-slate-400 mt-2">
                Click a category below to verify your GPS location within the CCWD authorized geofence boundary and capture a selfie.
            </p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
            <?php
            $attTypes = [
                ['type' => 'time-in',   'label' => 'Time In',   'icon' => 'fa-arrow-right-to-bracket', 'color' => 'emerald', 'done' => !empty($todayAtt['time_in']),   'time' => $todayAtt['time_in'] ?? null],
                ['type' => 'break-out', 'label' => 'Break Out', 'icon' => 'fa-mug-saucer',            'color' => 'amber',   'done' => !empty($todayAtt['break_out']), 'time' => $todayAtt['break_out'] ?? null],
                ['type' => 'break-in',  'label' => 'Break In',  'icon' => 'fa-utensils',              'color' => 'blue',    'done' => !empty($todayAtt['break_in']),  'time' => $todayAtt['break_in'] ?? null],
                ['type' => 'time-out',  'label' => 'Time Out',  'icon' => 'fa-arrow-right-from-bracket', 'color' => 'rose',   'done' => !empty($todayAtt['time_out']),  'time' => $todayAtt['time_out'] ?? null],
            ];
            foreach ($attTypes as $at):
            ?>
            <a href="<?= !$at['done'] ? baseUrl('/index.php?page=attendance&action=' . $at['type']) : '#' ?>"
               class="group flex flex-col items-center justify-center p-5 rounded-2xl border-2 transition-all duration-200
               <?= $at['done']
                    ? 'border-emerald-200 bg-emerald-50/50 cursor-default'
                    : 'border-slate-200 bg-white hover:border-blue-500 hover:bg-blue-50/20 hover:shadow-md hover:-translate-y-0.5' ?>">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-3 transition-transform group-hover:scale-110
                    <?= $at['done'] ? 'bg-emerald-500 text-white shadow-xs' : 'bg-' . $at['color'] . '-50 text-' . $at['color'] . '-600' ?>">
                    <i class="fas <?= $at['icon'] ?> text-lg"></i>
                </div>
                <span class="text-xs sm:text-sm font-bold font-heading text-slate-800">
                    <?= $at['label'] ?>
                </span>
                <?php if ($at['done']): ?>
                <span class="mt-2 inline-flex items-center text-[11px] font-bold text-emerald-800 bg-emerald-100/90 px-2 py-0.5 rounded-full font-mono">
                    <i class="fas fa-check text-[9px] mr-1"></i><?= formatTime($at['time']) ?>
                </span>
                <?php else: ?>
                <span class="mt-2 text-[10px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    Start Geofence &rarr;
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- ADMIN ATTENDANCE CALENDAR -->
    <?php
    $calMonth = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : (int)date('m');
    $calYear = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : (int)date('Y');
    $selDate = $_GET['date'] ?? date('Y-m-d');
    $totalEmp = (int)$db->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();

    $firstDay = mktime(0,0,1,$calMonth,1,$calYear);
    $daysInMonth = (int)date('t', $firstDay);
    $startDow = (int)date('w', $firstDay);
    $monthName = date('F Y', $firstDay);

    $monthDataStmt = $db->prepare("
        SELECT a.date, COUNT(a.id) as present,
               SUM(CASE WHEN a.time_in_status = 'verified' THEN 1 ELSE 0 END) as verified,
               SUM(CASE WHEN a.time_in IS NOT NULL AND TIME(a.time_in) > '08:15:00' THEN 1 ELSE 0 END) as late
        FROM attendance a
        WHERE DATE_FORMAT(a.date, '%Y-%m') = ?
        GROUP BY a.date
    ");
    $monthDataStmt->execute([sprintf('%04d-%02d', $calYear, $calMonth)]);
    $monthData = $monthDataStmt->fetchAll(PDO::FETCH_ASSOC);
    $dayStats = [];
    foreach ($monthData as $d) { $dayStats[$d['date']] = $d; }

    $selAttStmt = $db->prepare("
        SELECT a.*, e.first_name, e.last_name, e.employee_no, d.name as dept
        FROM attendance a
        JOIN employees e ON a.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE a.date = ?
        ORDER BY e.last_name ASC
    ");
    $selAttStmt->execute([$selDate]);
    $selAtt = $selAttStmt->fetchAll(PDO::FETCH_ASSOC);

    $prevMonth = $calMonth - 1 < 1 ? 12 : $calMonth - 1;
    $prevYear  = $calMonth - 1 < 1 ? $calYear - 1 : $calYear;
    $nextMonth = $calMonth + 1 > 12 ? 1 : $calMonth + 1;
    $nextYear  = $calMonth + 1 > 12 ? $calYear + 1 : $calYear;
    ?>

    <div class="app-card p-6">
        <!-- Calendar Nav Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <i class="fas fa-calendar-alt text-base"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-navy-950 font-heading"><?= $monthName ?></h2>
                    <p class="text-xs text-slate-500">Click any day tile to inspect staff presence</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="?page=attendance&cal_month=<?= $prevMonth ?>&cal_year=<?= $prevYear ?>&date=<?= $selDate ?>"
                   class="p-2 w-9 h-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center text-slate-600 transition-colors">
                    <i class="fas fa-chevron-left text-xs"></i>
                </a>
                <span class="text-xs font-bold text-slate-700 px-3 py-1.5 rounded-lg bg-slate-100 font-mono"><?= sprintf('%04d-%02d', $calYear, $calMonth) ?></span>
                <a href="?page=attendance&cal_month=<?= $nextMonth ?>&cal_year=<?= $nextYear ?>&date=<?= $selDate ?>"
                   class="p-2 w-9 h-9 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center text-slate-600 transition-colors">
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
                <a href="?page=attendance&cal_month=<?= (int)date('m') ?>&cal_year=<?= (int)date('Y') ?>&date=<?= date('Y-m-d') ?>"
                   class="px-3 py-1.5 text-xs font-bold rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors ml-1">
                    Today
                </a>
            </div>
        </div>

        <!-- Legend Pills -->
        <div class="flex flex-wrap items-center gap-4 text-xs font-medium text-slate-500 mb-4 pb-3 border-b border-slate-100">
            <span class="flex items-center space-x-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span><span>Present Turnout</span></span>
            <span class="flex items-center space-x-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span><span>Late Entry</span></span>
            <span class="flex items-center space-x-1.5"><span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span><span>Unrecorded</span></span>
            <span class="flex items-center space-x-1.5"><span class="w-2.5 h-2.5 rounded-full bg-slate-300"></span><span>Weekend / Holiday</span></span>
        </div>

        <!-- Calendar Month Grid -->
        <div class="rounded-2xl border border-slate-200 overflow-hidden shadow-2xs">
            <div class="grid grid-cols-7 text-center text-[11px] font-bold text-slate-500 uppercase tracking-wider font-heading border-b border-slate-200 bg-slate-100/75">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                <div class="py-2.5"><?= $d ?></div>
                <?php endforeach; ?>
            </div>
            <div class="grid grid-cols-7 divide-x divide-y divide-slate-100 bg-slate-50/30">
                <?php for ($i=0; $i<$startDow; $i++): ?>
                <div class="min-h-[75px] sm:min-h-[92px] p-2 bg-slate-50/60 opacity-40"></div>
                <?php endfor; ?>
                <?php for ($day=1; $day<=$daysInMonth; $day++):
                    $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
                    $isCurrentToday = ($dateStr === date('Y-m-d'));
                    $isSelected = ($dateStr === $selDate);
                    $stats = $dayStats[$dateStr] ?? null;
                    $rate = ($stats && $totalEmp > 0) ? round(($stats['present'] / $totalEmp) * 100) : 0;
                ?>
                <a href="?page=attendance&cal_month=<?= $calMonth ?>&cal_year=<?= $calYear ?>&date=<?= $dateStr ?>"
                   class="min-h-[75px] sm:min-h-[92px] p-2 hover:bg-blue-50/80 transition-all flex flex-col justify-between relative group
                   <?= $isSelected ? 'bg-blue-50/90 ring-2 ring-blue-600 ring-inset shadow-xs' : 'bg-white' ?>
                   <?= $isCurrentToday && !$isSelected ? 'bg-amber-50/30' : '' ?>">
                    <div class="flex items-center justify-between">
                        <span class="text-xs sm:text-sm font-bold font-heading <?= $isCurrentToday ? 'w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center' : ($isSelected ? 'text-blue-700' : 'text-slate-800') ?>">
                            <?= $day ?>
                        </span>
                        <?php if ($stats && $stats['present'] > 0): ?>
                        <span class="text-[10px] font-bold font-mono px-1.5 py-0.2 rounded-full <?= $rate >= 75 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                            <?= $stats['present'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($stats): ?>
                    <div class="my-1 flex flex-wrap gap-1">
                        <?php for ($p=0; $p<min($stats['present'], 5); $p++): ?>
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <?php endfor; ?>
                        <?php for ($l=0; $l<min($stats['late'], 3); $l++): ?>
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                        <?php endfor; ?>
                    </div>
                    <div>
                        <div class="w-full bg-slate-100 rounded-full h-1 overflow-hidden">
                            <div class="h-full rounded-full <?= $rate >= 80 ? 'bg-emerald-500' : ($rate >= 50 ? 'bg-amber-500' : 'bg-rose-500') ?>" style="width:<?= $rate ?>%"></div>
                        </div>
                        <span class="text-[9px] text-slate-400 mt-0.5 block leading-tight font-mono"><?= $rate ?>% turn</span>
                    </div>
                    <?php else: ?>
                    <div class="flex items-center justify-center py-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-200"></span>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- Attendance for Selected Day Table -->
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/50">
            <div>
                <div class="flex items-center space-x-2">
                    <h3 class="font-bold text-base text-navy-950 font-heading"><?= formatDate($selDate) ?></h3>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 text-blue-800">Attendance Log</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Staff presence, punch methods and selfie audit trail</p>
            </div>
            <div class="flex items-center space-x-3 text-xs font-semibold">
                <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200/60">
                    <i class="fas fa-check text-emerald-600 mr-1"></i><?= count($selAtt) ?> Present
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-800 border border-rose-200/60">
                    <i class="fas fa-xmark text-rose-600 mr-1"></i><?= max(0, $totalEmp - count($selAtt)) ?> Absent
                </span>
            </div>
        </div>

        <?php if (empty($selAtt)): ?>
        <div class="p-12 text-center text-slate-400">
            <i class="fas fa-clipboard-user text-3xl mb-2 text-slate-300"></i>
            <p class="text-sm font-semibold text-slate-600">No attendance entries recorded on <?= formatDate($selDate) ?>.</p>
            <p class="text-xs text-slate-400 mt-1">Personnel have not logged time-in records or this day is a declared non-working holiday.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Employee</th>
                        <th class="px-4 py-3">Time In</th>
                        <th class="px-4 py-3">Break Out</th>
                        <th class="px-4 py-3">Break In</th>
                        <th class="px-4 py-3">Time Out</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-center">Selfie Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($selAtt as $a):
                        $empInitial = strtoupper(substr($a['first_name'] ?? 'E', 0, 1));
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center text-xs shrink-0">
                                    <?= $empInitial ?>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 font-heading leading-tight">
                                        <?= escapeOutput($a['first_name'] . ' ' . $a['last_name']) ?>
                                    </p>
                                    <p class="text-[11px] text-slate-400 font-mono"><?= escapeOutput($a['employee_no']) ?> &bull; <?= escapeOutput($a['dept'] ?? 'Staff') ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-800">
                            <?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-600">
                            <?= $a['break_out'] ? formatTime($a['break_out']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-600">
                            <?= $a['break_in'] ? formatTime($a['break_in']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-800">
                            <?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <?= getStatusBadge($a['time_in_status'] ?? 'pending') ?>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <a href="<?= baseUrl() ?>/index.php?page=attendance-evidence&id=<?= $a['id'] ?>"
                               class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                               title="Inspect Watermarked Selfie Evidence">
                                <i class="fas fa-camera text-[11px]"></i>
                                <span>Evidence</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
