<?php
requireLogin();
$employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
$isAdminView = isAdmin() && isset($_GET['employee_id']);

if ($isAdminView) {
    $empId = intval($_GET['employee_id']);
} else {
    $empId = $employeeId;
}

$page = max(1, intval($_GET['p'] ?? 1));
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-t');

$where = "WHERE a.employee_id = ? AND a.date BETWEEN ? AND ?";
$params = [$empId, $dateFrom, $dateTo];

$countStmt = $db->prepare("SELECT COUNT(*) FROM attendance a $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = ceil($total / ITEMS_PER_PAGE);
$offset = ($page - 1) * ITEMS_PER_PAGE;

$stmt = $db->prepare("
    SELECT a.*, e.first_name, e.last_name, e.employee_no, d.name as dept
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    $where
    ORDER BY a.date DESC, a.time_in DESC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($params, [ITEMS_PER_PAGE, $offset]));
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$empInfo = null;
if (!isAdmin() || !$isAdminView) {
    $empInfo = getEmployeeData($employeeId);
} elseif ($isAdminView) {
    $empInfo = getEmployeeData($empId);
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=attendance" class="hover:text-blue-600">Attendance</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">History Logs</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Attendance History</h1>
            <?php if ($empInfo): ?>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                Records for <span class="font-bold text-slate-800"><?= escapeOutput($empInfo['first_name'] . ' ' . $empInfo['last_name']) ?></span>
                (<span class="font-mono"><?= escapeOutput($empInfo['employee_no']) ?></span> &bull; <?= escapeOutput($empInfo['department_name'] ?? 'Staff') ?>)
            </p>
            <?php endif; ?>
        </div>
        <div>
            <a href="<?= baseUrl() ?>/index.php?page=attendance"
               class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-xs transition-colors">
                <i class="fas fa-arrow-left text-slate-400"></i>
                <span>Back to Attendance</span>
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="app-card p-5">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <input type="hidden" name="page" value="attendance">
            <input type="hidden" name="subpage" value="history">
            <?php if ($isAdminView): ?>
            <input type="hidden" name="employee_id" value="<?= $empId ?>">
            <?php endif; ?>
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-1.5">Date From</label>
                <input type="date" name="from" value="<?= $dateFrom ?>"
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 font-heading mb-1.5">Date To</label>
                <input type="date" name="to" value="<?= $dateTo ?>"
                       class="w-full px-3.5 py-2 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <div class="flex items-center space-x-2">
                <button type="submit"
                        class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center space-x-2">
                    <i class="fas fa-filter text-xs"></i>
                    <span>Apply Filter</span>
                </button>
                <a href="<?= baseUrl() ?>/index.php?page=attendance&subpage=history<?= $isAdminView ? '&employee_id=' . $empId : '' ?>"
                   class="px-4 py-2 border border-slate-200 text-slate-600 hover:bg-slate-50 rounded-xl text-xs sm:text-sm font-semibold transition-colors">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table Card -->
    <div class="app-card overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-base text-navy-950 font-heading">Logged Records</h3>
            <span class="text-xs font-semibold text-slate-500"><?= $total ?> total entries</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs sm:text-sm text-left">
                <thead class="bg-slate-100/75 text-slate-600 text-[11px] font-bold uppercase tracking-wider font-heading border-b border-slate-200/80">
                    <tr>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-4 py-3">Time In</th>
                        <th class="px-4 py-3">Break Out</th>
                        <th class="px-4 py-3">Break In</th>
                        <th class="px-4 py-3">Time Out</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-center">Selfie Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-calendar-xmark text-3xl mb-2 text-slate-300"></i>
                            <p class="text-sm font-medium">No attendance records found within the selected date range.</p>
                        </td>
                    </tr>
                    <?php else: foreach ($records as $r): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-6 py-3.5 font-bold font-heading text-slate-800">
                            <?= formatDate($r['date'], 'M j, Y') ?>
                        </td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-800">
                            <?= $r['time_in'] ? formatTime($r['time_in']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-600">
                            <?= $r['break_out'] ? formatTime($r['break_out']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5 font-mono text-slate-600">
                            <?= $r['break_in'] ? formatTime($r['break_in']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5 font-bold font-mono text-slate-800">
                            <?= $r['time_out'] ? formatTime($r['time_out']) : '--' ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <?= getStatusBadge($r['time_in_status'] ?? 'pending') ?>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <a href="<?= baseUrl() ?>/index.php?page=attendance-evidence&id=<?= $r['id'] ?>"
                               class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                                <i class="fas fa-camera text-[11px]"></i>
                                <span>Evidence</span>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <?= paginate($page, $totalPages) ?>
        </div>
        <?php endif; ?>
    </div>
</div>
