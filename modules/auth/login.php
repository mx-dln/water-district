<?php
if (isLoggedIn()) {
    redirect(APP_URL . '/index.php?page=dashboard');
}

$error = '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password';
    } else {
        if (authenticateUser($username, $password, $remember)) {
            redirect(APP_URL . '/index.php?page=dashboard');
        } else {
            $error = 'Invalid username or password';
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>
<body class="bg-gradient-to-br from-blue-900 via-blue-800 to-blue-600 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-2xl shadow-lg mb-4 p-2">
                <img src="<?= APP_URL ?>/assets/images/logo.png" alt="<?= escapeOutput(APP_NAME) ?> Logo" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl font-bold text-white">GeoSnap</h1>
            <p class="text-blue-200 text-sm">Workforce Management System</p>
            <p class="text-blue-300 text-xs mt-1">Cauayan City Water District</p>
        </div>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Sign In</h2>
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i><?= escapeOutput($error) ?>
            </div>
            <?php endif; ?>
            <?php if (isset($_GET['expired'])): ?>
            <div class="mb-4 p-3 bg-yellow-50 text-yellow-700 rounded-lg text-sm flex items-center">
                <i class="fas fa-clock mr-2"></i>Session expired. Please login again.
            </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username / Email</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="username" required
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Enter username or email">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="password" name="password" required
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="Enter password">
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center space-x-2 text-sm">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-gray-600">Remember me</span>
                    </label>
                    <a href="<?= APP_URL ?>/index.php?page=password-recovery" class="text-sm text-blue-600 hover:underline">Forgot password?</a>
                </div>
                <button type="submit" class="w-full bg-blue-700 text-white py-2.5 rounded-lg font-medium hover:bg-blue-800 transition-colors focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                </button>
            </form>

        </div>
        <p class="text-center text-blue-200 text-xs mt-6">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
    </div>
</body>
</html>
