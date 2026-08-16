<?php
/* Copyright (C) 2026  modWeCom contributors
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/custom/wecom/wecom/webhook.php
 * \ingroup wecom
 * \brief   WeCom callback endpoint: GET = URL verification, POST = event receive.
 *
 * Public endpoint: no Dolibarr login. Security relies on the WeCom message
 * signature (sha1 over token/timestamp/nonce/ciphertext) and AES decryption
 * with the EncodingAESKey (spec §18). Answers fast, never runs a long sync.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	// POST callbacks come from WeCom servers, no Dolibarr session/token exists.
	// Security is enforced by the message signature + AES decryption below.
	define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
if (!defined('NOBROWSERNOTIFY')) {
	define('NOBROWSERNOTIFY', '1');
}
if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/wecom/class/wecomcrypt.class.php');
dol_include_once('/wecom/class/wecomeventlog.class.php');
dol_include_once('/wecom/class/wecomcontactmap.class.php');
dol_include_once('/wecom/class/wecomapi.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 */

// Raw request inputs (GETPOST filters too aggressively for crypto material;
// WeCom always sends msg_signature/timestamp/nonce in the query string)
$msgSignature = isset($_GET['msg_signature']) ? (string) $_GET['msg_signature'] : '';
$timestamp = isset($_GET['timestamp']) ? (string) $_GET['timestamp'] : '';
$nonce = isset($_GET['nonce']) ? (string) $_GET['nonce'] : '';
$echostr = isset($_GET['echostr']) ? (string) $_GET['echostr'] : '';

$token = getDolGlobalString('WECOM_TOKEN');
$encodingAesKey = getDolGlobalString('WECOM_ENCODING_AES_KEY');
$corpId = getDolGlobalString('WECOM_CORP_ID');

if ($token === '' || $encodingAesKey === '' || $corpId === '') {
	header('HTTP/1.1 503 Service Unavailable');
	dol_syslog('WeCom webhook: module not configured (token/aeskey/corpid)', LOG_WARNING);
	exit;
}

// ---------------------------------------------------------------- GET: URL verification
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
	try {
		if (!WeComCrypt::verify($token, $timestamp, $nonce, $echostr, $msgSignature)) {
			throw new Exception('signature mismatch');
		}
		$plain = WeComCrypt::decrypt($echostr, $encodingAesKey, $corpId);
		echo $plain; // WeCom expects the decrypted echostr
	} catch (Exception $e) {
		header('HTTP/1.1 403 Forbidden');
		dol_syslog('WeCom webhook GET verification failed: '.$e->getMessage(), LOG_WARNING);
	}
	exit;
}

// ---------------------------------------------------------------- POST: event receive
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('HTTP/1.1 405 Method Not Allowed');
	exit;
}

$rawBody = file_get_contents('php://input');

try {
	// 1. Parse the encrypted envelope
	$xml = simplexml_load_string($rawBody, 'SimpleXMLElement', LIBXML_NOCDATA);
	if ($xml === false) {
		throw new Exception('invalid XML envelope');
	}
	$encrypt = (string) $xml->Encrypt;

	// 2. Signature check
	if (!WeComCrypt::verify($token, $timestamp, $nonce, $encrypt, $msgSignature)) {
		throw new Exception('signature mismatch');
	}

	// 3. Decrypt + parse event
	$plainXml = WeComCrypt::decrypt($encrypt, $encodingAesKey, $corpId);
	$event = simplexml_load_string($plainXml, 'SimpleXMLElement', LIBXML_NOCDATA);
	if ($event === false) {
		throw new Exception('invalid decrypted XML');
	}

	$eventType = (string) $event->Event;
	$eventTime = (int) $event->CreateTime;
	$msgId = isset($event->MsgId) ? (string) $event->MsgId : '';

	// 4. Idempotency key (spec §19)
	$businessIds = array(
		'FromUserName' => (string) $event->FromUserName,
		'ToUserName' => (string) $event->ToUserName,
		'ChangeType' => isset($event->ChangeType) ? (string) $event->ChangeType : '',
		'ExternalUserID' => isset($event->ExternalUserID) ? (string) $event->ExternalUserID : '',
		'UserID' => isset($event->UserID) ? (string) $event->UserID : '',
	);
	$eventId = ($msgId !== '') ? $msgId : WeComEventLog::buildEventId($eventType, $eventTime, $businessIds, $plainXml);

	// 5. Insert into event log (unique index on event_id = idempotency)
	$logger = new WeComEventLog($db);
	$rowid = $logger->insertEvent($eventId, $eventType, $eventTime, $plainXml);
	if ($rowid === 0) {
		// Duplicate: already processed once, ignore (spec §21)
		dol_syslog('WeCom webhook: duplicate event '.$eventId.' ignored', LOG_INFO);
		echo 'success';
		exit;
	}
	if ($rowid < 0) {
		throw new Exception('event log insert failed');
	}

	// 6. Dispatch (fast processing only - spec §18: no long sync here)
	$status = WeComEventLog::STATUS_PROCESSED;
	$message = '';
	try {
		$message = wecom_dispatch_event($event);
	} catch (Exception $e) {
		$status = WeComEventLog::STATUS_ERROR;
		$message = $e->getMessage();
	}
	$logger->markProcessed($rowid, $status, $message);

	echo 'success';
} catch (Exception $e) {
	header('HTTP/1.1 403 Forbidden');
	dol_syslog('WeCom webhook POST failed: '.$e->getMessage(), LOG_WARNING);
	exit;
}

