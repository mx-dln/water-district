<?php
ob_start();
require_once __DIR__ . '/config/app.php';

checkRememberMe();

$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_-]/', '', $_GET['page']) : 'login';
$page = $page ?: 'login';

$publicPages = ['login', 'logout', 'password-recovery', 'reset-password'];

if (!in_array($page, $publicPages)) {
    if (in_array($page, ['kiosk', 'attendance-kiosk'], true)) {
        requireRole('Administrator');
    } else {
        requireLogin();
    }
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
    'kiosk', 'attendance-kiosk',
];

$customRoutes = [
    'leaves-cs-form-6' => __DIR__ . '/modules/leaves/cs-form-6.php',
    'kiosk' => __DIR__ . '/modules/attendance/kiosk.php',
    'attendance-kiosk' => __DIR__ . '/modules/attendance/kiosk.php',
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

$noLayoutPages = ['leaves-cs-form-6', 'kiosk', 'attendance-kiosk'];
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
<div id="mainContent" class="lg:ml-64 pt-16 min-h-screen bg-slate-50/70 transition-all duration-200">
    <main class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6">
        <?php
        $flash = getFlash();
        if ($flash):
            $typeIcons = [
                'success' => 'fa-check-circle text-emerald-500',
                'danger'  => 'fa-circle-xmark text-rose-500',
                'warning' => 'fa-triangle-exclamation text-amber-500',
                'info'    => 'fa-circle-info text-blue-500',
            ];
            $typeBgs = [
                'success' => 'bg-emerald-50/95 border-emerald-200/90 text-emerald-950',
                'danger'  => 'bg-rose-50/95 border-rose-200/90 text-rose-950',
                'warning' => 'bg-amber-50/95 border-amber-200/90 text-amber-950',
                'info'    => 'bg-blue-50/95 border-blue-200/90 text-blue-950',
            ];
            $t = $flash['type'] ?? 'info';
            $icon = $typeIcons[$t] ?? $typeIcons['info'];
            $bg = $typeBgs[$t] ?? $typeBgs['info'];
        ?>
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 -translate-y-2 scale-95"
             class="fixed top-20 left-1/2 -translate-x-1/2 z-50 w-[92vw] max-w-md px-4 py-3 rounded-2xl shadow-xl backdrop-blur-md border <?= $bg ?> flex items-start space-x-3">
            <i class="fas <?= $icon ?> text-lg mt-0.5 shrink-0"></i>
            <div class="flex-1 text-xs sm:text-sm font-medium leading-relaxed">
                <?= escapeOutput($flash['message']) ?>
            </div>
            <button @click="show = false" type="button" class="shrink-0 p-1 text-slate-400 hover:text-slate-700 transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <?php endif; ?>
        <?php require $pageFile; ?>
    </main>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php ob_end_flush(); ?>
