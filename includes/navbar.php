<?php
$notifCount = 0;
$unreadNotifs = [];
if (isLoggedIn()) {
    $unreadNotifs = getUnreadNotifications($_SESSION['user_id']);
    $notifCount = count($unreadNotifs);
    $userRole = $_SESSION['role'] ?? 'Employee';
    $userName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'User';
}
?>
<nav class="navbar-glass sticky top-0 left-0 right-0 z-30 h-16 border-b border-slate-200/80 bg-white/95 backdrop-blur-md">
    <div class="flex items-center justify-between h-full px-4 lg:px-6">
        <!-- Left: Sidebar Toggle & Brand -->
        <div class="flex items-center space-x-3">
            <button id="sidebarToggle" type="button" aria-label="Toggle Sidebar"
                    class="lg:hidden p-2 rounded-xl text-slate-600 hover:text-blue-700 hover:bg-blue-50 focus:outline-none transition-colors">
                <i class="fas fa-bars text-lg"></i>
            </button>
            <a href="<?= baseUrl() ?>/index.php?page=dashboard" class="flex items-center space-x-3 group">
                <div class="relative">
                    <img src="<?= baseUrl('assets/images/logo.png') ?>" alt="<?= escapeOutput(APP_NAME) ?> Logo"
                         class="w-9 h-9 rounded-xl object-contain shadow-xs border border-blue-100 group-hover:scale-105 transition-transform duration-200">
                    <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                </div>
                <div>
                    <div class="flex items-center space-x-1.5">
                        <span class="text-base font-extrabold tracking-tight text-navy-950 font-heading">GeoSnap</span>
                        <span class="hidden sm:inline-block px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200/60 rounded">CCWD</span>
                    </div>
                    <p class="hidden sm:block text-[11px] font-medium text-slate-500 leading-none -mt-0.5">Cauayan City Water District</p>
                </div>
            </a>
        </div>

        <!-- Center: Live Philippine Standard Time (PST) Display -->
        <div class="hidden md:flex items-center">
            <div id="livePstClock" class="flex items-center space-x-2 px-3 py-1.5 bg-slate-100/80 border border-slate-200/80 rounded-full text-xs font-medium text-slate-700 shadow-2xs">
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-600"></span>
                </span>
                <span class="font-semibold text-slate-800" id="pstLiveTime">--:--:-- --</span>
                <span class="text-slate-400">|</span>
                <span class="text-slate-600 hidden lg:inline" id="pstLiveDate">Philippine Standard Time</span>
            </div>
        </div>

        <!-- Right: Actions & User Dropdown -->
        <div class="flex items-center space-x-2 sm:space-x-3">
            <?php if (isLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                <!-- Quick Kiosk Launch (Admin) -->
                <a href="<?= baseUrl() ?>/index.php?page=kiosk" target="_blank"
                   class="hidden sm:inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80 hover:bg-emerald-100/80 hover:text-emerald-800 transition-all shadow-2xs">
                    <i class="fas fa-camera text-emerald-600"></i>
                    <span>Face Kiosk</span>
                </a>
                <?php endif; ?>

                <!-- Notifications Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" type="button" aria-label="Notifications"
                            class="relative p-2 rounded-xl text-slate-600 hover:text-blue-700 hover:bg-blue-50/80 focus:outline-none transition-colors">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($notifCount > 0): ?>
                        <span class="absolute top-1 right-1 flex h-4 min-w-4 px-1 items-center justify-center bg-rose-600 text-[10px] font-bold text-white rounded-full ring-2 ring-white">
                            <?= $notifCount > 9 ? '9+' : $notifCount ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                         class="fixed sm:absolute right-2 sm:right-0 top-16 sm:top-full sm:mt-2 w-[calc(100vw-1rem)] sm:w-88 max-w-sm bg-white rounded-2xl shadow-xl border border-slate-200 z-50 overflow-hidden">
                        <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between border-b border-slate-800">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-bell text-blue-400 text-sm"></i>
                                <h3 class="font-bold text-sm font-heading">Notifications</h3>
                            </div>
                            <?php if ($notifCount > 0): ?>
                            <span class="text-[11px] font-semibold bg-blue-800 px-2 py-0.5 rounded text-blue-100"><?= $notifCount ?> new</span>
                            <?php endif; ?>
                        </div>
                        <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                            <?php if ($notifCount > 0): foreach ($unreadNotifs as $n): ?>
                            <a href="<?= $n['link'] ?? '#' ?>" class="block px-4 py-3 hover:bg-slate-50/80 transition-colors">
                                <p class="text-xs font-semibold text-slate-800 break-words"><?= escapeOutput($n['title']) ?></p>
                                <p class="text-xs text-slate-500 mt-0.5 break-words line-clamp-2"><?= escapeOutput($n['message']) ?></p>
                                <p class="text-[10px] text-blue-600 font-medium mt-1.5 flex items-center space-x-1">
                                    <i class="far fa-clock"></i>
                                    <span><?= timeAgo($n['created_at']) ?></span>
                                </p>
                            </a>
                            <?php endforeach; else: ?>
                            <div class="py-8 text-center px-4">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-check text-sm"></i>
                                </div>
                                <p class="text-xs font-semibold text-slate-700">All caught up!</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">No unread notifications at this time.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- User Dropdown Chip -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" type="button"
                            class="flex items-center space-x-2.5 p-1 sm:px-2 sm:py-1 rounded-xl text-slate-700 hover:bg-slate-100 transition-colors focus:outline-none">
                        <div class="w-8 h-8 rounded-lg bg-blue-700 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                            <?= strtoupper(substr($userName, 0, 1)) ?>
                        </div>
                        <div class="hidden md:block text-left leading-tight">
                            <span class="block text-xs font-bold text-slate-800 font-heading"><?= escapeOutput($userName) ?></span>
                            <span class="block text-[10px] font-semibold text-blue-600 uppercase tracking-wider"><?= escapeOutput($userRole) ?></span>
                        </div>
                        <i class="fas fa-chevron-down text-[10px] text-slate-400 ml-0.5 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                         class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-200 z-50 p-1.5 divide-y divide-slate-100">
                        <div class="px-3 py-2">
                            <p class="text-xs font-bold text-slate-800"><?= escapeOutput($userName) ?></p>
                            <p class="text-[11px] text-slate-500"><?= escapeOutput($_SESSION['email'] ?? '') ?></p>
                            <div class="mt-1.5 inline-flex items-center px-2 py-0.5 text-[10px] font-bold rounded-full bg-blue-50 text-blue-700 border border-blue-200/60">
                                <i class="fas fa-shield-alt text-[9px] mr-1"></i><?= escapeOutput($userRole) ?>
                            </div>
                        </div>
                        <div class="py-1">
                            <a href="<?= baseUrl() ?>/index.php?page=profile" class="flex items-center space-x-2.5 px-3 py-2 text-xs font-semibold text-slate-700 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors">
                                <i class="fas fa-user-circle w-4 text-center text-slate-400"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="<?= baseUrl() ?>/index.php?page=change-password" class="flex items-center space-x-2.5 px-3 py-2 text-xs font-semibold text-slate-700 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors">
                                <i class="fas fa-key w-4 text-center text-slate-400"></i>
                                <span>Change Password</span>
                            </a>
                        </div>
                        <div class="pt-1">
                            <a href="<?= baseUrl() ?>/index.php?page=logout" class="flex items-center space-x-2.5 px-3 py-2 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-colors">
                                <i class="fas fa-sign-out-alt w-4 text-center text-rose-400"></i>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
// Philippine Standard Time Live Clock (UTC+8)
(function initPstClock() {
    function updateClock() {
        const timeEl = document.getElementById('pstLiveTime');
        const dateEl = document.getElementById('pstLiveDate');
        if (!timeEl) return;
        const now = new Date();
        const options = {
            timeZone: 'Asia/Manila',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        const dateOptions = {
            timeZone: 'Asia/Manila',
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        };
        try {
            timeEl.textContent = now.toLocaleTimeString('en-US', options) + ' PST';
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
            }
        } catch (e) {
            timeEl.textContent = now.toLocaleTimeString();
        }
    }
    updateClock();
    setInterval(updateClock, 1000);
})();
</script>
