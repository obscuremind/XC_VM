<?php

$rSessionTimeout = 60;

if (!defined('TMP_PATH')) {
        define('TMP_PATH', '/home/xc_vm/tmp/');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
}

$hasExpiredSession = isset($_SESSION['hash'], $_SESSION['last_activity'])
        && (time() - (int) $_SESSION['last_activity']) > ($rSessionTimeout * 60);

if ($hasExpiredSession) {
        foreach (array('hash', 'ip', 'code', 'verify', 'last_activity') as $sessionKey) {
                unset($_SESSION[$sessionKey]);
        }

        session_regenerate_id(true);
}

if (empty($_SESSION['hash'])) {
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

        header('Location: ./login' . $referrerSuffix);

        exit();
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
        echo json_encode(array('result' => true));

        exit();
}

$_SESSION['last_activity'] = time();
session_write_close();
