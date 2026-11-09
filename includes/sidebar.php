<?php
if (!isLoggedIn()) return;

$currentPage = $_GET['page'] ?? 'dashboard';

// Fetch pending leave count for badge
$sidebarPendingLeaves = 0;
try {
    if (isset($db)) {
        if (isAdmin()) {
            $sidebarPendingLeaves = (int)$db->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'")->fetchColumn();
        } else {
            $empId = getEmployeeIdFromUser($_SESSION['user_id'] ?? 0);
            if ($empId) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ? AND status = 'pending'");
                $stmt->execute([$empId]);
                $sidebarPendingLeaves = (int)$stmt->fetchColumn();
            }
        }
    }
} catch (Exception $e) {
    $sidebarPendingLeaves = 0;
}

if (isAdmin()) {
    $menuGroups = [
        'OVERVIEW' => [
            ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'badge' => null],
        ],
        'ATTENDANCE & GEOFENCE' => [
            ['page' => 'attendance', 'label' => 'Attendance Records', 'icon' => 'fa-clipboard-check', 'badge' => null],
            ['page' => 'kiosk', 'label' => 'Office Face Kiosk', 'icon' => 'fa-camera', 'badge' => 'LIVE', 'badgeClass' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'],
            ['page' => 'polygon', 'label' => 'Field Geofence', 'icon' => 'fa-draw-polygon', 'badge' => null],
        ],
        'WORKFORCE & CSC LEAVES' => [
            ['page' => 'employees', 'label' => 'Employees Directory', 'icon' => 'fa-users', 'badge' => null],
            ['page' => 'departments', 'label' => 'Departments', 'icon' => 'fa-building', 'badge' => null],
            ['page' => 'leaves', 'label' => 'Leave Requests', 'icon' => 'fa-calendar-alt', 'badge' => $sidebarPendingLeaves > 0 ? (string)$sidebarPendingLeaves : null, 'badgeClass' => 'bg-amber-500 text-navy-950 font-bold'],
        ],
        'REPORTS & SYSTEM' => [
            ['page' => 'reports', 'label' => 'CSC DTR Reports', 'icon' => 'fa-file-alt', 'badge' => null],
            ['page' => 'settings', 'label' => 'System Settings', 'icon' => 'fa-sliders-h', 'badge' => null],
        ],
    ];
} else {
    $menuGroups = [
        'MY WORKSPACE' => [
            ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'badge' => null],
            ['page' => 'attendance', 'label' => 'Field Attendance', 'icon' => 'fa-clipboard-check', 'badge' => null],
            ['page' => 'leaves', 'label' => 'My CSC Leaves', 'icon' => 'fa-calendar-alt', 'badge' => $sidebarPendingLeaves > 0 ? (string)$sidebarPendingLeaves : null, 'badgeClass' => 'bg-blue-500/30 text-blue-200 border border-blue-400/30'],
            ['page' => 'profile', 'label' => 'My Profile', 'icon' => 'fa-user-circle', 'badge' => null],
        ],
    ];
}

$userDisplayName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'User';
$userDisplayRole = $_SESSION['role'] ?? 'Employee';
?>
<!-- Sidebar Overlay for mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-navy-950/60 backdrop-blur-xs z-30 lg:hidden hidden transition-opacity"></div>

<aside id="sidebar" class="sidebar-solid fixed left-0 top-16 bottom-0 w-64 text-slate-300 transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out z-30 flex flex-col justify-between shadow-xl border-r border-slate-800 select-none">
    <!-- Top Branding / Nav Scroll Area -->
    <div class="flex-1 overflow-y-auto py-5 px-3 space-y-6 custom-scrollbar">
        <?php foreach ($menuGroups as $groupTitle => $items): ?>
        <div>
            <div class="px-3 mb-2 flex items-center justify-between text-[10px] font-bold tracking-wider text-slate-400/80 uppercase font-heading">
                <span><?= $groupTitle ?></span>
                <span class="w-8 h-px bg-slate-800"></span>
            </div>
            <nav class="space-y-1">
                <?php foreach ($items as $item):
                    $isActive = ($currentPage === $item['page']) || (strpos($currentPage, $item['page']) === 0);
                    $itemTarget = ($item['page'] === 'kiosk') ? 'target="_blank"' : '';
                ?>
                <a href="<?= baseUrl() ?>/index.php?page=<?= $item['page'] ?>" <?= $itemTarget ?>
                   class="group flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-semibold transition-all duration-150 <?= $isActive ? 'sidebar-item-active text-white font-bold' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors <?= $isActive ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-400 group-hover:text-blue-400 group-hover:bg-blue-950/40' ?>">
                            <i class="fas <?= $item['icon'] ?> text-xs"></i>
                        </div>
                        <span class="truncate"><?= $item['label'] ?></span>
                    </div>
                    <?php if (!empty($item['badge'])): ?>
                    <span class="text-[10px] px-2 py-0.5 rounded-full <?= $item['badgeClass'] ?? 'bg-blue-500/20 text-blue-300' ?>">
                        <?= $item['badge'] ?>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Bottom User Footer -->
    <div class="p-3 border-t border-navy-800/80 bg-navy-950/60">
        <div class="flex items-center justify-between p-2 rounded-xl bg-white/5 border border-white/5">
            <div class="flex items-center space-x-2.5 min-w-0">
                <div class="relative shrink-0">
                    <div class="w-8 h-8 rounded-lg bg-blue-700 flex items-center justify-center text-white text-xs font-bold shadow-xs">
                        <?= strtoupper(substr($userDisplayName, 0, 1)) ?>
                    </div>
                    <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 bg-emerald-500 border border-navy-950 rounded-full"></span>
                </div>
                <div class="truncate">
                    <p class="text-xs font-bold text-white truncate leading-tight font-heading"><?= escapeOutput($userDisplayName) ?></p>
                    <p class="text-[10px] text-slate-400 truncate leading-tight mt-0.5"><?= escapeOutput($userDisplayRole) ?></p>
                </div>
            </div>
            <a href="<?= baseUrl() ?>/index.php?page=logout" title="Sign Out"
               class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-950/40 transition-colors">
                <i class="fas fa-sign-out-alt text-xs"></i>
            </a>
        </div>
    </div>
</aside>
