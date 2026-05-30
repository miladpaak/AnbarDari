<?php
function app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile)) {
        $configFile = __DIR__ . '/../config/config.sample.php';
    }
    $config = require $configFile;
    date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
    return $config;
}

function base_url(string $path = ''): string
{
    $base = rtrim(app_config()['base_url'] ?? '', '/');
    if ($base === '') {
        $script = str_replace('/public/index.php', '', $_SERVER['SCRIPT_NAME'] ?? '/DP');
        $base = rtrim($script, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $route): never
{
    header('Location: ' . base_url($route));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
            http_response_code(419);
            exit('درخواست نامعتبر است. صفحه را دوباره بارگذاری کنید.');
        }
    }
}

function flash(?string $message = null, string $type = 'success'): ?array
{
    if ($message !== null) {
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $flash;
}

function require_login(): array
{
    if (empty($_SESSION['user'])) {
        redirect('login');
    }
    return $_SESSION['user'];
}

function can(string $permission): bool
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    $map = [
        'manager' => ['view', 'manage_items', 'stock_in', 'stock_out', 'transfer', 'reports', 'contacts'],
        'keeper' => ['view', 'stock_in', 'stock_out', 'transfer'],
        'viewer' => ['view', 'reports'],
    ];
    return in_array($permission, $map[$user['role']] ?? [], true);
}

function require_permission(string $permission): void
{
    if (!can($permission)) {
        http_response_code(403);
        exit('شما به این بخش دسترسی ندارید.');
    }
}

function moneyless_number($value): string
{
    return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
}

function jalali_like_datetime(?string $date): string
{
    if (!$date) {
        return '-';
    }
    return date('Y/m/d H:i', strtotime($date));
}
