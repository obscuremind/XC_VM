<?php

$rSessionTimeout = 60;

if (!defined('TMP_PATH')) {
        define('TMP_PATH', '/home/xc_vm/tmp/');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
}

$hasExpiredSession = isset($_SESSION['reseller'], $_SESSION['rlast_activity'])
        && (time() - (int) $_SESSION['rlast_activity']) > ($rSessionTimeout * 60);

if ($hasExpiredSession) {
        foreach (array('reseller', 'rip', 'rcode', 'rverify', 'rlast_activity') as $sessionKey) {
                unset($_SESSION[$sessionKey]);
        }

        session_regenerate_id(true);
}

if (!isset($_SESSION['reseller'])) {
        if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
                echo json_encode(array('result' => false));

                exit();
        }

        $requestPath = '';

        if (!empty($_SERVER['REQUEST_URI'])) {
                $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
        }

        $requestPath = $requestPath !== '' ? basename($requestPath, '.php') : '';
        $referrerSuffix = $requestPath !== '' ? '?referrer=' . rawurlencode($requestPath) : '';

        header('Location: login' . $referrerSuffix);

        exit();
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
        echo json_encode(array('result' => true));

        exit();
}

$_SESSION['rlast_activity'] = time();
session_write_close();
