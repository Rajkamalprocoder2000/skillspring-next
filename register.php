<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/utils.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? 'student');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($role, ['student', 'instructor'], true)) {
        $error = 'Invalid role selected.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?) RETURNING id');
            $insert->execute([$name, $email, $hash, $role]);
            login_user((int) $insert->fetchColumn());
            redirect('/index.php');
        }
    }
}

require __DIR__ . '/templates/header.php';
?>
<h1>Create Account</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card">
  <label for="name">Name</label>
  <input id="name" name="name" required>

  <label for="email">Email</label>
  <input id="email" name="email" type="email" required>

  <label for="password">Password</label>
  <input id="password" name="password" type="password" minlength="6" required>

  <label for="role">I am joining as</label>
  <select id="role" name="role">
    <option value="student">Student</option>
    <option value="instructor">Instructor</option>
  </select>

  <button class="btn" type="submit">Sign Up</button>
</form>
<?php require __DIR__ . '/templates/footer.php'; ?>


