<?php
requireRole('Administrator');

$status = $_GET['status'] ?? 'pending';
$stmt = $db->prepare("
    SELECT lr.*, lt.name as leave_type_name, e.first_name, e.last_name, e.employee_no, d.name as dept
    FROM leave_requests lr
    JOIN leave_types lt ON lr.leave_type_id = lt.id
    JOIN employees e ON lr.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE lr.status = ?
    ORDER BY lr.created_at DESC
");
$stmt->execute([$status]);
$leaves = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    if ($action === 'approve') {
        $stmt = $db->prepare("UPDATE leave_requests SET status = 'approved', approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $remarks, $id]);
        $lr = $db->prepare("SELECT employee_id FROM leave_requests WHERE id = ?");
        $lr->execute([$id]);
        $lrData = $lr->fetch();
        if ($lrData) {
            $emp = getEmployeeData($lrData['employee_id']);
            if ($emp) {
                createNotification($emp['user_id'], 'Leave Approved', 'Your leave request has been approved', 'success', APP_URL . '/index.php?page=leaves');
            }
        }
        setFlash('Leave request approved', 'success');
    } elseif ($action === 'reject') {
        $stmt = $db->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $remarks, $id]);
        $lr = $db->prepare("SELECT employee_id FROM leave_requests WHERE id = ?");
        $lr->execute([$id]);
        $lrData = $lr->fetch();
        if ($lrData) {
            $emp = getEmployeeData($lrData['employee_id']);
            if ($emp) {
                createNotification($emp['user_id'], 'Leave Rejected', 'Your leave request has been rejected. Reason: ' . ($remarks ?: 'Not specified'), 'danger', APP_URL . '/index.php?page=leaves');
            }
        }
        setFlash('Leave request rejected', 'success');
    }
    logAudit("leave_{$action}", 'leaves', "Leave request #$id $action");
    redirect(APP_URL . '/index.php?page=leaves&action=manage');
}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">Manage Leave Requests</h1>
        <a href="<?= APP_URL ?>/index.php?page=leaves" class="text-sm text-gray-600 hover:text-gray-800"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>

    <div class="flex space-x-2">
        <a href="?page=leaves&action=manage&status=pending" class="px-4 py-2 rounded-lg text-sm <?= $status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Pending</a>
        <a href="?page=leaves&action=manage&status=approved" class="px-4 py-2 rounded-lg text-sm <?= $status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Approved</a>
        <a href="?page=leaves&action=manage&status=rejected" class="px-4 py-2 rounded-lg text-sm <?= $status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">Rejected</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border">
        <?php foreach ($leaves as $l): ?>
        <div class="p-4 border-b last:border-0">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold"><?= escapeOutput($l['first_name'] . ' ' . $l['last_name']) ?></h3>
                    <p class="text-xs text-gray-500"><?= escapeOutput($l['employee_no']) ?> | <?= escapeOutput($l['dept'] ?? 'N/A') ?></p>
                </div>
                <span class="text-xs text-gray-500"><?= timeAgo($l['created_at']) ?></span>
            </div>
            <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                <div><span class="text-gray-500">Type:</span> <?= escapeOutput($l['leave_type_name']) ?></div>
                <div><span class="text-gray-500">From:</span> <?= formatDate($l['date_from']) ?></div>
                <div><span class="text-gray-500">To:</span> <?= formatDate($l['date_to']) ?></div>
                <div><span class="text-gray-500">Days:</span> <?= $l['days'] ?></div>
            </div>
            <div class="mt-2 text-sm"><span class="text-gray-500">Reason:</span> <?= escapeOutput($l['reason']) ?></div>
            <div class="mt-1 flex gap-3 text-sm">
                <?php if ($l['attachment']): ?>
                <a href="<?= APP_URL . '/' . $l['attachment'] ?>" target="_blank" class="text-blue-600 hover:underline"><i class="fas fa-paperclip mr-1"></i>View Attachment</a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/index.php?page=leaves-cs-form-6&id=<?= $l['id'] ?>" target="_blank" class="text-gray-600 hover:text-gray-800 hover:underline"><i class="fas fa-file-alt mr-1"></i>CS Form 6</a>
            </div>
            <?php if ($l['status'] === 'pending'): ?>
            <div class="mt-3 flex space-x-2">
                <form method="POST" class="inline" onsubmit="return confirm('Approve this leave?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                    <input type="text" name="remarks" placeholder="Remarks (optional)" class="px-2 py-1 border rounded text-xs">
                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded text-xs hover:bg-green-700"><i class="fas fa-check mr-1"></i>Approve</button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirm('Reject this leave?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= $l['id'] ?>">
                    <input type="text" name="remarks" placeholder="Reason (optional)" class="px-2 py-1 border rounded text-xs">
                    <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded text-xs hover:bg-red-700"><i class="fas fa-times mr-1"></i>Reject</button>
                </form>
            </div>
            <?php elseif ($l['remarks']): ?>
            <div class="mt-2 p-2 bg-gray-50 rounded text-sm">
                <span class="text-gray-500">Remarks:</span> <?= escapeOutput($l['remarks']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($leaves)): ?>
        <p class="p-8 text-center text-gray-500">No <?= $status ?> leave requests</p>
        <?php endif; ?>
    </div>
</div>
