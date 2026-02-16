USE skillspring;

INSERT INTO users (name, email, password_hash, role, is_active)
VALUES
  ('Admin User', 'admin@skillspring.test', '$2y$10$FVDPneWTvShKOhnfK3hUSu3xdO5nQHjz.c8N7TXvEXrM8HF2JX6ha', 'admin', 1),
  ('Instructor One', 'instructor@skillspring.test', '$2y$10$TYjGx/CjxQJd4JgqMtFxbek2WEAiJlR7CT2KwWNMRyvZXh.tuKtoi', 'instructor', 1),
  ('Student One', 'student@skillspring.test', '$2y$10$u.g.Use5IRlwsixvQAY8HeuJOibcUUxtNbGzF2JPNWQ5sgzQ2COCG', 'student', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

