<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/course.php';
require_once __DIR__ . '/../includes/utils.php';

$user = require_role(['student']);
$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
if ($courseId <= 0) {
    http_response_code(400);
    echo 'Invalid course';
    exit;
}
if (!user_enrolled_in_course((int) $user['id'], $courseId)) {
    http_response_code(403);
    echo 'Only enrolled students can review this course.';
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

$existingStmt = db()->prepare('SELECT rating, comment FROM reviews WHERE student_id = ? AND course_id = ?');
$existingStmt->execute([(int) $user['id'], $courseId]);
$existing = $existingStmt->fetch();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $error = 'Rating must be between 1 and 5.';
    } else {
        $stmt = db()->prepare("
          INSERT INTO reviews (student_id, course_id, rating, comment)
          VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([(int) $user['id'], $courseId, $rating, $comment]);
        flash_set('success', 'Review saved.');
        redirect('/student/dashboard.php');
    }
}

require __DIR__ . '/../templates/header.php';
?>
<h1>Review: <?= e($course['title']) ?></h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="card">
  <input type="hidden" name="course_id" value="<?= $courseId ?>">
  <label for="rating">Rating (1 to 5)</label>
  <input id="rating" name="rating" type="number" min="1" max="5" value="<?= (int) ($existing['rating'] ?? 5) ?>" required>

  <label for="comment">Comment</label>
  <textarea id="comment" name="comment"><?= e((string) ($existing['comment'] ?? '')) ?></textarea>

  <button class="btn" type="submit">Save Review</button>
</form>
<?php require __DIR__ . '/../templates/footer.php'; ?>
