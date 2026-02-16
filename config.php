<?php
declare(strict_types=1);

session_start();

const APP_NAME = 'SkillSpring';

const DB_HOST = '127.0.0.1';
const DB_NAME = 'skillspring';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

