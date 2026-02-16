<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';

$user = require_role(['instructor']);
$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_course') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $level = (string) ($_POST['level'] ?? 'beginner');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($title === '' || $description === '') {
            $error = 'Title and description are required.';
        } else {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();
            $stmt = db()->prepare("
              INSERT INTO courses (instructor_id, category_id, title, slug, description, price, level, status)
              VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')
            ");
            $stmt->execute([(int) $user['id'], $categoryId ?: null, $title, $slug, $description, $price, $level]);
            $newId = (int) db()->lastInsertId();
            flash_set('success', 'Course created as draft.');
            redirect('/instructor/course_builder.php?course_id=' . $newId);
        }
    }

    if ($courseId > 0 && $action === 'add_section') {
        $title = trim($_POST['section_title'] ?? '');
        if ($title === '') {
            $error = 'Section title is required.';
        } else {
            $stmt = db()->prepare('INSERT INTO course_sections (course_id, title, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$courseId, $title, (int) ($_POST['sort_order'] ?? 0)]);
            flash_set('success', 'Section added.');
            redirect('/instructor/course_builder.php?course_id=' . $courseId);
        }
    }

    if ($courseId > 0 && $action === 'add_lesson') {
        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $title = trim($_POST['lesson_title'] ?? '');
        $contentType = (string) ($_POST['content_type'] ?? 'video');
        $videoUrl = trim($_POST['video_url'] ?? '');
        $bodyText = trim($_POST['body_text'] ?? '');
        if ($sectionId <= 0 || $title === '') {
            $error = 'Section and lesson title are required.';
        } else {
            $stmt = db()->prepare("
              INSERT INTO course_lessons (section_id, title, content_type, video_url, body_text, duration_seconds, sort_order, is_preview)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $sectionId,
                $title,
                $contentType,
                $videoUrl !== '' ? $videoUrl : null,
                $bodyText !== '' ? $bodyText : null,
                (int) ($_POST['duration_seconds'] ?? 0),
                (int) ($_POST['lesson_sort_order'] ?? 0),
                isset($_POST['is_preview']) ? 1 : 0,
            ]);
            flash_set('success', 'Lesson added.');
            redirect('/instructor/course_builder.php?course_id=' . $courseId);
        }
    }

    if ($courseId > 0 && $action === 'submit_for_approval') {
        $stmt = db()->prepare("UPDATE courses SET status = 'pending', rejection_reason = NULL WHERE id = ? AND instructor_id = ?");
        $stmt->execute([$courseId, (int) $user['id']]);
        flash_set('success', 'Course submitted for admin approval.');
        redirect('/instructor/course_builder.php?course_id=' . $courseId);
    }
}

$course = null;
$sections = [];
if ($courseId > 0) {
    $stmt = db()->prepare('SELECT * FROM courses WHERE id = ? AND instructor_id = ?');
    $stmt->execute([$courseId, (int) $user['id']]);
    $course = $stmt->fetch();

    if ($course) {
        $secStmt = db()->prepare("
          SELECT cs.id, cs.title, cs.sort_order, cl.id AS lesson_id, cl.title AS lesson_title
          FROM course_sections cs
          LEFT JOIN course_lessons cl ON cl.section_id = cs.id
          WHERE cs.course_id = ?
          ORDER BY cs.sort_order, cl.sort_order
        ");
        $secStmt->execute([$courseId]);
        foreach ($secStmt->fetchAll() as $row) {
            $sid = (int) $row['id'];
            if (!isset($sections[$sid])) {
                $sections[$sid] = ['id' => $sid, 'title' => $row['title'], 'lessons' => []];
            }
            if ($row['lesson_id']) {
                $sections[$sid]['lessons'][] = $row['lesson_title'];
            }
        }
    }
}

require __DIR__ . '/../templates/header.php';
?>
<h1>Course Builder</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<section class="card">
  <h2>Create New Course</h2>
  <form method="post">
    <input type="hidden" name="action" value="create_course">
    <div class="row">
      <div>
        <label for="title">Course Title</label>
        <input id="title" name="title" required>
      </div>
      <div>
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
          <option value="0">General</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>"><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="level">Level</label>
        <select id="level" name="level">
          <option value="beginner">Beginner</option>
          <option value="intermediate">Intermediate</option>
          <option value="advanced">Advanced</option>
        </select>
      </div>
      <div>
        <label for="price">Price (USD)</label>
        <input id="price" name="price" type="number" min="0" step="0.01" value="0">
      </div>
    </div>
    <label for="description">Description</label>
    <textarea id="description" name="description" required></textarea>
    <button class="btn" type="submit">Create Draft</button>
  </form>
</section>

<?php if ($course): ?>
  <section class="card">
    <h2>Editing: <?= e($course['title']) ?></h2>
    <p>Status: <strong><?= e($course['status']) ?></strong></p>
    <?php if (!empty($course['rejection_reason'])): ?>
      <p class="alert error">Rejection reason: <?= e((string) $course['rejection_reason']) ?></p>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="action" value="submit_for_approval">
      <button class="btn success" type="submit">Submit for Approval</button>
    </form>
  </section>

  <section class="card">
    <h2>Add Section</h2>
    <form method="post">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="action" value="add_section">
      <div class="row">
        <div>
          <label for="section_title">Section Title</label>
          <input id="section_title" name="section_title" required>
        </div>
        <div>
          <label for="sort_order">Sort Order</label>
          <input id="sort_order" name="sort_order" type="number" value="0">
        </div>
      </div>
      <button class="btn" type="submit">Add Section</button>
    </form>
  </section>

  <section class="card">
    <h2>Add Lesson</h2>
    <form method="post">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="action" value="add_lesson">
      <div class="row">
        <div>
          <label for="section_id">Section</label>
          <select id="section_id" name="section_id" required>
            <option value="">Select section</option>
            <?php foreach ($sections as $section): ?>
              <option value="<?= (int) $section['id'] ?>"><?= e($section['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="lesson_title">Lesson Title</label>
          <input id="lesson_title" name="lesson_title" required>
        </div>
        <div>
          <label for="content_type">Content Type</label>
          <select id="content_type" name="content_type">
            <option value="video">Video</option>
            <option value="text">Text</option>
          </select>
        </div>
      </div>
      <label for="video_url">Video URL (YouTube/Vimeo embed URL)</label>
      <input id="video_url" name="video_url">
      <label for="body_text">Text Content</label>
      <textarea id="body_text" name="body_text"></textarea>
      <div class="row">
        <div>
          <label for="duration_seconds">Duration (sec)</label>
          <input id="duration_seconds" name="duration_seconds" type="number" value="0">
        </div>
        <div>
          <label for="lesson_sort_order">Sort Order</label>
          <input id="lesson_sort_order" name="lesson_sort_order" type="number" value="0">
        </div>
      </div>
      <label><input type="checkbox" name="is_preview" value="1"> Mark as preview lesson</label>
      <br><br>
      <button class="btn" type="submit">Add Lesson</button>
    </form>
  </section>

  <section class="card">
    <h2>Current Curriculum</h2>
    <?php foreach ($sections as $section): ?>
      <h3><?= e($section['title']) ?></h3>
      <ul>
        <?php foreach ($section['lessons'] as $lessonTitle): ?>
          <li><?= e($lessonTitle) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/../templates/footer.php'; ?>

