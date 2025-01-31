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

switch (_OP_) {
	case "user_inbox":
		$search_category = array(
			_('Time') => 'in_datetime',
			_('From') => 'in_sender',
			_('Message') => 'in_msg' 
		);
		
		$base_url = 'index.php?app=main&inc=feature_report&route=user_inbox&op=user_inbox';
		
		if ($in_sender = trim($_REQUEST['in_sender'])) {
			$subpage_label = "<p class=lead>" . sprintf(_('List of messages from %s'), $in_sender) . "</p>";
			$home_link = _back($base_url);
			$base_url .= '&in_sender=' . urlencode($in_sender);
			$search = themes_search($search_category, $base_url);
			$conditions = array(
				'in_sender' => $in_sender,
				'in_uid' => $user_config['uid'],
				'flag_deleted' => 0 
			);
			$keywords = $search['dba_keywords'];
			$count = dba_count(_DB_PREF_ . '_tblSMSInbox', $conditions, $keywords);
			$nav = themes_nav($count, $search['url']);
			$extras = array(
				'ORDER BY' => 'in_id DESC',
				'LIMIT' => $nav['limit'],
				'OFFSET' => $nav['offset'] 
			);
			$list = dba_search(_DB_PREF_ . '_tblSMSInbox', 'in_id, in_uid, in_datetime, in_sender, in_msg', $conditions, $keywords, $extras);
		} else {
			$search = themes_search($search_category, $base_url);
			$conditions = array(
				'in_uid' => $user_config['uid'],
				'flag_deleted' => 0 
			);
			$keywords = $search['dba_keywords'];
			$list = dba_search(_DB_PREF_ . '_tblSMSInbox', 'in_id', $conditions, $keywords, array(
				'GROUP BY' => 'in_sender, in_id'
			));
			$count = count($list);
			$nav = themes_nav($count, $search['url']);
			$extras = array(
				'GROUP BY' => 'in_sender, in_id',
				'ORDER BY' => 'in_id DESC',
				'LIMIT' => $nav['limit'],
				'OFFSET' => $nav['offset'] 
			);
			$list = dba_search(_DB_PREF_ . '_tblSMSInbox', 'in_id, in_uid, in_datetime, in_sender, in_msg, COUNT(*) AS message_count', $conditions, $keywords, $extras);
		}
		
		$tpl = array(
			'vars' => array(
				'SEARCH_FORM' => $search['form'],
				'NAV_FORM' => $nav['form'],
				'SUBPAGE_LABEL' => $subpage_label,
				'HOME_LINK' => $home_link,
				'Inbox' => _('Inbox'),
				'Export' => $icon_config['export'],
				'Delete' => _submit(_('Are you sure you want to delete ?'), 'fm_inbox', 'delete'),
				'DateTime' => _('Date/Time'),
				'From' => _('From'),
				'Message' => _('Message'),
				'ARE_YOU_SURE' => _('Are you sure you want to delete these items ?'),
				'in_sender' => urlencode($in_sender) 
			) 
		);
		if (isset($list) && is_array($list) && count($list) > 0) {
			foreach ( $list as $item ) {
				$item = core_display_data($item);
				$in_id = $item['in_id'];
				$in_sender = $item['in_sender'];
				$current_sender = report_resolve_sender($user_config['uid'], $in_sender);
				$in_datetime = core_display_datetime($item['in_datetime']);
				$in_msg = $item['in_msg'];
				$reply = '';
				$forward = '';
				if ($in_msg && $in_sender) {
					$reply = _sendsms($in_sender, $in_msg);
					$forward = _sendsms('', $in_msg, $icon_config['forward']);
				}
				$message_count = $item['message_count'];
				$view_all_link = "";
				if ($message_count > 1) {
					$view_all_link = "<a href='" . $base_url . "&in_sender=" . urlencode($in_sender) . "'>" . sprintf(_('view all %d'), $message_count) . "</a>";
				}
				$tpl['loops']['data'][] = array(
					'tr_class' => $tr_class,
					'current_sender' => $current_sender,
					'view_all_link' => $view_all_link,
					'in_msg' => $in_msg,
					'in_datetime' => $in_datetime,
					'reply' => $reply,
					'forward' => $forward,
					'in_id' => $in_id,
				);
			}
		}
		$tpl['vars']['DIALOG_DISPLAY'] = _dialog();
		$tpl['name'] = 'user_inbox';
		$content = tpl_apply($tpl);
		_p($content);
		break;
	
	case "actions":
		$nav = themes_nav_session();
		$search = themes_search_session();
		$go = $_REQUEST['go'];
		switch ($go) {
			case 'export':
				$conditions = array(
					'in_uid' => $user_config['uid'],
					'flag_deleted' => 0 
				);
				if ($in_sender = trim($_REQUEST['in_sender'])) {
					$conditions['in_sender'] = $in_sender;
				}
				$list = dba_search(_DB_PREF_ . '_tblSMSInbox', 'in_datetime, in_sender, in_msg', $conditions, $search['dba_keywords']);
				$data[0] = array(
					_('Time'),
					_('From'),
					_('Message') 
				);
				for ($i = 0; $i < count($list); $i++) {
					$j = $i + 1;
					$data[$j] = array(
						core_display_datetime($list[$i]['in_datetime']),
						$list[$i]['in_sender'],
						$list[$i]['in_msg'] 
					);
				}
				$content = core_csv_format($data);
				if ($in_sender) {
					$fn = 'user_inbox-' . $user_config['username'] . '-' . $core_config['datetime']['now_stamp'] . '-' . $in_sender . '.csv';
				} else {
					$fn = 'user_inbox-' . $user_config['username'] . '-' . $core_config['datetime']['now_stamp'] . '.csv';
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
							'in_uid' => $user_config['uid'],
							'in_id' => $itemid 
						);
						if ($in_sender = trim($_REQUEST['in_sender'])) {
							$conditions['in_sender'] = $in_sender;
						}
						dba_update(_DB_PREF_ . '_tblSMSInbox', $up, $conditions);
					}
				}
				$ref = $nav['url'] . '&search_keyword=' . $search['keyword'] . '&page=' . $nav['page'] . '&nav=' . $nav['nav'];
				$_SESSION['dialog']['info'][] = _('Selected incoming message has been deleted');
				header("Location: " . _u($ref));
				exit();
		}
		break;
}
