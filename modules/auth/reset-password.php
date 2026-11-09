<?php
if (isLoggedIn()) {
    redirect(baseUrl() . '/index.php?page=dashboard');
}

$token = sanitizeInput($_GET['token'] ?? '');
$validToken = false;
$userId = null;
$error = '';
$success = '';

if ($token) {
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $validToken = true;
        $userId = $user['id'];
    } else {
        $error = 'Password reset link has expired or is invalid. Please request a new recovery link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken && validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($password) || strlen($password) < 8) {
        $error = 'Password must be at least 8 characters in length.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please re-enter.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$hash, $userId]);
        logAudit('reset_password', 'auth', 'Password reset completed');
        setFlash('Password reset successfully! You can now sign in with your new credentials.', 'success');
        redirect(baseUrl() . '/index.php?page=login');
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password &bull; GeoSnap &bull; Cauayan City Water District</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen h-full flex flex-col justify-between bg-slate-900 text-slate-100 antialiased">

    <!-- Top Water District Brand Header -->
    <header class="w-full px-6 py-4 flex items-center justify-between border-b border-slate-800 bg-slate-950">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-lg bg-white p-1 border border-slate-700 flex items-center justify-center">
                <img src="<?= baseUrl('assets/images/logo.png') ?>" alt="Cauayan City Water District Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <span class="text-sm font-bold tracking-wide text-white">GeoSnap</span>
                <p class="text-[11px] text-slate-400 font-medium">Cauayan City Water District</p>
            </div>
        </div>
        <a href="<?= baseUrl() ?>/index.php?page=login" class="text-xs text-blue-400 hover:text-white transition-colors">
            &larr; Return to Sign In
        </a>
    </header>

    <main class="flex-1 flex items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 p-8 text-slate-800">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-blue-50 border border-blue-200 mb-3 text-blue-700 text-2xl">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900 tracking-tight">Create New Password</h2>
                    <p class="text-xs text-slate-500 mt-1">Set a secure password of at least 8 characters</p>
                </div>

                <?php if ($error): ?>
                <div class="mb-5 p-3.5 bg-rose-50 border border-rose-200 text-rose-800 rounded-lg text-xs leading-relaxed">
                    <i class="fas fa-circle-exclamation mr-1.5 text-rose-600"></i><?= escapeOutput($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($validToken): ?>
                <form method="POST" class="space-y-4">
                    <?= csrfField() ?>
                    <div>
                        <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock text-xs"></i>
                            </div>
                            <input type="password" id="password" name="password" required minlength="8"
                                   placeholder="Minimum 8 characters"
                                   class="block w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-900 text-sm focus:outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700">
                        </div>
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Confirm New Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock text-xs"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                                   placeholder="Re-type new password"
                                   class="block w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-900 text-sm focus:outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700">
                        </div>
                    </div>
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full py-2.5 px-4 bg-blue-700 hover:bg-blue-800 active:bg-blue-900 text-white font-bold text-sm rounded-lg transition-colors flex items-center justify-center space-x-2">
                            <span>Update System Password</span>
                            <i class="fas fa-check text-xs"></i>
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="text-center pt-2">
                    <a href="<?= baseUrl() ?>/index.php?page=password-recovery"
                       class="inline-block px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white rounded-lg text-xs font-bold">
                        Request New Reset Link
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="w-full px-6 py-4 border-t border-slate-800 bg-slate-950 text-center text-xs text-slate-400">
        <p>&copy; <?= date('Y') ?> Cauayan City Water District &bull; GeoSnap System</p>
    </footer>

</body>
</html>
