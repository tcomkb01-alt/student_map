<?php
/**
 * auth.php – Authentication helpers
 * Student Home Visit Map System
 */

require_once __DIR__ . '/config.php';

/**
 * Redirect to login if not authenticated.
 */
function requireLogin(): void
{
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Check if the current admin session is valid.
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * Log in an admin by verifying credentials.
 * Returns true on success, false on failure.
 */
function loginAdmin(string $username, string $password): bool
{
    require_once __DIR__ . '/db.php';
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id, password, full_name, role FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];
        return true;
    }
    return false;
}

/**
 * Destroy admin session.
 */
function logoutAdmin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Returns true if logged-in user has full admin role.
 */
function isAdmin(): bool
{
    return ($_SESSION['admin_role'] ?? '') === 'admin';
}

/**
 * Redirect to dashboard with error if not full admin.
 */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        $_SESSION['flash_error'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
        header('Location: ' . BASE_URL . '/admin/dashboard');
        exit;
    }
}

/**
 * Returns true if logged-in user can delete records.
 */
function canDelete(): bool
{
    return isAdmin();
}
