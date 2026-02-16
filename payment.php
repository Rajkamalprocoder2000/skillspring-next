<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/includes/course.php';

$user = require_role(['student']);
$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$mode = (string) ($_GET['mode'] ?? $_POST['mode'] ?? 'mock');

if ($courseId <= 0) {
    http_response_code(400);
    echo 'Invalid course';
    exit;
}

$stmt = db()->prepare("SELECT id, title, slug, price, status FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course || $course['status'] !== 'approved') {
    http_response_code(404);
    echo 'Course unavailable';
    exit;
}

if (user_enrolled_in_course((int) $user['id'], (int) $course['id'])) {
    redirect('/student/player.php?course_id=' . (int) $course['id']);
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($mode, ['mock', 'stripe', 'razorpay'], true)) {
        $error = 'Unsupported payment mode.';
    } else {
        $providerStatus = $mode === 'mock' ? 'success' : 'pending';
        $paymentRef = strtoupper($mode) . '-' . uniqid();
        $paymentStatus = $mode === 'mock' ? 'mock_paid' : ($mode . '_paid');

        db()->beginTransaction();
        try {
            $pay = db()->prepare("
              INSERT INTO payments (student_id, course_id, provider, amount, status, provider_ref)
              VALUES (?, ?, ?, ?, ?, ?)
            ");
            $pay->execute([
                (int) $user['id'],
                (int) $course['id'],
                $mode,
                (float) $course['price'],
                $providerStatus,
                $paymentRef,
            ]);

            // For MVP, mock mode enrolls immediately. Stripe/Razorpay should enroll via webhook confirmation.
            if ($mode === 'mock') {
                $enroll = db()->prepare("
                  INSERT INTO enrollments (student_id, course_id, payment_status, payment_ref)
                  VALUES (?, ?, ?, ?)
                ");
                $enroll->execute([
                    (int) $user['id'],
                    (int) $course['id'],
                    $paymentStatus,
                    $paymentRef,
                ]);
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }

        if ($mode === 'mock') {
            redirect('/student/player.php?course_id=' . (int) $course['id']);
        }
        flash_set('success', ucfirst($mode) . ' payment initiated. Integrate webhook to complete enrollment.');
        redirect('/course.php?slug=' . urlencode((string) $course['slug']));
    }
}

require __DIR__ . '/templates/header.php';
?>
<h1>Checkout</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card">
  <h2><?= e($course['title']) ?></h2>
  <p class="price">$<?= number_format((float) $course['price'], 2) ?></p>
</div>
<form method="post" class="card">
  <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
  <label for="mode">Payment Provider</label>
  <select id="mode" name="mode">
    <option value="mock" <?= $mode === 'mock' ? 'selected' : '' ?>>Mock (MVP)</option>
    <option value="stripe" <?= $mode === 'stripe' ? 'selected' : '' ?>>Stripe (stub)</option>
    <option value="razorpay" <?= $mode === 'razorpay' ? 'selected' : '' ?>>Razorpay (stub)</option>
  </select>
  <button type="submit" class="btn">Pay & Enroll</button>
</form>
<?php require __DIR__ . '/templates/footer.php'; ?>
