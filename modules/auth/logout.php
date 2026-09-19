<?php
$_SESSION = [];
session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
setcookie('user_id', '', time() - 3600, '/');
while (ob_get_level() > 0) { ob_end_clean(); }
header("Location: " . baseUrl('/index.php?page=login'));
echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . baseUrl('/index.php?page=login') . '"></head><body><p>Logging out...</p><a href="' . baseUrl('/index.php?page=login') . '">Click here</a></body></html>';
exit;
