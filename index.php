<?php
/**
 * index.php — User creation form.
 */

declare(strict_types=1);

session_start(); // Session is used to store the CSRF token
$customers = get_customers();

// Issue a CSRF token if one does not exist yet
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash_error   = $_SESSION['flash_error']   ?? '';
$flash_warning = $_SESSION['flash_warning'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_warning']);

// Repopulate form fields after a validation error
$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CES Portal — Create User</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-wrapper">

<header class="site-header">
    <h1>CES — Active Directory User Portal</h1>
</header>

<main>
    <div class="container">
        <div class="card">
            <h2 class="card__title">Create Active Directory User</h2>

            <?php if ($flash_error): ?>
                <div class="alert alert--error"><?= htmlspecialchars($flash_error) ?></div>
            <?php endif; ?>

            <?php if ($flash_warning): ?>
                <div class="alert alert--warning"><?= htmlspecialchars($flash_warning) ?></div>
            <?php endif; ?>

            <form action="process.php" method="post" novalidate>
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <!-- Customer -->
                <div class="form-group">
                    <label for="customer">Customer <span class="required">*</span></label>
                    <select name="customer" id="customer" required>
                        <option value="">— Select a customer —</option>
                        <?php foreach ($customers as $key => $c): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                <?= (($old['customer'] ?? '') === $key) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-hint">Determines the OU and group membership for the new account.</span>
                </div>

                <!-- Name row -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               required maxlength="64"
                               pattern="[A-Za-z\-' ]+"
                               title="Letters, hyphens, apostrophes and spaces only"
                               placeholder="e.g. Jane"
                               value="<?= htmlspecialchars($old['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name"
                               required maxlength="64"
                               pattern="[A-Za-z\-' ]+"
                               title="Letters, hyphens, apostrophes and spaces only"
                               placeholder="e.g. Smith"
                               value="<?= htmlspecialchars($old['last_name'] ?? '') ?>">
                    </div>
                </div>

                <!-- Optional fields row -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="job_title">Job Title</label>
                        <input type="text" id="job_title" name="job_title"
                               maxlength="128"
                               placeholder="e.g. Systems Administrator"
                               value="<?= htmlspecialchars($old['job_title'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department"
                               maxlength="128"
                               placeholder="e.g. IT"
                               value="<?= htmlspecialchars($old['department'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <a href="index.php" class="btn btn--secondary">Clear</a>
                    <button type="submit" class="btn btn--primary">Create User</button>
                </div>
            </form>
        </div><!-- .card -->
    </div><!-- .container -->
</main>

<footer class="site-footer">
    CES User Portal &mdash; Accounts are created with <em>Change password at next logon</em> enabled.
</footer>

</body>
</html>
