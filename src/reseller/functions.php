<?php

if (!defined('MAIN_HOME')) {
    define('MAIN_HOME', '/home/xc_vm/');
}

require_once MAIN_HOME . 'includes/admin.php';

if (!empty($rMobile)) {
    $rSettings['js_navigate'] = 0;
}

if (isset($_SESSION['reseller'])) {
    $rUserInfo = getRegisteredUser($_SESSION['reseller']);

    if (!empty($rUserInfo['timezone'])) {
        date_default_timezone_set($rUserInfo['timezone']);
    }

    setcookie('hue', (string) ($rUserInfo['hue'] ?? ''), time() + 604800);
    setcookie('theme', (string) ($rUserInfo['theme'] ?? ''), time() + 604800);

    $memberGroupId = $rUserInfo['member_group_id'] ?? null;
    $userId = $rUserInfo['id'] ?? null;
    $rPermissions = ($memberGroupId !== null && $userId !== null)
        ? array_merge(getPermissions($memberGroupId), getGroupPermissions($userId))
        : [];

    $allReports = $rPermissions['all_reports'] ?? [];
    $rUserInfo['reports'] = array_map('intval', array_merge($userId !== null ? [$userId] : [], $allReports));

    $rIP = getIP();
    $sessionIP = $_SESSION['rip'] ?? '';

    if (!empty($rSettings['ip_subnet_match'])) {
        $rIPMatch = implode('.', array_slice(explode('.', $sessionIP), 0, -1)) === implode('.', array_slice(explode('.', $rIP), 0, -1));
    } else {
        $rIPMatch = $sessionIP === $rIP;
    }

    $isValid = !empty($rUserInfo)
        && !empty($rPermissions)
        && !empty($rPermissions['is_reseller'])
        && ($rIPMatch || empty($rSettings['ip_logout']));

    $verifyHash = md5(($rUserInfo['username'] ?? '') . '||' . ($rUserInfo['password'] ?? ''));
    $sessionVerify = $_SESSION['rverify'] ?? '';

    if (
        !$isValid
        || (!empty($rSettings['ip_logout']) && !$rIPMatch)
        || $sessionVerify !== $verifyHash
    ) {
        unset($rUserInfo, $rPermissions);

        destroySession('reseller');
        header('Location: ./index');

        exit();
    }

    if ($sessionIP !== $rIP && empty($rSettings['ip_logout'])) {
        $_SESSION['rip'] = $rIP;
    }
}

if (isset(CoreUtilities::$rRequest['status'])) {
    $_STATUS = (int) CoreUtilities::$rRequest['status'];
    $rArgs = CoreUtilities::$rRequest;
    unset($rArgs['status']);
    $customScript = setArgs($rArgs);
}
