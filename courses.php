<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/utils.php';

$q = trim($_GET['q'] ?? '');
$categoryId = (int) ($_GET['category_id'] ?? 0);
$level = trim($_GET['level'] ?? '');

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$sql = "
  SELECT c.id, c.title, c.slug, c.description, c.price, c.level, cat.name AS category_name, u.name AS instructor_name,
         COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS reviews_count
  FROM courses c
  JOIN users u ON u.id = c.instructor_id
  LEFT JOIN categories cat ON cat.id = c.category_id
  LEFT JOIN reviews r ON r.course_id = c.id
  WHERE c.status = 'approved'
";
$params = [];

if ($q !== '') {
    $sql .= " AND (c.title ILIKE :q OR c.description ILIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($categoryId > 0) {
    $sql .= " AND c.category_id = :category_id";
    $params[':category_id'] = $categoryId;
}
if (in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
    $sql .= " AND c.level = :level";
    $params[':level'] = $level;
}

$sql .= " GROUP BY c.id, cat.name, u.name ORDER BY c.created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

require __DIR__ . '/templates/header.php';
?>
<h1>Course Marketplace</h1>
<form method="get" class="card">
  <div class="row">
    <div>
      <label for="q">Search</label>
      <input id="q" name="q" value="<?= e($q) ?>" placeholder="Search by title or keyword">
    </div>
    <div>
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id">
        <option value="0">All categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
            <?= e($cat['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="level">Level</label>
      <select id="level" name="level">
        <option value="">All levels</option>
        <?php foreach (['beginner', 'intermediate', 'advanced'] as $lv): ?>
          <option value="<?= $lv ?>" <?= $level === $lv ? 'selected' : '' ?>><?= e(ucfirst($lv)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <button class="btn" type="submit">Apply Filters</button>
</form>

<div class="grid">
  <?php if (!$courses): ?>
    <p class="muted">No courses found.</p>
  <?php endif; ?>
  <?php foreach ($courses as $course): ?>
    <article class="card">
      <span class="pill"><?= e($course['category_name'] ?? 'General') ?></span>
      <h3><?= e($course['title']) ?></h3>
      <p class="muted"><?= e(mb_strimwidth($course['description'], 0, 150, '...')) ?></p>
      <p class="muted"><?= e($course['instructor_name']) ?> | <?= e(ucfirst($course['level'])) ?></p>
      <p class="muted">Rating: <?= number_format((float) $course['avg_rating'], 1) ?> (<?= (int) $course['reviews_count'] ?>)</p>
      <p class="price">$<?= number_format((float) $course['price'], 2) ?></p>
      <a class="btn secondary" href="<?= e(app_url('/course.php?slug=' . urlencode((string) $course['slug']))) ?>">View Details</a>
    </article>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