/**
 * Dispatch one event. Fast operations only.
 *
 * @param	SimpleXMLElement	$event	Decrypted event XML
 * @return	string						Short processing summary
 */
function wecom_dispatch_event($event)
{
	global $db;

	$eventType = (string) $event->Event;
	$changeType = isset($event->ChangeType) ? (string) $event->ChangeType : '';

	if ($eventType === 'change_external_contact') {
		$externalUserId = (string) $event->ExternalUserID;

		if (in_array($changeType, array('del_external_contact', 'del_follow_user'))) {
			// Contact deleted or owner removed: invalidate mapping, never delete Dolibarr data
			$map = new WeComContactMap($db);
			if ($map->fetchByExternalUserId($externalUserId) > 0) {
				$map->unbind();
				return 'mapping unbound ('.$changeType.')';
			}
			return 'no mapping for '.$externalUserId;
		}

		if (in_array($changeType, array('add_external_contact', 'edit_external_contact', 'add_half_external_contact', 'external_contact_remark'))) {
			// Refresh existing mapping only (one API call). Creating thirdparties
			// in the webhook is intentionally avoided: run a customer sync instead.
			$map = new WeComContactMap($db);
			if ($map->fetchByExternalUserId($externalUserId) <= 0) {
				return 'unknown contact, a full customer sync is needed';
			}
			$api = new WeComApi($db);
			$detail = $api->getExternalContactDetail($externalUserId);
			$external = isset($detail['external_contact']) ? $detail['external_contact'] : array();
			$followUsers = isset($detail['follow_user']) && is_array($detail['follow_user']) ? $detail['follow_user'] : array();
			$extra = array(
				'wecom_name' => isset($external['name']) ? $external['name'] : '',
				'wecom_avatar' => isset($external['avatar']) ? $external['avatar'] : '',
				'wecom_corp_name' => isset($external['corp_name']) ? $external['corp_name'] : '',
			);
			// Refresh owner and tags from follow_user (V0.2)
			foreach ($followUsers as $fu) {
				if (isset($fu['userid']) && $fu['userid'] == $map->owner_wecom_userid) {
					$tags = array();
					if (!empty($fu['tags']) && is_array($fu['tags'])) {
						foreach ($fu['tags'] as $tag) {
							if (!empty($tag['tag_name'])) {
								$tags[] = !empty($tag['group_name']) ? $tag['group_name'].'/'.$tag['tag_name'] : $tag['tag_name'];
							}
						}
					}
					$extra['wecom_tags'] = implode(', ', array_unique($tags));
					break;
				}
			}
			$map->updateWeComFields($extra);
			return 'mapping refreshed ('.$changeType.')';
		}

		return 'change type '.$changeType.' logged only';
	}

	// V0.2: WeCom addressbook events (Event = change_contact, ChangeType = *_user)
	if ($eventType === 'change_contact') {
		$userId = (string) $event->UserID;

		if ($changeType === 'delete_user') {
			// Employee left: invalidate the mapping only, never delete the Dolibarr user
			$sql = "UPDATE ".$db->prefix()."wecom_user_map SET status = 0 WHERE wecom_userid = '".$db->escape($userId)."'";
			$db->query($sql);
			return 'user mapping disabled ('.$userId.')';
		}

		if ($changeType === 'update_user') {
			// Refresh mapping fields with one API call (departments changed, etc.)
			$sql = "SELECT rowid FROM ".$db->prefix()."wecom_user_map WHERE wecom_userid = '".$db->escape($userId)."'";
			$resql = $db->query($sql);
			if (!$resql || !($obj = $db->fetch_object($resql))) {
				return 'unknown user, a full user sync is needed';
			}
			try {
				$api = new WeComApi($db);
				$userInfo = $api->getUserDetail($userId);
				$deptIds = isset($userInfo['department']) && is_array($userInfo['department']) ? implode(',', array_map('intval', $userInfo['department'])) : '';
				$sql = "UPDATE ".$db->prefix()."wecom_user_map SET";
				$sql .= " wecom_department_ids = ".($deptIds !== '' ? "'".$db->escape($deptIds)."'" : 'NULL').",";
				$sql .= " status = 1";
				$sql .= " WHERE rowid = ".((int) $obj->rowid);
				$db->query($sql);
				return 'user mapping refreshed ('.$userId.')';
			} catch (WeComApiException $e) {
				return 'user refresh failed: '.$e->getMessage();
			}
		}

		if ($changeType === 'create_user') {
			return 'user created, a full user sync is needed';
		}

		return 'contact change type '.$changeType.' logged only';
	}

	// Any other event: logged, admin runs sync
	return 'logged only';
}
