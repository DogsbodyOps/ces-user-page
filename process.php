<?php
/**
 * process.php — Handles the user-creation form submission.
 *
 * Flow:
 *   1. Verify CSRF token.
 *   2. Validate and sanitise all user input.
 *   3. Write parameters to a secure temporary file (avoids shell injection).
 *   4. Call the PowerShell script, passing only the temp-file path.
 *   5. Parse the JSON result and render the success / failure page.
 */

declare(strict_types=1);

session_start(); // Session is used to store and validate the CSRF token

// ── CSRF check ────────────────────────────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])
) {
    $_SESSION['flash_error'] = 'Invalid request token. Please try again.';
    header('Location: index.php');
    exit;
}

// Rotate CSRF token after each submission
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Load customer config ──────────────────────────────────────────────────────
require_once __DIR__ . '/config/customers.php';
$customers = get_customers();

// ── Collect & validate input ──────────────────────────────────────────────────
$customerKey = (string)($_POST['customer']   ?? '');
$firstName   = trim((string)($_POST['first_name']  ?? ''));
$lastName    = trim((string)($_POST['last_name']   ?? ''));
$jobTitle    = trim((string)($_POST['job_title']   ?? ''));
$department  = trim((string)($_POST['department']  ?? ''));

$errors = [];

if (!array_key_exists($customerKey, $customers)) {
    $errors[] = 'Please select a valid customer.';
}

if (!preg_match("/^[A-Za-z\-' ]{1,64}$/u", $firstName)) {
    $errors[] = 'First name may only contain letters, hyphens, apostrophes and spaces (max 64 characters).';
}

if (!preg_match("/^[A-Za-z\-' ]{1,64}$/u", $lastName)) {
    $errors[] = 'Last name may only contain letters, hyphens, apostrophes and spaces (max 64 characters).';
}

// Sanitise optional free-text fields — strip characters that are not
// printable alphanumerics, spaces or common punctuation.
$jobTitle   = preg_replace('/[^\p{L}\p{N}\s\-,\.()&]/u', '', $jobTitle);
$department = preg_replace('/[^\p{L}\p{N}\s\-,\.()&]/u', '', $department);

if ($errors) {
    $_SESSION['flash_error'] = implode(' ', $errors);
    $_SESSION['old_input'] = [
        'customer'   => $customerKey,
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'job_title'  => $jobTitle,
        'department' => $department,
    ];
    header('Location: index.php');
    exit;
}

$customer = $customers[$customerKey];

// ── Write parameters to a temp file ──────────────────────────────────────────
// Passing data via a temp file keeps the command line free of user-supplied
// values and eliminates the risk of shell injection.

