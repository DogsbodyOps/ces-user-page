<?php
/**
 * login.php — Portal login page.
 *
 * Reads authorised users from config/settings.php (gitignored).
 * Copy config/settings.example.php → config/settings.php and add real
 * credentials before deploying.
 */

declare(strict_types=1);

session_start();

// Already authenticated — go to the form
if (!empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}

$settings_file = __DIR__ . '/config/settings.php';
$error         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Basic brute-force mitigation: lock out after 5 failed attempts for 5 min
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['lockout_until']  = 0;
    }

    if (time() < (int)$_SESSION['lockout_until']) {
        $wait = (int)$_SESSION['lockout_until'] - time();
        $error = "Too many failed attempts. Please wait {$wait} seconds.";
    } else {
        if (!file_exists($settings_file)) {
            $error = 'Application is not configured. '
                   . 'Please copy config/settings.example.php to config/settings.php and set credentials.';
        } else {
            $settings = require $settings_file;
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $hash = $settings['users'][$username] ?? null;

            if ($hash && password_verify($password, $hash)) {
                // Regenerate session to prevent fixation
                session_regenerate_id(true);
                $_SESSION['authenticated']   = true;
                $_SESSION['username']        = $username;
                $_SESSION['login_attempts']  = 0;
                $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit;
            } else {
                $_SESSION['login_attempts']++;
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['lockout_until'] = time() + 300; // 5 min
                    $_SESSION['login_attempts'] = 0;
                    $error = 'Too many failed attempts. You are locked out for 5 minutes.';
                } else {
                    $error = 'Invalid username or password.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CES Portal — Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="login-wrapper">
    <div style="width:100%;max-width:420px;padding:1rem;">
        <div class="login-logo">
            <span>CES</span>
            <p>Active Directory User Portal</p>
        </div>

        <div class="card">
            <h2 class="card__title">Sign in</h2>

            <?php if ($error): ?>
                <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           required autofocus autocomplete="username"
                           value="<?= htmlspecialchars((string)($_POST['username'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           required autocomplete="current-password">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary" style="width:100%;">Sign in</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
