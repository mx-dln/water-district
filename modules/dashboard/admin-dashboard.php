<?php
$today = date('Y-m-d');

$totalEmployees = (int)$db->query("SELECT COUNT(*) FROM employees WHERE is_active = 1")->fetchColumn();

$presentTodayStmt = $db->prepare("SELECT COUNT(DISTINCT employee_id) FROM attendance WHERE date = ? AND time_in IS NOT NULL");
$presentTodayStmt->execute([$today]);
$presentToday = (int)$presentTodayStmt->fetchColumn();

$onLeaveStmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE ? BETWEEN date_from AND date_to AND status = 'approved'");
$onLeaveStmt->execute([$today]);
$onLeave = (int)$onLeaveStmt->fetchColumn();

$absentToday = $totalEmployees - $presentToday - $onLeave;
if ($absentToday < 0) $absentToday = 0;

$lateTodayStmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date = ? AND time_in IS NOT NULL AND TIME(time_in) > '08:15:00'");
$lateTodayStmt->execute([$today]);
$lateToday = (int)$lateTodayStmt->fetchColumn();

$pendingLeaves = (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();

$monthlyAttendance = $db->query("
    SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as total,
           SUM(CASE WHEN time_in IS NOT NULL THEN 1 ELSE 0 END) as present
    FROM attendance
    WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
")->fetchAll(PDO::FETCH_ASSOC);

$leaveTrend = $db->query("
    SELECT lt.name, COUNT(lr.id) as total
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    WHERE lr.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY lt.name
")->fetchAll(PDO::FETCH_ASSOC);

$deptAttendance = $db->query("
    SELECT d.name, COUNT(a.id) as total
    FROM departments d
    LEFT JOIN employees e ON e.department_id = d.id
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = CURDATE()
    GROUP BY d.name
")->fetchAll(PDO::FETCH_ASSOC);

$recentAttendance = $db->query("
    SELECT a.*, e.first_name, e.last_name, e.employee_no, d.name as dept
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE a.date = CURDATE()
    ORDER BY a.time_in DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Attendance rate percentage
$attendanceRate = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100) : 0;
?>

<div class="space-y-6">
    <!-- Top Welcome Banner & Actions -->
    <div class="rounded-xl bg-slate-900 text-white p-6 sm:p-7 border border-slate-800 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="inline-flex items-center space-x-2 px-2.5 py-1 rounded bg-slate-800 text-blue-300 text-xs font-semibold mb-2.5 border border-slate-700">
                    <i class="fas fa-water text-blue-400"></i>
                    <span>Cauayan City Water District &bull; Workforce Control</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight font-heading">Executive Dashboard</h1>
                <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-xl">
                    Real-time monitoring of staff attendance, field geofence compliance, and CSC leave records for <span class="font-semibold text-white"><?= formatDate($today) ?></span>.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="<?= baseUrl() ?>/index.php?page=kiosk" target="_blank"
                   class="inline-flex items-center space-x-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs sm:text-sm font-bold shadow-xs transition-colors">
                    <span class="w-2 h-2 rounded-full bg-emerald-300"></span>
                    <span>Office Face Kiosk</span>
                </a>
                <a href="<?= baseUrl() ?>/index.php?page=attendance"
                   class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white rounded-lg text-xs sm:text-sm font-bold shadow-xs transition-colors">
                    <i class="fas fa-clipboard-check text-blue-300"></i>
                    <span>View Attendance</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 5 KPI Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Card 1: Total Employees -->
        <div class="app-card card-hover kpi-blue p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Total Staff</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shadow-xs">
                    <i class="fas fa-users text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-navy-950 font-heading tracking-tight"><?= $totalEmployees ?></div>
                <div class="flex items-center space-x-1.5 text-xs text-slate-500 mt-1 font-medium">
                    <span class="text-blue-600 font-semibold"><i class="fas fa-check-circle text-[11px] mr-1"></i>Active</span>
                    <span>personnel</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Present Today -->
        <div class="app-card card-hover kpi-green p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Present Today</span>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shadow-xs">
                    <i class="fas fa-user-check text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-emerald-600 font-heading tracking-tight"><?= $presentToday ?></div>
                <div class="flex items-center space-x-1 text-xs text-slate-500 mt-1 font-medium">
                    <span class="font-semibold text-emerald-700"><?= $attendanceRate ?>%</span>
                    <span>turnout today</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Absent Today -->
        <div class="app-card card-hover kpi-red p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Absent Today</span>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shadow-xs">
                    <i class="fas fa-user-xmark text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-rose-600 font-heading tracking-tight"><?= $absentToday ?></div>
                <div class="flex items-center space-x-1 text-xs text-slate-500 mt-1 font-medium">
                    <span>Unaccounted absence</span>
                </div>
            </div>
        </div>

        <!-- Card 4: On Leave -->
        <div class="app-card card-hover kpi-amber p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">On CSC Leave</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shadow-xs">
                    <i class="fas fa-calendar-day text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-amber-600 font-heading tracking-tight"><?= $onLeave ?></div>
                <div class="flex items-center space-x-1 text-xs text-slate-500 mt-1 font-medium">
                    <span>Approved leaves</span>
                </div>
            </div>
        </div>

        <!-- Card 5: Late Today -->
        <div class="app-card card-hover kpi-indigo p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 font-heading">Late Arrivals</span>
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shadow-xs">
                    <i class="fas fa-clock text-sm"></i>
                </div>
            </div>
            <div class="mt-4">
                <div class="text-3xl font-extrabold text-indigo-600 font-heading tracking-tight"><?= $lateToday ?></div>
                <div class="flex items-center space-x-1 text-xs text-slate-500 mt-1 font-medium">
                    <span>After 8:15 AM PST</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts & Action Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Monthly Attendance Trend (2 cols) -->
        <div class="lg:col-span-2 app-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-base text-navy-950 font-heading">Monthly Attendance Performance</h3>
                    <p class="text-xs text-slate-500">12-Month attendance turnout vs total recorded headcount</p>
                </div>
                <span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 text-blue-700 border border-blue-200/60">
                    <i class="fas fa-chart-line mr-1"></i>Trend
                </span>
            </div>
            <div class="chart-container" style="position:relative;height:300px;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Pending Leave Action Card (1 col) -->
        <div class="app-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-base text-navy-950 font-heading">Pending Approvals</h3>
                    <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $pendingLeaves > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $pendingLeaves ?> Pending
                    </span>
                </div>
                <div class="p-4 rounded-xl bg-amber-50/80 border border-amber-200/80 space-y-2">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold shadow-xs">
                            <i class="fas fa-file-signature text-base"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-amber-950 font-heading">CSC Form 6 Leave Requests</p>
                            <p class="text-xs text-amber-700">Awaiting management review</p>
                        </div>
                    </div>
                    <p class="text-xs text-amber-800 leading-relaxed pt-1">
                        There are currently <strong class="text-amber-950 font-bold"><?= $pendingLeaves ?></strong> pending leave applications requiring approval or disapproval.
                    </p>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 space-y-2">
                <a href="<?= baseUrl() ?>/index.php?page=leaves"
                   class="w-full inline-flex items-center justify-center space-x-2 px-4 py-2.5 rounded-xl bg-navy-950 hover:bg-navy-900 text-white text-xs font-bold transition-all shadow-xs">
                    <span>Review Leave Requests</span>
                    <i class="fas fa-arrow-right text-[11px]"></i>
                </a>
                <a href="<?= baseUrl() ?>/index.php?page=polygon"
                   class="w-full inline-flex items-center justify-center space-x-2 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200/80 text-slate-700 text-xs font-semibold transition-colors">
                    <i class="fas fa-draw-polygon text-slate-500 mr-1"></i>
                    <span>Configure Geofence Boundary</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Secondary Charts: Leave Trend & Dept Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Leave Types Breakdown -->
        <div class="app-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-base text-navy-950 font-heading">Leave Types Distribution</h3>
                    <p class="text-xs text-slate-500">Distribution by CSC leave classification (Past 12 Months)</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
                    <i class="fas fa-chart-pie text-xs"></i>
                </div>
            </div>
            <div class="chart-container" style="position:relative;height:260px;">
                <canvas id="leaveChart"></canvas>
            </div>
        </div>

        <!-- Department Attendance Breakdown -->
        <div class="app-card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="font-bold text-base text-navy-950 font-heading">Department Attendance</h3>
                    <p class="text-xs text-slate-500">Today's recorded entries per water district department</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
                    <i class="fas fa-building text-xs"></i>
                </div>
            </div>
            <div class="chart-container" style="position:relative;height:260px;">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Today's Attendance Stream Table -->
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h3 class="font-bold text-base text-navy-950 font-heading">Today's Attendance Stream</h3>
                <p class="text-xs text-slate-500">Latest 10 attendance timestamps recorded today</p>
            </div>
            <a href="<?= baseUrl() ?>/index.php?page=attendance" class="inline-flex items-center space-x-1 text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors">
                <span>View Full Log</span>
                <i class="fas fa-chevron-right text-[10px]"></i>
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Employee</th>
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Time In</th>
                        <th class="px-4 py-3">Time Out</th>
                        <th class="px-4 py-3">Method & Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentAttendance)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-clipboard-user text-3xl mb-2 text-slate-300"></i>
                            <p class="text-sm font-medium">No attendance entries recorded today yet.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($recentAttendance as $a):
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
                                    <p class="text-[11px] text-slate-400 font-mono"><?= escapeOutput($a['employee_no']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 font-medium">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 text-slate-700">
                                <?= escapeOutput($a['dept'] ?? 'Unassigned') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="font-bold text-slate-800 font-mono"><?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?></span>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="font-bold text-slate-800 font-mono"><?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center space-x-2">
                                <?= getStatusBadge($a['time_in_status'] ?? 'pending') ?>
                                <?php if (!empty($a['attendance_type'])): ?>
                                <span class="text-[10px] uppercase font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded">
                                    <?= escapeOutput($a['attendance_type']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
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
    if (document.getElementById("attendanceChart") && attData.length > 0) {
        new Chart(document.getElementById("attendanceChart"), {
            type: "line",
            data: {
                labels: attData.map(d => d.month),
                datasets: [{
                    label: "Present Turnout",
                    data: attData.map(d => d.present),
                    borderColor: "#1d4ed8",
                    backgroundColor: "rgba(29, 78, 216, 0.08)",
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: "#1d4ed8",
                    pointRadius: 3
                }, {
                    label: "Total Logs",
                    data: attData.map(d => d.total),
                    borderColor: "#94a3b8",
                    backgroundColor: "transparent",
                    borderWidth: 1.5,
                    borderDash: [4, 4],
                    pointRadius: 0,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "top",
                        labels: { font: { family: "\'Plus Jakarta Sans\', sans-serif", size: 11 }, boxWidth: 12 }
                    }
                },
                scales: {
                    y: {
                        grid: { color: "rgba(226, 232, 240, 0.6)" },
                        ticks: { font: { family: "\'Plus Jakarta Sans\', sans-serif", size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "\'Plus Jakarta Sans\', sans-serif", size: 11 } }
                    }
                }
            }
        });
    }

    const leaveData = ' . json_encode($leaveTrend) . ';
    if (document.getElementById("leaveChart") && leaveData.length > 0) {
        new Chart(document.getElementById("leaveChart"), {
            type: "doughnut",
            data: {
                labels: leaveData.map(d => d.name),
                datasets: [{
                    data: leaveData.map(d => d.total),
                    backgroundColor: ["#1d4ed8", "#0284c7", "#059669", "#d97706", "#7c3aed", "#e11d48"],
                    borderWidth: 2,
                    borderColor: "#ffffff"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "70%",
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: { font: { family: "\'Plus Jakarta Sans\', sans-serif", size: 11 }, boxWidth: 10 }
                    }
                }
            }
        });
    }

    const deptData = ' . json_encode($deptAttendance) . ';
    if (document.getElementById("deptChart") && deptData.length > 0) {
        new Chart(document.getElementById("deptChart"), {
            type: "bar",
            data: {
                labels: deptData.map(d => d.name),
                datasets: [{
                    label: "Attendance Today",
                    data: deptData.map(d => d.total),
                    backgroundColor: "#2563eb",
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: "rgba(226, 232, 240, 0.6)" },
                        ticks: { stepSize: 1, font: { family: "\'Plus Jakarta Sans\', sans-serif", size: 11 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: "\'Plus Jakarta Sans\', sans-serif", size: 10 } }
                    }
                }
            }
        });
    }
});
</script>
'; ?>
