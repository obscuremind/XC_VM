<?php
$rSkipVerify = true;
include 'functions.php';

if (file_exists('install.php') && !file_exists('config.php') && !extension_loaded('xc_vm')) {
    header('Location: install.php');
    exit;
}

destroySession();

define('CLIENT_INVALID', 0);
define('CLIENT_IS_E2', 1);
define('CLIENT_IS_MAG', 2);
define('CLIENT_IS_STALKER', 3);
define('CLIENT_EXPIRED', 4);
define('CLIENT_BANNED', 5);
define('CLIENT_DISABLED', 6);
define('CLIENT_DISALLOWED', 7);

$rErrors = array(
    'Invalid username or password.',
    'Enigma lines are not permitted here.',
    'MAG lines are not permitted here.',
    'Stalker lines are not permitted here.',
    'Your line has expired.',
    'Your line has been banned.',
    'Your line has been disabled.',
    'You are not allowed to access this player.'
);

$rStatus = null;

if (!empty(CoreUtilities::$rRequest['username']) || !empty(CoreUtilities::$rRequest['password'])) {
    $rIP = CoreUtilities::getUserIP();
    $rIPInfo = CoreUtilities::getIPInfo($rIP);
    $rCountryCode = $rIPInfo['country']['iso_code'] ?? '';
    $rUserInfo = CoreUtilities::getUserInfo(null, CoreUtilities::$rRequest['username'], CoreUtilities::$rRequest['password'], true);
    $rUserAgent = empty($_SERVER['HTTP_USER_AGENT']) ? '' : htmlentities(trim($_SERVER['HTTP_USER_AGENT']));
    $rAllowedCountries = CoreUtilities::$rSettings['allow_countries'] ?? array();
    $rDeny = true;

    if (!$rUserInfo) {
        $rStatus = CLIENT_INVALID;
    } elseif (!empty($rUserInfo['is_e2'])) {
        $rStatus = CLIENT_IS_E2;
    } elseif (!empty($rUserInfo['is_mag'])) {
        $rStatus = CLIENT_IS_MAG;
    } elseif (!empty($rUserInfo['is_stalker'])) {
        $rStatus = CLIENT_IS_STALKER;
    } elseif (!is_null($rUserInfo['exp_date']) && $rUserInfo['exp_date'] <= time()) {
        $rStatus = CLIENT_EXPIRED;
    } elseif (empty($rUserInfo['admin_enabled'])) {
        $rStatus = CLIENT_BANNED;
    } elseif (empty($rUserInfo['enabled'])) {
        $rStatus = CLIENT_DISABLED;
    } else {
        $rAllowedIPs = array();
        if (!empty($rUserInfo['allowed_ips']) && is_array($rUserInfo['allowed_ips'])) {
            $rAllowedIPs = array_map('gethostbyname', $rUserInfo['allowed_ips']);
        }

        $rForceCountry = !empty($rUserInfo['forced_country']);
        $rCountryAllowed = true;

        if (!empty($rAllowedIPs) && !in_array($rIP, $rAllowedIPs, true)) {
            $rStatus = CLIENT_DISALLOWED;
        } elseif (!empty($rCountryCode)) {
            if ($rForceCountry && $rUserInfo['forced_country'] != 'ALL' && $rCountryCode != $rUserInfo['forced_country']) {
                $rCountryAllowed = false;
            } elseif (!($rForceCountry || in_array('ALL', $rAllowedCountries, true) || in_array($rCountryCode, $rAllowedCountries, true))) {
                $rCountryAllowed = false;
            }

            if (!$rCountryAllowed) {
                $rStatus = CLIENT_DISALLOWED;
            }
        }

        if (is_null($rStatus)) {
            if (!empty($rUserInfo['allowed_ua']) && is_array($rUserInfo['allowed_ua']) && !in_array($rUserAgent, $rUserInfo['allowed_ua'], true)) {
                $rStatus = CLIENT_DISALLOWED;
            } elseif (!empty($rUserInfo['isp_violate'])) {
                $rStatus = CLIENT_DISALLOWED;
            } elseif (!empty($rUserInfo['isp_is_server']) && empty($rUserInfo['is_restreamer'])) {
                $rStatus = CLIENT_DISALLOWED;
            } else {
                $rDeny = false;
                $_SESSION['phash'] = $rUserInfo['id'];
                $_SESSION['pverify'] = md5($rUserInfo['username'] . '||' . $rUserInfo['password']);
                header('Location: index.php');
                exit;
            }
        }
    }

    if ($rDeny) {
        CoreUtilities::checkFlood();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="css/bootstrap-reboot.min.css">
    <link rel="stylesheet" href="css/bootstrap-grid.min.css">
    <link rel="stylesheet" href="css/default-skin.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="shortcut icon" href="img/favicon.ico">
    <title><?php echo CoreUtilities::$rSettings['server_name']; ?></title>
</head>
<body class="body" style="padding-bottom: 0 !important;">
    <div class="sign">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="sign__content">
                    <?php if (file_exists('install.php')) { ?>
                        <div class="alert bg-animate" style="color: #fff;padding-top: 80px; padding-bottom: 80px;">
                            Installation has been completed!<br/><br/>Please delete <strong>install.php</strong> to continue.
                        </div>
                    <?php } else { ?>
                        <form action="./login.php" class="sign__form" method="post">
                            <span class="sign__logo">
                                <img src="img/logo.png" alt="" height="80px">
                            </span>
                            <div class="sign__group">
                                <input type="text" name="username" class="sign__input" placeholder="Username">
                            </div>
                            <div class="sign__group">
                                <input type="password" name="password" class="sign__input" placeholder="Password">
                            </div>
                            <?php if (!is_null($rStatus)) { ?>
                            <div class="alert alert-danger">
                                <?php echo $rErrors[$rStatus]; ?>
                            </div>
                            <?php } ?>
                            <button class="sign__btn" type="submit">LOGIN</button>
                        </form>
                    <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="js/jquery-3.5.1.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
