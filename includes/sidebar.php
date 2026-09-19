<?php if (!isLoggedIn()) return; ?>
<?php
$currentPage = $_GET['page'] ?? 'dashboard';
$menuItems = [];

if (isAdmin()) {
    $menuItems = [
        ['page' => 'dashboard',       'label' => 'Dashboard',      'icon' => 'fa-chart-pie'],
        ['page' => 'attendance',      'label' => 'Attendance',     'icon' => 'fa-clipboard-check'],
        ['page' => 'employees',       'label' => 'Employees',      'icon' => 'fa-users'],
        ['page' => 'departments',     'label' => 'Departments',    'icon' => 'fa-building'],
        ['page' => 'leaves',          'label' => 'Leave Requests', 'icon' => 'fa-calendar-alt'],
        ['page' => 'polygon',         'label' => 'Office Polygon', 'icon' => 'fa-draw-polygon'],
        ['page' => 'reports',         'label' => 'Reports',        'icon' => 'fa-file-alt'],
        ['page' => 'settings',        'label' => 'Settings',       'icon' => 'fa-cog'],
    ];
} else {
    $menuItems = [
        ['page' => 'dashboard',       'label' => 'Dashboard',      'icon' => 'fa-chart-pie'],
        ['page' => 'attendance',      'label' => 'Attendance',     'icon' => 'fa-clipboard-check'],
        ['page' => 'leaves',          'label' => 'My Leaves',      'icon' => 'fa-calendar-alt'],
        ['page' => 'profile',         'label' => 'Profile',        'icon' => 'fa-user'],
    ];
}
?>
<aside id="sidebar" class="fixed left-0 top-16 bottom-0 w-64 bg-blue-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 z-20 overflow-y-auto">
    <div class="py-4">
        <nav class="space-y-1 px-3">
            <?php foreach ($menuItems as $item):
                $active = $currentPage === $item['page'] || (strpos($currentPage, $item['page']) === 0);
            ?>
            <a href="<?= baseUrl() ?>/index.php?page=<?= $item['page'] ?>"
               class="flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm transition-colors <?= $active ? 'bg-blue-800 text-white font-medium' : 'text-blue-200 hover:bg-blue-800 hover:text-white' ?>">
                <i class="fas <?= $item['icon'] ?> w-5 text-center"></i>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-blue-800">
        <a href="<?= baseUrl() ?>/index.php?page=logout" class="flex items-center space-x-3 text-blue-300 hover:text-white text-sm px-3 py-2">
            <i class="fas fa-sign-out-alt w-5 text-center"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
