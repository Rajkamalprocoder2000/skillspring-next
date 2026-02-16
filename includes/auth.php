<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function require_role(array $allowedRoles): array
{
    $user = require_login();
    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $user;
}

function login_user(int $userId): void
{
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    session_unset();
    session_destroy();
}

