<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';

start_session();

if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';
$notice = isset($_GET['signed_out']) ? 'You have been signed out.' : '';

$lockedUntil = $_SESSION['login_locked_until'] ?? 0;

// Live figures for the welcome panel (falls back to dashes if MySQL is off).
try {
    $heroStats = db()->query(
        'SELECT (SELECT COUNT(*) FROM students) students,
                (SELECT COUNT(*) FROM sections x JOIN terms t ON t.id = x.term_id WHERE t.is_current = 1) classes,
                (SELECT COUNT(*) FROM colleges) colleges'
    )->fetch();
} catch (Throwable $ex) {
    $heroStats = ['students' => null, 'classes' => null, 'colleges' => null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!verify_csrf($_POST['csrf'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($lockedUntil > time()) {
        $error = 'Too many failed attempts. Try again in ' . ceil(($lockedUntil - time()) / 60) . ' minute(s).';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        try {
            $user = attempt_login($email, $password);
        } catch (PDOException $ex) {
            $user = null;
            $error = 'Cannot reach the database. Make sure MySQL is running in XAMPP.';
        }

        if ($user) {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            $_SESSION['user'] = [
                'id'    => (int) $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));

            if (!empty($_POST['remember'])) {
                $p = session_get_cookie_params();
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + 60 * 60 * 24 * 14,
                    'path'     => $p['path'],
                    'secure'   => $p['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            header('Location: index.php');
            exit;
        }

        if ($error === '') {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + LOCKOUT_SECONDS;
                $_SESSION['login_attempts'] = 0;
                $error = 'Too many failed attempts. Try again in ' . ceil(LOCKOUT_SECONDS / 60) . ' minutes.';
            } else {
                $error = 'Incorrect email or password.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
</head>
<body class="auth-body">
<main class="auth">
    <section class="auth-hero" aria-hidden="true">
        <span class="auth-grid"></span><span class="orb orb--1"></span><span class="orb orb--2"></span><span class="orb orb--3"></span>
        <div class="brand brand--light">
            <div class="brand-mark" role="img" aria-label="<?= e(APP_NAME) ?> logo"></div>
            <div>
                <div class="brand-name"><?= e(APP_NAME) ?></div>
                <div class="brand-sub"><?= e(APP_TAGLINE) ?></div>
            </div>
        </div>

        <div class="auth-hero-copy">
            <h1 style="--i: 1">Every college, class and student in one place.</h1>
            <p style="--i: 2">Track enrollment, grade submissions, at-risk students and faculty evaluations across the whole university.</p>

            <ul class="auth-points">
                <li style="--i: 3"><span><?= icon('users', 18) ?></span>Live school-wide enrollment and admissions</li>
                <li style="--i: 4"><span><?= icon('check-sq', 18) ?></span>Grade submission progress by college</li>
                <li style="--i: 5"><span><?= icon('shield', 18) ?></span>Role-protected records, anonymous evaluations</li>
            </ul>
        </div>

        <div class="auth-hero-stats">
            <?php foreach (['students' => 'Students', 'classes' => 'Active classes', 'colleges' => 'Colleges'] as $key => $label): ?>
                <div style="--i: <?= 6 + array_search($key, array_keys($heroStats)) ?>"><strong data-count="<?= (int) $heroStats[$key] ?>"><?= $heroStats[$key] === null ? '—' : number_format((int) $heroStats[$key]) ?></strong><span><?= $label ?></span></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="brand auth-mobile-brand">
                <div class="brand-mark" role="img" aria-label="<?= e(APP_NAME) ?> logo"></div>
                <div>
                    <div class="brand-name"><?= e(APP_NAME) ?></div>
                    <div class="brand-sub"><?= e(APP_TAGLINE) ?></div>
                </div>
            </div>

            <h2 style="--i: 0">Welcome back</h2>
            <p class="muted" style="--i: 1">Sign in as an Authorized Academic Administrator to manage university-wide academic records.</p>

            <?php if ($error): ?>
                <div class="alert alert--error" role="alert"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div>
            <?php elseif ($notice): ?>
                <div class="alert alert--info" role="status"><?= icon('check', 18) ?><span><?= e($notice) ?></span></div>
            <?php endif; ?>

            <form method="post" action="login.php" class="auth-form" novalidate>
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

                <label class="field" style="--i: 2">
                    <span class="field-label">Email address</span>
                    <span class="field-control">
                        <?= icon('mail', 18) ?>
                        <input type="text" name="email" value="<?= e($email) ?>" placeholder="Enter your email" autocomplete="username" required autofocus>
                    </span>
                </label>

                <label class="field" style="--i: 3">
                    <span class="field-label">Password</span>
                    <span class="field-control">
                        <?= icon('lock', 18) ?>
                        <input type="password" name="password" id="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="field-toggle" id="togglePassword" aria-label="Show password">
                            <span class="i-show"><?= icon('eye', 18) ?></span>
                            <span class="i-hide" hidden><?= icon('eye-off', 18) ?></span>
                        </button>
                    </span>
                    <span class="caps-warn" id="capsWarn" hidden><?= icon('alert', 14) ?> Caps Lock is on</span>
                </label>

                <div class="auth-row" style="--i: 4">
                    <label class="check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Keep me signed in</span>
                    </label>
                    <a href="#" class="link" id="forgotLink">Forgot password?</a>
                </div>

                <div class="alert alert--info" id="forgotNote" hidden><?= icon('mail', 18) ?><span>Passwords are reset by the Registrar’s Office. Visit the office or call local 104 with your employee ID.</span></div>

                <button type="submit" class="btn btn--primary btn--block" id="signIn" style="--i: 5"><span>Sign in</span> <?= icon('chev-r', 16) ?></button>
            </form>

            <p class="auth-foot" style="--i: 7"><?= icon('shield', 14) ?> Access is restricted to authorized academic administrators.</p>
        </div>
    </section>
</main>

<script>
(function () {
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var password = document.getElementById('password');

    // Show / hide password
    document.getElementById('togglePassword').addEventListener('click', function () {
        var show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        this.querySelector('.i-show').hidden = show;
        this.querySelector('.i-hide').hidden = !show;
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        password.focus();
    });

    // Caps Lock warning
    var caps = document.getElementById('capsWarn');
    ['keydown', 'keyup'].forEach(function (type) {
        password.addEventListener(type, function (ev) {
            if (ev.getModifierState) caps.hidden = !ev.getModifierState('CapsLock');
        });
    });
    password.addEventListener('blur', function () { caps.hidden = true; });

    // Fields keep a soft highlight once filled in
    document.querySelectorAll('.field-control input').forEach(function (input) {
        var mark = function () { input.parentNode.classList.toggle('is-filled', input.value !== ''); };
        input.addEventListener('input', mark);
        mark();
    });

    // Forgot password: explain inline instead of a browser pop-up
    document.getElementById('forgotLink').addEventListener('click', function (ev) {
        ev.preventDefault();
        var note = document.getElementById('forgotNote');
        note.hidden = !note.hidden;
    });

    // Signing in: spinner on the button, card eases out
    document.querySelector('.auth-form').addEventListener('submit', function () {
        var btn = document.getElementById('signIn');
        btn.classList.add('is-busy');
        btn.querySelector('span').textContent = 'Signing in…';
        document.body.classList.add('is-leaving');
        setTimeout(function () { btn.disabled = true; }, 0);
    });
    // Coming back with the browser's Back button: undo the "signing in" state
    window.addEventListener('pageshow', function (ev) {
        if (!ev.persisted) return;
        var btn = document.getElementById('signIn');
        btn.disabled = false;
        btn.classList.remove('is-busy');
        btn.querySelector('span').textContent = 'Sign in';
        document.body.classList.remove('is-leaving');
    });

    // Welcome panel figures count up
    if (!calm) {
        document.querySelectorAll('[data-count]').forEach(function (el) {
            var target = +el.dataset.count, start = null;
            if (!target) return;
            el.textContent = '0';
            function frame(now) {
                start = start || now;
                var t = Math.min(1, Math.max(0, (now - start - 600) / 1100));
                el.textContent = Math.round(target * (1 - Math.pow(1 - t, 3))).toLocaleString('en-US');
                if (t < 1) requestAnimationFrame(frame);
            }
            requestAnimationFrame(frame);
        });
    }
})();
</script>
</body>
</html>
