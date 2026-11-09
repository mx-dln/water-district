<?php
if (isLoggedIn()) {
    redirect(baseUrl() . '/index.php?page=dashboard');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            if (authenticateUser($username, $password, $remember)) {
                redirect(baseUrl() . '/index.php?page=dashboard');
            } else {
                $error = 'Invalid credentials. Please verify your username and password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &bull; GeoSnap &bull; Cauayan City Water District</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen h-full flex flex-col justify-between bg-slate-900 text-slate-100 antialiased selection:bg-blue-600 selection:text-white">

    <!-- Top Water District Brand Header -->
    <header class="w-full px-6 py-4 flex items-center justify-between border-b border-slate-800 bg-slate-950">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-lg bg-white p-1 border border-slate-700 flex items-center justify-center">
                <img src="<?= baseUrl('assets/images/logo.png') ?>" alt="Cauayan City Water District Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-bold tracking-wide text-white">GeoSnap</span>
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-blue-900 text-blue-200 border border-blue-700">Official Portal</span>
                </div>
                <p class="text-[11px] text-slate-400 font-medium">Cauayan City Water District</p>
            </div>
        </div>
        <div class="hidden sm:flex items-center space-x-2 text-xs text-slate-300 bg-slate-900 border border-slate-800 px-3 py-1.5 rounded-md">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>PST System Online</span>
        </div>
    </header>

    <!-- Main Sign-In Card Container -->
    <main class="flex-1 flex items-center justify-center px-4 py-12 sm:px-6 lg:px-8">
        <div class="w-full max-w-md">

            <!-- Card -->
            <div class="bg-white text-slate-800 rounded-xl border border-slate-200 shadow-xl p-8">
                <!-- Agency Header in Card -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-blue-50 border border-blue-200 mb-3 text-blue-700 text-2xl font-bold">
                        <i class="fas fa-water"></i>
                    </div>
                    <h2 class="text-xl font-bold text-slate-900 tracking-tight">Staff Authentication</h2>
                    <p class="text-xs text-slate-500 mt-1">Sign in to record attendance and access CSC DTR records</p>
                </div>

                <!-- Alert Message -->
                <?php if ($error): ?>
                <div class="mb-5 p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 flex items-start space-x-3 text-xs leading-relaxed" role="alert">
                    <i class="fas fa-circle-exclamation text-rose-600 text-sm mt-0.5 shrink-0"></i>
                    <div class="font-medium"><?= escapeOutput($error) ?></div>
                </div>
                <?php endif; ?>

                <?php
                $flash = getFlash();
                if ($flash):
                    $fClass = $flash['type'] === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-blue-50 border-blue-200 text-blue-800';
                    $fIcon = $flash['type'] === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-circle-info text-blue-600';
                ?>
                <div class="mb-5 p-3.5 rounded-lg <?= $fClass ?> border flex items-start space-x-3 text-xs leading-relaxed">
                    <i class="fas <?= $fIcon ?> text-sm mt-0.5 shrink-0"></i>
                    <div class="font-medium"><?= escapeOutput($flash['message']) ?></div>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="<?= baseUrl() ?>/index.php?page=login" class="space-y-4" x-data="{ showPass: false }">
                    <?= csrfField() ?>

                    <!-- Username Field -->
                    <div>
                        <label for="username" class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                            Username or Employee ID
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-user text-xs"></i>
                            </div>
                            <input type="text" id="username" name="username" required autofocus
                                   value="<?= escapeOutput($_POST['username'] ?? '') ?>"
                                   placeholder="Enter username"
                                   autocomplete="username"
                                   class="block w-full pl-10 pr-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-900 text-sm placeholder:text-slate-400 focus:outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                                Password
                            </label>
                            <a href="<?= baseUrl() ?>/index.php?page=password-recovery"
                               class="text-xs font-semibold text-blue-700 hover:text-blue-900 hover:underline">
                                Forgot password?
                            </a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fas fa-lock text-xs"></i>
                            </div>
                            <input :type="showPass ? 'text' : 'password'" id="password" name="password" required
                                   placeholder="Enter password"
                                   autocomplete="current-password"
                                   class="block w-full pl-10 pr-10 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-900 text-sm placeholder:text-slate-400 focus:outline-none focus:border-blue-700 focus:ring-1 focus:ring-blue-700">
                            <button type="button" @click="showPass = !showPass"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i class="fas text-xs" :class="showPass ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center text-xs text-slate-600 font-medium cursor-pointer">
                            <input type="checkbox" name="remember" value="1"
                                   class="w-4 h-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
                            <span class="ml-2">Keep me signed in</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full py-2.5 px-4 bg-blue-700 hover:bg-blue-800 active:bg-blue-900 text-white font-bold text-sm rounded-lg transition-colors flex items-center justify-center space-x-2">
                            <span>Sign In to Workstation</span>
                            <i class="fas fa-arrow-right text-xs"></i>
                        </button>
                    </div>
                </form>

                <!-- Kiosk Mode Link -->
                <div class="mt-6 pt-5 border-t border-slate-100 text-center">
                    <p class="text-xs text-slate-500 mb-2">Office attendance terminal?</p>
                    <a href="<?= baseUrl() ?>/index.php?page=kiosk"
                       class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors">
                        <i class="fas fa-camera text-slate-500"></i>
                        <span>Switch to Office Face Kiosk</span>
                    </a>
                </div>
            </div>

            <!-- Disclaimer -->
            <div class="text-center mt-6 text-xs text-slate-400 space-y-1">
                <p>Authorized Personnel Only &bull; Republic of the Philippines</p>
                <p class="text-[11px] text-slate-500">Protected under Civil Service Rules & Data Privacy Act (R.A. 10173)</p>
            </div>
        </div>
    </main>

    <!-- Bottom Footer Bar -->
    <footer class="w-full px-6 py-4 border-t border-slate-800 bg-slate-950 text-center text-xs text-slate-400">
        <p>&copy; <?= date('Y') ?> Cauayan City Water District. All rights reserved. &bull; GeoSnap Workforce System</p>
    </footer>

</body>
</html>
