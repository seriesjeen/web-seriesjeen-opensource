<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Core\Database;

final class AdminController
{
    public function index(Request $request, array $args): void
    {
        $db = Database::connect();

        // 1. Fetch all keys ordered by creation date descending
        $stmt = $db->query("SELECT * FROM `key_codes` ORDER BY `created_at` DESC");
        $keys = $stmt->fetchAll();

        // 2. Compute statistics
        $stats = [
            'total' => count($keys),
            'admin' => 0,
            'active_user' => 0,
            'expired_user' => 0,
        ];

        $now = time();
        foreach ($keys as &$k) {
            $isExpired = false;
            if ($k['expires_at'] !== null) {
                $expiry = strtotime($k['expires_at']);
                if ($expiry < $now) {
                    $isExpired = true;
                }
            }
            $k['is_expired'] = $isExpired;

            if ($k['role'] === 'admin') {
                $stats['admin']++;
            } elseif ($isExpired) {
                $stats['expired_user']++;
            } else {
                $stats['active_user']++;
            }
        }
        unset($k);

        Response::html(View::render('admin/keys', [
            'keys' => $keys,
            'stats' => $stats,
            'flash_success' => Session::flash('flash_success'),
            'flash_error' => Session::flash('flash_error'),
        ]));
    }

    public function settings(Request $request, array $args): void
    {
        Response::html(View::render('admin/settings', [
            'settings' => Database::getSettings(),
            'flash_success' => Session::flash('flash_success'),
            'flash_error' => Session::flash('flash_error'),
        ]));
    }

    public function createKey(Request $request, array $args): void
    {
        $role = trim((string)$request->post('role', 'user'));
        if (!in_array($role, ['user', 'admin'], true)) {
            $role = 'user';
        }

        $duration = trim((string)$request->post('duration', '7d'));
        
        $expiresAt = null;
        if ($role === 'user') {
            $expiresAt = match ($duration) {
                '1h'  => date('Y-m-d H:i:s', strtotime('+1 hour')),
                '1d'  => date('Y-m-d H:i:s', strtotime('+1 day')),
                '7d'  => date('Y-m-d H:i:s', strtotime('+7 days')),
                '30d' => date('Y-m-d H:i:s', strtotime('+30 days')),
                '365d'=> date('Y-m-d H:i:s', strtotime('+365 days')),
                'custom' => !empty($request->post('custom_expiry')) ? date('Y-m-d H:i:s', strtotime((string)$request->post('custom_expiry'))) : null,
                default => null, // unlimited
            };
        }

        // Generate unique UUID v7 code
        $db = Database::connect();
        $code = '';
        $maxAttempts = 10;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $testCode = $this->generateUuidV7();
            $stmt = $db->prepare("SELECT COUNT(*) FROM `key_codes` WHERE `code` = :code");
            $stmt->execute(['code' => $testCode]);
            if (((int)$stmt->fetchColumn()) === 0) {
                $code = $testCode;
                break;
            }
            $attempt++;
        }

        if ($code === '') {
            Session::flash('flash_error', 'เกิดข้อผิดพลาดในการสร้างรหัสเข้าใช้งาน กรุณาลองใหม่อีกครั้ง');
            Response::redirect('/admin');
        }

