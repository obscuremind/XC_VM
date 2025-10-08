<?php

set_time_limit(0);
require '../init.php';
$rSignals = array();

if (CoreUtilities::isProxy($_SERVER['REMOTE_ADDR'])) {
        $db = new Database($_INFO['username'], $_INFO['password'], $_INFO['database'], $_INFO['hostname'], $_INFO['port']);
        CoreUtilities::$db = &$db;
        $rServerID = intval($_POST['server_id']);
        $rStats = $_POST['stats'];
        $rServers = CoreUtilities::$rServers;

        if (!isset($rServers[$rServerID]) || CoreUtilities::isHostOffline($rServers[$rServerID])) {
                exit();
        }

        $rServerInfo = $rServers[$rServerID];
	$db->query('SELECT `bytes_sent_total`, `bytes_received_total`, `time` FROM `servers_stats` WHERE `server_id` = ? ORDER BY `id` DESC LIMIT 1;', $rServerID);

        if ($db->num_rows() != 1) {
        } else {
                $rRow = $db->get_row();
                $rTimeSince = time() - intval($rRow['time']);

                if (0 < $rTimeSince) {
                        $rDeltaSent = max(0, $rStats['bytes_sent_total'] - $rRow['bytes_sent_total']);
                        $rDeltaReceived = max(0, $rStats['bytes_received_total'] - $rRow['bytes_received_total']);
                        $rStats['bytes_sent'] = $rDeltaSent / $rTimeSince;
                        $rStats['bytes_received'] = $rDeltaReceived / $rTimeSince;
                } else {
                        $rStats['bytes_sent'] = 0;
                        $rStats['bytes_received'] = 0;
                }
        }

	$rAddresses = $_POST['addresses'];
        $rHardware = array('total_ram' => $rStats['total_mem'], 'total_used' => $rStats['total_mem_used'], 'cores' => $rStats['cpu_cores'], 'threads' => $rStats['cpu_cores'], 'kernel' => $rStats['kernel'], 'total_running_streams' => $rStats['total_running_streams'], 'cpu_name' => $rStats['cpu_name'], 'cpu_usage' => $rStats['cpu'], 'network_speed' => $rStats['network_speed'], 'bytes_sent' => $rStats['bytes_sent'], 'bytes_received' => $rStats['bytes_received']);
        $rPing = (pingserver($rServerInfo['server_ip'], $rServerInfo['http_broadcast_port']) ?: 0);

	if ($rPing >= 0) {
	} else {
		$rPing = 0;
	}

	if (CoreUtilities::$rSettings['redis_handler']) {
                $rConnections = $rServerInfo['connections'];
                $rUsers = $rServerInfo['users'];
                $rAllUsers = 0;

                foreach ($rServers as $rOtherServerInfo) {
                        if (CoreUtilities::isHostOffline($rOtherServerInfo)) {
                                continue;
                        }

                        $rAllUsers += $rOtherServerInfo['users'];
                }
        } else {
                $db->query('SELECT COUNT(*) AS `count` FROM `lines_live` WHERE `proxy_id` = ? AND `hls_end` = 0;', $rServerID);
		$rConnections = intval($db->get_row()['count']);
		$db->query('SELECT `activity_id` FROM `lines_live` WHERE `proxy_id` = ? AND `hls_end` = 0 GROUP BY `user_id`;', $rServerID);
		$rUsers = intval($db->num_rows());
		$db->query('SELECT `activity_id` FROM `lines_live` WHERE `hls_end` = 0 GROUP BY `user_id`;');
		$rAllUsers = intval($db->num_rows());
	}

	$db->query('INSERT INTO `servers_stats`(`server_id`, `cpu`, `cpu_cores`, `cpu_avg`, `total_mem`, `total_mem_free`, `total_mem_used`, `total_mem_used_percent`, `total_disk_space`, `uptime`, `total_running_streams`, `bytes_sent`, `bytes_received`, `bytes_sent_total`, `bytes_received_total`, `cpu_load_average`, `connections`, `total_users`, `users`, `time`) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);', $rServerID, $rStats['cpu'], $rStats['cpu_cores'], $rStats['cpu_avg'], $rStats['total_mem'], $rStats['total_mem_free'], $rStats['total_mem_used'], $rStats['total_mem_used_percent'], $rStats['total_disk_space'], $rStats['uptime'], $rStats['total_running_streams'], $rStats['bytes_sent'], $rStats['bytes_received'], $rStats['bytes_sent_total'], $rStats['bytes_received_total'], $rStats['cpu_load_average'], $rConnections, $rAllUsers, $rUsers, time());
	$db->query('UPDATE `servers` SET `connections` = ?, `users` = ?, `ping` = ?,`server_hardware` = ?,`whitelist_ips` = ?, `interfaces` = ?, `watchdog_data` = ?, `last_check_ago` = ? WHERE `id` = ?', $rConnections, $rUsers, $rPing, json_encode($rHardware), json_encode($rAddresses), json_encode($rStats['interfaces']), json_encode($rStats, JSON_PARTIAL_OUTPUT_ON_ERROR), time(), $rServerID);

	if ($db->query("SELECT `signal_id`, `custom_data` FROM `signals` WHERE `server_id` = ? AND `custom_data` <> '' ORDER BY signal_id ASC;", $rServerID)) {


		if (0 >= $db->num_rows()) {
		} else {
			foreach ($db->get_rows() as $rRow) {
				$rData = json_decode($rRow['custom_data'], true);
				$db->query('DELETE FROM `signals` WHERE `signal_id` = ?;', $rRow['signal_id']);
				$rSignals[] = $rData;
			}
		}

		echo json_encode($rSignals);

		exit();
	}

	exit();
} else {
	generate404();
}

function pingServer($rIP, $rPort) {
	$rStartTime = microtime(true);
	$rSocket = fsockopen($rIP, $rPort, $rErrNo, $rErrStr, 3);
	$rStopTime = microtime(true);

	if (!$rSocket) {
		$rStatus = -1;
	} else {
		fclose($rSocket);
		$rStatus = floor(($rStopTime - $rStartTime) * 1000);
	}

	return $rStatus;
}
