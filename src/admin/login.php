<?php

require_once 'functions.php';

if (file_exists(TMP_PATH . '.migration.first')) {
    header('Location: setup');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['hash'])) {
    header('Location: dashboard');
    exit();
}

$rBypassRecaptcha = in_array(getCurrentCode(), array('setup', 'rescue'), true);

if ($rBypassRecaptcha) {
    $rSettings['recaptcha_enable'] = false;
}

$rIP = getIP();
$rLoginFloodLimit = intval($rSettings['login_flood'] ?? 0);

if ($rLoginFloodLimit > 0) {
    $db->query(
        "SELECT COUNT(`id`) AS `count` FROM `login_logs` WHERE `status` = 'INVALID_LOGIN' AND `login_ip` = ? " .
        'AND TIME_TO_SEC(TIMEDIFF(NOW(), `date`)) <= 86400;',
        $rIP
    );

    $rLoginAttempts = 0;

    if ($db->num_rows() === 1) {
        $rRow = $db->get_row();

        if (is_array($rRow) && isset($rRow['count'])) {
            $rLoginAttempts = intval($rRow['count']);
        }
    }

    if ($rLoginAttempts >= $rLoginFloodLimit) {
        API::blockIP(array('ip' => $rIP, 'notes' => 'LOGIN FLOOD ATTACK'));
        exit();
    }
}

$_STATUS = null;

if (!empty(CoreUtilities::$rRequest['login'])) {
    $rReturn = API::processLogin(CoreUtilities::$rRequest, $rBypassRecaptcha);
    $_STATUS = $rReturn['status'] ?? STATUS_FAILURE;

    if ($_STATUS === STATUS_SUCCESS) {
        if (getCurrentCode() === 'setup') {
            header('Location: codes');
            exit();
        }

        $rReferer = '';
        $rRequestReferrer = CoreUtilities::$rRequest['referrer'] ?? '';

        if ($rRequestReferrer !== '') {
            $rReferer = basename($rRequestReferrer);

            if (strpos($rReferer, 'logout') === 0) {
                $rReferer = 'dashboard';
            }
        }

        header('Location: ' . ($rReferer ?: 'dashboard'));
        exit();
    }
}

$rThemeIsDark = isset($_COOKIE['theme']) && $_COOKIE['theme'] == 1;
$rHue = $_COOKIE['hue'] ?? null;
$rHueIsValid = is_string($rHue) && $rHue !== '' && isset($rHues[$rHue]);
$rFocusColor = $rHueIsValid ? $rHues[$rHue] : '#4fc3f7';
$rReferrerValue = htmlspecialchars(CoreUtilities::$rRequest['referrer'] ?? '', ENT_QUOTES, 'UTF-8');
$rShowRecaptcha = !$rBypassRecaptcha && !empty($rSettings['recaptcha_enable']);
$rThemeColours = array(
    'content' => $rThemeIsDark ? '#1a1d29' : '#ffffff',
    'card' => $rThemeIsDark ? '#252a3d' : '#ffffff',
    'formBorder' => $rThemeIsDark ? '#3a4157' : '#e8ecf4',
    'formBackground' => $rThemeIsDark ? '#1e2139' : '#f8f9fa',
    'title' => $rThemeIsDark ? '#ffffff' : '#2c3e50',
    'subtitle' => $rThemeIsDark ? '#8b93a7' : '#7c8db0',
    'label' => $rThemeIsDark ? '#ffffff' : '#2c3e50',
    'footerBackground' => $rThemeIsDark ? '#252a3d' : '#f8f9fa',
    'footerBorder' => $rThemeIsDark ? '#3a4157' : '#e8ecf4',
    'footerText' => $rThemeIsDark ? '#8b93a7' : '#7c8db0',
);
$rButtonGradient = $rHueIsValid ? $rHues[$rHue] . ', ' . $rHues[$rHue] . 'cc' : '#4fc3f7, #4fc3f7cc';
$rFocusShadow = $rHueIsValid ? $rHues[$rHue] . '40' : '#4fc3f740';

$rStatusMessages = array(
    STATUS_FAILURE => $_['login_message_1'],
    STATUS_INVALID_CODE => $_['login_message_2'],
    STATUS_NOT_ADMIN => $_['login_message_3'],
    STATUS_DISABLED => $_['login_message_4'],
    STATUS_INVALID_CAPTCHA => $_['login_message_5'],
);

