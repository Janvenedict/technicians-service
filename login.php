<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Already logged in? go straight to the app
if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (attemptLogin($pdo, $username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log In &mdash; Service Technicians Manager</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>&#128295; Techs Manager</h1>
        <p class="sub">Sign in to manage technicians and jobs</p>

        <?php if ($error): ?>
            <div class="errors"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <div class="field" style="margin-bottom:14px;">
                <label>Username</label>
                <input type="text" name="username" class="btn-block" style="width:100%;" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="field" style="margin-bottom:18px;">
                <label>Password</label>
                <input type="password" name="password" style="width:100%;" required>
            </div>
            <button type="submit" class="btn btn-block">Log In</button>
        </form>

        <div class="hint">
            Default account &mdash; username: <strong>admin</strong>, password: <strong>admin123</strong><br>
            Change this after first login by editing the <code>users</code> table.
        </div>
    </div>
</div>
</body>
</html>
