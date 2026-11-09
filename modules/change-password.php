<?php
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($currentPwd, $user['password'])) {
        setFlash('Current password is incorrect', 'danger');
    } elseif (strlen($newPwd) < 8) {
        setFlash('New password must be at least 8 characters in length', 'danger');
    } elseif ($newPwd !== $confirmPwd) {
        setFlash('New password and confirmation do not match', 'danger');
    } else {
        $hash = password_hash($newPwd, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        logAudit('change_password', 'auth', 'User changed system password');
        setFlash('Password updated successfully. Please use your new credentials on next sign in.', 'success');
        redirect(baseUrl() . '/index.php?page=dashboard');
    }
}
?>

<div class="max-w-md mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center space-x-2 text-xs text-slate-500 font-medium mb-1">
                <a href="<?= baseUrl() ?>/index.php?page=profile" class="hover:text-blue-600">Profile</a>
                <span>&bull;</span>
                <span class="text-slate-700 font-semibold">Security</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-heading tracking-tight">Change Password</h1>
        </div>
        <a href="<?= baseUrl() ?>/index.php?page=profile"
           class="inline-flex items-center space-x-2 px-3.5 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-bold shadow-xs transition-colors">
            <i class="fas fa-arrow-left text-slate-400"></i>
            <span>Back</span>
        </a>
    </div>

    <div class="app-card p-6 sm:p-8">
        <div class="mb-6 flex items-center space-x-3 p-3.5 rounded-xl bg-blue-50/70 border border-blue-200/60 text-xs text-blue-900">
            <i class="fas fa-shield-halved text-blue-600 text-base shrink-0"></i>
            <span>Use at least 8 alphanumeric characters with mixed capitalization for government system compliance.</span>
        </div>

        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Current Password <span class="text-rose-500">*</span></label>
                <input type="password" name="current_password" required placeholder="Enter current password"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">New Password <span class="text-rose-500">*</span></label>
                <input type="password" name="new_password" required minlength="8" placeholder="Minimum 8 characters"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 font-heading mb-1.5">Confirm New Password <span class="text-rose-500">*</span></label>
                <input type="password" name="confirm_password" required minlength="8" placeholder="Re-type new password"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50/50 text-xs sm:text-sm focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 outline-none transition-all">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center space-x-3">
                <button type="submit"
                        class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition-colors flex items-center justify-center space-x-2">
                    <i class="fas fa-lock text-xs"></i>
                    <span>Update Password</span>
                </button>
            </div>
        </form>
    </div>
</div>
