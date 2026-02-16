<?php
declare(strict_types=1);

session_start();

const APP_NAME = 'SkillSpring';
const DEFAULT_APP_BASE_PATH = '';

const DB_HOST = '';
const DB_PORT = '5432';
const DB_NAME = 'postgres';
const DB_USER = '';
const DB_PASS = '';
const DB_SSLMODE = 'require';

function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function app_base_path(): string
{
    return env_or_default('APP_BASE_PATH', DEFAULT_APP_BASE_PATH);
}

function app_url(string $path = ''): string
{
    $base = rtrim(app_base_path(), '/');
    if ($path === '') {
        return $base !== '' ? $base : '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $normalized = '/' . ltrim($path, '/');
    return ($base !== '' ? $base : '') . $normalized;
}
