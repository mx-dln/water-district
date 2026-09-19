<?php
require_once __DIR__ . '/../config/app.php';
requireRole('Administrator');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(APP_URL . '/index.php?page=departments'); }

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if ($action === 'delete' && $id && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $db->prepare("UPDATE employees SET department_id = NULL WHERE department_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]);
    logAudit('delete_department', 'departments', "Deleted department ID: $id");
    setFlash('Department deleted', 'success');
}
redirect(APP_URL . '/index.php?page=departments');
