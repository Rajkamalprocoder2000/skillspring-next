<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/course.php';
require_once __DIR__ . '/includes/utils.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    echo 'Course not found';
    exit;
}

$stmt = db()->prepare("
  SELECT c.*, u.name AS instructor_name, cat.name AS category_name
  FROM courses c
  JOIN users u ON u.id = c.instructor_id
  LEFT JOIN categories cat ON cat.id = c.category_id
  WHERE c.slug = ? AND c.status = 'approved'
");
$stmt->execute([$slug]);
$course = $stmt->fetch();
if (!$course) {
    http_response_code(404);
    echo 'Course not found';
    exit;
}

$sectionsStmt = db()->prepare("
  SELECT cs.id, cs.title, cs.sort_order, cl.id AS lesson_id, cl.title AS lesson_title, cl.is_preview, cl.sort_order AS lesson_order
  FROM course_sections cs
  LEFT JOIN course_lessons cl ON cl.section_id = cs.id
  WHERE cs.course_id = ?
  ORDER BY cs.sort_order, cl.sort_order
");
$sectionsStmt->execute([(int) $course['id']]);
$rows = $sectionsStmt->fetchAll();

$sections = [];
foreach ($rows as $row) {
    $sectionId = (int) $row['id'];
    if (!isset($sections[$sectionId])) {
        $sections[$sectionId] = [
            'title' => $row['title'],
            'lessons' => [],
        ];
    }
    if ($row['lesson_id']) {
        $sections[$sectionId]['lessons'][] = [
            'id' => (int) $row['lesson_id'],
            'title' => $row['lesson_title'],
            'is_preview' => (int) $row['is_preview'] === 1,
        ];
    }
}

$reviewsStmt = db()->prepare("
  SELECT r.rating, r.comment, r.created_at, u.name
  FROM reviews r
  JOIN users u ON u.id = r.student_id
  WHERE r.course_id = ?
  ORDER BY r.created_at DESC
  LIMIT 12
");
$reviewsStmt->execute([(int) $course['id']]);
$reviews = $reviewsStmt->fetchAll();

$avgStmt = db()->prepare('SELECT COALESCE(AVG(rating),0) AS avg_rating, COUNT(*) AS total FROM reviews WHERE course_id = ?');
$avgStmt->execute([(int) $course['id']]);
$rating = $avgStmt->fetch();

$user = current_user();
$isEnrolled = false;
if ($user && $user['role'] === 'student') {
    $isEnrolled = user_enrolled_in_course((int) $user['id'], (int) $course['id']);
}

require __DIR__ . '/templates/header.php';
?>
<article class="card">
  <span class="pill"><?= e($course['category_name'] ?? 'General') ?></span>
  <h1><?= e($course['title']) ?></h1>
  <p class="muted">By <?= e($course['instructor_name']) ?> | <?= e(ucfirst($course['level'])) ?></p>
  <p class="muted">Rating: <?= number_format((float) $rating['avg_rating'], 1) ?> (<?= (int) $rating['total'] ?> reviews)</p>
  <p><?= nl2br(e($course['description'])) ?></p>
  <p class="price">$<?= number_format((float) $course['price'], 2) ?></p>
  <?php if ($user && $user['role'] === 'student'): ?>
    <?php if ($isEnrolled): ?>
      <a class="btn success" href="<?= e(app_url('/student/player.php?course_id=' . (int) $course['id'])) ?>">Go to Course Player</a>
    <?php else: ?>
      <a class="btn" href="<?= e(app_url('/payment.php?course_id=' . (int) $course['id'] . '&mode=mock')) ?>">Enroll (Mock Payment)</a>
    <?php endif; ?>
  <?php else: ?>
    <a class="btn" href="<?= e(app_url('/login.php')) ?>">Login as Student to Enroll</a>
  <?php endif; ?>
</article>

<section class="card">
  <h2>Course Content</h2>
  <?php foreach ($sections as $section): ?>
    <h3><?= e($section['title']) ?></h3>
    <ul>
      <?php foreach ($section['lessons'] as $lesson): ?>
        <li>
          <?= e($lesson['title']) ?>
          <?php if ($lesson['is_preview']): ?><span class="pill">Preview</span><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>Reviews</h2>
  <?php if (!$reviews): ?><p class="muted">No reviews yet.</p><?php endif; ?>
  <?php foreach ($reviews as $review): ?>
    <article>
      <strong><?= e($review['name']) ?></strong> - <?= (int) $review['rating'] ?>/5
      <p><?= e($review['comment'] ?? '') ?></p>
    </article>
    <hr>
  <?php endforeach; ?>
</section>
<?php require __DIR__ . '/templates/footer.php'; ?>
