<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(app_url('/assets/style.css')) ?>">
</head>
<body>
<header class="topbar">
  <div class="container nav">
    <a class="logo" href="<?= e(app_url('/index.php')) ?>"><?= e(APP_NAME) ?></a>
    <nav>
      <a href="<?= e(app_url('/courses.php')) ?>">Courses</a>
      <?php if ($user): ?>
        <?php if ($user['role'] === 'student'): ?><a href="<?= e(app_url('/student/dashboard.php')) ?>">Student</a><?php endif; ?>
        <?php if ($user['role'] === 'instructor'): ?><a href="<?= e(app_url('/instructor/dashboard.php')) ?>">Instructor</a><?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?><a href="<?= e(app_url('/admin/dashboard.php')) ?>">Admin</a><?php endif; ?>
        <a href="<?= e(app_url('/logout.php')) ?>">Logout</a>
      <?php else: ?>
        <a href="<?= e(app_url('/login.php')) ?>">Login</a>
        <a href="<?= e(app_url('/register.php')) ?>">Signup</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">
<?php if ($msg = flash_get('success')): ?>
  <div class="alert success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash_get('error')): ?>
  <div class="alert error"><?= e($msg) ?></div>
<?php endif; ?>
