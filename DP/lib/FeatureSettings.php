<?php
final class FeatureSettings
{
    public static function ensureSchema(): void
    {
        Database::query("CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::query("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('accounting_enabled', '1')");

        $imageColumn = Database::one("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'image_path'");
        if (!$imageColumn) {
            Database::query('ALTER TABLE items ADD COLUMN image_path VARCHAR(255) NULL AFTER description');
        }
    }

    public static function accountingEnabled(): bool
    {
        $row = Database::one("SELECT setting_value FROM app_settings WHERE setting_key = 'accounting_enabled'");
        return ($row['setting_value'] ?? '1') === '1';
    }

    public static function setAccountingEnabled(bool $enabled): void
    {
        Database::query("INSERT INTO app_settings (setting_key, setting_value) VALUES ('accounting_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$enabled ? '1' : '0']);
    }
}
