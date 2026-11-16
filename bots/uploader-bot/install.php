<?php
/**
 * اسکریپت نصب خودکار جداول پایگاه داده ربات آپلودر سورس یو
 * مرجع: sourcio.ir
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . "/config.php";

$queries = [
    "CREATE TABLE IF NOT EXISTS `user` (
        `id` bigint(40) PRIMARY KEY,
        `step` varchar(200) NOT NULL DEFAULT 'none',
        `step2` varchar(200) NOT NULL DEFAULT 'none',
        `step3` varchar(200) NOT NULL DEFAULT 'none',
        `step4` varchar(200) NOT NULL DEFAULT 'none',
        `step5` varchar(200) NOT NULL DEFAULT 'none',
        `spam` varchar(20) NOT NULL DEFAULT '0'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `admin` (
        `admin` bigint(40) PRIMARY KEY
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `settings` (
        `botid` varchar(30) PRIMARY KEY,
        `bot_mode` varchar(20) NOT NULL DEFAULT 'on',
        `mtn_s_ch` TEXT NOT NULL,
        `chupl` varchar(200) NOT NULL DEFAULT 'none',
        `forall` varchar(20) NOT NULL DEFAULT 'false',
        `sendall` varchar(20) NOT NULL DEFAULT 'false',
        `tedad` varchar(20) NOT NULL DEFAULT '0',
        `text` TEXT NOT NULL,
        `chat_id` varchar(20) NOT NULL DEFAULT 'none',
        `msg_id2` varchar(20) NOT NULL DEFAULT 'none',
        `is_all` varchar(20) NOT NULL DEFAULT 'no',
        `time_del` varchar(20) NOT NULL DEFAULT '1',
        `msg_id` varchar(20) NOT NULL DEFAULT 'none'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `channels` (
        `idoruser` varchar(50) PRIMARY KEY,
        `link` varchar(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `files` (
        `code` varchar(200) PRIMARY KEY,
        `msg_id` varchar(200) NOT NULL DEFAULT '',
        `ghfl_ch` varchar(200) NOT NULL DEFAULT '',
        `zd_filter` varchar(200) NOT NULL DEFAULT '',
        `file_id` varchar(200) NOT NULL DEFAULT '',
        `file_size` varchar(200) NOT NULL DEFAULT '',
        `file_type` varchar(200) NOT NULL DEFAULT '',
        `id` varchar(200) NOT NULL DEFAULT '',
        `dl` bigint(20) NOT NULL DEFAULT 0,
        `pass` varchar(200) NOT NULL DEFAULT '',
        `mahdodl` varchar(200) NOT NULL DEFAULT '',
        `tozihat` TEXT NOT NULL,
        `zaman` varchar(200) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS `dbremove` (
        `id` bigint(40) NOT NULL,
        `message_id` bigint(20) NOT NULL,
        `time` bigint(20) NOT NULL,
        INDEX (`time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "INSERT IGNORE INTO `admin` (`admin`) VALUES ('" . (int)$adminm . "');",

    "INSERT IGNORE INTO `settings` (`botid`, `bot_mode`, `mtn_s_ch`, `chupl`, `forall`, `sendall`, `tedad`, `text`, `chat_id`, `msg_id2`, `is_all`, `time_del`, `msg_id`) 
    VALUES ('" . mysqli_real_escape_string($connect, $botid) . "', 'on', 'none', 'none', 'false', 'false', '0', 'none', 'none', 'none', 'no', '1', 'none');"
];

$successCount = 0;
foreach ($queries as $q) {
    if (mysqli_query($connect, $q)) {
        $successCount++;
    } else {
        echo "خطا در اجرای کوئری: " . mysqli_error($connect) . "<br>";
    }
}

header('Content-Type: text/html; charset=utf-8');
echo "<div style='font-family: tahoma; direction: rtl; padding: 20px; line-height: 2;'>";
echo "<h2>✅ نصب دیتابیس ربات آپلودر با موفقیت انجام شد!</h2>";
echo "<p>تعداد {$successCount} کوئری با موفقیت در دیتابیس اجرا گردید و ادمین اصلی ثبت شد.</p>";
echo "<p>اکنون می توانید بر روی فایل <code>index.php</code> وب هوک ست نمایید.</p>";
echo "<p>مرجع: <a href='https://sourcio.ir'>سورس یو (sourcio.ir)</a></p>";
echo "</div>";
