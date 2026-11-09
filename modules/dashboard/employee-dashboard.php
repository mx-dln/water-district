<?php
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
if (!$employeeId) {
    echo '<div class="p-6 bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl flex items-center space-x-3"><i class="fas fa-exclamation-triangle text-xl shrink-0"></i><div><h4 class="font-bold text-sm">Employee Profile Not Found</h4><p class="text-xs mt-0.5">Please contact the CCWD HR administrator to link your account to an active employee record.</p></div></div>';
    return;
}
$emp = getEmployeeData($employeeId);

$today = date('Y-m-d');
$todayAttStmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$todayAttStmt->execute([$employeeId, $today]);
$todayAtt = $todayAttStmt->fetch(PDO::FETCH_ASSOC);

$recentAttStmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 8");
$recentAttStmt->execute([$employeeId]);
$recentAtt = $recentAttStmt->fetchAll(PDO::FETCH_ASSOC);

$pendingLeavesStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
$pendingLeavesStmt->execute([$employeeId]);
$pendingLeaves = (int)$pendingLeavesStmt->fetchColumn();

$monthStatsStmt = $db->prepare("
    SELECT COUNT(*) as total_days,
           SUM(CASE WHEN time_in IS NOT NULL THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN time_in IS NOT NULL AND TIME(time_in) > '08:15:00' THEN 1 ELSE 0 END) as late_days
    FROM attendance
    WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$monthStatsStmt->execute([$employeeId]);
$monthStats = $monthStatsStmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Welcome Hero Banner -->
    <div class="rounded-xl bg-slate-900 text-white p-6 sm:p-7 border border-slate-800 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center space-x-4">
                <div class="w-14 h-14 rounded-xl bg-blue-700 flex items-center justify-center text-white text-xl font-bold font-heading shrink-0 shadow-sm">
                    <?= strtoupper(substr($emp['first_name'] ?? 'U', 0, 1)) ?>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-slate-800 text-blue-300 border border-slate-700 rounded">
                            <?= escapeOutput($emp['department_name'] ?? 'CCWD Employee') ?>
                        </span>
                        <span class="text-xs text-slate-400 font-mono">ID: <?= escapeOutput($emp['employee_no']) ?></span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight font-heading text-white">
                        Welcome back, <?= escapeOutput($emp['first_name']) ?>!
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300 mt-0.5">
                        <?= formatDate($today) ?> &bull; <span class="text-blue-300 font-medium">Cauayan City Water District</span>
                    </p>
                </div>
            </div>
            <div>
                <a href="<?= baseUrl() ?>/index.php?page=leaves-request"
                   class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white rounded-lg text-xs sm:text-sm font-bold shadow-xs transition-colors">
                    <i class="fas fa-file-signature text-blue-200"></i>
                    <span>Apply for CSC Leave</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 4 Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Monthly Present -->
        <div class="app-card card-hover kpi-green p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Monthly Turnout</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fas fa-calendar-check text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-emerald-600 font-heading"><?= $monthStats['present_days'] ?? 0 ?></div>
                <p class="text-xs text-slate-400 mt-1">Days logged this month</p>
            </div>
        </div>

        <!-- Monthly Late -->
        <div class="app-card card-hover kpi-amber p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Monthly Late</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="fas fa-clock text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-amber-600 font-heading"><?= $monthStats['late_days'] ?? 0 ?></div>
                <p class="text-xs text-slate-400 mt-1">Arrivals after 8:15 AM</p>
            </div>
        </div>

        <!-- Pending Leaves -->
        <div class="app-card card-hover kpi-blue p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Pending Leaves</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-blue-600 font-heading"><?= $pendingLeaves ?></div>
                <p class="text-xs text-slate-400 mt-1">Awaiting approval</p>
            </div>
        </div>

        <!-- Status Today -->
        <div class="app-card card-hover <?= ($todayAtt && $todayAtt['time_in']) ? 'kpi-green' : 'kpi-indigo' ?> p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Today's Status</span>
                <div class="w-10 h-10 rounded-xl <?= ($todayAtt && $todayAtt['time_in']) ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400' ?> flex items-center justify-center">
                    <i class="fas <?= ($todayAtt && $todayAtt['time_in']) ? 'fa-check' : 'fa-hourglass-start' ?> text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-2xl font-extrabold <?= ($todayAtt && $todayAtt['time_in']) ? 'text-emerald-600' : 'text-slate-400' ?> font-heading">
                    <?= ($todayAtt && $todayAtt['time_in']) ? 'Timed In' : 'Not Yet In' ?>
                </div>
                <p class="text-xs text-slate-400 mt-1 font-mono">
                    <?= ($todayAtt && $todayAtt['time_in']) ? formatTime($todayAtt['time_in']) : 'No entry today' ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Field Attendance Punch Board -->
    <div class="app-card p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-5 border-b border-slate-100 mb-6">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading flex items-center space-x-2">
                    <span>Field Geofence Attendance</span>
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-100 text-blue-800 uppercase">GPS + Selfie</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Punches must be within authorized CCWD field polygon coordinates.</p>
            </div>
            <div class="text-xs text-slate-500 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200/80 flex items-center space-x-1.5">
                <i class="fas fa-info-circle text-blue-600"></i>
                <span>Office staff can also use the Office Face Kiosk</span>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            $actions = [
                ['type' => 'time-in',   'label' => 'Time In',   'icon' => 'fa-arrow-right-to-bracket', 'color' => 'emerald', 'val' => $todayAtt['time_in'] ?? null],
                ['type' => 'break-out', 'label' => 'Break Out', 'icon' => 'fa-mug-saucer',            'color' => 'amber',   'val' => $todayAtt['break_out'] ?? null],
                ['type' => 'break-in',  'label' => 'Break In',  'icon' => 'fa-utensils',              'color' => 'blue',    'val' => $todayAtt['break_in'] ?? null],
                ['type' => 'time-out',  'label' => 'Time Out',  'icon' => 'fa-arrow-right-from-bracket', 'color' => 'rose',   'val' => $todayAtt['time_out'] ?? null],
            ];
            foreach ($actions as $act):
                $isDone = !empty($act['val']);
            ?>
            <button onclick="startAttendance('<?= $act['type'] ?>')"
                    <?= $isDone ? 'disabled' : '' ?>
                    class="group relative flex flex-col items-center justify-center p-5 rounded-2xl border-2 transition-all duration-200
                    <?= $isDone
                        ? 'border-slate-200 bg-slate-50/80 text-slate-400 cursor-not-allowed opacity-80'
                        : 'border-slate-200 bg-white hover:border-blue-500 hover:bg-blue-50/30 hover:shadow-md cursor-pointer hover:-translate-y-0.5' ?>">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-3 transition-transform group-hover:scale-110
                    <?= $isDone ? 'bg-slate-200/70 text-slate-400' : 'bg-' . $act['color'] . '-50 text-' . $act['color'] . '-600' ?>">
                    <i class="fas <?= $act['icon'] ?> text-lg"></i>
                </div>
                <span class="text-xs sm:text-sm font-bold font-heading text-slate-800 group-hover:text-blue-700">
                    <?= $act['label'] ?>
                </span>
                <?php if ($isDone): ?>
                <span class="mt-2 inline-flex items-center text-[11px] font-bold text-emerald-700 bg-emerald-100/80 px-2 py-0.5 rounded-full font-mono">
                    <i class="fas fa-check text-[9px] mr-1"></i><?= formatTime($act['val']) ?>
                </span>
                <?php else: ?>
                <span class="mt-2 text-[10px] font-semibold text-slate-400 group-hover:text-blue-600">
                    Ready to punch &rarr;
                </span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Attendance Stream & Shortcuts -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Attendance History Stream (2 cols) -->
        <div class="lg:col-span-2 app-card overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="font-bold text-base text-navy-950 font-heading">Recent Attendance Logs</h3>
                    <p class="text-xs text-slate-500">Your latest recorded entries</p>
                </div>
                <a href="<?= baseUrl() ?>/index.php?page=attendance-history" class="text-xs font-bold text-blue-600 hover:text-blue-800">
                    View Complete History &rarr;
                </a>
            </div>
            <div class="divide-y divide-slate-100">
                <?php if (empty($recentAtt)): ?>
                <div class="p-8 text-center text-slate-400">
                    <i class="fas fa-clipboard-list text-3xl mb-2 text-slate-300"></i>
                    <p class="text-xs font-medium">No previous attendance records found.</p>
                </div>
                <?php else: foreach ($recentAtt as $a): ?>
                <div class="px-6 py-3.5 flex items-center justify-between hover:bg-slate-50/60 transition-colors">
                    <div>
                        <p class="text-xs font-bold text-slate-800 font-heading"><?= formatDate($a['date']) ?></p>
                        <p class="text-[11px] text-slate-500 mt-0.5 font-mono">
                            In: <strong class="text-slate-700"><?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?></strong> &bull;
                            Out: <strong class="text-slate-700"><?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></strong>
                        </p>
                    </div>
                    <div>
                        <?= getStatusBadge($a['time_in_status'] ?? 'pending') ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Shortcuts Card (1 col) -->
        <div class="app-card p-6 flex flex-col justify-between">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading mb-4">Quick Employee Actions</h3>
                <div class="space-y-2.5">
                    <a href="<?= baseUrl() ?>/index.php?page=leaves"
                       class="flex items-center space-x-3 p-3 rounded-xl bg-blue-50/70 hover:bg-blue-100/70 border border-blue-200/50 text-slate-700 hover:text-blue-900 transition-all">
                        <div class="w-8 h-8 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0">
                            <i class="fas fa-calendar-alt text-xs"></i>
                        </div>
                        <div class="truncate">
                            <p class="text-xs font-bold font-heading">My Leave Balances</p>
                            <p class="text-[10px] text-slate-500">View VL, SL, SPL summary</p>
                        </div>
                    </a>
                    <a href="<?= baseUrl() ?>/index.php?page=profile"
                       class="flex items-center space-x-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100/80 border border-slate-200/60 text-slate-700 hover:text-navy-950 transition-all">
                        <div class="w-8 h-8 rounded-lg bg-navy-900 text-white flex items-center justify-center shrink-0">
                            <i class="fas fa-id-card text-xs"></i>
                        </div>
                        <div class="truncate">
                            <p class="text-xs font-bold font-heading">Employee Profile</p>
                            <p class="text-[10px] text-slate-500">Personal & employment details</p>
                        </div>
                    </a>
                    <a href="<?= baseUrl() ?>/index.php?page=change-password"
                       class="flex items-center space-x-3 p-3 rounded-xl bg-slate-50 hover:bg-slate-100/80 border border-slate-200/60 text-slate-700 hover:text-navy-950 transition-all">
                        <div class="w-8 h-8 rounded-lg bg-slate-700 text-white flex items-center justify-center shrink-0">
                            <i class="fas fa-key text-xs"></i>
                        </div>
                        <div class="truncate">
                            <p class="text-xs font-bold font-heading">Account Security</p>
                            <p class="text-[10px] text-slate-500">Update system password</p>
                        </div>
                    </a>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100">
                <div class="p-3 rounded-xl bg-slate-100 text-slate-600 text-[11px] leading-relaxed">
                    <i class="fas fa-shield-alt text-blue-600 mr-1"></i>
                    All attendance entries are time-stamped and encrypted under CCWD security protocol.
                </div>
            </div>
        </div>
    </div>
</div>

<?php $additionalScripts = '
<script>
function startAttendance(type) {
    window.location.href = "' . baseUrl() . '/index.php?page=attendance&action=" + encodeURIComponent(type);
}
</script>
'; ?>
