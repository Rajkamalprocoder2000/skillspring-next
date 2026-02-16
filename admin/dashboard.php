<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

$admin = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'approve_course') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $stmt = db()->prepare("
          UPDATE courses
          SET status = 'approved', published_at = NOW(), rejection_reason = NULL
          WHERE id = ? AND status IN ('pending', 'rejected')
        ");
        $stmt->execute([$courseId]);
        $log = db()->prepare('INSERT INTO course_approval_logs (course_id, admin_id, action, note) VALUES (?, ?, "approved", ?)');
        $log->execute([$courseId, (int) $admin['id'], trim($_POST['note'] ?? '')]);
        flash_set('success', 'Course approved.');
        redirect('/admin/dashboard.php');
    }

    if ($action === 'reject_course') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Rejected by admin.');
        $stmt = db()->prepare("UPDATE courses SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $courseId]);
        $log = db()->prepare('INSERT INTO course_approval_logs (course_id, admin_id, action, note) VALUES (?, ?, "rejected", ?)');
        $log->execute([$courseId, (int) $admin['id'], $reason]);
        flash_set('success', 'Course rejected.');
        redirect('/admin/dashboard.php');
    }

    if ($action === 'toggle_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 0);
        $stmt = db()->prepare('UPDATE users SET is_active = ? WHERE id = ? AND id <> ?');
        $stmt->execute([$isActive, $userId, (int) $admin['id']]);
        flash_set('success', 'User status updated.');
        redirect('/admin/dashboard.php');
    }

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = db()->prepare('INSERT IGNORE INTO categories (name) VALUES (?)');
            $stmt->execute([$name]);
            flash_set('success', 'Category saved.');
        }
        redirect('/admin/dashboard.php');
    }
}

$stats = [
    'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'courses' => (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
    'pending' => (int) db()->query("SELECT COUNT(*) FROM courses WHERE status = 'pending'")->fetchColumn(),
    'enrollments' => (int) db()->query('SELECT COUNT(*) FROM enrollments')->fetchColumn(),
];

$pendingCourses = db()->query("
  SELECT c.id, c.title, c.price, c.created_at, u.name AS instructor_name
  FROM courses c
  JOIN users u ON u.id = c.instructor_id
  WHERE c.status = 'pending'
  ORDER BY c.created_at ASC
")->fetchAll();

$users = db()->query('SELECT id, name, email, role, is_active FROM users ORDER BY created_at DESC LIMIT 100')->fetchAll();
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

require __DIR__ . '/../templates/header.php';
?>
<h1>Admin Dashboard</h1>
<div class="grid">
  <div class="card"><h3>Total Users</h3><p><?= $stats['users'] ?></p></div>
  <div class="card"><h3>Total Courses</h3><p><?= $stats['courses'] ?></p></div>
  <div class="card"><h3>Pending Approvals</h3><p><?= $stats['pending'] ?></p></div>
  <div class="card"><h3>Total Enrollments</h3><p><?= $stats['enrollments'] ?></p></div>
</div>

<section class="card">
  <h2>Course Approvals</h2>
  <?php if (!$pendingCourses): ?><p class="muted">No pending courses.</p><?php endif; ?>
  <?php foreach ($pendingCourses as $course): ?>
    <article class="card">
      <h3><?= e($course['title']) ?></h3>
      <p class="muted">Instructor: <?= e($course['instructor_name']) ?> | $<?= number_format((float) $course['price'], 2) ?></p>
      <form method="post">
        <input type="hidden" name="action" value="approve_course">
        <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
        <input type="text" name="note" placeholder="Approval note (optional)">
        <button class="btn success" type="submit">Approve</button>
      </form>
      <form method="post">
        <input type="hidden" name="action" value="reject_course">
        <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
        <input type="text" name="reason" placeholder="Rejection reason" required>
        <button class="btn danger" type="submit">Reject</button>
      </form>
    </article>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>User Management</h2>
  <div class="table-wrap">
    <table>
      <thead>
      <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['role']) ?></td>
          <td><?= (int) $u['is_active'] === 1 ? 'Active' : 'Disabled' ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="action" value="toggle_user">
              <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="is_active" value="<?= (int) $u['is_active'] === 1 ? 0 : 1 ?>">
              <button class="btn secondary" type="submit"><?= (int) $u['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card">
  <h2>Categories</h2>
  <form method="post">
    <input type="hidden" name="action" value="add_category">
    <label for="name">New Category</label>
    <input id="name" name="name" required>
    <button class="btn" type="submit">Add</button>
  </form>
  <p class="muted">Current: <?= e(implode(', ', array_column($categories, 'name'))) ?></p>
</section>
<?php require __DIR__ . '/../templates/footer.php'; ?>

