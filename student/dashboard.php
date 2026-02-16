<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course.php';
require_once __DIR__ . '/../includes/utils.php';

$user = require_role(['student']);

$stmt = db()->prepare("
  SELECT c.id, c.title, c.slug, c.price, c.level, e.enrolled_at
  FROM enrollments e
  JOIN courses c ON c.id = e.course_id
  WHERE e.student_id = ?
  ORDER BY e.enrolled_at DESC
");
$stmt->execute([(int) $user['id']]);
$courses = $stmt->fetchAll();

require __DIR__ . '/../templates/header.php';
?>
<h1>Student Dashboard</h1>
<p class="muted">Your enrolled courses and progress.</p>
<div class="grid">
  <?php if (!$courses): ?><p class="muted">You have not enrolled in any courses yet.</p><?php endif; ?>
  <?php foreach ($courses as $course): ?>
    <?php $progress = course_progress_percent((int) $user['id'], (int) $course['id']); ?>
    <article class="card">
      <h3><?= e($course['title']) ?></h3>
      <p class="muted"><?= e(ucfirst($course['level'])) ?></p>
      <p class="muted">Progress: <?= number_format($progress, 1) ?>%</p>
      <a class="btn" href="<?= e(app_url('/student/player.php?course_id=' . (int) $course['id'])) ?>">Continue Learning</a>
      <a class="btn secondary" href="<?= e(app_url('/student/review.php?course_id=' . (int) $course['id'])) ?>">Review</a>
    </article>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
