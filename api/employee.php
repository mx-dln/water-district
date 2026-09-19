<?php
require_once __DIR__ . '/../config/app.php';
requireRole('Administrator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(APP_URL . '/index.php?page=employees'); }

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if ($action === 'delete' && $id && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    // Get user_id
    $stmt = $db->prepare("SELECT user_id FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if ($emp) {
        $db->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$emp['user_id']]);
        logAudit('delete_employee', 'employees', "Deleted employee ID: $id");
        setFlash('Employee deleted successfully', 'success');
    }
}
redirect(APP_URL . '/index.php?page=employees');
