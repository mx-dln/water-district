<?php
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
if (!$employeeId) {
    echo '<div class="p-4 bg-red-50 text-red-700 rounded-lg"><i class="fas fa-exclamation-triangle mr-2"></i>Employee profile not found. Contact administrator.</div>';
    return;
}
$emp = getEmployeeData($employeeId);

$today = date('Y-m-d');
$todayAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$todayAtt->execute([$employeeId, $today]);
$todayAtt = $todayAtt->fetch();

$recentAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 10");
$recentAtt->execute([$employeeId]);
$recentAtt = $recentAtt->fetchAll();

$pendingLeaves = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
$pendingLeaves->execute([$employeeId]);
$pendingLeaves = $pendingLeaves->fetchColumn();

$monthStats = $db->prepare("
    SELECT COUNT(*) as total_days,
           SUM(CASE WHEN time_in IS NOT NULL THEN 1 ELSE 0 END) as present_days,
           SUM(CASE WHEN time_in IS NOT NULL AND TIME(time_in) > '08:15:00' THEN 1 ELSE 0 END) as late_days
    FROM attendance
    WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
");
$monthStats->execute([$employeeId]);
$monthStats = $monthStats->fetch();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Welcome back, <?= escapeOutput($emp['first_name']) ?>!</h1>
            <p class="text-sm text-gray-500"><?= formatDate($today) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs sm:text-sm text-gray-500"><?= escapeOutput($emp['employee_no']) ?></span>
            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full whitespace-nowrap"><?= escapeOutput($emp['department_name'] ?? 'N/A') ?></span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-sm text-gray-500">Monthly Present</p>
            <p class="text-2xl font-bold text-green-600 mt-1"><?= $monthStats['present_days'] ?? 0 ?></p>
            <p class="text-xs text-gray-400">out of <?= $monthStats['total_days'] ?? 0 ?> days</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-sm text-gray-500">Monthly Late</p>
            <p class="text-2xl font-bold text-orange-600 mt-1"><?= $monthStats['late_days'] ?? 0 ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-sm text-gray-500">Pending Leaves</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1"><?= $pendingLeaves ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <p class="text-sm text-gray-500">Status Today</p>
            <p class="text-xl font-bold mt-1 <?= $todayAtt && $todayAtt['time_in'] ? 'text-green-600' : 'text-gray-400' ?>">
                <?= $todayAtt && $todayAtt['time_in'] ? 'Timed In' : 'Not Yet' ?>
            </p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Quick Attendance</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            $actions = [
                ['type' => 'time-in', 'label' => 'Time In', 'icon' => 'fa-sign-in-alt', 'color' => 'green', 'done' => $todayAtt && $todayAtt['time_in']],
                ['type' => 'break-out', 'label' => 'Break Out', 'icon' => 'fa-coffee', 'color' => 'yellow', 'done' => $todayAtt && $todayAtt['break_out']],
                ['type' => 'break-in', 'label' => 'Break In', 'icon' => 'fa-mug-hot', 'color' => 'orange', 'done' => $todayAtt && $todayAtt['break_in']],
                ['type' => 'time-out', 'label' => 'Time Out', 'icon' => 'fa-sign-out-alt', 'color' => 'red', 'done' => $todayAtt && $todayAtt['time_out']],
            ];
            foreach ($actions as $act):
                $disabled = $act['done'] ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-' . $act['color'] . '-50';
            ?>
            <button onclick="startAttendance('<?= $act['type'] ?>')" class="flex flex-col items-center justify-center p-4 sm:p-6 border-2 border-dashed rounded-xl transition-colors <?= $disabled ?>">
                <i class="fas <?= $act['icon'] ?> text-2xl sm:text-3xl text-<?= $act['color'] ?>-600 mb-1 sm:mb-2"></i>
                <span class="text-xs sm:text-sm font-medium text-gray-700"><?= $act['label'] ?></span>
                <?php if ($act['done']): ?><span class="text-xs text-green-600 mt-1">Done</span><?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Recent Attendance</h3>
                <a href="<?= APP_URL ?>/index.php?page=attendance-history" class="text-sm text-blue-600 hover:underline">View All</a>
            </div>
            <div class="divide-y">
                <?php if (empty($recentAtt)): ?>
                <p class="p-4 text-sm text-gray-500 text-center">No attendance records yet</p>
                <?php else: foreach ($recentAtt as $a): ?>
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium"><?= formatDate($a['date']) ?></p>
                        <p class="text-xs text-gray-500">
                            In: <?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?> |
                            Out: <?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?>
                        </p>
                    </div>
                    <?= getStatusBadge($a['time_in_status'] ?? 'pending') ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border">
            <div class="px-5 py-4 border-b">
                <h3 class="font-semibold text-gray-800">Quick Links</h3>
            </div>
            <div class="p-5 space-y-3">
                <a href="<?= APP_URL ?>/index.php?page=attendance" class="flex items-center space-x-3 p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                    <i class="fas fa-camera text-blue-600"></i>
                    <span class="text-sm font-medium">Take Attendance</span>
                </a>
                <a href="<?= APP_URL ?>/index.php?page=leaves-request" class="flex items-center space-x-3 p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                    <i class="fas fa-paper-plane text-green-600"></i>
                    <span class="text-sm font-medium">Request Leave</span>
                </a>
                <a href="<?= APP_URL ?>/index.php?page=profile" class="flex items-center space-x-3 p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                    <i class="fas fa-user-edit text-purple-600"></i>
                    <span class="text-sm font-medium">Edit Profile</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php $additionalScripts = '
<script>
function startAttendance(type) {
    window.location.href = "' . APP_URL . '/index.php?page=attendance&action=" + type;
}
</script>
'; ?>
