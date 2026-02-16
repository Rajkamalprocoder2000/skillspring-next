CREATE DATABASE IF NOT EXISTS skillspring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE skillspring;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'instructor', 'student') NOT NULL DEFAULT 'student',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  instructor_id INT NOT NULL,
  category_id INT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  description TEXT NOT NULL,
  thumbnail_url VARCHAR(500) NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  level ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
  status ENUM('draft', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
  rejection_reason TEXT NULL,
  published_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_courses_instructor FOREIGN KEY (instructor_id) REFERENCES users(id),
  CONSTRAINT fk_courses_category FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS course_sections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sections_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS course_lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  section_id INT NOT NULL,
  title VARCHAR(180) NOT NULL,
  content_type ENUM('video', 'text') NOT NULL DEFAULT 'video',
  video_url VARCHAR(500) NULL,
  body_text MEDIUMTEXT NULL,
  duration_seconds INT NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_preview TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lessons_section FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  payment_status ENUM('free', 'mock_paid', 'stripe_paid', 'razorpay_paid') NOT NULL DEFAULT 'free',
  payment_ref VARCHAR(190) NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_enrollment_student_course (student_id, course_id),
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES users(id),
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lesson_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  lesson_id INT NOT NULL,
  is_completed TINYINT(1) NOT NULL DEFAULT 1,
  completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_progress_student_lesson (student_id, lesson_id),
  CONSTRAINT fk_progress_student FOREIGN KEY (student_id) REFERENCES users(id),
  CONSTRAINT fk_progress_lesson FOREIGN KEY (lesson_id) REFERENCES course_lessons(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_review_student_course (student_id, course_id),
  CONSTRAINT fk_review_student FOREIGN KEY (student_id) REFERENCES users(id),
  CONSTRAINT fk_review_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  provider ENUM('mock', 'stripe', 'razorpay') NOT NULL DEFAULT 'mock',
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
  provider_ref VARCHAR(190) NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_student FOREIGN KEY (student_id) REFERENCES users(id),
  CONSTRAINT fk_payment_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS course_approval_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  admin_id INT NOT NULL,
  action ENUM('approved', 'rejected') NOT NULL,
  note TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES users(id)
);

INSERT IGNORE INTO categories (id, name) VALUES
  (1, 'Web Development'),
  (2, 'Data Science'),
  (3, 'Design'),
  (4, 'Marketing');

