<?php

register_shutdown_function('shutdown');
set_time_limit(0);
require '../init.php';
$rIP = CoreUtilities::getUserIP();
$rAllowedIPs = CoreUtilities::getAllowedIPs();
$rConfiguredIPs = (CoreUtilities::$rSettings['api_ips'] ?? array());

if (!is_array($rConfiguredIPs)) {
        $rConfiguredIPs = array_filter(array_map('trim', explode(',', (string) $rConfiguredIPs)));
}

if ($rConfiguredIPs) {
        $rAllowedIPs = array_unique(array_merge($rAllowedIPs, $rConfiguredIPs));
}

if (!in_array($rIP, $rAllowedIPs, true)) {
        generate404();
}

$rRequestPass = (CoreUtilities::$rRequest['api_pass'] ?? null);
$rApiPass = (CoreUtilities::$rSettings['api_pass'] ?? '');

if ($rApiPass !== '') {
        $rMatches = false;

        if (is_string($rRequestPass)) {
                if (function_exists('hash_equals')) {
                        $rMatches = hash_equals((string) $rApiPass, $rRequestPass);
                } else {
                        $rMatches = ((string) $rApiPass === $rRequestPass);
                }
        }

        if (!$rMatches) {
                generate404();
        }
}

$db = new Database($_INFO['username'], $_INFO['password'], $_INFO['database'], $_INFO['hostname'], $_INFO['port']);
CoreUtilities::$db = &$db;
$rAction = (!empty(CoreUtilities::$rRequest['action']) ? CoreUtilities::$rRequest['action'] : '');
$rSubAction = (!empty(CoreUtilities::$rRequest['sub']) ? CoreUtilities::$rRequest['sub'] : '');

function normalizeIDList($rValue) {
        if (is_string($rValue)) {
                $rValue = array_filter(array_map('trim', explode(',', $rValue)), 'strlen');
        }

        if (!is_array($rValue)) {
                return array();
        }

        $rIDs = array();

        foreach ($rValue as $rID) {
                $rID = intval($rID);

                if (0 < $rID) {
                        $rIDs[] = $rID;
                }
        }

        return array_values(array_unique($rIDs));
}

function normalizeServerList($rValue) {
        $rAllServers = array_keys((array) CoreUtilities::$rServers);

        if (!$rAllServers) {
                return array();
        }

        $rIDs = normalizeIDList($rValue);

        if (!$rIDs) {
                return $rAllServers;
        }

        $rAvailable = array();

        foreach ($rIDs as $rServerID) {
                if (isset(CoreUtilities::$rServers[$rServerID])) {
                        $rAvailable[] = $rServerID;
                }
        }

        return ($rAvailable ?: $rAllServers);
}

