<?php

session_start();

class BotProtection {
    private $blocked_ips_file = 'blocked_ips.txt';
    private $rate_limit_file = 'rate_limit.json';
    private $max_requests_per_minute = 30; 
    private $captcha_required_after = 40; 
    
    private $bot_signatures = [
        'curl/', 'wget/', 'python-requests', 'scrapy', 'go-http-client',
        'java/', 'libwww-perl', 'googlebot', 'bingbot', 'yandexbot', 'duckduckbot',
        'facebookexternalhit', 'twitterbot', 'whatsapp',  'mechanize', 'phantomjs', 'headlesschrome'
    ];
    
    private $allowed_bots = [
        'telegram'
    ];
    
    public function __construct() {
        $this->checkVisitor();
    }
    
    private function checkVisitor() {
        $ip = $this->getClientIP();
        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        if (isset($_GET['js_verify']) && isset($_SESSION['js_token'])) {
            if ($_GET['js_verify'] === $_SESSION['js_token']) {
                $_SESSION['verified'] = true;
                $_SESSION['verified_time'] = time();
                setcookie('human_verified', '1', time() + 86400, '/', '', false, true);
                unset($_SESSION['js_token']);
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }
        }
        
        if (isset($_COOKIE['js_check']) && $_COOKIE['js_check'] === '1') {
            $_SESSION['verified'] = true;
            $_SESSION['verified_time'] = time();
            setcookie('human_verified', '1', time() + 86400, '/', '', false, true);
            setcookie('js_check', '', time() - 3600, '/');
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        
        if ($this->isIPBlocked($ip)) {
            $this->showCaptcha("Access temporarily restricted");
            return;
        }
        
        if ($this->isDefinitelyBot($user_agent)) {
            $this->blockAccess("Automated access detected");
            return;
        }
        
        $requests = $this->getRequestCount($ip);
        if ($requests > $this->max_requests_per_minute) {
            $this->blockIPTemporarily($ip);
            $this->showCaptcha("Too many requests");
            return;
        } elseif ($requests > $this->captcha_required_after) {
            $this->showCaptcha("Security verification required");
            return;
        }
        
        if (!isset($_COOKIE['human_verified']) && !isset($_SESSION['verified'])) {
            $this->showSimpleJSCheck();
            return;
        }
    }
    
    private function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function isDefinitelyBot($user_agent) {
        foreach ($this->allowed_bots as $allowed) {
            if (strpos($user_agent, $allowed) !== false) {
                return false;
            }
        }
        
        foreach ($this->bot_signatures as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }
        
        if (empty($user_agent)) {
            return true;
        }
        
        return false;
    }
    
    private function getRequestCount($ip) {
        $rate_limits = [];
        
        if (file_exists($this->rate_limit_file)) {
            $rate_limits = json_decode(file_get_contents($this->rate_limit_file), true) ?: [];
        }
        
        $current_time = time();
        $minute_ago = $current_time - 60;
        
        foreach ($rate_limits as $stored_ip => $timestamps) {
            $rate_limits[$stored_ip] = array_filter($timestamps, function($ts) use ($minute_ago) {
                return $ts > $minute_ago;
            });
        }
        
        if (!isset($rate_limits[$ip])) {
            $rate_limits[$ip] = [];
        }
        $rate_limits[$ip][] = $current_time;
        
        file_put_contents($this->rate_limit_file, json_encode($rate_limits), LOCK_EX);
        
        return count($rate_limits[$ip]);
    }
    
    private function isIPBlocked($ip) {
        if (!file_exists($this->blocked_ips_file)) {
            return false;
        }
        
        $blocked = file($this->blocked_ips_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($blocked as $entry) {
            list($blocked_ip, $timestamp) = explode('|', $entry . '|0');
            if ($blocked_ip === $ip) {
                if ($timestamp && (time() - $timestamp) > 3600) {
                    continue;
                }
                return true;
            }
        }
        return false;
    }
    
    private function blockIPTemporarily($ip) {
        $entry = $ip . '|' . time() . PHP_EOL;
        file_put_contents($this->blocked_ips_file, $entry, FILE_APPEND | LOCK_EX);
    }
    
    private function showSimpleJSCheck() {
        $token = bin2hex(random_bytes(32));
        $_SESSION['js_token'] = $token;
        
        ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка безопасности</title>
    <style>
        :root {
            --vp-bg: #0b0f19;
            --vp-card: rgba(255, 255, 255, 0.04);
            --vp-accent: #6c5ce7;
            --vp-accent-2: #00cec9;
            --vp-text: #eef2ff;
            --vp-muted: #a5b4fc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
        }

        html, body {
            height: 100%;
        }

        body {
            background:
                radial-gradient(1200px 600px at 15% 10%, rgba(108, 92, 231, 0.25), transparent 60%),
                radial-gradient(900px 500px at 85% 90%, rgba(0, 206, 201, 0.18), transparent 60%),
                url(/images/desktop-background.jpg) center/cover no-repeat fixed,
                var(--vp-bg);
            min-height: 100vh;
            display: grid;
            place-items: center;
            color: var(--vp-text);
            overflow: hidden;
        }

        .vp-shell {
            width: min(92vw, 460px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 22px;
            text-align: center;
            padding: 32px 24px 18vh;
        }

        .vp-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 12px 30px rgba(0, 0, 0, 0.45));
        }

        .vp-brand img {
            width: min(78vw, 320px);
            height: auto;
            display: block;
        }

        .vp-status {
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            color: var(--vp-text);
            background: var(--vp-card);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 999px;
            padding: 10px 22px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .vp-loader {
            position: relative;
            width: 64px;
            height: 64px;
        }

        .vp-loader::before,
        .vp-loader::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 3px solid transparent;
        }

        .vp-loader::before {
            border-top-color: var(--vp-accent);
            border-right-color: var(--vp-accent-2);
            animation: vp-spin 0.9s cubic-bezier(0.6, 0.2, 0.4, 0.8) infinite;
        }

        .vp-loader::after {
            inset: 10px;
            border-bottom-color: rgba(255, 255, 255, 0.35);
            animation: vp-spin-reverse 1.4s linear infinite;
        }

        @keyframes vp-spin {
            to { transform: rotate(360deg); }
        }

        @keyframes vp-spin-reverse {
            to { transform: rotate(-360deg); }
        }

        .vp-hint {
            font-size: 0.8rem;
            color: var(--vp-muted);
            opacity: 0.85;
        }

        @media (max-width: 420px) {
            .vp-shell { padding-bottom: 12vh; }
            .vp-status { font-size: 0.92rem; }
        }
    </style>
</head>
<body>
    <div class="vp-shell">
        <div class="vp-brand">
            <img src="images/logo_large-highcontrast-b0ea17ccbcee1ad7b0cb2ef4d5083204.png" alt="logo">
        </div>
        <div class="vp-status">Проверяем безопасное соединение…</div>
        <div class="vp-loader" aria-hidden="true"></div>
        <div class="vp-hint">Это займёт всего пару секунд</div>
    </div>
    
    <script>
        (function () {
            var token = "<?php echo $token; ?>";
            var endpoint = "?js_verify=" + encodeURIComponent(token);

            function fallback() {
                document.cookie = "js_check=1; path=/; max-age=3600; SameSite=Lax";
                setTimeout(function () { window.location.reload(); }, 400);
            }

            fetch(endpoint, {
                method: "GET",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                credentials: "same-origin"
            })
            .then(function (res) {
                if (res.ok) {
                    window.location.reload();
                } else {
                    fallback();
                }
            })
            .catch(fallback);

            setTimeout(function () {
                if (document.cookie.indexOf("human_verified") === -1) {
                    fallback();
                }
            }, 3000);
        })();
    </script>
</body>
</html>
        <?php
        exit;
    }
    
    private function showCaptcha($reason = '') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha'])) {
            if ($this->verifyCaptcha($_POST['captcha'])) {
                $_SESSION['verified'] = true;
                $_SESSION['verified_time'] = time();
                setcookie('human_verified', '1', time() + 86400, '/', '', false, true);
                
                $this->removeIPFromBlocklist($this->getClientIP());
                
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } else {
                $error = true;
            }
        }
        
