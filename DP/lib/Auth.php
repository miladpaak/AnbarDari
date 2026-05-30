<?php
final class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = Database::one('SELECT * FROM users WHERE username = ? AND is_active = 1', [$username]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role' => $user['role'],
        ];
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }
}
