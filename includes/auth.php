<?php
require_once __DIR__ . '/db.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Keep server-side session data for 14 days so "Keep me signed in" works.
    // Without that option the cookie still ends when the browser closes.
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 14));
    session_name('veritas_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(mixed $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    start_session();
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    // Re-read persisted role on each request; session role changes cannot grant access.
    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([(int) $user['id']]);
    $fresh = $stmt->fetch();
    if (!$fresh) {
        unset($_SESSION['user']);
        header('Location: login.php');
        exit;
    }
    $_SESSION['user'] = $fresh;
    return $fresh;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '' && ctype_alpha($part[0])) {
            $letters .= strtoupper($part[0]);
        }
    }
    if ($letters === '') {
        return '?';
    }
    return strlen($letters) === 1 ? $letters : $letters[0] . substr($letters, -1);
}

/**
 * Checks credentials. Returns the user row on success, null on failure.
 */
function attempt_login(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Authentication deliberately preserves every existing account field.
    unset($user['password_hash']);
    return $user;
}

/** Explicit permission mapping; unrecognized roles fail closed. No role records are changed. */
function role_permissions(string $role): array
{
    return match ($role) {
        'University-wide access' => ['academic.view', 'academic.manage', 'sete.view', 'sete.manage', 'prediction.demo'],
        'Academic Viewer' => ['academic.view', 'sete.view', 'prediction.demo'],
        // No account-to-faculty relationship exists: never infer access from a name/email.
        'Faculty' => ['prediction.demo'],
        default => [],
    };
}

function can(string $permission, ?array $user = null): bool
{
    $user ??= current_user();
    return $user !== null && in_array($permission, role_permissions((string) ($user['role'] ?? '')), true);
}

function require_permission(string $permission, ?array $user = null): void
{
    if (!can($permission, $user)) {
        http_response_code(403);
        exit('Access denied. Your account role does not authorize this feature.');
    }
}

function action_permission(string $action): string
{
    return match ($action) {
        'catalog_save' => 'academic.manage',
        'faculty_save', 'faculty_delete' => 'sete.manage',
        'applicant_save', 'applicant_delete', 'student_save', 'student_delete',
        'section_save', 'section_delete', 'grades_save', 'enrollment_add', 'enrollment_remove',
        'intervention_save', 'intervention_delete' => 'academic.manage',
        default => 'denied',
    };
}

function home_for_user(array $user): string
{
    return can('academic.view', $user) ? 'index.php' : 'predictions.php';
}
