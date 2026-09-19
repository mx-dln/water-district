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
$total = $countStmt->fetchColumn();
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
$records = $stmt->fetchAll();

$empInfo = null;
if (!isAdmin() || !$isAdminView) {
    $empInfo = getEmployeeData($employeeId);
}
?>
<div class="space-y-4 sm:space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Attendance History</h1>
            <?php if ($empInfo): ?>
            <p class="text-sm text-gray-500"><?= escapeOutput($empInfo['first_name'] . ' ' . $empInfo['last_name']) ?> | <?= escapeOutput($empInfo['employee_no']) ?></p>
            <?php endif; ?>
        </div>
        <a href="<?= APP_URL ?>/index.php?page=attendance" class="text-sm text-gray-600 hover:text-gray-800 self-start sm:self-auto"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-4">
        <form method="GET" class="flex flex-wrap gap-2 sm:gap-3 items-end">
            <input type="hidden" name="page" value="attendance">
            <input type="hidden" name="subpage" value="history">
            <?php if ($isAdminView): ?>
            <input type="hidden" name="employee_id" value="<?= $empId ?>">
            <?php endif; ?>
            <div class="flex-1 min-w-[120px]">
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="from" value="<?= $dateFrom ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div class="flex-1 min-w-[120px]">
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="to" value="<?= $dateTo ?>" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-blue-700 text-white rounded-lg text-sm hover:bg-blue-800"><i class="fas fa-search mr-2"></i>Filter</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium">Date</th>
                        <th class="text-left px-4 py-3 font-medium">Time In</th>
                        <th class="text-left px-4 py-3 font-medium">Break Out</th>
                        <th class="text-left px-4 py-3 font-medium">Break In</th>
                        <th class="text-left px-4 py-3 font-medium">Time Out</th>
                        <th class="text-left px-4 py-3 font-medium">Status</th>
                        <th class="text-center px-4 py-3 font-medium">Evidence</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($records)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No records found</td></tr>
                    <?php else: foreach ($records as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= formatDate($r['date'], 'M j, Y') ?></td>
                        <td class="px-4 py-3"><?= $r['time_in'] ? formatTime($r['time_in']) : '--' ?></td>
                        <td class="px-4 py-3"><?= $r['break_out'] ? formatTime($r['break_out']) : '--' ?></td>
                        <td class="px-4 py-3"><?= $r['break_in'] ? formatTime($r['break_in']) : '--' ?></td>
                        <td class="px-4 py-3"><?= $r['time_out'] ? formatTime($r['time_out']) : '--' ?></td>
                        <td class="px-4 py-3"><?= getStatusBadge($r['time_in_status'] ?? 'pending') ?></td>
                        <td class="px-4 py-3 text-center">
                            <a href="<?= APP_URL ?>/index.php?page=attendance-evidence&id=<?= $r['id'] ?>" class="text-blue-600 hover:text-blue-800"><i class="fas fa-image"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="sm:hidden divide-y">
            <?php if (empty($records)): ?>
            <p class="p-4 text-sm text-gray-500 text-center">No records found</p>
            <?php else: foreach ($records as $r): ?>
            <div class="p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-sm"><?= formatDate($r['date'], 'M j, Y') ?></span>
                    <?= getStatusBadge($r['time_in_status'] ?? 'pending') ?>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                    <div><span class="text-gray-400">In:</span> <?= $r['time_in'] ? formatTime($r['time_in']) : '--' ?></div>
                    <div><span class="text-gray-400">Out:</span> <?= $r['time_out'] ? formatTime($r['time_out']) : '--' ?></div>
                    <div><span class="text-gray-400">Break Out:</span> <?= $r['break_out'] ? formatTime($r['break_out']) : '--' ?></div>
                    <div><span class="text-gray-400">Break In:</span> <?= $r['break_in'] ? formatTime($r['break_in']) : '--' ?></div>
                </div>
                <div class="text-right">
                    <a href="<?= APP_URL ?>/index.php?page=attendance-evidence&id=<?= $r['id'] ?>" class="text-blue-600 text-xs hover:underline"><i class="fas fa-image mr-1"></i>View Evidence</a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t"><?= paginate($page, $totalPages) ?></div>
        <?php endif; ?>
    </div>
</div>
