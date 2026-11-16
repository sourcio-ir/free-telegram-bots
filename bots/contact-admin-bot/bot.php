<?php
/**
 * سورس ربات پیام رسان و ارتباط مستقیم با ادمین تلگرام
 * نسخه: 2.0.0
 * مرجع دانلود و توسعه: وب سایت سورس یو (sourcio.ir)
 */

require_once __DIR__ . '/config.php';

// پوشه های ذخیره سازی داده
$dataPath = __DIR__ . '/data';
$spamPath = __DIR__ . '/data/spam';
if (!file_exists($dataPath)) @mkdir($dataPath, 0755, true);
if (!file_exists($spamPath)) @mkdir($spamPath, 0755, true);

// دریافت آپدیت ورودی از وب هوک تلگرام
$rawInput = file_get_contents('php://input');
$update   = json_decode($rawInput, true);

// پاسخ پیش فرض در صورت فراخوانی مستقیم از مرورگر وب
if (!$update) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Sourcio Advanced Contact Admin Bot is running successfully!\nPHP Version: " . PHP_VERSION;
    exit;
}

// توابع اصلی ارتباط با وب سرویس تلگرام
function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch  = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function sendMessage($chatId, $text, $parseMode = 'HTML', $replyTo = null, $keyboard = null) {
    $params = [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'disable_web_page_preview' => true
    ];
    if ($parseMode) $params['parse_mode'] = $parseMode;
    if ($replyTo)   $params['reply_to_message_id'] = $replyTo;
    if ($keyboard)  $params['reply_markup'] = $keyboard;
    return bot('sendMessage', $params);
}

// تابع حذف کامل محتویات یک پوشه (جهت پاکسازی اسپم)
function cleanFolder($path) {
    if (!is_dir($path)) return;
    $files = scandir($path);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $current = $path . '/' . $file;
            if (is_file($current)) @unlink($current);
            elseif (is_dir($current)) {
                cleanFolder($current);
                @rmdir($current);
            }
        }
    }
}

// سیستم کنترل اسپم و محدودیت ارسال پیام (Rate Limiter)
function checkSpam($userId, $chatId) {
    if ($userId == ADMIN_ID) return false;
    $file = __DIR__ . '/data/spam/' . $userId . '.json';
    $now  = time();
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: [0, $now + 2]) : [0, $now + 2];

    if (isset($data['blocked_until']) && $data['blocked_until'] > $now) {
        $remaining = $data['blocked_until'] - $now;
        sendMessage($chatId, "⚠️ به دلیل ارسال رگباری و سریع پیام، شما به مدت {$remaining} ثانیه از ارسال پیام محدود شده اید.", 'HTML');
        return true;
    }

    if ($now < $data[1]) {
        if ($data[0] + 1 > 4) {
            $data = ['blocked_until' => $now + 60];
            @file_put_contents($file, json_encode($data));
            sendMessage($chatId, "⏱ شما به دلیل ارسال پیام های مکرر و نامتعارف به مدت ۶۰ ثانیه محدود شدید.", 'HTML');
            return true;
        } else {
            $data[0] += 1;
        }
    } else {
        $data = [1, $now + 2];
    }

    @file_put_contents($file, json_encode($data));
    return false;
}

