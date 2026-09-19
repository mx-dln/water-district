<?php
$notifCount = 0;
if (isLoggedIn()) {
    $notifs = getUnreadNotifications($_SESSION['user_id']);
    $notifCount = count($notifs);
}
?>
<nav class="bg-white border-b border-gray-200 fixed top-0 left-0 right-0 z-30 h-16">
    <div class="flex items-center justify-between h-full px-4 lg:px-6">
        <div class="flex items-center">
            <button id="sidebarToggle" class="lg:hidden mr-3 text-gray-500 hover:text-gray-700 focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="flex items-center space-x-3">
                <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="flex items-center space-x-3">
                    <img src="<?= baseUrl('assets/images/logo.png') ?>" alt="<?= escapeOutput(APP_NAME) ?> Logo" class="w-8 h-8 rounded-lg object-contain">
                    <div class="hidden sm:block">
                        <h1 class="text-sm font-bold text-gray-800">GeoSnap</h1>
                        <p class="text-xs text-gray-500 -mt-1">Workforce Management</p>
                    </div>
                </a>
            </div>
        </div>
        <div class="flex items-center space-x-4">
            <?php if (isLoggedIn()): ?>
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="relative text-gray-500 hover:text-gray-700">
                    <i class="fas fa-bell text-lg"></i>
                    <?php if ($notifCount > 0): ?>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center"><?= $notifCount ?></span>
                    <?php endif; ?>
                </button>
                <div x-show="open" @click.outside="open = false" class="fixed sm:absolute right-2 sm:right-0 top-16 sm:top-full sm:mt-2 w-[calc(100vw-16px)] sm:w-80 max-w-sm bg-white rounded-lg shadow-lg border z-50" x-cloak>
                    <div class="p-3 border-b">
                        <h3 class="font-semibold text-sm">Notifications</h3>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <?php if ($notifCount > 0): foreach ($notifs as $n): ?>
                        <a href="<?= $n['link'] ?? '#' ?>" class="block px-4 py-3 hover:bg-gray-50 border-b last:border-0">
                            <p class="text-sm font-medium break-words"><?= escapeOutput($n['title']) ?></p>
                            <p class="text-xs text-gray-500 mt-1 break-words"><?= escapeOutput($n['message']) ?></p>
                            <p class="text-xs text-gray-400 mt-1"><?= timeAgo($n['created_at']) ?></p>
                        </a>
                        <?php endforeach; else: ?>
                        <p class="p-4 text-sm text-gray-500 text-center">No notifications</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-blue-700"><?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?></span>
                    </div>
                    <span class="hidden md:block text-sm font-medium"><?= escapeOutput($_SESSION['username'] ?? 'User') ?></span>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border z-50" x-cloak>
                    <a href="<?= baseUrl() ?>/index.php?page=profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-user mr-2"></i>Profile</a>
                    <a href="<?= baseUrl() ?>/index.php?page=change-password" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-key mr-2"></i>Change Password</a>
                    <hr class="my-1">
                    <a href="<?= baseUrl() ?>/index.php?page=logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
