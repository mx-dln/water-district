<?php
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);
            $resetLink = APP_URL . '/index.php?page=reset-password&token=' . $token;
            $message = 'A password reset link has been generated. In production, this would be emailed.';
            $message .= '<br><small class="text-gray-500">Debug: <a href="' . $resetLink . '" class="text-blue-600">' . $resetLink . '</a></small>';
        } else {
            $message = 'If the email exists, a reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Recovery | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>
<body class="bg-gradient-to-br from-blue-900 via-blue-800 to-blue-600 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <img src="<?= APP_URL ?>/assets/images/logo.png" alt="<?= escapeOutput(APP_NAME) ?> Logo" class="w-16 h-16 mx-auto object-contain mb-3">
                <h2 class="text-xl font-bold text-gray-800">Recover Password</h2>
                <p class="text-sm text-gray-500 mt-1">Enter your email to receive reset instructions</p>
            </div>
            <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm"><?= escapeOutput($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="email" name="email" required
                               class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                               placeholder="your@email.com">
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-700 text-white py-2.5 rounded-lg font-medium hover:bg-blue-800 transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i>Send Reset Link
                </button>
            </form>
            <div class="mt-4 text-center">
                <a href="<?= APP_URL ?>/index.php?page=login" class="text-sm text-blue-600 hover:underline">
                    <i class="fas fa-arrow-left mr-1"></i>Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
