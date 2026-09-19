<?php
require_once __DIR__ . '/../config/app.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(APP_URL . '/index.php?page=leaves'); }
if (!isset($_POST[CSRF_TOKEN_NAME]) || !validateCSRFToken($_POST[CSRF_TOKEN_NAME])) { setFlash('Invalid token', 'danger'); redirect(APP_URL . '/index.php?page=leaves'); }

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if ($action === 'cancel') {
    $employeeId = getEmployeeIdFromUser($_SESSION['user_id']);
    $stmt = $db->prepare("UPDATE leave_requests SET status = 'cancelled' WHERE id = ? AND employee_id = ? AND status = 'pending'");
    $stmt->execute([$id, $employeeId]);
    setFlash('Leave request cancelled', 'success');
} elseif (in_array($action, ['approve', 'reject']) && isAdmin()) {
    $remarks = sanitizeInput($_POST['remarks'] ?? ($action === 'reject' ? 'Rejected by administrator' : ''));
    $status = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $db->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ?");
    $stmt->execute([$status, $_SESSION['user_id'], $remarks, $id]);

    $lr = $db->prepare("SELECT employee_id FROM leave_requests WHERE id = ?");
    $lr->execute([$id]);
    $lrData = $lr->fetch();
    if ($lrData) {
        $emp = getEmployeeData($lrData['employee_id']);
        if ($emp) {
            $notifType = $status === 'approved' ? 'success' : 'danger';
            $notifTitle = 'Leave ' . ucfirst($status);
            $notifMsg = "Your leave request has been $status." . ($remarks ? " Remarks: $remarks" : '');
            createNotification($emp['user_id'], $notifTitle, $notifMsg, $notifType, APP_URL . '/index.php?page=leaves');
        }
    }
    setFlash('Leave request ' . $status, 'success');
}

redirect(APP_URL . '/index.php?page=leaves');
