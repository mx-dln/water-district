<?php
requireRole('Administrator');
$id = intval($_GET['id'] ?? 0);
$stmt = $db->prepare("
    SELECT e.*, d.name as department_name, u.username, u.email as user_email, u.last_login, u.is_active
    FROM employees e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch();
if (!$emp) { setFlash('Employee not found', 'danger'); redirect(APP_URL . '/index.php?page=employees'); }

$recentAtt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 5");
$recentAtt->execute([$id]);
$recentAtt = $recentAtt->fetchAll();
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Employee Profile</h1>
        <div class="flex space-x-2">
            <a href="<?= APP_URL ?>/index.php?page=employees-edit&id=<?= $id ?>" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm hover:bg-yellow-600"><i class="fas fa-edit mr-2"></i>Edit</a>
            <a href="<?= APP_URL ?>/index.php?page=employees" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <div class="flex items-start space-x-6">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-2xl font-bold text-blue-700"><?= strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)) ?></span>
            </div>
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-500 uppercase">Employee No</p>
                    <p class="font-medium"><?= escapeOutput($emp['employee_no']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Full Name</p>
                    <p class="font-medium"><?= escapeOutput($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Status</p>
                    <p><?= getStatusBadge($emp['employment_status']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Department</p>
                    <p class="font-medium"><?= escapeOutput($emp['department_name'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Position</p>
                    <p class="font-medium"><?= escapeOutput($emp['position'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Contact</p>
                    <p class="font-medium"><?= escapeOutput($emp['contact_no'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Email</p>
                    <p class="font-medium"><?= escapeOutput($emp['user_email'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Username</p>
                    <p class="font-medium"><?= escapeOutput($emp['username']) ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">Last Login</p>
                    <p class="font-medium"><?= $emp['last_login'] ? formatDateTime($emp['last_login']) : 'Never' ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <div class="px-5 py-4 border-b"><h3 class="font-semibold text-gray-800">Recent Attendance</h3></div>
        <div class="divide-y">
            <?php if (empty($recentAtt)): ?>
            <p class="p-4 text-sm text-gray-500 text-center">No attendance records</p>
            <?php else: foreach ($recentAtt as $a): ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium"><?= formatDate($a['date']) ?></p>
                    <p class="text-xs text-gray-500">In: <?= $a['time_in'] ? formatTime($a['time_in']) : '--' ?> | Out: <?= $a['time_out'] ? formatTime($a['time_out']) : '--' ?></p>
                </div>
                <div class="flex items-center space-x-2">
                    <?= getStatusBadge($a['time_in_status'] ?? 'pending') ?>
                    <a href="<?= APP_URL ?>/index.php?page=attendance-evidence&id=<?= $a['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm" title="View Evidence"><i class="fas fa-image"></i></a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
