<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/utils.php';

$stmt = db()->query("
  SELECT c.id, c.title, c.slug, c.description, c.price, c.level, u.name AS instructor_name,
         COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS reviews_count
  FROM courses c
  JOIN users u ON u.id = c.instructor_id
  LEFT JOIN reviews r ON r.course_id = c.id
  WHERE c.status = 'approved'
  GROUP BY c.id, u.name
  ORDER BY reviews_count DESC, c.created_at DESC
  LIMIT 6
");
$trending = $stmt->fetchAll();

require __DIR__ . '/templates/header.php';
?>
<section class="hero">
  <h1>Learn in-demand skills with <?= e(APP_NAME) ?></h1>
  <p class="muted">Explore expert-led courses, track your progress, and earn completion confidence.</p>
  <a class="btn" href="<?= e(app_url('/courses.php')) ?>">Explore Courses</a>
</section>

<h2>Trending Courses</h2>
<div class="grid">
  <?php foreach ($trending as $course): ?>
    <article class="card">
      <h3><?= e($course['title']) ?></h3>
      <p class="muted"><?= e(mb_strimwidth($course['description'], 0, 150, '...')) ?></p>
      <p class="muted">By <?= e($course['instructor_name']) ?> | <?= e(ucfirst($course['level'])) ?></p>
      <p class="muted">Rating: <?= number_format((float) $course['avg_rating'], 1) ?> (<?= (int) $course['reviews_count'] ?>)</p>
      <p class="price">$<?= number_format((float) $course['price'], 2) ?></p>
      <a class="btn secondary" href="<?= e(app_url('/course.php?slug=' . urlencode((string) $course['slug']))) ?>">View Course</a>
    </article>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
