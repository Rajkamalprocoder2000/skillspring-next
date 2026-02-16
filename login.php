<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/utils.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $error = 'Invalid credentials.';
    } elseif ((int) $user['is_active'] !== 1) {
        $error = 'Account is deactivated.';
    } else {
        login_user((int) $user['id']);
        redirect('/index.php');
    }
}

require __DIR__ . '/templates/header.php';
?>
<h1>Login</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card">
  <label for="email">Email</label>
  <input id="email" name="email" type="email" required>

  <label for="password">Password</label>
  <input id="password" name="password" type="password" required>

  <button class="btn" type="submit">Login</button>
</form>
<?php require __DIR__ . '/templates/footer.php'; ?>

