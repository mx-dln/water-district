<?php
require_once __DIR__ . '/../config/constants.php';

session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'domain' => '',
    'secure' => parse_url(APP_URL, PHP_URL_SCHEME) === 'https',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csc_attendance.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

date_default_timezone_set(TIMEZONE);

$db = Database::getInstance()->getConnection();

if (isset($_SESSION['user_id'])) {
    $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
    $checkStmt->execute([$_SESSION['user_id']]);
    if (!$checkStmt->fetch()) {
        session_destroy();
        redirect(APP_URL . '/index.php?page=login');
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        redirect(APP_URL . '/index.php?page=login&expired=1');
    }
    $_SESSION['last_activity'] = time();
}
