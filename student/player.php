<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course.php';
require_once __DIR__ . '/../includes/utils.php';

$user = require_role(['student']);
$courseId = (int) ($_GET['course_id'] ?? 0);
$lessonId = (int) ($_GET['lesson_id'] ?? 0);

if ($courseId <= 0) {
    http_response_code(400);
    echo 'Invalid course';
    exit;
}
if (!user_enrolled_in_course((int) $user['id'], $courseId)) {
    http_response_code(403);
    echo 'You are not enrolled in this course.';
    exit;
}

$courseStmt = db()->prepare('SELECT id, title FROM courses WHERE id = ?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch();
if (!$course) {
    http_response_code(404);
    echo 'Course not found';
    exit;
}

$lessonStmt = db()->prepare("
  SELECT cl.id, cl.title, cl.content_type, cl.video_url, cl.body_text, cl.sort_order, cs.id AS section_id, cs.title AS section_title
  FROM course_lessons cl
  JOIN course_sections cs ON cs.id = cl.section_id
  WHERE cs.course_id = ?
  ORDER BY cs.sort_order, cl.sort_order
");
$lessonStmt->execute([$courseId]);
$lessons = $lessonStmt->fetchAll();
if (!$lessons) {
    require __DIR__ . '/../templates/header.php';
    echo '<div class="alert error">No lessons available yet.</div>';
    require __DIR__ . '/../templates/footer.php';
    exit;
}

if ($lessonId <= 0) {
    $lessonId = (int) $lessons[0]['id'];
}

$activeLesson = null;
foreach ($lessons as $lesson) {
    if ((int) $lesson['id'] === $lessonId) {
        $activeLesson = $lesson;
        break;
    }
}
if (!$activeLesson) {
    $activeLesson = $lessons[0];
    $lessonId = (int) $activeLesson['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_lesson'])) {
    $mark = db()->prepare('INSERT INTO lesson_progress (student_id, lesson_id, is_completed) VALUES (?, ?, 1) ON CONFLICT (student_id, lesson_id) DO NOTHING');
    $mark->execute([(int) $user['id'], $lessonId]);
    flash_set('success', 'Lesson marked as completed.');
    redirect('/student/player.php?course_id=' . $courseId . '&lesson_id=' . $lessonId);
}

$doneStmt = db()->prepare('SELECT lesson_id FROM lesson_progress WHERE student_id = ?');
$doneStmt->execute([(int) $user['id']]);
$doneIds = array_map('intval', array_column($doneStmt->fetchAll(), 'lesson_id'));
$doneLookup = array_flip($doneIds);

require __DIR__ . '/../templates/header.php';
?>
<h1><?= e($course['title']) ?> - Player</h1>
<div class="row">
  <section class="card">
    <h2><?= e($activeLesson['title']) ?></h2>
    <?php if ($activeLesson['content_type'] === 'video' && !empty($activeLesson['video_url'])): ?>
      <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:8px;">
        <iframe src="<?= e($activeLesson['video_url']) ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;" allowfullscreen></iframe>
      </div>
    <?php else: ?>
      <article><?= nl2br(e((string) $activeLesson['body_text'])) ?></article>
    <?php endif; ?>
    <form method="post">
      <button class="btn success" type="submit" name="complete_lesson" value="1">Mark Completed</button>
    </form>
  </section>
  <aside class="card">
    <h3>Lessons</h3>
    <ul>
      <?php foreach ($lessons as $lesson): ?>
        <?php $isDone = isset($doneLookup[(int) $lesson['id']]); ?>
        <li>
          <a href="<?= e(app_url('/student/player.php?course_id=' . $courseId . '&lesson_id=' . (int) $lesson['id'])) ?>">
            <?= e($lesson['title']) ?>
          </a>
          <?= $isDone ? '(done)' : '' ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>
<?php require __DIR__ . '/../templates/footer.php'; ?>
