<?php
/**
 * فایل تنظیمات ربات پیشرفته آپلودر فایل و تبدیل فایل به لینک
 * مرجع انتشار: وب سایت سورس یو (sourcio.ir)
 * کانال تلگرام: @sourcio
 */

error_reporting(0);
date_default_timezone_set('Asia/Tehran');

// توکن دریافتی از بات فادر (@BotFather)
$API_KC = "YOUR_BOT_TOKEN_HERE";

// شناسه عددی ادمین اصلی ربات (دریافت از @userinfobot)
$adminm = 123456789;

// اطلاعات اتصال به دیتابیس MySQL
$dbname   = "database_name";
$username = "database_user";
$password = "database_password";

// برقراری ارتباط با پایگاه داده
$connect = mysqli_connect("localhost", $username, $password, $dbname);
if (!$connect) {
    die("خطا در اتصال به پایگاه داده: " . mysqli_connect_error());
}
mysqli_query($connect, "SET NAMES 'utf8mb4'");
mysqli_query($connect, "SET SESSION collation_connection = 'utf8mb4_unicode_ci'");

// استخراج شناسه ربات
$botid  = explode(':', $API_KC)[0];
$bottag = "YourBotUsername";
$auther = "Sourcio.ir";
