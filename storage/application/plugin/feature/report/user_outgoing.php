<?php

/**
 * This file is part of playSMS.
 *
 * playSMS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * playSMS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with playSMS. If not, see <http://www.gnu.org/licenses/>.
 */
defined('_SECURE_') or die('Forbidden');

if (!auth_isvalid()) {
	auth_block();
}

@set_time_limit(0);

switch (_OP_) {
	case "user_outgoing":
		$search_category = array(
			_('Gateway') => 'p_gateway',
			_('SMSC') => 'p_smsc',
			_('Time') => 'p_datetime',
			_('To') => 'p_dst',
			_('Message') => 'p_msg',
			_('Footer') => 'p_footer',
			_('Queue') => 'queue_code',
		);
		
		$base_url = 'index.php?app=main&inc=feature_report&route=user_outgoing&op=user_outgoing';
		$queue_label = "";
		$queue_home_link = "";
		
		$table = _DB_PREF_ . "_tblSMSOutgoing AS A";
		$fields = "B.username, A.p_gateway, A.p_smsc, A.smslog_id, A.p_dst, A.p_sms_type, A.p_msg, A.p_footer, A.p_datetime, A.p_update, A.p_status, B.uid, A.queue_code";
		$conditions = [
			'B.uid' => $_SESSION['uid'],
			'A.flag_deleted' => 0,
		];
		$extras = [];
		
		if ($queue_code = trim($_REQUEST['queue_code'])) {
			$conditions['A.queue_code'] = $queue_code;
			$queue_label = "<p class=lead>" . sprintf(_('List of queue %s'), $queue_code) . "</p>";
			$queue_home_link = _back($base_url);
			$base_url .= '&queue_code=' . $queue_code;
		} else {
			$fields .= ", COUNT(A.queue_code) AS queue_count";
			$extras['GROUP BY'] = "A.queue_code";
		}
		
		$search = themes_search($search_category, $base_url);
		$keywords = $search['dba_keywords'];
		$extras['ORDER BY'] = "A.smslog_id DESC";
		$join = "INNER JOIN " . _DB_PREF_ . "_tblUser AS B ON A.uid=B.uid AND A.flag_deleted=B.flag_deleted";
		$list = dba_search($table, $fields, $conditions, $keywords, $extras, $join);

		$nav = themes_nav(count($list), $search['url']);
		$extras['LIMIT'] = $nav['limit'];
		$extras['OFFSET'] = $nav['offset'];
		$list = dba_search($table, $fields, $conditions, $keywords, $extras, $join);
		
		$content = _dialog() . "
			<h2 class=page-header-title>" . _('My sent messages') . "</h2>
			" . $queue_label . "
			<p>" . $search['form'] . "</p>
			<form id=fm_user_outgoing name=fm_user_outgoing action=\"index.php?app=main&inc=feature_report&route=user_outgoing&op=actions&queue_code=" . $queue_code . "\" method=POST>
			" . _CSRF_FORM_ . "
			<input type=hidden name=go value=delete>
			<div class=playsms-actions-box>
				<div class=pull-left>
					<a href=\"" . _u('index.php?app=main&inc=feature_report&route=user_outgoing&op=actions&go=export&queue_code=' . $queue_code) . "\">" . $icon_config['export'] . "</a>
				</div>
				<div class=pull-right>" . _submit(_('Are you sure you want to delete ?'), 'fm_user_outgoing', 'delete') . "</div>
			</div>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead>
			<tr>
				<th width=15%>" . _('Date/Time') . "</th>
				<th width=15%>" . _('To') . "</th>
				<th width=67%>" . _('Message') . "</th>
				<th width=3% class=\"sorttable_nosort\" nowrap><input type=checkbox onclick=CheckUncheckAll(document.fm_user_outgoing)></th>
			</tr>
			</thead>
			<tbody>";
		
		if (isset($list) && is_array($list) && count($list) > 0) {
			foreach ( $list as $item ) {
				$item = core_display_data($item);
				$p_username = $item['username'];
				$p_gateway = $item['p_gateway'];
				$p_smsc = $item['p_smsc'];
				$smslog_id = $item['smslog_id'];
				$p_uid = $item['uid'];
				$p_dst = $item['p_dst'];
				$current_p_dst = report_resolve_sender($p_uid, $p_dst);
				$p_sms_type = $item['p_sms_type'];
				if (($p_footer = $item['p_footer']) && (($p_sms_type == "text") || ($p_sms_type == "flash"))) {
					$p_msg = $p_msg . ' ' . $p_footer;
				}
				$p_datetime = core_display_datetime($item['p_datetime']);
				$p_update = $item['p_update'];
				$p_status = $item['p_status'];
				$c_queue_code = $item['queue_code'];
				$c_queue_count = (int) $item['queue_count'];

				$queue_view_link = "";
				if ($c_queue_count > 1) {
					$queue_view_link = "<a href='" . $base_url . "&queue_code=" . $c_queue_code . "'>" . sprintf(_('view all %d'), $c_queue_count) . "</a>";
				}

				// 0 = pending
				// 1 = sent
				// 2 = failed
				// 3 = delivered
				if ($p_status == "1") {
					$p_status = "<span class=status_sent title='" . _('Sent') . "'></span>";
				} else if ($p_status == "2") {
					$p_status = "<span class=status_failed title='" . _('Failed') . "'></span>";
				} else if ($p_status == "3") {
					$p_status = "<span class=status_delivered title='" . _('Delivered') . "'></span>";
				} else {
					$p_status = "<span class=status_pending title='" . _('Pending') . "'></span>";
				}
				$p_status = "<span class='msg_status'>" . $p_status . "</span>";

				// get billing info
				$billing = billing_getdata($smslog_id);
				$p_count = ($billing['count'] ? $billing['count'] : '0');
				$p_count = "<span class='msg_price'>" . $p_count . " sms</span>";

				$p_rate = core_display_credit($billing['rate'] ? $billing['rate'] : '0.0');
				$p_rate = "<span class='msg_rate'><span class='playsms-icon fas fa-table' title='" . _('Rate') . "'></span>" . $p_rate . "</span>";

				$p_charge = core_display_credit($billing['charge'] ? $billing['charge'] : '0.0');
				$p_charge = "<span class='msg_charge'><span class='playsms-icon fas fa-file-invoice-dollar' title='" . _('Charge') . "'></span>" . $p_charge . "</span>";

				// if send SMS failed then display charge as 0
				if ($item['p_status'] == 2) {
					$p_charge = '0.00';
				}

				$p_msg = $item['p_msg'];
				if ($p_msg && $p_dst) {
					$resend = _sendsms($p_dst, $p_msg, $icon_config['resend']);
					$forward = _sendsms('', $p_msg, $icon_config['forward']);
				}
				$c_message = "
					<div class=\"row\">
						<div class=\"col-sm\">
							<div id=\"user_outgoing_msg\">
								<div class='msg_text'>" . $p_msg . "</div>
							</div>
						</div>
						<div class=\"col-sm\">
							<div class=\"row pull-right\">
								<div class=\"col d-none d-md-block\">
									<div class=\"msg_option\">" . $resend . " " . $forward . "</div>
									<div class=\"msg_info\">" . $p_status . " " . $p_count . " " . $p_rate . " " . $p_charge . "</div>
								</div>
							</div>
						</div>
					</div>";
				$content .= "
					<tr>
						<td>$p_datetime</td>
						<td><div>" . $current_p_dst . "</div><div>" . $queue_view_link . "</div></td>
						<td>$c_message</td>
						<td nowrap>
							<input type=checkbox name=itemid[] value=\"$smslog_id\">
						</td>
					</tr>";
			}
		}
		
		$content .= "
			</tbody>
			</table>
			</div>
			<div class=pull-right>" . $nav['form'] . "</div>
			</form>" . $queue_home_link;
		
		_p($content);
		break;
	
	case "actions":
		$nav = themes_nav_session();
		$search = themes_search_session();
		$go = $_REQUEST['go'];
		switch ($go) {
			case 'export':
				$table = _DB_PREF_ . "_tblSMSOutgoing AS A";
				$fields = "B.username, A.p_gateway, A.p_smsc, A.p_datetime, A.p_dst, A.p_msg, A.p_footer, A.p_status, A.queue_code";
				$conditions = array(
					'B.uid' => $_SESSION['uid'],
					'A.flag_deleted' => 0,
				);
				if ($queue_code = trim($_REQUEST['queue_code'])) {
					$conditions['A.queue_code'] = $queue_code;
				}
				$keywords = $search['dba_keywords'];
				
				// fixme anton - will solve this later, for now maxed to 50k
				$extras = array(
					'ORDER BY' => "A.smslog_id DESC",
					'LIMIT' => 50000,
				);

				$join = "INNER JOIN " . _DB_PREF_ . "_tblUser AS B ON A.uid=B.uid AND A.flag_deleted=B.flag_deleted";
				$list = dba_search($table, $fields, $conditions, $keywords, $extras, $join);

				if (!(count($list) > 0)) {
					$ref = $nav['url'] . '&search_keyword=' . $search['keyword'] . '&page=' . $nav['page'] . '&nav=' . $nav['nav'];
					$_SESSION['dialog']['info'][] = _('Nothing to export');
					header("Location: " . _u($ref));
					exit();
				}

				$data[0] = array(
					_('Gateway'),
					_('SMSC'),
					_('Time'),
					_('To'),
					_('Message'),
					_('Status'),
					_('Queue'),
				);
				for ($i = 0; $i < count($list); $i++) {
					$j = $i + 1;
					$data[$j] = array(
						$list[$i]['p_gateway'],
						$list[$i]['p_smsc'],
						core_display_datetime($list[$i]['p_datetime']),
						$list[$i]['p_dst'],
						$list[$i]['p_msg'] . $list[$i]['p_footer'],
						$list[$i]['p_status'],
						$list[$i]['queue_code'],
					);
				}
				$content = core_csv_format($data);
				if ($queue_code) {
					$fn = 'user_outgoing-' . $core_config['datetime']['now_stamp'] . '-' . $queue_code . '.csv';
				} else {
					$fn = 'user_outgoing-' . $core_config['datetime']['now_stamp'] . '.csv';
				}
				core_download($content, $fn, 'text/csv');
				break;
			
			case 'delete':
				if (isset($_POST['itemid'])) {
					foreach ($_POST['itemid'] as $itemid) {
						$up = array(
							'c_timestamp' => time(),
							'flag_deleted' => '1' 
						);
						$conditions = array(
							'uid' => $_SESSION['uid'],
							'smslog_id' => $itemid,
						);
						if ($queue_code = trim($_REQUEST['queue_code'])) {
							$conditions['queue_code'] = $queue_code;
						}
						dba_update(_DB_PREF_ . '_tblSMSOutgoing', $up, $conditions);
					}
				}
				$ref = $nav['url'] . '&search_keyword=' . $search['keyword'] . '&page=' . $nav['page'] . '&nav=' . $nav['nav'];
				$_SESSION['dialog']['info'][] = _('Selected outgoing message has been deleted');
				header("Location: " . _u($ref));
				exit();
		}
		break;
}