switch ($rAction) {
	case 'server':
		switch ($rSubAction) {
			case 'list':
				$rOutput = array();

                                foreach ((array) CoreUtilities::$rServers as $rServerID => $rServerInfo) {
                                        if (!isset($rServerInfo['server_name'], $rServerInfo['server_online'])) {
                                                continue;
                                        }

                                        $rHardware = (isset($rServerInfo['server_hardware']) ? json_decode($rServerInfo['server_hardware'], true) : null);

                                        $rOutput[] = array('id' => $rServerID, 'server_name' => $rServerInfo['server_name'], 'online' => $rServerInfo['server_online'], 'info' => $rHardware);
                                }
				echo json_encode($rOutput);

				break;
		}

		break;

	case 'vod':
		switch ($rSubAction) {
			case 'start':
                                $rStreamIDs = normalizeIDList(CoreUtilities::$rRequest['stream_ids'] ?? array());
                                $rForce = (!empty(CoreUtilities::$rRequest['force']));
                                $rServers = normalizeServerList(CoreUtilities::$rRequest['servers'] ?? array());

                                if (!$rStreamIDs || !$rServers) {
                                        echo json_encode(array('result' => false, 'error' => 'INVALID_PARAMETERS'));

                                        exit();
                                }

                                $rURLs = array();

                                foreach ($rServers as $rServerID) {
                                        if (empty(CoreUtilities::$rServers[$rServerID]['api_url_ip'])) {
                                                continue;
                                        }

                                        $rURLs[$rServerID] = array('url' => CoreUtilities::$rServers[$rServerID]['api_url_ip'] . '&action=vod', 'postdata' => array('function' => $rSubAction, 'stream_ids' => $rStreamIDs, 'force' => $rForce));
                                }
                                if (!$rURLs) {
                                        echo json_encode(array('result' => false, 'error' => 'NO_TARGET_SERVERS'));

                                        exit();
                                }
                                CoreUtilities::getMultiCURL($rURLs);
                                echo json_encode(array('result' => true));

                                exit();

			case 'stop':
                                $rStreamIDs = normalizeIDList(CoreUtilities::$rRequest['stream_ids'] ?? array());
                                $rServers = normalizeServerList(CoreUtilities::$rRequest['servers'] ?? array());

                                if (!$rStreamIDs || !$rServers) {
                                        echo json_encode(array('result' => false, 'error' => 'INVALID_PARAMETERS'));

                                        exit();
                                }

                                $rURLs = array();

                                foreach ($rServers as $rServerID) {
                                        if (empty(CoreUtilities::$rServers[$rServerID]['api_url_ip'])) {
                                                continue;
                                        }

                                        $rURLs[$rServerID] = array('url' => CoreUtilities::$rServers[$rServerID]['api_url_ip'] . '&action=vod', 'postdata' => array('function' => $rSubAction, 'stream_ids' => $rStreamIDs));
                                }
                                if (!$rURLs) {
                                        echo json_encode(array('result' => false, 'error' => 'NO_TARGET_SERVERS'));

                                        exit();
                                }
                                CoreUtilities::getMultiCURL($rURLs);
                                echo json_encode(array('result' => true));

                                exit();
		}

		break;

	case 'stream':
		switch ($rSubAction) {
			case 'start':
                                $rStreamIDs = normalizeIDList(CoreUtilities::$rRequest['stream_ids'] ?? array());
                                $rServers = normalizeServerList(CoreUtilities::$rRequest['servers'] ?? array());

                                if (!$rStreamIDs || !$rServers) {
                                        echo json_encode(array('result' => false, 'error' => 'INVALID_PARAMETERS'));

                                        exit();
                                }

                                $rURLs = array();

                                foreach ($rServers as $rServerID) {
                                        if (empty(CoreUtilities::$rServers[$rServerID]['api_url_ip'])) {
                                                continue;
                                        }

                                        $rURLs[$rServerID] = array('url' => CoreUtilities::$rServers[$rServerID]['api_url_ip'] . '&action=stream', 'postdata' => array('function' => $rSubAction, 'stream_ids' => $rStreamIDs));
                                }
                                if (!$rURLs) {
                                        echo json_encode(array('result' => false, 'error' => 'NO_TARGET_SERVERS'));

                                        exit();
                                }
                                CoreUtilities::getMultiCURL($rURLs);
                                echo json_encode(array('result' => true));

                                exit();

			case 'stop':
                                $rStreamIDs = normalizeIDList(CoreUtilities::$rRequest['stream_ids'] ?? array());
                                $rServers = normalizeServerList(CoreUtilities::$rRequest['servers'] ?? array());

                                if (!$rStreamIDs || !$rServers) {
                                        echo json_encode(array('result' => false, 'error' => 'INVALID_PARAMETERS'));

                                        exit();
                                }

                                $rURLs = array();

                                foreach ($rServers as $rServerID) {
                                        if (empty(CoreUtilities::$rServers[$rServerID]['api_url_ip'])) {
                                                continue;
                                        }

                                        $rURLs[$rServerID] = array('url' => CoreUtilities::$rServers[$rServerID]['api_url_ip'] . '&action=stream', 'postdata' => array('function' => $rSubAction, 'stream_ids' => $rStreamIDs));
                                }
                                if (!$rURLs) {
                                        echo json_encode(array('result' => false, 'error' => 'NO_TARGET_SERVERS'));

                                        exit();
                                }
                                CoreUtilities::getMultiCURL($rURLs);
                                echo json_encode(array('result' => true));

                                exit();

			case 'list':
				$rOutput = array();
				$db->query('SELECT id,stream_display_name FROM `streams` WHERE type <> 2');

				foreach ($db->get_rows() as $rRow) {
					$rOutput[] = array('id' => $rRow['id'], 'stream_name' => $rRow['stream_display_name']);
				}
				echo json_encode($rOutput);

				break;

			case 'offline':
				$db->query('SELECT t1.stream_status,t1.server_id,t1.stream_id  FROM `streams_servers` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.type <> 2 WHERE t1.stream_status <> 0');
				$rStreams = $db->get_rows(true, 'stream_id', false, 'server_id');
				$rOutput = array();

				foreach ($rStreams as $rStreamID => $rServers) {
					$rOutput[$rStreamID] = array_keys($rServers);
				}
				echo json_encode($rOutput);

				break;

			case 'online':
				$db->query('SELECT t1.stream_status,t1.server_id,t1.stream_id FROM `streams_servers` t1 INNER JOIN `streams` t2 ON t2.id = t1.stream_id AND t2.type <> 2 WHERE t1.pid > 0 AND t1.stream_status = 0');
				$rStreams = $db->get_rows(true, 'stream_id', false, 'server_id');
				$rOutput = array();

				foreach ($rStreams as $rStreamID => $rServers) {
					$rOutput[$rStreamID] = array_keys($rServers);
				}
				echo json_encode($rOutput);

				break;
		}

		break;

	case 'line':
		switch ($rSubAction) {
			case 'info':
				if (!empty(CoreUtilities::$rRequest['username']) && !empty(CoreUtilities::$rRequest['password'])) {
					$rUsername = CoreUtilities::$rRequest['username'];
					$rPassword = CoreUtilities::$rRequest['password'];
					$rUserInfo = CoreUtilities::getUserInfo(false, $rUsername, $rPassword, true, true);

					if (!empty($rUserInfo)) {
						echo json_encode(array('result' => true, 'user_info' => $rUserInfo));
					} else {
						echo json_encode(array('result' => false, 'error' => 'NOT EXISTS'));
					}
				} else {
					echo json_encode(array('result' => false, 'error' => 'PARAMETER ERROR (user/pass)'));
				}

				break;
		}

		break;

	case 'reg_user':
		switch ($rSubAction) {
			case 'list':
				$db->query('SELECT id,username,credits,group_id,group_name,last_login,date_registered,email,ip,status FROM `users` t1 INNER JOIN `users_groups` t2 ON t1.member_group_id = t2.group_id');
				$rResults = $db->get_rows();
				echo json_encode($rResults);

				break;
		}

		break;

	default:
		break;
}
function shutdown() {
        global $db;

        if ($db instanceof Database) {
                $db->close_mysql();
        }
}