$rStatusMessage = ($_STATUS !== null && isset($rStatusMessages[$_STATUS])) ? $rStatusMessages[$_STATUS] : null;
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <title data-id="login">XC_VM | <?= $_['login'] ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link rel="shortcut icon" href="assets/images/favicon.ico">
        <link href="assets/css/icons.css" rel="stylesheet">
        <?php if ($rThemeIsDark): ?>
            <link href="assets/css/bootstrap.dark.css" rel="stylesheet">
            <link href="assets/css/app.dark.css" rel="stylesheet">
        <?php else: ?>
            <link href="assets/css/bootstrap.css" rel="stylesheet">
            <link href="assets/css/app.css" rel="stylesheet">
        <?php endif; ?>

        <link href="assets/css/extra.css" rel="stylesheet">

        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body,
            html {
                height: 100%;
                overflow: hidden;
            }

            .login-container {
                display: flex;
                height: 100vh;
                width: 100vw;
            }

            .video-section {
                width: 63%;
                height: 100%;
                position: relative;
                overflow: hidden;
            }

            .background-video {
                position: absolute;
                top: 45%;
                left: 50%;
                min-width: 100%;
                min-height: 100%;
                width: auto;
                height: auto;
                z-index: 1;
                transform: translateX(-50%) translateY(-50%);
                background-size: cover;
            }

            .video-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: linear-gradient(135deg, rgba(0, 0, 0, 0.3) 0%, rgba(0, 0, 0, 0.6) 100%);
                z-index: 0;
            }

            .login-section {
                width: 37%;
                height: 100%;
                display: flex;
                flex-direction: column;
                position: relative;
            }

            .login-content {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 40px;
                background: <?= $rThemeColours['content']; ?>;
            }

            .login-form-wrapper {
                width: 100%;
                max-width: 565px;
            }

            .logo-section {
                text-align: center;
                margin-bottom: 40px;
            }

            .logo-section img {
                height: 80px;
                margin-bottom: 20px;
            }

            .login-title {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 10px;
                color: <?= $rThemeColours['title']; ?>;
            }

            .login-subtitle {
                color: <?= $rThemeColours['subtitle']; ?>;
                margin-bottom: 30px;
            }

            .login-form .card {
                border: none;
                box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
                border-radius: 15px;
                overflow: hidden;
            }

            .login-form .card-body {
                padding: 40px;
                background: <?= $rThemeColours['card']; ?>;
            }

            .login-form .form-control {
                border-radius: 10px;
                padding: 15px 20px;
                font-size: 14px;
                border: 2px solid <?= $rThemeColours['formBorder']; ?>;
                background: <?= $rThemeColours['formBackground']; ?>;
                transition: all 0.3s ease;
            }

            .login-form .form-control:focus {
                border-color: <?= $rFocusColor; ?>;
                box-shadow: 0 0 0 0.2rem <?= $rFocusShadow; ?>;
            }

            .login-form label {
                font-weight: 600;
                margin-bottom: 8px;
                color: <?= $rThemeColours['label']; ?>;
            }

            .login-btn {
                border-radius: 10px;
                padding: 15px;
                font-size: 16px;
                font-weight: 600;
                border: none;
                width: 100%;
                transition: all 0.3s ease;
                background: linear-gradient(135deg, <?= $rButtonGradient; ?>);
            }

            .login-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }

            .alert {
                border-radius: 10px;
                border: none;
                padding: 15px 20px;
                margin-bottom: 25px;
            }

            .g-recaptcha {
                display: flex;
                justify-content: center;
                margin: 20px 0;
            }

            .login-footer {
                background: <?= $rThemeColours['footerBackground']; ?>;
                padding: 20px 40px;
                border-top: 1px solid <?= $rThemeColours['footerBorder']; ?>;
                text-align: center;
                color: <?= $rThemeColours['footerText']; ?>;
                font-size: 14px;
            }

            @media (max-width: 992px) {
                .login-container {
                    flex-direction: column;
                }

                .video-section {
                    display: none;
                }

                .login-section {
                    width: 100%;
                }

                .login-content {
                    padding: 20px;
                }

                .login-form .card-body {
                    padding: 30px;
                }
            }

            @media (max-width: 576px) {
                .login-content {
                    padding: 15px;
                }

                .login-form .card-body {
                    padding: 20px;
                }

                .login-footer {
                    padding: 15px 20px;
                }
            }
        </style>
    </head>

    <body>
        <div class="login-container">
            <div class="video-section">
                <video class="background-video" autoplay muted loop>
                    <source src="assets/videos/login-bg.mp4" type="video/mp4">
                    <source src="assets/videos/login-bg.webm" type="video/webm">
                </video>
                <div class="video-overlay"></div>
            </div>
            <div class="login-section">
                <div class="login-content">
                    <div class="login-form-wrapper">
                        <div class="logo-section">
                            <img src="assets/images/logo.png" alt="XC_VM Logo">
                            <div class="login-title">Welcome Back</div>
                            <div class="login-subtitle">Sign in to your account</div>
                        </div>
                        <?php if ($rStatusMessage !== null): ?>
                            <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                                <?= $rStatusMessage; ?>
                            </div>
                        <?php endif; ?>
                        <form action="./login" method="POST" data-parsley-validate class="login-form">
                            <div class="card">
                                <div class="card-body">
                                    <input type="hidden" name="referrer" value="<?= $rReferrerValue; ?>">

                                    <div class="form-group mb-3" id="username_group">
                                        <label for="username"><?= $_['username'] ?></label>
                                        <input class="form-control" autocomplete="off" type="text" id="username" name="username" required
                                            data-parsley-trigger="change" placeholder="Enter your username">
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="password"><?= $_['password'] ?></label>
                                        <input class="form-control" autocomplete="off" type="password" required
                                            data-parsley-trigger="change" id="password" name="password"
                                            placeholder="Enter your password">
                                    </div>

                                    <?php if ($rShowRecaptcha): ?>
                                        <div class="text-center">
                                            <div class="g-recaptcha" data-callback="recaptchaCallback"
                                                id="verification" data-sitekey="<?= $rSettings['recaptcha_v2_site_key'] ?>"></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="form-group mb-0 mt-4">
                                        <button class="login-btn" type="submit" id="login_button" name="login"
                                            <?= $rShowRecaptcha ? 'disabled' : '' ?>>
                                            <?= $_['login'] ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="login-footer">
                    <div>&copy; <?= date('Y') ?> XC_VM Admin Panel. All rights reserved.</div>
                </div>
            </div>
        </div>
        <script src="assets/js/vendor.min.js"></script>
        <script src="assets/libs/parsleyjs/parsley.min.js"></script>
        <script src="assets/js/app.min.js"></script>

        <?php if ($rShowRecaptcha): ?>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <script>
                function recaptchaCallback() {
                    $('#login_button').removeAttr('disabled');
                };
            </script>
        <?php endif; ?>
    </body>

    </html>
