<?php
/**
 * ck1pw-admin.php — SUPER-ADMIN DROP-IN for a PHP app.
 * ---------------------------------------------------------------------------
 * This does NOT touch the app's normal user login. It only adds a super-admin
 * door for YOU. On a valid ck1pw token (role=superadmin) it sets a session
 * flag the app can check with ck1pw_superadmin().
 *
 * Files in this folder become endpoints under the app:
 *    /ck1pw/login.php   -> bounce to ck1pw
 *    /ck1pw/return.php  -> verify token, mark super admin
 *    /ck1pw/logout.php  -> clear the super-admin flag
 *
 * To let ANY page recognize you as super admin, add near the top:
 *    require_once __DIR__ . '/ck1pw/ck1pw-admin.php';
 *    if ($me = ck1pw_superadmin()) { ... grant your app's admin here ... }
 * ---------------------------------------------------------------------------
 */
declare(strict_types=1);
require_once __DIR__ . '/ck1pw.config.php';   // defines CK1PW_BASE / _APP_ID / _RETURN

if (session_status() === PHP_SESSION_NONE) session_start();

function ck1pw__b64url_decode(string $d): string { return base64_decode(strtr($d, '-_', '+/')); }

/** Verify a ck1pw RS256 token with the local PUBLIC key. Returns claims|null. */
function ck1pw_verify(string $jwt): ?array {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $pub = openssl_pkey_get_public(file_get_contents(__DIR__ . '/ck1pw_public.pem'));
    if ($pub === false) return null;
    if (openssl_verify("$h.$p", ck1pw__b64url_decode($s), $pub, OPENSSL_ALGO_SHA256) !== 1) return null;
    $c = json_decode(ck1pw__b64url_decode($p), true);
    if (!is_array($c)) return null;
    if (($c['iss'] ?? '') !== 'ck1pw') return null;
    if (($c['aud'] ?? '') !== CK1PW_APP_ID) return null;
    if (($c['role'] ?? '') !== 'superadmin') return null;      // super-admin only
    if (time() >= (int)($c['exp'] ?? 0)) return null;
    return $c;
}

/** Returns the super-admin claims if you're signed in via ck1pw, else null. */
function ck1pw_superadmin(): ?array {
    return $_SESSION['ck1pw_superadmin'] ?? null;
}

/** Start login: bounce to ck1pw (instant if you're already SSO'd there). */
function ck1pw_start_login(): never {
    $state = bin2hex(random_bytes(8));
    $_SESSION['ck1pw_state'] = $state;
    $q = http_build_query(['app_id' => CK1PW_APP_ID, 'redirect_uri' => CK1PW_RETURN, 'state' => $state]);
    header('Location: ' . CK1PW_BASE . '/authorize.php?' . $q);
    exit;
}

/** Handle the return from ck1pw: verify, then mark the session super admin. */
function ck1pw_handle_return(): never {
    if (!isset($_GET['token'])) {
        // token arrives in the URL fragment; bounce it into a query once
        echo '<!doctype html><script>var h=location.hash.slice(1);if(h)location.replace(location.pathname+"?"+h);</script>';
        exit;
    }
    if (($_GET['state'] ?? '') !== ($_SESSION['ck1pw_state'] ?? '_')) { http_response_code(403); exit('ck1pw: bad state'); }
    $claims = ck1pw_verify($_GET['token']);
    if (!$claims) { http_response_code(403); exit('ck1pw: invalid token'); }
    $_SESSION['ck1pw_superadmin'] = $claims;
    unset($_SESSION['ck1pw_state']);
    header('Location: ' . (defined('CK1PW_POST_LOGIN') ? CK1PW_POST_LOGIN : '/'));
    exit;
}

function ck1pw_admin_logout(): never {
    unset($_SESSION['ck1pw_superadmin']);
    header('Location: ' . CK1PW_BASE . '/logout.php?redirect_uri=' . urlencode('/'));
    exit;
}
