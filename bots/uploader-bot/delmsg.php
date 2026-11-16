<?php
// Don't edit This File.
// Set CronJob ( 1 min ) 
/* Published in : Sourcio

Website : https://sourcio.ir
Telegram channel : @SourcioIR

Author :Arian 
Team : https://hectortm.ir | @HectorTM

Hosting : Hostingio.ir

منتشر شده در : سورسیو

وبسایت : https://sourcio.ir
کانال تلگرام : @SourcioIR

نویسنده : Arian
تیم : https://hectortm.ir | @HectorTM 
هاسیتنگ مورد تایید ما : 
Hostingio.ir

کپی برداری از این اثر بصورت غیر مجاز ( پاک کردن تگ های منبع (همین تگ) و تغییر نام و پاک کردن لینک ها) شرعا و قانونا حرام است و در صورت مشاهده توسط هکتورتیم پیگیری قضایی صورت میگیرد. */
//---------------------- i n c l u d e --------------------------------//
include "config.php";
//------------------------------------------------------//
flush();
ob_start();
ob_implicit_flush(1);
 ini_set( "expose_php","Off" );
ini_set( "Allow_url_fopen","Off" );
// functions restriction disabled
ini_set( "max_execution_time","25" );
ini_set( "max_input_time","25" );
ini_set( "memory_limit","15M" );
ini_set( "file_uploads","Off" );
ini_set( "post_max_size","10k" );
error_reporting(0);
ini_set( "log_errors","Off" );
ignore_user_abort(true);
date_default_timezone_set('Asia/Tehran');
define('API_KEY',$API_KC);
function bot($method,$datas=[]){
    $url = "https://api.telegram.org/bot".API_KEY."/".$method;
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_POSTFIELDS,$datas);
    $res = curl_exec($ch);
    if(curl_error($ch)){
        var_dump(curl_error($ch));
    }else{
        return json_decode($res);
    }
}
$get = mysqli_query($connect, "SELECT * FROM dbremove WHERE time <= ".time()."");
 while($row = $get->fetch_assoc()) {
  bot('deleteMessage',[
            'chat_id' => $row['id'],
            'message_id' => $row['message_id'],
  ]);
 $connect->query("DELETE FROM dbremove WHERE id = '{$row['id']}' and message_id = '{$row['message_id']}' LIMIT 1");
 }