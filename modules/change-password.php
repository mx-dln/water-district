<?php
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!password_verify($currentPwd, $user['password'])) {
        setFlash('Current password is incorrect', 'danger');
    } elseif (strlen($newPwd) < 8) {
        setFlash('New password must be at least 8 characters', 'danger');
    } elseif ($newPwd !== $confirmPwd) {
        setFlash('New passwords do not match', 'danger');
    } else {
        $hash = password_hash($newPwd, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        logAudit('change_password', 'auth', 'User changed password');
        setFlash('Password changed successfully', 'success');
        redirect(APP_URL . '/index.php?page=dashboard');
    }
}
?>
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Change Password</h1>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                <input type="password" name="current_password" required class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                <input type="password" name="new_password" required minlength="8" class="w-full px-3 py-2 border rounded-lg text-sm">
                <p class="text-xs text-gray-400 mt-1">Min. 8 characters</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                <input type="password" name="confirm_password" required minlength="8" class="w-full px-3 py-2 border rounded-lg text-sm">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-blue-700 text-white rounded-lg text-sm font-medium hover:bg-blue-800"><i class="fas fa-save mr-2"></i>Change Password</button>
        </form>
    </div>
</div>
