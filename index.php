<?php
ob_start();
require_once __DIR__ . '/config/app.php';

checkRememberMe();

$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_-]/', '', $_GET['page']) : 'login';
$page = $page ?: 'login';

$publicPages = ['login', 'logout', 'password-recovery', 'reset-password'];

if (!in_array($page, $publicPages)) {
    requireLogin();
}

$allowedPages = [
    'login', 'logout', 'password-recovery', 'reset-password',
    'dashboard', 'attendance', 'attendance-today', 'attendance-history',
    'attendance-evidence', 'attendance-time-in', 'attendance-time-out',
    'attendance-break-in', 'attendance-break-out',
    'employees', 'employees-create', 'employees-edit', 'employees-view',
    'departments', 'departments-create', 'departments-edit',
    'leaves', 'leaves-request', 'leaves-manage', 'leaves-cs-form-6',
    'polygon', 'reports', 'settings', 'profile', 'change-password',
];

$customRoutes = [
    'leaves-cs-form-6' => __DIR__ . '/modules/leaves/cs-form-6.php',
];

$pageFile = $customRoutes[$page] ?? (__DIR__ . '/modules/' . str_replace('-', '/', $page) . '.php');
if (!file_exists($pageFile)) {
    $pageFile = __DIR__ . '/modules/auth/' . $page . '.php';
}

if (!in_array($page, $allowedPages) || !file_exists($pageFile)) {
    $page = 'dashboard';
    $pageFile = __DIR__ . '/modules/dashboard.php';
}

if (in_array($page, ['login', 'logout', 'password-recovery', 'reset-password'])) {
    require $pageFile;
    exit;
}

$noLayoutPages = ['leaves-cs-form-6'];
if (in_array($page, $noLayoutPages) && file_exists($pageFile)) {
    require $pageFile;
    exit;
}

if ($page === 'reports' && ($_GET['format'] ?? 'html') !== 'html' && file_exists($pageFile)) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    require $pageFile;
    exit;
}

$pageTitle = ucwords(str_replace(['-', '_'], ' ', $page));

$db = Database::getInstance()->getConnection();
$db->exec("SET time_zone = '+08:00'");

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/navbar.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div id="mainContent" class="lg:ml-64 pt-16 min-h-screen">
    <div class="p-4 lg:p-6">
        <?php
        $flash = getFlash();
        if ($flash): ?>
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition
             class="fixed top-20 left-1/2 -translate-x-1/2 z-50 w-[90vw] max-w-sm px-5 py-3 rounded-xl shadow-lg text-sm font-medium border
             <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border-green-200' : '' ?>
             <?= $flash['type'] === 'danger' ? 'bg-red-50 text-red-800 border-red-200' : '' ?>
             <?= $flash['type'] === 'warning' ? 'bg-yellow-50 text-yellow-800 border-yellow-200' : '' ?>
             <?= $flash['type'] === 'info' ? 'bg-blue-50 text-blue-800 border-blue-200' : '' ?>">
            <div class="flex items-center justify-between">
                <span><i class="fas fa-info-circle mr-2"></i><?= escapeOutput($flash['message']) ?></span>
                <button @click="show = false" class="ml-3 text-current opacity-50 hover:opacity-100">&times;</button>
            </div>
        </div>
        <?php endif; ?>
        <?php require $pageFile; ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php ob_end_flush(); ?>
