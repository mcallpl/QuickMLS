<?php
/**
 * QuickMLS — Session & Auth Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie. "secure" is set only when the request is
    // actually over HTTPS so local http:// development still works.
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // Persistent sessions: dedicated dir so the distro GC cron cannot purge
    // them; cookie + server-side lifetime one year.
    $appSessDir = '/var/lib/php/app-sessions/quickmls';
    if (is_dir($appSessDir) && is_writable($appSessDir)) {
        ini_set('session.save_path', $appSessDir);
        ini_set('session.gc_maxlifetime', '31536000');
    }
    session_set_cookie_params([
        'lifetime' => 31536000,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $secure,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
    ];
}

/** Return (creating if needed) this session's CSRF token. */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Constant-time check of a submitted CSRF token against the session's. */
function verifyCsrf(?string $token): bool {
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Admin required']);
        exit;
    }
}
