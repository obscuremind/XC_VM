<?php

register_shutdown_function('shutdown');
require 'init.php';
set_time_limit(0);
header('Access-Control-Allow-Origin: *');

$rDeny = true;
$rDownloading = false;
$db = null;
$rUserInfo = null;
$loginIdentifier = null;

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$requestPath = ltrim(parse_url($requestUri, PHP_URL_PATH) ?? '', '/');
$scriptBase = strtolower(explode('.', $requestPath, 2)[0] ?? '');

if ($scriptBase === 'get' || empty(CoreUtilities::$rSettings['legacy_get'])) {
        $rDeny = false;
        generateError('LEGACY_GET_DISABLED');
}

$rIP = CoreUtilities::getUserIP();
$ipInfo = CoreUtilities::getIPInfo($rIP);
$rCountryCode = $ipInfo['country']['iso_code'] ?? null;
$rUserAgent = isset($_SERVER['HTTP_USER_AGENT']) ? htmlentities(trim($_SERVER['HTTP_USER_AGENT'])) : '';
$rDeviceKey = CoreUtilities::$rRequest['type'] ?? 'm3u_plus';
$rTypeKey = isset(CoreUtilities::$rRequest['key']) && CoreUtilities::$rRequest['key'] !== '' ? explode(',', CoreUtilities::$rRequest['key']) : null;
$rOutputKey = CoreUtilities::$rRequest['output'] ?? '';
$rNoCache = !empty(CoreUtilities::$rRequest['nocache']);

if (isset(CoreUtilities::$rRequest['username'], CoreUtilities::$rRequest['password'])) {
        $rUsername = trim(CoreUtilities::$rRequest['username']);
        $rPassword = trim(CoreUtilities::$rRequest['password']);
        $loginIdentifier = $rUsername;

        if ($rUsername === '' || $rPassword === '') {
                generateError('NO_CREDENTIALS');
        }

        $rUserInfo = CoreUtilities::getUserInfo(null, $rUsername, $rPassword, true, false, $rIP);
} elseif (!empty(CoreUtilities::$rRequest['token'])) {
        $loginIdentifier = CoreUtilities::$rRequest['token'];
        $rUserInfo = CoreUtilities::getUserInfo(null, $loginIdentifier, null, true, false, $rIP);
} else {
        generateError('NO_CREDENTIALS');
}

ini_set('memory_limit', -1);

if (!$rUserInfo) {
        CoreUtilities::checkBruteforce(null, null, $loginIdentifier);
        generateError('INVALID_CREDENTIALS');
}

$rDeny = false;

if (empty($rUserInfo['is_restreamer']) && !empty(CoreUtilities::$rSettings['disable_playlist'])) {
        generateError('PLAYLIST_DISABLED');
}

if (!empty($rUserInfo['is_restreamer']) && !empty(CoreUtilities::$rSettings['disable_playlist_restreamer'])) {
        generateError('PLAYLIST_DISABLED');
}

if (isset($rUserInfo['bypass_ua']) && intval($rUserInfo['bypass_ua']) === 0 && CoreUtilities::checkBlockedUAs($rUserAgent, true)) {
        generateError('BLOCKED_USER_AGENT');
}

$expDate = $rUserInfo['exp_date'] ?? null;
if (!is_null($expDate) && $expDate <= time()) {
        generateError('EXPIRED');
}

if (!empty($rUserInfo['is_mag']) || !empty($rUserInfo['is_e2'])) {
        generateError('DEVICE_NOT_ALLOWED');
}

if (empty($rUserInfo['admin_enabled'])) {
        generateError('BANNED');
}

if (empty($rUserInfo['enabled'])) {
        generateError('DISABLED');
}

if (!empty(CoreUtilities::$rSettings['restrict_playlists'])) {
        if ($rUserAgent === '' && (CoreUtilities::$rSettings['disallow_empty_user_agents'] ?? 0) == 1) {
                generateError('EMPTY_USER_AGENT');
        }

        $allowedIps = $rUserInfo['allowed_ips'] ?? array();
        if (!empty($allowedIps)) {
                $resolvedIps = array_map('gethostbyname', $allowedIps);

                if (!in_array($rIP, $resolvedIps, true)) {
                        generateError('NOT_IN_ALLOWED_IPS');
                }
        }

        if (!empty($rCountryCode)) {
                $forcedCountry = $rUserInfo['forced_country'] ?? null;
                $hasForcedCountry = !empty($forcedCountry);

                if ($hasForcedCountry && $forcedCountry !== 'ALL' && $rCountryCode !== $forcedCountry) {
                        generateError('FORCED_COUNTRY_INVALID');
                }

                $allowedCountries = CoreUtilities::$rSettings['allow_countries'] ?? array();
                if (!$hasForcedCountry
                        && !in_array('ALL', $allowedCountries, true)
                        && !in_array($rCountryCode, $allowedCountries, true)) {
                        generateError('NOT_IN_ALLOWED_COUNTRY');
                }
        }

        $allowedUserAgents = $rUserInfo['allowed_ua'] ?? array();
        if (!empty($allowedUserAgents) && !in_array($rUserAgent, $allowedUserAgents, true)) {
                generateError('NOT_IN_ALLOWED_UAS');
        }

        if (isset($rUserInfo['isp_violate']) && intval($rUserInfo['isp_violate']) === 1) {
                generateError('ISP_BLOCKED');
        }

        if (isset($rUserInfo['isp_is_server'])
                && intval($rUserInfo['isp_is_server']) === 1
                && empty($rUserInfo['is_restreamer'])) {
                generateError('ASN_BLOCKED');
        }
}

$rDownloading = true;

if (CoreUtilities::startDownload('playlist', $rUserInfo, getmypid())) {
        $db = new Database($_INFO['username'], $_INFO['password'], $_INFO['database'], $_INFO['hostname'], $_INFO['port']);
        CoreUtilities::$db = &$db;

        $isProxy = CoreUtilities::isProxy($_SERVER['HTTP_X_IP'] ?? null);
        if (!CoreUtilities::generatePlaylist($rUserInfo, $rDeviceKey, $rOutputKey, $rTypeKey, $rNoCache, $isProxy)) {
                generateError('GENERATE_PLAYLIST_FAILED');
        }
} else {
        generateError('DOWNLOAD_LIMIT_REACHED', false);
        http_response_code(429);
        exit();
}

function shutdown() {
        global $db;
        global $rDeny;
        global $rDownloading;
        global $rUserInfo;

        if ($rDeny) {
                CoreUtilities::checkFlood();
        }

        if (is_object($db)) {
                $db->close_mysql();
        }

        if ($rDownloading) {
                CoreUtilities::stopDownload('playlist', $rUserInfo, getmypid());
        }
}
