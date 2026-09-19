<?php
requireLogin();
if (isAdmin()) {
    require __DIR__ . '/dashboard/admin-dashboard.php';
} else {
    require __DIR__ . '/dashboard/employee-dashboard.php';
}
