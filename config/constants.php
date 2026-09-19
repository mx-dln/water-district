<?php
define('APP_NAME', 'GeoSnap Workforce Management System');
define('COMPANY_NAME', 'Cauayan City Water District');

function resolveAppUrl(): string
{
    $configuredUrl = getenv('APP_URL');
    if ($configuredUrl) {
        return rtrim($configuredUrl, '/');
    }

    if (PHP_SAPI === 'cli') {
        return 'http://localhost';
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(str_replace('/index.php', '', dirname($scriptName)), '/');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '';
    }

    return $scheme . '://' . $host . $basePath;
}

define('APP_URL', resolveAppUrl());
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('ATTENDANCE_UPLOAD_PATH', UPLOAD_PATH . '/attendance');
define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('TIMEZONE', 'Asia/Manila');
define('SESSION_TIMEOUT', 3600);
define('ITEMS_PER_PAGE', 15);
define('CSRF_TOKEN_NAME', 'csrf_token');
