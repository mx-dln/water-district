<?php
$today = date('Y-m-d');

$totalEmployees = $db->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();

$presentToday = $db->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE date = ? AND time_in IS NOT NULL");
$presentToday->execute([$today]);
$presentToday = $presentToday->fetchColumn();

$onLeave = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE ? BETWEEN date_from AND date_to AND status = 'approved'");
$onLeave->execute([$today]);
$onLeave = $onLeave->fetchColumn();

$absentToday = $totalEmployees - $presentToday - $onLeave;
if ($absentToday < 0) $absentToday = 0;

$lateToday = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND time_in IS NOT NULL AND TIME(time_in) > '08:15:00'");
$lateToday->execute([$today]);
$lateToday = $lateToday->fetchColumn();

$pendingLeaves = $db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();

$monthlyAttendance = $db->query("
    SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as total,
           SUM(CASE WHEN time_in IS NOT NULL THEN 1 ELSE 0 END) as present
    FROM attendance
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
")->fetchAll();

$leaveTrend = $db->query("
    SELECT lt.name, COUNT(lr.id) as total
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY lt.name
")->fetchAll();

$deptAttendance = $db->query("
    SELECT d.name, COUNT(a.id) as total
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = CURDATE()
    GROUP BY d.name
")->fetchAll();

$recentAttendance = $db->query("
    SELECT a.*, e.first_name, e.last_name, e.employee_no, d.name as dept
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE a.date = CURDATE()
    ORDER BY a.time_in DESC
    LIMIT 10
")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Admin Dashboard</h1>
            <p class="text-sm text-gray-500"><?= formatDate($today) ?></p>
        </div>
        <div class="flex space-x-2">
            <a href="<?= APP_URL ?>/index.php?page=attendance" class="px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800"><i class="fas fa-clipboard-check mr-2"></i>View Attendance</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Employees</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?= $totalEmployees ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center"><i class="fas fa-users text-blue-600 text-xl"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Present Today</p>
                    <p class="text-2xl font-bold text-green-600 mt-1"><?= $presentToday ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center"><i class="fas fa-check-circle text-green-600 text-xl"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Absent Today</p>
                    <p class="text-2xl font-bold text-red-600 mt-1"><?= $absentToday ?></p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center"><i class="fas fa-times-circle text-red-600 text-xl"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">On Leave</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1"><?= $onLeave ?></p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center"><i class="fas fa-calendar-alt text-yellow-600 text-xl"></i></div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Late Today</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1"><?= $lateToday ?></p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center"><i class="fas fa-clock text-orange-600 text-xl"></i></div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Monthly Attendance Trend</h3>
            <div class="chart-container" style="position:relative;height:280px;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Leave Trend Analysis</h3>
            <div class="chart-container" style="position:relative;height:280px;">
                <canvas id="leaveChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Department Attendance</h3>
            <div class="chart-container" style="position:relative;height:280px;">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Pending Actions</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-calendar-check text-yellow-600"></i>
                        <span class="text-sm">Pending Leave Requests</span>
                    </div>
                    <span class="text-lg font-bold text-yellow-600"><?= $pendingLeaves ?></span>
                </div>
                <a href="<?= APP_URL ?>/index.php?page=leaves" class="block text-center text-sm text-blue-600 hover:underline mt-2">Review Leave Requests &rarr;</a>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Today's Attendance</h3>
            <a href="<?= APP_URL ?>/index.php?page=attendance" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Employee</th>
                        <th class="text-left px-4 py-3 font-medium">Department</th>
                        <th class="text-left px-4 py-3 font-medium">Time In</th>
                        <th class="text-left px-4 py-3 font-medium">Time Out</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($recentAttendance)): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No attendance records today</td></tr>
                    <?php else: foreach ($recentAttendance as $a): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3"><?= escapeOutput($a['first_name'] . ' ' . $a['last_name']) ?><br><span class="text-xs text-gray-500"><?= escapeOutput($a['employee_no']) ?></span></td>
                        <td class="px-4 py-3"><?= escapeOutput($a['dept'] ?? 'N/A') ?></td>
                        <td class="px-4 py-3"><?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?></td>
                        <td class="px-4 py-3"><?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></td>
                        <td class="px-4 py-3"><?= getStatusBadge($a['time_in_status'] ?? 'pending') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $additionalScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    const attData = ' . json_encode($monthlyAttendance) . ';
    if (document.getElementById("attendanceChart")) {
        new Chart(document.getElementById("attendanceChart"), {
            type: "line",
            data: {
                labels: attData.map(d => d.month),
                datasets: [{
                    label: "Present",
                    data: attData.map(d => d.present),
                    borderColor: "#2563eb",
                    backgroundColor: "rgba(37, 99, 235, 0.1)",
                    fill: true,
                    tension: 0.4
                }, {
                    label: "Total",
                    data: attData.map(d => d.total),
                    borderColor: "#9ca3af",
                    backgroundColor: "rgba(156, 163, 175, 0.1)",
                    fill: true,
                    tension: 0.4,
                    borderDash: [5, 5]
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
        });
    }
    const leaveData = ' . json_encode($leaveTrend) . ';
    if (document.getElementById("leaveChart")) {
        new Chart(document.getElementById("leaveChart"), {
            type: "doughnut",
            data: {
                labels: leaveData.map(d => d.name),
                datasets: [{
                    data: leaveData.map(d => d.total),
                    backgroundColor: ["#2563eb", "#ef4444", "#f59e0b", "#10b981", "#8b5cf6"]
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: "bottom" } } }
        });
    }
    const deptData = ' . json_encode($deptAttendance) . ';
    if (document.getElementById("deptChart")) {
        new Chart(document.getElementById("deptChart"), {
            type: "bar",
            data: {
                labels: deptData.map(d => d.name),
                datasets: [{
                    label: "Attendance",
                    data: deptData.map(d => d.total),
                    backgroundColor: "#2563eb"
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
});
</script>
'; ?>