        $captcha = $this->generateCaptcha();
        $_SESSION['captcha_answer'] = $captcha['answer'];
        
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <title>Проверка безопасности</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                :root {
                    --cc-ink: #0f172a;
                    --cc-muted: #64748b;
                    --cc-line: #e2e8f0;
                    --cc-soft: #f8fafc;
                    --cc-brand: #5b21b6;
                    --cc-brand-2: #7c3aed;
                    --cc-danger: #dc2626;
                    --cc-danger-bg: #fef2f2;
                    --cc-danger-line: #fecaca;
                    --cc-radius: 16px;
                }

                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
                }

                body {
                    background:
                        radial-gradient(800px 400px at 20% 0%, rgba(124, 58, 237, 0.06), transparent 60%),
                        #ffffff;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 24px;
                    color: var(--cc-ink);
                }

                .cc-card {
                    background: #ffffff;
                    border: 1px solid var(--cc-line);
                    border-radius: var(--cc-radius);
                    max-width: 480px;
                    width: 100%;
                    overflow: hidden;
                    box-shadow: 0 24px 60px -30px rgba(15, 23, 42, 0.25);
                }

                .cc-top {
                    padding: 30px 28px 22px;
                    border-bottom: 1px solid var(--cc-soft);
                    text-align: center;
                }

                .cc-logo {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 10px;
                }

                .cc-logo img {
                    width: 160px;
                    height: auto;
                    display: block;
                }

                .cc-kicker {
                    font-size: 13px;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    color: var(--cc-muted);
                    font-weight: 600;
                }

                .cc-body {
                    padding: 28px;
                }

                .cc-title {
                    font-size: 22px;
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: var(--cc-ink);
                }

                .cc-lead {
                    color: var(--cc-muted);
                    margin-bottom: 24px;
                    font-size: 14px;
                    line-height: 1.55;
                }

                .cc-challenge {
                    background: var(--cc-soft);
                    border: 1px dashed var(--cc-line);
                    border-radius: 12px;
                    padding: 22px;
                    margin-bottom: 22px;
                    text-align: center;
                }

                .cc-question {
                    font-size: 19px;
                    color: var(--cc-ink);
                    font-weight: 600;
                }

                .cc-grid {
                    display: grid;
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                    gap: 12px;
                    margin-bottom: 24px;
                }

                .cc-tile {
                    position: relative;
                    aspect-ratio: 1 / 1;
                    border: 2px solid var(--cc-line);
                    border-radius: 12px;
                    background: #ffffff;
                    cursor: pointer;
                    transition: border-color 0.18s ease, background 0.18s ease, transform 0.18s ease;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 30px;
                    user-select: none;
                }

                .cc-tile:hover {
                    border-color: var(--cc-brand-2);
                    transform: translateY(-1px);
                }

                .cc-tile.is-active {
                    border-color: var(--cc-brand);
                    background: var(--cc-brand);
                    color: #ffffff;
                    box-shadow: 0 10px 24px -12px rgba(91, 33, 182, 0.8);
                }

                .cc-tile input[type="radio"] {
                    position: absolute;
                    opacity: 0;
                    pointer-events: none;
                }

                .cc-field {
                    width: 100%;
                    padding: 13px 16px;
                    border: 1px solid var(--cc-line);
                    border-radius: 10px;
                    font-size: 16px;
                    margin-bottom: 20px;
                    background: #ffffff;
                    color: var(--cc-ink);
                    transition: border-color 0.18s ease, box-shadow 0.18s ease;
                }

                .cc-field:focus {
                    outline: none;
                    border-color: var(--cc-brand-2);
                    box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.12);
                }

                .cc-submit {
                    background: var(--cc-brand);
                    color: #ffffff;
                    border: none;
                    border-radius: 10px;
                    padding: 13px 28px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.18s ease, transform 0.18s ease;
                    width: 100%;
                }

                .cc-submit:hover {
                    background: var(--cc-brand-2);
                }

                .cc-submit:active {
                    transform: translateY(1px);
                }

                .cc-error {
                    color: var(--cc-danger);
                    margin-bottom: 20px;
                    font-size: 14px;
                    padding: 12px 14px;
                    background: var(--cc-danger-bg);
                    border: 1px solid var(--cc-danger-line);
                    border-radius: 10px;
                }

                .cc-note {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 12px;
                    color: var(--cc-muted);
                    margin-top: 24px;
                    padding-top: 22px;
                    border-top: 1px solid var(--cc-soft);
                }

                .cc-note-icon {
                    font-size: 14px;
                }

                @media (max-width: 420px) {
                    .cc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                    .cc-body { padding: 22px; }
                    .cc-title { font-size: 20px; }
                }
            </style>
        </head>
        <body>
            <div class="cc-card">
                <div class="cc-top">
                    <div class="cc-logo">
                        <img src="images/logo_large-highcontrast-b0ea17ccbcee1ad7b0cb2ef4d5083204.png" alt="logo">
                    </div>
                    <div class="cc-kicker">Проверка безопасности</div>
                </div>
                
                <div class="cc-body">
                    <h1 class="cc-title">Подтвердите, что вы человек</h1>
                    <p class="cc-lead">Для вашей безопасности пройдите быструю проверку, чтобы продолжить.</p>
                    
                    <?php if (isset($error)): ?>
                        <div class="cc-error">Неверный ответ. Попробуйте ещё раз.</div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <?php if ($captcha['type'] === 'math'): ?>
                            <div class="cc-challenge">
                                <div class="cc-question"><?php echo $captcha['question']; ?></div>
                            </div>
                            <input type="text" name="captcha" class="cc-field" placeholder="Введите ответ" required autofocus>
                        <?php elseif ($captcha['type'] === 'icons'): ?>
                            <div class="cc-challenge">
                                <div class="cc-question"><?php echo $captcha['question']; ?></div>
                            </div>
                            <div class="cc-grid">
                                <?php foreach ($captcha['options'] as $value => $icon): ?>
                                    <label class="cc-tile">
                                        <input type="radio" name="captcha" value="<?php echo $value; ?>" required>
                                        <span><?php echo $icon; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="cc-submit">Продолжить</button>
                    </form>
                    
                    <div class="cc-note">
                        <span class="cc-note-icon">🔒</span>
                        <span>Эта проверка защищает ваш аккаунт от несанкционированного доступа.</span>
                    </div>
                </div>
            </div>
            
            <script>
                (function () {
                    var tiles = document.querySelectorAll(".cc-tile");
                    tiles.forEach(function (tile) {
                        tile.addEventListener("click", function () {
                            tiles.forEach(function (t) { t.classList.remove("is-active"); });
                            tile.classList.add("is-active");
                            var input = tile.querySelector("input");
                            if (input) { input.checked = true; }
                        });
                    });

                    var field = document.querySelector(".cc-field");
                    if (field) { field.focus(); }
                })();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    private function generateCaptcha() {
        $types = ['math', 'icons'];
        $type = $types[array_rand($types)];
        
        if ($type === 'math') {
            $operations = [
                ['text' => 'плюс', 'symbol' => '+'],
                ['text' => 'минус', 'symbol' => '-']
            ];
            
            $a = rand(1, 10);
            $b = rand(1, 10);
            $op = $operations[array_rand($operations)];
            
            if ($op['symbol'] === '-' && $b > $a) {
                list($a, $b) = [$b, $a];
            }
            
            $answer = $op['symbol'] === '+' ? $a + $b : $a - $b;
            
            return [
                'type' => 'math',
                'question' => "Сколько будет $a {$op['text']} $b?",
                'answer' => $answer
            ];
        } else {
            $questions = [
                [
                    'question' => 'Выберите транспорт:',
                    'correct' => '🚗',
                    'options' => ['🚗', '🏠', '📱', '🌳']
                ],
                [
                    'question' => 'Выберите здание:',
                    'correct' => '🏢',
                    'options' => ['🐕', '🏢', '🍎', '⚽']
                ],
                [
                    'question' => 'Выберите замок:',
                    'correct' => '🔒',
                    'options' => ['💰', '📊', '🔒', '📈']
                ],
                [
                    'question' => 'Выберите ключ:',
                    'correct' => '🔑',
                    'options' => ['🔑', '📧', '🌐', '💳']
                ],
                [
                    'question' => 'Выберите щит:',
                    'correct' => '🛡️',
                    'options' => ['📱', '🛡️', '🎯', '📋']
                ]
            ];
            
            $q = $questions[array_rand($questions)];
            shuffle($q['options']);
            
            return [
                'type' => 'icons',
                'question' => $q['question'],
                'answer' => array_search($q['correct'], $q['options']),
                'options' => $q['options']
            ];
        }
    }
    
    private function verifyCaptcha($answer) {
        if (!isset($_SESSION['captcha_answer'])) {
            return false;
        }
        
        $correct = $_SESSION['captcha_answer'];
        unset($_SESSION['captcha_answer']);
        
        return $answer == $correct;
    }
    
    private function removeIPFromBlocklist($ip) {
        if (!file_exists($this->blocked_ips_file)) {
            return;
        }
        
        $lines = file($this->blocked_ips_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $new_lines = [];
        
        foreach ($lines as $line) {
            list($blocked_ip) = explode('|', $line . '|');
            if ($blocked_ip !== $ip) {
                $new_lines[] = $line;
            }
        }
        
        file_put_contents($this->blocked_ips_file, implode(PHP_EOL, $new_lines) . PHP_EOL);
    }
    
    private function blockAccess($reason) {
        $log_entry = date('Y-m-d H:i:s') . " | " . $this->getClientIP() . " | " . $reason . " | " . 
                     ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . PHP_EOL;
        file_put_contents('bot_blocks.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        http_response_code(403);
        die('<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>Доступ запрещён</title><style>body{font-family:Arial,sans-serif;background:#0b0f19;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;color:#eef2ff}.container{background:#111827;padding:40px 44px;border:1px solid #1f2937;border-radius:16px;text-align:center;box-shadow:0 20px 50px -20px rgba(0,0,0,.6)}h1{color:#f87171;font-size:22px;margin:0 0 8px}p{color:#94a3b8;margin:0;font-size:14px}</style></head><body><div class="container"><h1>Доступ запрещён</h1><p>Ошибка 403: Forbidden</p></div></body></html>');
    }
}

new BotProtection();

function isHumanVerified() {
    return isset($_SESSION['verified']) || isset($_COOKIE['human_verified']);
}

function clearSession() {
    session_destroy();
    setcookie('human_verified', '', time() - 3600, '/');
    setcookie('js_check', '', time() - 3600, '/');
}
?>