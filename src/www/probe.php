<?php

register_shutdown_function('shutdown');
include './stream/init.php';

$requestData = CoreUtilities::$rRequest['data'] ?? null;
if (!is_string($requestData) || $requestData === '') {
        generate404();
}

$decodedPath = base64_decode($requestData, true);
if (!is_string($decodedPath) || $decodedPath === '') {
        generate404();
}

$decodedPath = trim($decodedPath);
[$streamId, $userInfo] = resolveProbeRequest($decodedPath);

if (!($streamId && is_array($userInfo))) {
        generate404();
}

$expiration = $userInfo['exp_date'] ?? null;
if (!is_null($expiration) && $expiration <= time()) {
        generate404();
}

if (isset($userInfo['admin_enabled']) && intval($userInfo['admin_enabled']) === 0) {
        generate404();
}

if (isset($userInfo['enabled']) && intval($userInfo['enabled']) === 0) {
        generate404();
}

if (empty($userInfo['is_restreamer'])) {
        generate404();
}

$channelInfo = CoreUtilities::redirectStream($streamId, 'ts', $userInfo, null, '', 'live');
if (!is_array($channelInfo)) {
        generate404();
}

$serverId = (!empty($channelInfo['redirect_id']) && $channelInfo['redirect_id'] != SERVER_ID)
        ? $channelInfo['redirect_id']
        : SERVER_ID;

if (!isset(CoreUtilities::$rServers[$serverId])
        || CoreUtilities::isHostOffline(CoreUtilities::$rServers[$serverId])
        || empty($channelInfo['monitor_pid'])
        || empty($channelInfo['pid'])
        || $channelInfo['monitor_pid'] <= 0
        || $channelInfo['pid'] <= 0) {
        generate404();
}

$streamInfoPath = STREAMS_PATH . $streamId . '_.stream_info';
$streamInfoJson = null;
if (is_readable($streamInfoPath)) {
        $streamInfoJson = file_get_contents($streamInfoPath);
} elseif (isset($channelInfo['stream_info'])) {
        $streamInfoJson = $channelInfo['stream_info'];
}

$streamInfo = json_decode((string) $streamInfoJson, true);
if (!is_array($streamInfo)
        || !isset($streamInfo['codecs'], $streamInfo['container'], $streamInfo['bitrate'])) {
        generate404();
}

echo json_encode(
        array(
                'codecs' => $streamInfo['codecs'],
                'container' => $streamInfo['container'],
                'bitrate' => $streamInfo['bitrate'],
        )
);
exit();

function resolveProbeRequest($path) {
        $userInfo = null;
        $streamId = null;

        $encryptionKey = CoreUtilities::$rSettings['live_streaming_pass'] ?? null;

        if ($encryptionKey) {
                if (preg_match('#/auth/(.+)$#m', $path, $matches)) {
                        $payload = decodeProbeToken($matches[1], $encryptionKey);

                        if (is_array($payload)
                                && isset($payload['username'], $payload['password'], $payload['stream_id'])) {
                                $userInfo = CoreUtilities::getUserInfo(null, $payload['username'], $payload['password'], true);
                                $streamId = intval($payload['stream_id']);
                        }
                }

                if (!$streamId || !$userInfo) {
                        if (preg_match('#/play/([^/]+)#m', $path, $matches)) {
                                $segments = decodeProbeToken($matches[1], $encryptionKey);

                                if (is_array($segments) && isset($segments[0]) && $segments[0] === 'live' && count($segments) >= 4) {
                                        $userInfo = CoreUtilities::getUserInfo(null, $segments[1], $segments[2], true);
                                        $streamId = intval($segments[3]);
                                }
                        }
                }
        }

        if (!$streamId || !$userInfo) {
                $patterns = array(
                        '#/live/([^/]+)/(\d+)(?:\.[^/]+)?$#m' => function ($matches) {
                                return array(
                                        CoreUtilities::getUserInfo(null, $matches[1], null, true),
                                        intval($matches[2]),
                                );
                        },
                        '#/(?:live/)?([^/]+)/([^/]+)/(\d+)(?:\.[^/]+)?$#m' => function ($matches) {
                                return array(
                                        CoreUtilities::getUserInfo(null, $matches[1], $matches[2], true),
                                        intval($matches[3]),
                                );
                        },
                );

                foreach ($patterns as $pattern => $resolver) {
                        if (preg_match($pattern, $path, $matches)) {
                                [$userInfo, $streamId] = $resolver($matches);

                                if ($streamId && $userInfo) {
                                        break;
                                }
                        }
                }
        }

        return array($streamId, $userInfo);
}

function decodeProbeToken($token, $key) {
        $decrypted = CoreUtilities::decryptData($token, $key, OPENSSL_EXTRA);

        if (!is_string($decrypted) || $decrypted === '') {
                return null;
        }

        $json = json_decode($decrypted, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
        }

        $segments = explode('/', $decrypted);
        return $segments ?: null;
}

function shutdown() {
        if (is_object(CoreUtilities::$db)) {
                CoreUtilities::$db->close_mysql();
        }
}
