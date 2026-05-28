<?php
declare(strict_types=1);

namespace App\Core;

final class Database
{
    private static ?\PDO $connection = null;
    private static ?array $settings = null;

    /** Bump when the schema/seed logic below changes to force a one-time re-run. */
    private const SCHEMA_VERSION = '2024-06-1';

    public static function connect(): \PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $dbName = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

        try {
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT            => 5,
            ]);
            self::$connection = $pdo;
            return $pdo;
        } catch (\PDOException $e) {
            error_log('[Database] Connection failed: ' . $e->getMessage());
            throw new \RuntimeException('ไม่สามารถเชื่อมต่อฐานข้อมูลภายนอกได้: ' . $e->getMessage());
        }
    }

    public static function init(): void
    {
        // The schema/seed below issues ~17 round-trips to a (remote) MySQL server.
        // Running it on every request — including every video segment hitting /proxy/*
        // — is the main source of site-wide latency. Provision once, then skip via a
        // local flag file. Delete storage/.db-initialized (or bump SCHEMA_VERSION) to re-run.
        $flagFile = dirname(__DIR__, 2) . '/storage/.db-initialized';
        if (is_file($flagFile) && trim((string)@file_get_contents($flagFile)) === self::SCHEMA_VERSION) {
            return;
        }

        $db = self::connect();

        // 1. Create table key_codes if it doesn't exist
        $sqlKeyCodes = "CREATE TABLE IF NOT EXISTS `key_codes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(100) UNIQUE NOT NULL,
            `role` VARCHAR(50) NOT NULL DEFAULT 'user',
            `expires_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $db->exec($sqlKeyCodes);

        // 1.5. Dynamic Migration for key_codes columns (hwid, last_active_at, user_agent, ip_address)
        $colsNeeded = [
            'hwid' => "VARCHAR(255) NULL AFTER `expires_at`",
            'last_active_at' => "DATETIME NULL AFTER `hwid`",
            'user_agent' => "TEXT NULL AFTER `last_active_at`",
            'ip_address' => "VARCHAR(100) NULL AFTER `user_agent`"
        ];
        foreach ($colsNeeded as $col => $definition) {
            $stmt = $db->prepare("SHOW COLUMNS FROM `key_codes` LIKE :col");
            $stmt->execute(['col' => $col]);
            if ($stmt->fetch() === false) {
                $db->exec("ALTER TABLE `key_codes` ADD COLUMN `{$col}` {$definition}");
                error_log("[Database] Dynamically appended column `{$col}` to `key_codes` table");
            }
        }

        // 2. Seed a random admin key if the table is empty. A hardcoded default
        //    (the old SJ-ADMIN-9999) is a publicly known credential — anyone could log
        //    into a fresh install as admin. The generated key is logged once and written
        //    to storage/.initial-admin-key so the operator can retrieve it.
        $stmtKeyCodes = $db->query("SELECT COUNT(*) FROM `key_codes`");
        if (((int)$stmtKeyCodes->fetchColumn()) === 0) {
            $adminCode = 'SJ-ADMIN-' . strtoupper(bin2hex(random_bytes(8)));
            $stmtInsert = $db->prepare("INSERT INTO `key_codes` (`code`, `role`, `expires_at`, `created_at`) VALUES (:code, :role, :expires_at, :created_at)");
            $stmtInsert->execute([
                'code' => $adminCode,
                'role' => 'admin',
                'expires_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            error_log('[Database] Seeded initial admin key (shown ONCE): ' . $adminCode);
            $keyFile = dirname(__DIR__, 2) . '/storage/.initial-admin-key';
            @file_put_contents($keyFile, $adminCode . "\n");
            @chmod($keyFile, 0600);
        }

        // 3. Create table settings if it doesn't exist
        $sqlSettings = "CREATE TABLE IF NOT EXISTS `settings` (
            `setting_key` VARCHAR(100) PRIMARY KEY,
            `setting_value` TEXT NULL,
            `is_visible` TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $db->exec($sqlSettings);

        // 4. Ensure default settings exist if table is empty
        $stmtSettings = $db->query("SELECT COUNT(*) FROM `settings`");
        if (((int)$stmtSettings->fetchColumn()) === 0) {
            $defaults = [
                'web_name' => ['value' => 'SeriesJeen', 'visible' => 1],
                'contact_line' => ['value' => '@seriesjeen', 'visible' => 1],
                'contact_facebook' => ['value' => 'https://facebook.com/seriesjeen', 'visible' => 1],
                'contact_other' => ['value' => 'https://t.me/seriesjeen', 'visible' => 1],
            ];

            $stmtInsertSet = $db->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`, `is_visible`) VALUES (:key, :val, :visible)");
            foreach ($defaults as $k => $d) {
                $stmtInsertSet->execute([
                    'key' => $k,
                    'val' => $d['value'],
                    'visible' => $d['visible'],
                ]);
            }
            error_log('[Database] Auto-seeded default website settings');
        }

        // Ensure new logo settings exist
        $newDefaults = [
            'web_logo_url' => ['value' => '', 'visible' => 1],
            'web_logo_width' => ['value' => '32', 'visible' => 1],
            'web_footer_text' => ['value' => 'api.seriesjeen.online', 'visible' => 1],
            'web_footer_url' => ['value' => 'https://api.seriesjeen.online', 'visible' => 1],
            'web_login_description' => ['value' => "คีย์ API ลับของระบบสตรีมมิ่งจะถูกเก็บไว้ที่ฝั่งเซิร์ฟเวอร์อย่างปลอดภัยโดยไม่เปิดเผยแก่ผู้ใช้ทั่วไป\nรหัสเข้าใช้งานของท่านได้รับการคุ้มครองและควบคุมความปลอดภัยผ่านระบบฐานข้อมูลส่วนกลาง", 'visible' => 1],
            'web_theme_color' => ['value' => '#6366f1', 'visible' => 1],
            'web_gradient_color' => ['value' => '#a855f7', 'visible' => 1],
            'web_navbar_color' => ['value' => '#080c1c', 'visible' => 1],
            'web_footer_color' => ['value' => '#060814', 'visible' => 1],
            'web_bg_color' => ['value' => '#060814', 'visible' => 1],
        ];
        foreach ($newDefaults as $k => $d) {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM `settings` WHERE `setting_key` = :key");
            $stmtCheck->execute(['key' => $k]);
            if (((int)$stmtCheck->fetchColumn()) === 0) {
                $stmtInsertSet = $db->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`, `is_visible`) VALUES (:key, :val, :visible)");
                $stmtInsertSet->execute([
                    'key' => $k,
                    'val' => $d['value'],
                    'visible' => $d['visible'],
                ]);
            }
        }

        // Provisioning complete — record version so subsequent requests skip init().
        @file_put_contents($flagFile, self::SCHEMA_VERSION);
    }

    /**
     * Retrieve all settings loaded once per request.
     */
    public static function getSettings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        self::$settings = [];
        try {
            $db = self::connect();
            $stmt = $db->query("SELECT * FROM `settings`");
            while ($row = $stmt->fetch()) {
                self::$settings[$row['setting_key']] = [
                    'value' => $row['setting_value'] !== null ? (string)$row['setting_value'] : '',
                    'is_visible' => ((int)$row['is_visible']) === 1,
                ];
            }
        } catch (\Throwable $e) {
            error_log('[Database] Failed to getSettings: ' . $e->getMessage());
        }

        return self::$settings;
    }

    public static function getSettingValue(string $key, string $default = ''): string
    {
        $settings = self::getSettings();
        return isset($settings[$key]) ? $settings[$key]['value'] : $default;
    }

    public static function isSettingVisible(string $key): bool
    {
        $settings = self::getSettings();
        return isset($settings[$key]) ? (bool)$settings[$key]['is_visible'] : false;
    }

    public static function adjustBrightness(string $hex, int $steps): string
    {
        $steps = max(-255, min(255, $steps));
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) === 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}
