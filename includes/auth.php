<?php
function authenticateUser($username, $password, $remember = false) {
    global $db;
    $stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1 LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        logAudit('login_failed', 'auth', "Failed login attempt for: $username");
        return false;
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['role_id'] = $user['role_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['created'] = time();

    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$hashedToken, $user['id']]);
        setcookie('remember_token', $token, time() + 86400 * 30, '/', '', true, true);
        setcookie('user_id', $user['id'], time() + 86400 * 30, '/', '', true, true);
    }

    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    logAudit('login', 'auth', "User '{$user['username']}' logged in");
    return true;
}

function checkRememberMe() {
    if (isset($_COOKIE['remember_token']) && isset($_COOKIE['user_id']) && !isset($_SESSION['user_id'])) {
        global $db;
        $stmt = $db->prepare("SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.is_active = 1 LIMIT 1");
        $stmt->execute([$_COOKIE['user_id']]);
        $user = $stmt->fetch();
        if ($user && password_verify($_COOKIE['remember_token'], $user['remember_token'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['last_activity'] = time();
            $_SESSION['created'] = time();
        }
    }
}


function logoutUser() {
    if (isset($_SESSION['user_id'])) {
        global $db;
        $stmt = $db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    setcookie('user_id', '', time() - 3600, '/', '', true, true);
    session_unset();
    session_destroy();
}