$params = json_encode([
    'FirstName'  => $firstName,
    'LastName'   => $lastName,
    'OU'         => $customer['ou'],
    'Groups'     => implode(',', $customer['groups']),
    'JobTitle'   => $jobTitle,
    'Department' => $department,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

$tmpFile = tempnam(sys_get_temp_dir(), 'aduser_');
if ($tmpFile === false) {
    die('Could not create temporary file.');
}
file_put_contents($tmpFile, $params, LOCK_EX);
chmod($tmpFile, 0600);

// ── Locate the PowerShell script ──────────────────────────────────────────────
$scriptPath = realpath(__DIR__ . '/scripts/create_ad_user.ps1');
if ($scriptPath === false || !is_file($scriptPath)) {
    @unlink($tmpFile);
    die('PowerShell script not found. Please check the server installation.');
}

// ── Execute PowerShell ────────────────────────────────────────────────────────
// Only the temp-file path (an OS-generated random name) is passed on the
// command line; all user data travels through the file.
$cmd = sprintf(
    'powershell.exe -NonInteractive -NoProfile -ExecutionPolicy Bypass -File %s -ParamsFile %s 2>&1',
    escapeshellarg($scriptPath),
    escapeshellarg($tmpFile)
);

$raw = shell_exec($cmd);

// The script removes the temp file itself; clean up defensively anyway.
if (file_exists($tmpFile)) {
    @unlink($tmpFile);
}

// ── Parse result ──────────────────────────────────────────────────────────────
$result  = null;
$success = false;

if ($raw !== null) {
    // Find the JSON object within the output (ignore any warning lines)
    if (preg_match('/\{.*\}/s', $raw, $m)) {
        $result = json_decode($m[0], true);
        $success = (bool)($result['success'] ?? false);
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
$pageTitle = $success ? 'User Created Successfully' : 'User Creation Failed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CES Portal — <?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-wrapper">

<header class="site-header">
    <h1>CES — Active Directory User Portal</h1>
</header>

<main>
    <div class="container">
        <div class="card">
            <h2 class="card__title"><?= htmlspecialchars($pageTitle) ?></h2>

            <?php if ($success): ?>

                <div class="alert alert--success">
                    The Active Directory account for
                    <strong><?= htmlspecialchars($result['displayName']) ?></strong>
                    has been created and is ready to use.
                    <br>Please share the credentials below securely with the user — the password
                    will <strong>not</strong> be shown again.
                </div>

                <div class="result-box">
                    <p class="result-box__title">&#10003; New account details</p>
                    <table class="result-table">
                        <tr>
                            <th>Display Name</th>
                            <td><?= htmlspecialchars($result['displayName']) ?></td>
                        </tr>
                        <tr>
                            <th>SAMAccountName (username)</th>
                            <td><?= htmlspecialchars($result['sam']) ?></td>
                        </tr>
                        <tr>
                            <th>User Principal Name</th>
                            <td><?= htmlspecialchars($result['upn']) ?></td>
                        </tr>
                        <tr>
                            <th>Customer</th>
                            <td><?= htmlspecialchars($customer['name']) ?></td>
                        </tr>
                        <tr>
                            <th>Groups added</th>
                            <td><?= htmlspecialchars(implode(', ', $customer['groups'])) ?></td>
                        </tr>
                        <?php if (!empty($jobTitle)): ?>
                        <tr>
                            <th>Job Title</th>
                            <td><?= htmlspecialchars($jobTitle) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($department)): ?>
                        <tr>
                            <th>Department</th>
                            <td><?= htmlspecialchars($department) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>Temporary Password</th>
                            <td>
                                <div class="password-cell">
                                    <span id="pwd"><?= htmlspecialchars($result['password']) ?></span>
                                    <button class="copy-btn" onclick="copyPassword()">Copy</button>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="form-actions" style="margin-top:1.5rem;">
                    <a href="index.php" class="btn btn--primary">Create another user</a>
                </div>

            <?php else: ?>

                <div class="alert alert--error">
                    <strong>The user could not be created.</strong><br>
                    <?php
                    $errMsg = $result['error'] ?? 'Unknown error. Check the server logs for details.';
                    echo htmlspecialchars($errMsg);
                    ?>
                </div>

                <?php if ($raw && $raw !== $errMsg): ?>
                    <details style="margin-top:1rem;">
                        <summary style="cursor:pointer;font-size:.85rem;color:var(--color-text-muted);">
                            Raw script output (for diagnostics)
                        </summary>
                        <pre style="background:#f8fafc;border:1px solid var(--color-border);
                                    border-radius:var(--radius);padding:.75rem;
                                    font-size:.8rem;overflow-x:auto;margin-top:.5rem;"><?= htmlspecialchars((string)$raw) ?></pre>
                    </details>
                <?php endif; ?>

                <div class="form-actions" style="margin-top:1.5rem;">
                    <a href="index.php" class="btn btn--secondary">Go back</a>
                </div>

            <?php endif; ?>

        </div><!-- .card -->
    </div><!-- .container -->
</main>

<footer class="site-footer">
    CES User Portal &mdash; Accounts are created with <em>Change password at next logon</em> enabled.
</footer>

<script>
function copyPassword() {
    var pwd = document.getElementById('pwd').textContent;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(pwd).then(function() {
            var btn = document.querySelector('.copy-btn');
            btn.textContent = 'Copied!';
            setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
        });
    }
}
</script>

</body>
</html>