        try {
            $stmt = $db->prepare("INSERT INTO `key_codes` (`code`, `role`, `expires_at`, `created_at`) VALUES (:code, :role, :expires_at, :created_at)");
            $stmt->execute([
                'code' => $code,
                'role' => $role,
                'expires_at' => $expiresAt,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Session::flash('flash_success', 'สร้างรหัสเข้าใช้งานสำเร็จ: ' . $code . ' (' . ($role === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้ใช้ทั่วไป') . ')');
        } catch (\Throwable $e) {
            error_log('[AdminController] Failed to insert key_code: ' . $e->getMessage());
            Session::flash('flash_error', 'เกิดข้อผิดพลาดในการบันทึกรหัสเข้าใช้งาน: ' . $e->getMessage());
        }

        Response::redirect('/admin');
    }

    public function deleteKey(Request $request, array $args): void
    {
        $id = (int)$request->post('id');
        $currentUserCode = Session::get('user_code');

        if ($id <= 0) {
            Session::flash('flash_error', 'ข้อมูลไม่ถูกต้อง');
            Response::redirect('/admin');
        }

        $db = Database::connect();
        
        try {
            // Retrieve both code and role for the target key code
            $stmt = $db->prepare("SELECT `code`, `role` FROM `key_codes` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $target = $stmt->fetch();

            if (!$target) {
                Session::flash('flash_error', 'ไม่พบรหัสเข้าใช้งานดังกล่าวในระบบ');
                Response::redirect('/admin');
            }

            $targetCode = $target['code'];
            $targetRole = $target['role'];
            
            // Prevent deleting the key code currently used by the admin to log in!
            if ($targetCode === $currentUserCode) {
                Session::flash('flash_error', 'ไม่สามารถลบรหัสเข้าใช้งานที่คุณกำลังใช้งานล็อคอินอยู่ ณ ขณะนี้ได้เพื่อความปลอดภัย');
                Response::redirect('/admin');
            }

            // Prevent deleting the last remaining admin key in the database!
            if ($targetRole === 'admin') {
                $countStmt = $db->query("SELECT COUNT(*) FROM `key_codes` WHERE `role` = 'admin'");
                $adminCount = (int)$countStmt->fetchColumn();

                if ($adminCount <= 1) {
                    Session::flash('flash_error', 'ไม่สามารถลบรหัสแอดมินนี้ได้ เนื่องจากต้องมีรหัสแอดมินเหลืออย่างน้อย 1 รหัสในฐานข้อมูล เพื่อป้องกันการล็อกระบบล็อกเอาต์โดยสมบูรณ์!');
                    Response::redirect('/admin');
                }
            }

            $stmtDelete = $db->prepare("DELETE FROM `key_codes` WHERE `id` = :id");
            $stmtDelete->execute(['id' => $id]);
            Session::flash('flash_success', 'ลบรหัสเข้าใช้งาน ' . $targetCode . ' เรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            error_log('[AdminController] Failed to delete key_code: ' . $e->getMessage());
            Session::flash('flash_error', 'เกิดข้อผิดพลาดในการลบรหัสเข้าใช้งาน: ' . $e->getMessage());
        }

        Response::redirect('/admin');
    }

    public function resetHwid(Request $request, array $args): void
    {
        $id = (int)$request->post('id');

        if ($id <= 0) {
            Session::flash('flash_error', 'ข้อมูลไม่ถูกต้อง');
            Response::redirect('/admin');
        }

        $db = Database::connect();
        
        try {
            $stmt = $db->prepare("SELECT `code` FROM `key_codes` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $targetCode = $stmt->fetchColumn();

            if (!$targetCode) {
                Session::flash('flash_error', 'ไม่พบรหัสเข้าใช้งานดังกล่าวในระบบ');
                Response::redirect('/admin');
            }

            $stmtReset = $db->prepare("UPDATE `key_codes` SET `hwid` = NULL, `last_active_at` = NULL, `user_agent` = NULL, `ip_address` = NULL WHERE `id` = :id");
            $stmtReset->execute(['id' => $id]);
            
            Session::flash('flash_success', 'ปลดล็อกอุปกรณ์สำหรับรหัส ' . $targetCode . ' เรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            error_log('[AdminController] Failed to reset HWID: ' . $e->getMessage());
            Session::flash('flash_error', 'เกิดข้อผิดพลาดในการปลดล็อกอุปกรณ์: ' . $e->getMessage());
        }

        Response::redirect('/admin');
    }

    public function updateSettings(Request $request, array $args): void
    {
        $webName = trim((string)$request->post('web_name'));
        $logoUrl = trim((string)$request->post('web_logo_url'));
        $logoWidth = trim((string)$request->post('web_logo_width'));
        $line = trim((string)$request->post('contact_line'));
        $facebook = trim((string)$request->post('contact_facebook'));
        $other = trim((string)$request->post('contact_other'));
        
        $footerText = trim((string)$request->post('web_footer_text'));
        $footerUrl = trim((string)$request->post('web_footer_url'));
        $loginDesc = trim((string)$request->post('web_login_description'));
        
        $themeColor = trim((string)$request->post('web_theme_color', '#6366f1'));
        if ($themeColor === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)) {
            $themeColor = '#6366f1';
        }

        $gradientColor = trim((string)$request->post('web_gradient_color', '#a855f7'));
        if ($gradientColor === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $gradientColor)) {
            $gradientColor = '#a855f7';
        }

        $navbarColor = trim((string)$request->post('web_navbar_color', '#080c1c'));
        if ($navbarColor === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $navbarColor)) {
            $navbarColor = '#080c1c';
        }

        $footerColor = trim((string)$request->post('web_footer_color', '#060814'));
        if ($footerColor === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $footerColor)) {
            $footerColor = '#060814';
        }

        $bgColor = trim((string)$request->post('web_bg_color', '#060814'));
        if ($bgColor === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $bgColor)) {
            $bgColor = '#060814';
        }

        $lineVisible = ((int)$request->post('line_visible')) === 1 ? 1 : 0;
        $facebookVisible = ((int)$request->post('facebook_visible')) === 1 ? 1 : 0;
        $otherVisible = ((int)$request->post('other_visible')) === 1 ? 1 : 0;

        if ($webName === '') {
            Session::flash('flash_error', 'กรุณาระบุชื่อเว็บไซต์');
            Response::redirect('/admin/settings');
        }

        // Default logo width fallback
        if ($logoWidth === '' || !is_numeric($logoWidth)) {
            $logoWidth = '32';
        }

        try {
            $db = Database::connect();
            $stmt = $db->prepare("UPDATE `settings` SET `setting_value` = :val, `is_visible` = :visible WHERE `setting_key` = :key");
            
            $stmt->execute(['key' => 'web_name', 'val' => $webName, 'visible' => 1]);
            $stmt->execute(['key' => 'web_logo_url', 'val' => $logoUrl, 'visible' => 1]);
            $stmt->execute(['key' => 'web_logo_width', 'val' => $logoWidth, 'visible' => 1]);
            $stmt->execute(['key' => 'contact_line', 'val' => $line, 'visible' => $lineVisible]);
            $stmt->execute(['key' => 'contact_facebook', 'val' => $facebook, 'visible' => $facebookVisible]);
            $stmt->execute(['key' => 'contact_other', 'val' => $other, 'visible' => $otherVisible]);
            
            $stmt->execute(['key' => 'web_footer_text', 'val' => $footerText, 'visible' => 1]);
            $stmt->execute(['key' => 'web_footer_url', 'val' => $footerUrl, 'visible' => 1]);
            $stmt->execute(['key' => 'web_login_description', 'val' => $loginDesc, 'visible' => 1]);
            $stmt->execute(['key' => 'web_theme_color', 'val' => $themeColor, 'visible' => 1]);
            $stmt->execute(['key' => 'web_gradient_color', 'val' => $gradientColor, 'visible' => 1]);
            $stmt->execute(['key' => 'web_navbar_color', 'val' => $navbarColor, 'visible' => 1]);
            $stmt->execute(['key' => 'web_footer_color', 'val' => $footerColor, 'visible' => 1]);
            $stmt->execute(['key' => 'web_bg_color', 'val' => $bgColor, 'visible' => 1]);

            Session::flash('flash_success', 'บันทึกการตั้งค่าเว็บไซต์เรียบร้อยแล้ว');
        } catch (\Throwable $e) {
            error_log('[AdminController] Failed to update settings: ' . $e->getMessage());
            Session::flash('flash_error', 'เกิดข้อผิดพลาดในการบันทึกการตั้งค่า: ' . $e->getMessage());
        }

        Response::redirect('/admin/settings');
    }

    private function generateUuidV7(): string
    {
        // 48-bit timestamp in milliseconds
        $milliTime = (int)floor(microtime(true) * 1000);
        $timestampHex = str_pad(dechex($milliTime), 12, '0', STR_PAD_LEFT);
        
        $part1 = substr($timestampHex, 0, 8);
        $part2 = substr($timestampHex, 8, 4);
        
        // 4 bits version (7) + 12 bits random
        $part3 = '7' . str_pad(dechex(random_int(0, 0x0fff)), 3, '0', STR_PAD_LEFT);
        
        // 2 bits variant (10) + 14 bits random
        $variantAndRand = (random_int(0, 0xffff) & 0x3fff) | 0x8000;
        $part4 = str_pad(dechex($variantAndRand), 4, '0', STR_PAD_LEFT);
        
        // 48 bits random
        $part5 = str_pad(dechex(random_int(0, 0xffff)), 4, '0', STR_PAD_LEFT) .
                 str_pad(dechex(random_int(0, 0xffff)), 4, '0', STR_PAD_LEFT) .
                 str_pad(dechex(random_int(0, 0xffff)), 4, '0', STR_PAD_LEFT);
                 
        return sprintf('%s-%s-%s-%s-%s', $part1, $part2, $part3, $part4, $part5);
    }
}
