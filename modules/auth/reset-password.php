<?php
$token = $_GET['token'] ?? '';
if (empty($token)) {
    redirect(APP_URL . '/index.php?page=login');
}

$stmt = $db->prepare("SELECT pr.*, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    setFlash('Invalid or expired reset token.', 'danger');
    redirect(APP_URL . '/index.php?page=login');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $reset['user_id']]);
        $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
        $stmt->execute([$reset['id']]);
        setFlash('Password reset successful. You can now login.', 'success');
        redirect(APP_URL . '/index.php?page=login');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>
<body class="bg-gradient-to-br from-blue-900 via-blue-800 to-blue-600 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <img src="<?= APP_URL ?>/assets/images/logo.png" alt="<?= escapeOutput(APP_NAME) ?> Logo" class="w-16 h-16 mx-auto object-contain mb-4">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Reset Password</h2>
            <p class="text-sm text-gray-500 mb-6">Choose a new password for <strong><?= escapeOutput($reset['username']) ?></strong></p>
            <?php if (isset($error)): ?>
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= escapeOutput($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                           placeholder="Min. 8 characters">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="8"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                           placeholder="Confirm new password">
                </div>
                <button type="submit" class="w-full bg-blue-700 text-white py-2.5 rounded-lg font-medium hover:bg-blue-800 transition-colors">
                    <i class="fas fa-save mr-2"></i>Reset Password
                </button>
            </form>
        </div>
    </div>
</body>
</html>
