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
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/DP');
        $script = preg_replace('#/(public/)?index\.php$#', '', $script) ?: '/DP';
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
        'manager' => ['view', 'manage_items', 'stock_in', 'stock_out', 'transfer', 'reports', 'contacts', 'accounting'],
        'keeper' => ['view', 'stock_in', 'stock_out', 'transfer'],
        'viewer' => ['view', 'reports'],
    ];
    return in_array($permission, $map[$user['role']] ?? [], true);
}


function is_admin(): bool
{
    return ($_SESSION['user']['role'] ?? '') === 'admin';
}

function is_warehouse_manager(): bool
{
    return ($_SESSION['user']['role'] ?? '') === 'manager';
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

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $gy -= 1600;
    $gm -= 1;
    $gd -= 1;

    $gDayNo = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
    for ($i = 0; $i < $gm; $i++) {
        $gDayNo += $gDaysInMonth[$i];
    }
    if ($gm > 1 && (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0))) {
        $gDayNo++;
    }
    $gDayNo += $gd;

    $jDayNo = $gDayNo - 79;
    $jNp = intdiv($jDayNo, 12053);
    $jDayNo %= 12053;

    $jy = 979 + 33 * $jNp + 4 * intdiv($jDayNo, 1461);
    $jDayNo %= 1461;

    if ($jDayNo >= 366) {
        $jy += intdiv($jDayNo - 1, 365);
        $jDayNo = ($jDayNo - 1) % 365;
    }

    for ($i = 0; $i < 11 && $jDayNo >= $jDaysInMonth[$i]; $i++) {
        $jDayNo -= $jDaysInMonth[$i];
    }

    return [$jy, $i + 1, $jDayNo + 1];
}

function jalali_like_datetime(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }
    [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y', $timestamp), (int) date('n', $timestamp), (int) date('j', $timestamp));
    return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, date('H:i', $timestamp));
}

function jalali_like_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    return substr(jalali_like_datetime($date), 0, 10);
}
