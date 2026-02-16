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
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="container nav">
    <a class="logo" href="/index.php"><?= e(APP_NAME) ?></a>
    <nav>
      <a href="/courses.php">Courses</a>
      <?php if ($user): ?>
        <?php if ($user['role'] === 'student'): ?><a href="/student/dashboard.php">Student</a><?php endif; ?>
        <?php if ($user['role'] === 'instructor'): ?><a href="/instructor/dashboard.php">Instructor</a><?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?><a href="/admin/dashboard.php">Admin</a><?php endif; ?>
        <a href="/logout.php">Logout</a>
      <?php else: ?>
        <a href="/login.php">Login</a>
        <a href="/register.php">Signup</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container">

