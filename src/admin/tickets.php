<?php







include 'session.php';
include 'functions.php';

if (checkPermissions()) {
} else {
	goHome();
}

$rStatusArray = array('CLOSED', 'OPEN', 'RESPONDED TO', 'READ BY USER', 'NEW RESPONSE', 'READ BY ME', 'READ BY USER');
$_TITLE = 'Tickets';
include 'header.php';
echo '<div class="wrapper"';

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
} else {
	echo ' style="display: none;"';
}

echo '>' . "\r\n" . '    <div class="container-fluid">' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n\t\t\t\t" . '<div class="page-title-box">' . "\r\n\t\t\t\t\t" . '<div class="page-title-right">' . "\r\n" . '                        ';
include 'topbar.php';
echo "\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t" . '<h4 class="page-title">Tickets</h4>' . "\r\n\t\t\t\t" . '</div>' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>     ' . "\r\n\t\t" . '<div class="row">' . "\r\n\t\t\t" . '<div class="col-12">' . "\r\n\t\t\t\t" . '<div class="card-box">' . "\r\n\t\t\t\t\t" . '<table class="table table-striped table-borderless dt-responsive nowrap w-100" id="tickets-table">' . "\r\n\t\t\t\t\t\t" . '<thead>' . "\r\n\t\t\t\t\t\t\t" . '<tr>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">ID</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th>Reseller</th>' . "\r\n" . '                                <th>Title</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Status</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Created Date</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Last Reply</th>' . "\r\n\t\t\t\t\t\t\t\t" . '<th class="text-center">Action</th>' . "\r\n\t\t\t\t\t\t\t" . '</tr>' . "\r\n\t\t\t\t\t\t" . '</thead>' . "\r\n\t\t\t\t\t\t" . '<tbody>' . "\r\n\t\t\t\t\t\t\t";
$rTickets = getTickets($rUserInfo['id'], true);

