<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

$user = require_role(['instructor']);

$coursesStmt = db()->prepare("
  SELECT c.id, c.title, c.status, c.price, c.created_at,
         COUNT(DISTINCT e.id) AS enrollments
  FROM courses c
  LEFT JOIN enrollments e ON e.course_id = c.id
  WHERE c.instructor_id = ?
  GROUP BY c.id
  ORDER BY c.created_at DESC
");
$coursesStmt->execute([(int) $user['id']]);
$courses = $coursesStmt->fetchAll();

$earnStmt = db()->prepare("
  SELECT COALESCE(SUM(p.amount),0) AS earnings
  FROM payments p
  JOIN courses c ON c.id = p.course_id
  WHERE c.instructor_id = ? AND p.status = 'success'
");
$earnStmt->execute([(int) $user['id']]);
$earnings = (float) $earnStmt->fetchColumn();

require __DIR__ . '/../templates/header.php';
?>
<h1>Instructor Dashboard</h1>
<div class="card">
  <p><strong>Total Earnings:</strong> $<?= number_format($earnings, 2) ?></p>
  <a class="btn" href="<?= e(app_url('/instructor/course_builder.php')) ?>">Create / Manage Courses</a>
</div>

<div class="table-wrap">
  <table>
    <thead>
    <tr>
      <th>Title</th>
      <th>Status</th>
      <th>Price</th>
      <th>Enrollments</th>
      <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($courses as $course): ?>
      <tr>
        <td><?= e($course['title']) ?></td>
        <td><?= e($course['status']) ?></td>
        <td>$<?= number_format((float) $course['price'], 2) ?></td>
        <td><?= (int) $course['enrollments'] ?></td>
        <td><a href="<?= e(app_url('/instructor/course_builder.php?course_id=' . (int) $course['id'])) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
