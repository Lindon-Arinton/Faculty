<?php
require_once __DIR__ . '/includes/auth.php';

start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf'] ?? null)) {
    header('Location: index.php');
    exit;
}

$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => $p['path'],
    'secure'   => $p['secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();

header('Location: login.php?signed_out=1');
exit;