// مدیریت فایل تنظیمات داینامیک ربات
$settingsFile = $dataPath . '/settings.json';
if (!file_exists($settingsFile)) {
    $defaultSettings = [
        'mode'         => 'on',
        'start_text'   => DEFAULT_START_TEXT,
        'send_guide'   => DEFAULT_SEND_GUIDE,
        'sent_confirm' => DEFAULT_SENT_CONFIRM,
        'profile_text' => DEFAULT_PROFILE_TEXT
    ];
    @file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$settings = json_decode(file_get_contents($settingsFile), true) ?: [];

// استخراج اطلاعات آپدیت
$fromId   = null;
$chatId   = null;
$msgId    = null;
$text     = '';
$fullName = 'کاربر';
$username = 'ندارد';

if (isset($update['message'])) {
    $msg      = $update['message'];
    $chatId   = $msg['chat']['id'];
    $msgId    = $msg['message_id'];
    $fromId   = $msg['from']['id'];
    $text     = isset($msg['text']) ? trim($msg['text']) : '';
    $fullName = trim(($msg['from']['first_name'] ?? '') . ' ' . ($msg['from']['last_name'] ?? ''));
    $username = !empty($msg['from']['username']) ? '@' . $msg['from']['username'] : 'ندارد';
} elseif (isset($update['callback_query'])) {
    $cb       = $update['callback_query'];
    $chatId   = $cb['message']['chat']['id'];
    $msgId    = $cb['message']['message_id'];
    $fromId   = $cb['from']['id'];
    $text     = $cb['data'] ?? '';
    $fullName = trim(($cb['from']['first_name'] ?? '') . ' ' . ($cb['from']['last_name'] ?? ''));
    $username = !empty($cb['from']['username']) ? '@' . $cb['from']['username'] : 'ندارد';
}

if (!$fromId) exit;

// ثبت کاربر جدید در لیست اعضا
$membersFile = $dataPath . '/members.txt';
$userFile    = $dataPath . '/' . $fromId . '.json';
if (!file_exists($userFile)) {
    $members = file_exists($membersFile) ? file_get_contents($membersFile) : '';
    if (strpos($members, (string)$fromId) === false) {
        @file_put_contents($membersFile, $fromId . "\n", FILE_APPEND | LOCK_EX);
    }
    $userData = [
        'user_id'    => $fromId,
        'name'       => $fullName,
        'username'   => $username,
        'step'       => 'none',
        'joined_at'  => date('Y/m/d H:i:s'),
        'is_banned'  => false
    ];
    @file_put_contents($userFile, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
} else {
    $userData = json_decode(file_get_contents($userFile), true) ?: [];
}

$userStep = $userData['step'] ?? 'none';
$isBanned = !empty($userData['is_banned']);

// تابع کمکی ذخیره وضعیت کاربر
function setUserStep($userId, $step) {
    global $dataPath, $userData;
    $file = $dataPath . '/' . $userId . '.json';
    $userData['step'] = $step;
    @file_put_contents($file, json_encode($userData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// منوهای کاربری و پنل مدیریت
$userMenu = json_encode([
    'keyboard' => [
        [['text' => "💬 ارسال پیام به مدیریت"], ['text' => "👤 مشخصات کاربری"]]
    ],
    'resize_keyboard' => true
]);

$adminMenu = json_encode([
    'keyboard' => [
        [['text' => "💬 ارسال پیام به مدیریت"], ['text' => "👤 مشخصات کاربری"]],
        [['text' => "👨🏻‍💻 پنل مدیریت"]]
    ],
    'resize_keyboard' => true
]);

$mainMenu = ($fromId == ADMIN_ID) ? $adminMenu : $userMenu;

$adminPanel = json_encode([
    'keyboard' => [
        [['text' => "📊 آمار و وضعیت ربات"]],
        [['text' => "📨 ارسال همگانی (متن)"], ['text' => "📢 فوروارد همگانی"]],
        [['text' => "📝 تنظیم متون ربات"], ['text' => "🗑 پاکسازی کش اسپم"]],
        [['text' => "🚫 مدیریت مسدودسازی و وضعیت"], ['text' => "↩️ خروج از پنل"]]
    ],
    'resize_keyboard' => true
]);

$textSettingsMenu = json_encode([
    'keyboard' => [
        [['text' => "📍 تنظیم متن شروع (/start)"], ['text' => "📍 تنظیم راهنمای ارسال"]],
        [['text' => "📍 تنظیم پیام تایید ارسال"], ['text' => "📍 تنظیم متن پروفایل"]],
        [['text' => "↩️ بازگشت به پنل مدیریت"]]
    ],
    'resize_keyboard' => true
]);

$blockSettingsMenu = json_encode([
    'keyboard' => [
        [['text' => "❌ مسدود کردن کاربر"], ['text' => "✅ رفع مسدودیت کاربر"]],
        [['text' => "🔴 خاموش کردن ربات"], ['text' => "🟢 روشن کردن ربات"]],
        [['text' => "↩️ بازگشت به پنل مدیریت"]]
    ],
    'resize_keyboard' => true
]);

$backUserMenu = json_encode([
    'keyboard' => [
        [['text' => "↩️ بازگشت"]]
    ],
    'resize_keyboard' => true
]);

$backAdminMenu = json_encode([
    'keyboard' => [
        [['text' => "↩️ بازگشت به پنل مدیریت"]]
    ],
    'resize_keyboard' => true
]);

// بررسی مسدودیت کاربر
if ($isBanned && $fromId != ADMIN_ID) {
    sendMessage($chatId, "⛔️ دسترسی شما به این ربات توسط مدیریت مسدود شده است.", 'HTML');
    exit;
}

// بررسی کنترل اسپم
if (checkSpam($fromId, $chatId)) exit;

// بررسی وضعیت روشن یا خاموش بودن ربات
if (($settings['mode'] ?? 'on') !== 'on' && $fromId != ADMIN_ID) {
    sendMessage($chatId, "⚠️ ربات در حال حاضر جهت ارتقا و به روزرسانی به صورت موقت خاموش می باشد. لطفا ساعاتی دیگر مراجعه فرمایید.", 'HTML');
    exit;
}

// -------------------------------------------------------------
// مدیریت کلیک روی دکمه های شیشه ای (Callback Query)
// -------------------------------------------------------------
if (isset($update['callback_query'])) {
    $cbId = $update['callback_query']['id'];
    $data = $update['callback_query']['data'];

    // دکمه پاسخ مستقیم ادمین به کاربر
    if (strpos($data, 'rep_') === 0 && $fromId == ADMIN_ID) {
        $targetId = str_replace('rep_', '', $data);
        setUserStep(ADMIN_ID, 'answering_' . $targetId);
        @file_put_contents($dataPath . '/target_reply.txt', $targetId);

        bot('answerCallbackQuery', [
            'callback_query_id' => $cbId,
            'text'              => "حالت پاسخ فعال شد. پیام خود را ارسال فرمایید.",
            'show_alert'        => false
        ]);

        sendMessage(ADMIN_ID, "✍️ در حال پاسخگویی به کاربر با آیدی <code>{$targetId}</code> هستید.\n\nپیام خود را (متن، عکس، ویس، فایل یا هر رسانه دیگر) ارسال نمایید.\nجهت انصراف روی دکمه «↩️ بازگشت به پنل مدیریت» کلیک کنید.", 'HTML', null, $backAdminMenu);
        exit;
    }

    // دکمه مسدودسازی سریع کاربر توسط ادمین
    if (strpos($data, 'ban_') === 0 && $fromId == ADMIN_ID) {
        $targetId = str_replace('ban_', '', $data);
        $targetFile = $dataPath . '/' . $targetId . '.json';
        if (file_exists($targetFile)) {
            $tData = json_decode(file_get_contents($targetFile), true) ?: [];
            $tData['is_banned'] = true;
            @file_put_contents($targetFile, json_encode($tData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            bot('answerCallbackQuery', [
                'callback_query_id' => $cbId,
                'text'              => "کاربر {$targetId} با موفقیت مسدود گردید.",
                'show_alert'        => true
            ]);
            sendMessage(ADMIN_ID, "🚫 کاربر با شناسه <code>{$targetId}</code> با موفقیت مسدود شد.", 'HTML');
            sendMessage($targetId, "⛔️ حساب کاربری شما توسط مدیریت ربات مسدود گردید.", 'HTML');
        } else {
            bot('answerCallbackQuery', [
                'callback_query_id' => $cbId,
                'text'              => "اطلاعات کاربر یافت نشد.",
                'show_alert'        => true
            ]);
        }
        exit;
    }

    bot('answerCallbackQuery', ['callback_query_id' => $cbId]);
    exit;
}

// -------------------------------------------------------------
// مدیریت دستور استارت و بازگشت کاربر
// -------------------------------------------------------------
if ($text === '/start' || $text === '↩️ بازگشت') {
    setUserStep($fromId, 'none');
    $startText = $settings['start_text'] ?? DEFAULT_START_TEXT;
    sendMessage($chatId, $startText, 'HTML', null, $mainMenu);
    exit;
}

// -------------------------------------------------------------
// مشاهده مشخصات کاربری (پروفایل)
// -------------------------------------------------------------
if ($text === '👤 مشخصات کاربری' || $text === '/profile') {
    $profileTemplate = $settings['profile_text'] ?? DEFAULT_PROFILE_TEXT;
    $statusText = $isBanned ? 'مسدود شده ❌' : 'فعال و تایید شده ✅';
    $profileText = str_replace(
        ['{NAME}', '{USER_ID}', '{USERNAME}', '{STATUS}'],
        [$fullName, $fromId, $username, $statusText],
        $profileTemplate
    );
    sendMessage($chatId, $profileText, 'HTML', null, $mainMenu);
    exit;
}

// -------------------------------------------------------------
// درخواست ارسال پیام به مدیریت توسط کاربر
// -------------------------------------------------------------
if ($text === '💬 ارسال پیام به مدیریت') {
    setUserStep($fromId, 'waiting_user_message');
    $guideText = $settings['send_guide'] ?? DEFAULT_SEND_GUIDE;
    sendMessage($chatId, $guideText, 'HTML', null, $backUserMenu);
    exit;
}

// دریافت پیام کاربر و ارسال برای ادمین
if ($userStep === 'waiting_user_message' && $text !== '↩️ بازگشت') {
    setUserStep($fromId, 'none');

    // ارسال تاییدیه دریافت پیام به کاربر
    $confirmText = $settings['sent_confirm'] ?? DEFAULT_SENT_CONFIRM;
    sendMessage($chatId, $confirmText, 'HTML', $msgId, $mainMenu);

    // ارسال اعلان مشخصات به ادمین همراه با دکمه های شیشه ای پاسخ و مسدودسازی
    $adminHeader = "📩 <b>پیام جدید از طرف کاربر:</b>\n\n"
                 . "👤 نام: <b>" . htmlspecialchars($fullName) . "</b>\n"
                 . "🔢 آیدی عددی: <code>" . $fromId . "</code>\n"
                 . "🆔 نام کاربری: " . $username . "\n"
                 . "⏰ زمان ارسال: " . date('Y/m/d - H:i:s');

    $inlineActions = json_encode([
        'inline_keyboard' => [
            [
                ['text' => "📩 پاسخ به کاربر", 'callback_data' => "rep_" . $fromId],
                ['text' => "⛔️ مسدودسازی", 'callback_data' => "ban_" . $fromId]
            ]
        ]
    ]);

    sendMessage(ADMIN_ID, $adminHeader, 'HTML', null, $inlineActions);

    // کپی یا فوروارد مستقیم محتوای پیام کاربر برای ادمین
    bot('copyMessage', [
        'chat_id'      => ADMIN_ID,
        'from_chat_id' => $chatId,
        'message_id'   => $msgId
    ]);
    exit;
}

// -------------------------------------------------------------
// بخش اختصاصی ادمین و پنل مدیریت
// -------------------------------------------------------------
if ($fromId == ADMIN_ID) {

    // ورود به پنل مدیریت یا بازگشت به پنل
    if ($text === '👨🏻‍💻 پنل مدیریت' || $text === '/panel' || $text === '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        sendMessage(ADMIN_ID, "👨🏻‍💻 به پنل مدیریت ربات خوش آمدید.\nجهت مشاهده راهنما دستور /help را ارسال نمایید.", 'HTML', null, $adminPanel);
        exit;
    }

    // خروج از پنل مدیریت
    if ($text === '↩️ خروج از پنل') {
        setUserStep(ADMIN_ID, 'none');
        sendMessage(ADMIN_ID, "از پنل مدیریت خارج شدید.", 'HTML', null, $mainMenu);
        exit;
    }

    // راهنمای پنل مدیریت
    if ($text === '/help') {
        $helpText = "📖 <b>راهنمای بخش های مختلف پنل مدیریت:</b>\n\n"
                  . "• <b>📊 آمار و وضعیت:</b> نمایش تعداد کل اعضا، آخرین کاربران عضو شده و وضعیت سیستم.\n"
                  . "• <b>📨 ارسال همگانی:</b> ارسال یک متن سفارشی برای تمامی اعضای ربات.\n"
                  . "• <b>📢 فوروارد همگانی:</b> فوروارد هر نوع پیام، تصویر یا رسانه برای همه کاربران.\n"
                  . "• <b>📝 تنظیم متون ربات:</b> ویرایش زنده متن خوش آمدگویی، راهنما، تایید و پروفایل.\n"
                  . "• <b>🚫 مدیریت مسدودسازی و وضعیت:</b> بن یا آن بن کردن کاربران با آیدی و خاموش/روشن کردن ربات.\n"
                  . "• <b>🗑 پاکسازی کش اسپم:</b> حذف فایل های موقت آنتی اسپم.";
        sendMessage(ADMIN_ID, $helpText, 'HTML', null, $adminPanel);
        exit;
    }

    // مشاهده آمار و وضعیت ربات
    if ($text === '📊 آمار و وضعیت ربات') {
        $membersContent = file_exists($membersFile) ? file_get_contents($membersFile) : '';
        $membersList    = array_filter(array_map('trim', explode("\n", $membersContent)));
        $totalMembers   = count($membersList);

        $loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : [0.0, 0.0, 0.0];
        $botMode = ($settings['mode'] ?? 'on') === 'on' ? '🟢 آنلاین و روشن' : '🔴 خاموش (تعمیرات)';

        $lastMembersText = '';
        $lastTen = array_slice($membersList, -10);
        foreach ($lastTen as $uId) {
            $lastMembersText .= "• <a href=\"tg://user?id={$uId}\">{$uId}</a>\n";
        }
        if (!$lastMembersText) $lastMembersText = "هنوز کاربری ثبت نشده است.";

        $report = "📊 <b>آمار و وضعیت لحظه ای ربات:</b>\n\n"
                . "👥 تعداد کل کاربران: <b>{$totalMembers} نفر</b>\n"
                . "⚙️ وضعیت فعالیت ربات: <b>{$botMode}</b>\n"
                . "⚡️ لود سرور: <code>{$loadAvg[0]}</code>\n"
                . "📅 تاریخ: <b>" . date('Y/m/d') . "</b>\n"
                . "⏰ ساعت: <b>" . date('H:i:s') . "</b>\n\n"
                . "👥 <b>آخرین اعضای ثبت نامی:</b>\n" . $lastMembersText;

        sendMessage(ADMIN_ID, $report, 'HTML', $msgId, $adminPanel);
        exit;
    }

    // پاکسازی پوشه کش اسپم
    if ($text === '🗑 پاکسازی کش اسپم') {
        cleanFolder($spamPath);
        sendMessage(ADMIN_ID, "🗑 کلیه لاگ ها و فایل های موقت آنتی اسپم با موفقیت پاکسازی شدند.", 'HTML', null, $adminPanel);
        exit;
    }

    // منوی مدیریت مسدودسازی و وضعیت
    if ($text === '🚫 مدیریت مسدودسازی و وضعیت') {
        sendMessage(ADMIN_ID, "بخش مدیریت وضعیت ربات و کاربران:", 'HTML', null, $blockSettingsMenu);
        exit;
    }

    // روشن کردن ربات
    if ($text === '🟢 روشن کردن ربات') {
        $settings['mode'] = 'on';
        @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        sendMessage(ADMIN_ID, "🟢 ربات با موفقیت روشن شد و پاسخگوی تمامی کاربران است.", 'HTML', null, $blockSettingsMenu);
        exit;
    }

    // خاموش کردن ربات
    if ($text === '🔴 خاموش کردن ربات') {
        $settings['mode'] = 'off';
        @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        sendMessage(ADMIN_ID, "🔴 ربات با موفقیت خاموش شد (پیام حالت تعمیرات برای کاربران نمایش داده می شود).", 'HTML', null, $blockSettingsMenu);
        exit;
    }

    // شروع مسدود کردن کاربر با آیدی
    if ($text === '❌ مسدود کردن کاربر') {
        setUserStep(ADMIN_ID, 'set_ban_user');
        sendMessage(ADMIN_ID, "لطفا شناسه عددی (User ID) کاربر مورد نظر را جهت مسدودسازی ارسال فرمایید:", 'HTML', null, $backAdminMenu);
        exit;
    }

    if ($userStep === 'set_ban_user' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        $targetUser = trim($text);
        $tFile = $dataPath . '/' . $targetUser . '.json';
        if (file_exists($tFile)) {
            $tData = json_decode(file_get_contents($tFile), true) ?: [];
            $tData['is_banned'] = true;
            @file_put_contents($tFile, json_encode($tData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            sendMessage(ADMIN_ID, "🚫 کاربر <code>{$targetUser}</code> با موفقیت مسدود شد.", 'HTML', null, $blockSettingsMenu);
            sendMessage($targetUser, "⛔️ حساب شما توسط مدیریت مسدود گردید.", 'HTML');
        } else {
            sendMessage(ADMIN_ID, "❌ کاربری با این شناسه در دیتابیس یافت نشد.", 'HTML', null, $blockSettingsMenu);
        }
        exit;
    }

    // رفع مسدودیت کاربر با آیدی
    if ($text === '✅ رفع مسدودیت کاربر') {
        setUserStep(ADMIN_ID, 'set_unban_user');
        sendMessage(ADMIN_ID, "لطفا شناسه عددی (User ID) کاربر مورد نظر را جهت رفع مسدودیت ارسال فرمایید:", 'HTML', null, $backAdminMenu);
        exit;
    }

    if ($userStep === 'set_unban_user' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        $targetUser = trim($text);
        $tFile = $dataPath . '/' . $targetUser . '.json';
        if (file_exists($tFile)) {
            $tData = json_decode(file_get_contents($tFile), true) ?: [];
            $tData['is_banned'] = false;
            @file_put_contents($tFile, json_encode($tData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            sendMessage(ADMIN_ID, "✅ کاربر <code>{$targetUser}</code> با موفقیت رفع مسدودیت گردید.", 'HTML', null, $blockSettingsMenu);
            sendMessage($targetUser, "🎉 حساب کاربری شما توسط مدیریت فعال و رفع مسدودیت شد.", 'HTML');
        } else {
            sendMessage(ADMIN_ID, "❌ کاربری با این شناسه در دیتابیس یافت نشد.", 'HTML', null, $blockSettingsMenu);
        }
        exit;
    }

    // ارسال همگانی متن
    if ($text === '📨 ارسال همگانی (متن)') {
        setUserStep(ADMIN_ID, 'waiting_broadcast_pm');
        sendMessage(ADMIN_ID, "📝 متن مورد نظر جهت ارسال همگانی به تمامی کاربران را ارسال نمایید:", 'HTML', null, $backAdminMenu);
        exit;
    }

    if ($userStep === 'waiting_broadcast_pm' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        sendMessage(ADMIN_ID, "⏳ در حال ارسال پیام همگانی... لطفا شکیبا باشید.", 'HTML');

        $membersContent = file_exists($membersFile) ? file_get_contents($membersFile) : '';
        $membersList    = array_filter(array_map('trim', explode("\n", $membersContent)));
        $sentCount = 0;
        $failCount = 0;

        foreach ($membersList as $uId) {
            $res = sendMessage($uId, $text, 'HTML');
            if ($res && !empty($res['ok'])) $sentCount++;
            else $failCount++;
            usleep(40000); // تاخیر ۴۰ میلی ثانیه ای جهت جلوگیری از محدودیت ارسال تلگرام
        }

        sendMessage(ADMIN_ID, "✅ ارسال همگانی به پایان رسید.\n\n• موفق: <b>{$sentCount}</b>\n• ناموفق یا بلاک کرده: <b>{$failCount}</b>", 'HTML', null, $adminPanel);
        exit;
    }

    // فوروارد همگانی
    if ($text === '📢 فوروارد همگانی') {
        setUserStep(ADMIN_ID, 'waiting_broadcast_fwd');
        sendMessage(ADMIN_ID, "📢 پیامی که می خواهید برای تمامی اعضا فوروارد شود را ارسال نمایید:", 'HTML', null, $backAdminMenu);
        exit;
    }

    if ($userStep === 'waiting_broadcast_fwd' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        sendMessage(ADMIN_ID, "⏳ در حال فوروارد پیام همگانی... لطفا شکیبا باشید.", 'HTML');

        $membersContent = file_exists($membersFile) ? file_get_contents($membersFile) : '';
        $membersList    = array_filter(array_map('trim', explode("\n", $membersContent)));
        $sentCount = 0;
        $failCount = 0;

        foreach ($membersList as $uId) {
            $res = bot('forwardMessage', [
                'chat_id'      => $uId,
                'from_chat_id' => ADMIN_ID,
                'message_id'   => $msgId
            ]);
            if ($res && !empty($res['ok'])) $sentCount++;
            else $failCount++;
            usleep(40000);
        }

        sendMessage(ADMIN_ID, "✅ فوروارد همگانی به اتمام رسید.\n\n• موفق: <b>{$sentCount}</b>\n• ناموفق یا بلاک کرده: <b>{$failCount}</b>", 'HTML', null, $adminPanel);
        exit;
    }

    // منوی تنظیم متون ربات
    if ($text === '📝 تنظیم متون ربات') {
        sendMessage(ADMIN_ID, "بخش تنظیم متون داینامیک ربات:\nبرای تغییر هر متن روی دکمه مربوطه کلیک کنید.", 'HTML', null, $textSettingsMenu);
        exit;
    }

    // تنظیم متن شروع
    if ($text === '📍 تنظیم متن شروع (/start)') {
        setUserStep(ADMIN_ID, 'set_start_text');
        sendMessage(ADMIN_ID, "متن جدید برای دستور /start را ارسال کنید:", 'HTML', null, $backAdminMenu);
        exit;
    }
    if ($userStep === 'set_start_text' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        $settings['start_text'] = $text;
        @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        sendMessage(ADMIN_ID, "✅ متن شروع (/start) با موفقیت به روزرسانی شد.", 'HTML', null, $textSettingsMenu);
        exit;
    }

    // تنظیم راهنمای ارسال پیام
    if ($text === '📍 تنظیم راهنمای ارسال') {
        setUserStep(ADMIN_ID, 'set_send_guide');
        sendMessage(ADMIN_ID, "متن راهنمای قبل از ارسال پیام توسط کاربر را ارسال کنید:", 'HTML', null, $backAdminMenu);
        exit;
    }
    if ($userStep === 'set_send_guide' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        $settings['send_guide'] = $text;
        @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        sendMessage(ADMIN_ID, "✅ متن راهنمای ارسال با موفقیت به روزرسانی شد.", 'HTML', null, $textSettingsMenu);
        exit;
    }

    // تنظیم پیام تایید ارسال
    if ($text === '📍 تنظیم پیام تایید ارسال') {
        setUserStep(ADMIN_ID, 'set_sent_confirm');
        sendMessage(ADMIN_ID, "متن تایید پس از ارسال پیام توسط کاربر را ارسال کنید:", 'HTML', null, $backAdminMenu);
        exit;
    }
    if ($userStep === 'set_sent_confirm' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        $settings['sent_confirm'] = $text;
        @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        sendMessage(ADMIN_ID, "✅ متن تایید دریافت با موفقیت به روزرسانی شد.", 'HTML', null, $textSettingsMenu);
        exit;
    }

    // تنظیم متن مشخصات پروفایل
    if ($text === '📍 تنظیم متن پروفایل') {
        setUserStep(ADMIN_ID, 'set_profile_text');
        sendMessage(ADMIN_ID, "متن نمایش پروفایل را ارسال کنید (می توانید از متغیرهای {NAME}، {USER_ID}، {USERNAME} و {STATUS} استفاده نمایید):", 'HTML', null, $backAdminMenu);
        exit;
    }
    if ($userStep === 'set_profile_text' && $text !== '↩️ بازگشت به پنل مدیریت') {
        setUserStep(ADMIN_ID, 'in_panel');
        $settings['profile_text'] = $text;
        @file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        sendMessage(ADMIN_ID, "✅ متن پروفایل با موفقیت ذخیره شد.", 'HTML', null, $textSettingsMenu);
        exit;
    }

    // پاسخ ادمین در حالت مرحله ای (پس از زدن دکمه پاسخ شیشه ای)
    if (strpos($userStep, 'answering_') === 0 && $text !== '↩️ بازگشت به پنل مدیریت') {
        $targetId = str_replace('answering_', '', $userStep);
        setUserStep(ADMIN_ID, 'none');

        // کپی هر نوع پیام ارسالی ادمین (متن، عکس، صدا، ویدیو، استیکر و فایل) برای کاربر
        $sent = bot('copyMessage', [
            'chat_id'      => $targetId,
            'from_chat_id' => ADMIN_ID,
            'message_id'   => $msgId
        ]);

        if ($sent && !empty($sent['ok'])) {
            sendMessage(ADMIN_ID, "✅ پاسخ شما با موفقیت برای کاربر <code>{$targetId}</code> ارسال شد.", 'HTML', $msgId, $adminPanel);
            sendMessage($targetId, "📩 یک پیام جدید از طرف مدیریت دریافت نمودید.", 'HTML');
        } else {
            sendMessage(ADMIN_ID, "❌ ارسال پاسخ ناموفق بود. ممکن است کاربر ربات را بلاک کرده باشد.", 'HTML', $msgId, $adminPanel);
        }
        exit;
    }

    // حالت پاسخ با ریپلای مستقیم ادمین در تلگرام به پیام کاربر
    if (isset($update['message']['reply_to_message'])) {
        $replyTo = $update['message']['reply_to_message'];
        $replyText = $replyTo['text'] ?? ($replyTo['caption'] ?? '');
        $targetId = null;

        if (preg_match('/آیدی عددی:\s*<code>(\d+)<\/code>/u', $replyText, $matches) || preg_match('/id\s*:\s*<code>(\d+)<\/code>/i', $replyText, $matches) || preg_match('/ID:\s*(\d+)/', $replyText, $matches)) {
            $targetId = $matches[1];
        }

        if ($targetId) {
            $sent = bot('copyMessage', [
                'chat_id'      => $targetId,
                'from_chat_id' => ADMIN_ID,
                'message_id'   => $msgId
            ]);

            if ($sent && !empty($sent['ok'])) {
                sendMessage(ADMIN_ID, "✅ پاسخ شما با موفقیت برای کاربر <code>{$targetId}</code> ارسال شد.", 'HTML', $msgId);
                sendMessage($targetId, "📩 یک پیام جدید از طرف مدیریت دریافت نمودید.", 'HTML');
            } else {
                sendMessage(ADMIN_ID, "❌ ارسال پاسخ به کاربر ناموفق بود.", 'HTML', $msgId);
            }
            exit;
        }
    }
}