foreach ($rTickets as $rTicket) {
	echo "\t\t\t\t\t\t\t" . '<tr id="ticket-';
	echo intval($rTicket['id']);
	echo '">' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center"><a href="./ticket_view?id=';
	echo intval($rTicket['id']);
	echo '">';
	echo intval($rTicket['id']);
	echo '</a></td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td>';
	echo $rTicket['username'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td>';
	echo $rTicket['title'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center"><span class="badge badge-';
	echo array('secondary', 'warning', 'success', 'warning', 'info', 'purple', 'warning')[$rTicket['status']];
	echo '">';
	echo $rStatusArray[$rTicket['status']];
	echo '</span></td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center">';
	echo $rTicket['created'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center">';
	echo $rTicket['last_reply'];
	echo '</td>' . "\r\n\t\t\t\t\t\t\t\t" . '<td class="text-center">' . "\r\n\t\t\t\t\t\t\t\t\t" . '<div class="btn-group dropdown">' . "\r\n\t\t\t\t\t\t\t\t\t\t" . '<a href="javascript: void(0);" class="table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm" data-toggle="dropdown" aria-expanded="false"><i class="mdi mdi-dots-horizontal"></i></a>' . "\r\n\t\t\t\t\t\t\t\t\t\t" . '<div class="dropdown-menu dropdown-menu-right">' . "\r\n\t\t\t\t\t\t\t\t\t\t\t" . '<a class="dropdown-item" href="./ticket_view?id=';
	echo intval($rTicket['id']);
	echo '"><i class="mdi mdi-eye mr-2 text-muted font-18 vertical-middle"></i>View Ticket</a>' . "\r\n\t\t\t\t\t\t\t\t\t\t\t";

	if (!hasPermissions('adv', 'ticket')) {
	} else {
		if (0 < $rTicket['status']) {
			echo "\t\t\t\t\t\t\t\t\t\t\t" . '<a class="dropdown-item" href="javascript:void(0);" onClick="api(';
			echo intval($rTicket['id']);
			echo ", 'close');\"><i class=\"mdi mdi-check-all mr-2 text-muted font-18 vertical-middle\"></i>Close</a>" . "\r\n\t\t\t\t\t\t\t\t\t\t\t";
		} else {
			echo "\t\t\t\t\t\t\t\t\t\t\t" . '<a class="dropdown-item" href="javascript:void(0);" onClick="api(';
			echo intval($rTicket['id']);
			echo ", 'reopen');\"><i class=\"mdi mdi-check-all mr-2 text-muted font-18 vertical-middle\"></i>Re-Open</a>" . "\r\n\t\t\t\t\t\t\t\t\t\t\t";
		}

		echo "\t\t\t\t\t\t\t\t\t\t\t" . '<a class="dropdown-item" href="javascript:void(0);" onClick="api(';
		echo intval($rTicket['id']);
		echo ", 'delete');\"><i class=\"mdi mdi-delete mr-2 text-muted font-18 vertical-middle\"></i>Delete</a>" . "\r\n\t\t\t\t\t\t\t\t\t\t\t";
	}

	echo "\t\t\t\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t\t\t\t" . '</div>' . "\r\n\t\t\t\t\t\t\t\t" . '</td>' . "\r\n\t\t\t\t\t\t\t" . '</tr>' . "\r\n\t\t\t\t\t\t\t";
}
echo "\t\t\t\t\t\t" . '</tbody>' . "\r\n\t\t\t\t\t" . '</table>' . "\r\n\t\t\t\t" . '</div>' . "\r\n\t\t\t" . '</div>' . "\r\n\t\t" . '</div>' . "\r\n\t" . '</div>' . "\r\n" . '</div>' . "\r\n";
include 'footer.php';
		echo '        ' . "\r\n\t\t" . 'function api(rID, rType, rConfirm=false) {' . "\r\n" . '            if ((rType == "delete") && (!rConfirm)) {' . "\r\n" . '                new jBox("Confirm", {' . "\r\n" . '                    confirmButton: "Delete",' . "\r\n" . '                    cancelButton: "Cancel",' . "\r\n" . '                    content: "Are you sure you want to delete this ticket?",' . "\r\n" . '                    confirm: function () {' . "\r\n" . '                        api(rID, rType, true);' . "\r\n" . '                    }' . "\r\n" . '                }).open();' . "\r\n\t\t\t" . '} else {' . "\r\n" . '                rConfirm = true;' . "\r\n" . '            }' . "\r\n" . '            if (rConfirm) {' . "\r\n" . '                $.getJSON("./api?action=ticket&sub=" + rType + "&ticket_id=" + rID, function(data) {' . "\r\n" . '                    if (data.result == true) {' . "\r\n" . '                        reloadPage();' . "\r\n" . '                    } else {' . "\r\n" . '                        $.toast("An error occured while processing your request.");' . "\r\n" . '                    }' . "\r\n" . '                }).fail(function() {' . "\r\n" . '                    $.toast("An error occured while processing your request.");' . "\r\n" . '                });' . "\r\n" . '            }' . "\r\n\t\t" . '}' . "\r\n\t\t" . '$(document).ready(function() {' . "\r\n\t\t\t" . '$("#tickets-table").DataTable({' . "\r\n\t\t\t\t" . 'language: {' . "\r\n\t\t\t\t\t" . 'paginate: {' . "\r\n\t\t\t\t\t\t" . "previous: \"<i class='mdi mdi-chevron-left'>\"," . "\r\n\t\t\t\t\t\t" . "next: \"<i class='mdi mdi-chevron-right'>\"" . "\r\n\t\t\t\t\t" . '}' . "\r\n\t\t\t\t" . '},' . "\r\n\t\t\t\t" . 'drawCallback: function() {' . "\r\n" . '                    bindHref(); refreshTooltips();' . "\r\n\t\t\t\t" . '},' . "\r\n\t\t\t\t" . 'order: [[ 0, "desc" ]]' . "\r\n\t\t\t" . '});' . "\r\n\t\t\t" . '$("#tickets-table").css("width", "100%");' . "\r\n\t\t" . '});' . "\r\n" . '        ' . "\r\n\t\t";
		?>
</script>
<script src="assets/js/listings.js"></script>
</body>

</html>
