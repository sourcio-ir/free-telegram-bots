<?php
/**
 * سورس ربات قفل عضویت اجباری کانال تلگرام (Force Join)
 * توسعه داده شده توسط: سورس یو (sourcio.ir)
 * نسخه: 1.0.0 (رایگان)
 */

require_once __DIR__ . '/config.php';

$content = file_get_contents("php://input");
$update  = json_decode($content, true);

if (!$update) {
    echo "Sourcio Force Join Bot is Active!";
    exit;
}

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

// بررسی وضعیت عضویت کاربر در کانال با متد getChatMember
function checkJoin($user_id) {
    $res = bot('getChatMember', [
        'chat_id' => CHANNEL_USERNAME,
        'user_id' => $user_id
    ]);

    if ($res && isset($res['ok']) && $res['ok'] === true) {
        $status = $res['result']['status'];
        // وضعیت های معتبر عضویت: creator, administrator, member
        if (in_array($status, ['creator', 'administrator', 'member'])) {
            return true;
        }
    }
    return false;
}

// ساخت کیبورد شیشه ای جوین اجباری
function getJoinKeyboard() {
    return json_encode([
        'inline_keyboard' => [
            [
                ['text' => '📢 عضویت در کانال', 'url' => CHANNEL_LINK]
            ],
            [
                ['text' => '🔄 بررسی مجدد عضویت', 'callback_data' => 'check_membership']
            ]
        ]
    ]);
}

// مدیریت کلیک روی دکمه های شیشه ای (Callback Query)
if (isset($update['callback_query'])) {
    $cb         = $update['callback_query'];
    $cb_id      = $cb['id'];
    $cb_from_id = $cb['from']['id'];
    $cb_chat_id = $cb['message']['chat']['id'];
    $cb_msg_id  = $cb['message']['message_id'];
    $data       = $cb['data'];

    if ($data === 'check_membership') {
        if (checkJoin($cb_from_id)) {
            bot('answerCallbackQuery', [
                'callback_query_id' => $cb_id,
                'text'              => "عضویت شما تایید شد!",
                'show_alert'        => false
            ]);
            bot('editMessageText', [
                'chat_id'    => $cb_chat_id,
                'message_id' => $cb_msg_id,
                'text'       => SUCCESS_TEXT
            ]);
        } else {
            bot('answerCallbackQuery', [
                'callback_query_id' => $cb_id,
                'text'              => "شما هنوز در کانال عضو نشده اید!",
                'show_alert'        => true
            ]);
        }
    }
    exit;
}

// مدیریت پیام های متنی
if (isset($update['message'])) {
    $message    = $update['message'];
    $chat_id    = $message['chat']['id'];
    $from_id    = $message['from']['id'];
    $text       = isset($message['text']) ? $message['text'] : '';

    // چک کردن عضویت در کانال
    if (!checkJoin($from_id)) {
        bot('sendMessage', [
            'chat_id'      => $chat_id,
            'text'         => JOIN_REQUIRED_TEXT,
            'reply_markup' => getJoinKeyboard()
        ]);
        exit;
    }

    // اگر عضو بود:
    if ($text === '/start') {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text'    => SUCCESS_TEXT
        ]);
        exit;
    }

    // پاسخ به سایر پیام ها
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text'    => "پیام شما دریافت شد. ربات آماده ارائه خدمات است.\nسورس یو: sourcio.ir"
    ]);
    exit;
}
