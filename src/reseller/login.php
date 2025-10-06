<?php

require_once 'functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['reseller'])) {
    header('Location: dashboard');
    exit();
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
    $rReturn = ResellerAPI::processLogin(CoreUtilities::$rRequest);
    $_STATUS = $rReturn['status'] ?? STATUS_FAILURE;

    if ($_STATUS === STATUS_SUCCESS) {
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
$rBodyClass = 'bg-animate' . ($rHueIsValid ? '-' . $rHue : '');
$rButtonClass = 'bg-animate-' . ($rHueIsValid ? $rHue : 'info');
$rReferrerValue = htmlspecialchars(CoreUtilities::$rRequest['referrer'] ?? '', ENT_QUOTES, 'UTF-8');
$rShowRecaptcha = !empty($rSettings['recaptcha_enable']);

$rStatusMessages = array(
    STATUS_FAILURE => $_['login_message_1'],
    STATUS_INVALID_CODE => $_['login_message_2'],
    STATUS_NOT_RESELLER => $_['login_message_3'],
    STATUS_DISABLED => $_['login_message_4'],
    STATUS_INVALID_CAPTCHA => $_['login_message_5'],
);

$rStatusMessage = ($_STATUS !== null && isset($rStatusMessages[$_STATUS])) ? $rStatusMessages[$_STATUS] : null;
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title data-id="login">XC_VM | <?= $_['login']; ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <link rel="shortcut icon" href="assets/images/favicon.ico">
        <link href="assets/css/icons.css" rel="stylesheet" type="text/css" />
        <?php if ($rThemeIsDark): ?>
            <link href="assets/css/bootstrap.dark.css" rel="stylesheet" type="text/css" />
            <link href="assets/css/app.dark.css" rel="stylesheet" type="text/css" />
        <?php else: ?>
            <link href="assets/css/bootstrap.css" rel="stylesheet" type="text/css" />
            <link href="assets/css/app.css" rel="stylesheet" type="text/css" />
        <?php endif; ?>
        <link href="assets/css/extra.css" rel="stylesheet" type="text/css" />
        <style>
            .g-recaptcha {
                display: inline-block;
            }
            .vertical-center {
                margin: 0;
                position: absolute;
                top: 50%;
                -ms-transform: translateY(-50%);
                transform: translateY(-50%);
                width: 100%;
            }
        </style>
    </head>
    <body class="<?= $rBodyClass; ?>">
        <div class="body-full navbar-custom">
            <div class="account-pages vertical-center">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-8 col-lg-6 col-xl-5">
                            <div class="text-center w-75 m-auto">
                                <span><img src="assets/images/logo.png" height="80px" alt=""></span>
                                <p class="text-muted mb-4 mt-3"></p>
                            </div>
                            <?php if ($rStatusMessage !== null): ?>
                                <div class="alert alert-danger alert-dismissible bg-danger text-white border-0 fade show" role="alert">
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                    <?= $rStatusMessage; ?>
                                </div>
                            <?php endif; ?>
                            <form action="./login" method="POST" data-parsley-validate="">
                                <div class="card">
                                    <div class="card-body p-4">
                                        <input type="hidden" name="referrer" value="<?= $rReferrerValue; ?>" />
                                        <div class="form-group mb-3" id="username_group">
                                            <label for="username"><?= $_['username']; ?></label>
                                            <input class="form-control" autocomplete="off" type="text" id="username" name="username" required data-parsley-trigger="change" placeholder="">
                                        </div>
                                        <div class="form-group mb-3">
                                            <label for="password"><?= $_['password']; ?></label>
                                            <input class="form-control" autocomplete="off" type="password" required data-parsley-trigger="change" id="password" name="password" placeholder="">
                                        </div>
                                        <?php if ($rShowRecaptcha): ?>
                                            <h5 class="auth-title text-center" style="margin-bottom:0;">
                                                <div class="g-recaptcha" data-callback="recaptchaCallback" id="verification" data-sitekey="<?= $rSettings['recaptcha_v2_site_key']; ?>"></div>
                                            </h5>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="form-group mb-0 text-center">
                                    <button style="border:0" class="btn btn-info <?= $rButtonClass; ?> btn-block" type="submit" id="login_button" name="login"<?= $rShowRecaptcha ? ' disabled' : ''; ?>>
                                        <?= $_['login']; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="assets/js/vendor.min.js"></script>
        <script src="assets/libs/parsleyjs/parsley.min.js"></script>
        <script src="assets/js/app.min.js"></script>
        <?php if ($rShowRecaptcha): ?>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <?php endif; ?>
        <script>
            function recaptchaCallback() {
                $('#login_button').removeAttr('disabled');
            }
        </script>
    </body>
</html>
