<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function user_enrolled_in_course(int $userId, int $courseId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?');
    $stmt->execute([$userId, $courseId]);
    return (bool) $stmt->fetchColumn();
}

function course_progress_percent(int $studentId, int $courseId): float
{
    $stmt = db()->prepare("
      SELECT COUNT(cl.id) AS total_lessons,
             SUM(CASE WHEN lp.id IS NOT NULL THEN 1 ELSE 0 END) AS completed_lessons
      FROM course_lessons cl
      JOIN course_sections cs ON cs.id = cl.section_id
      LEFT JOIN lesson_progress lp ON lp.lesson_id = cl.id AND lp.student_id = ?
      WHERE cs.course_id = ?
    ");
    $stmt->execute([$studentId, $courseId]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['total_lessons'] === 0) {
        return 0.0;
    }
    return ((int) $row['completed_lessons'] / (int) $row['total_lessons']) * 100;
}

