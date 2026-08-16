<?php
/* Copyright (C) 2026  modWeCom contributors
 *
 * CLI script that simulates the WeCom callback protocol against the local
 * webhook endpoint: GET URL verification and POST encrypted events, with
 * signature check, replay (idempotency) test and tamper detection.
 *
 * Usage: php simulate_webhook.php
 * Reads Token / EncodingAESKey / Corp ID from llx_const (decrypted with Dolibarr).
 */

if (PHP_SAPI !== 'cli') {
	die('CLI only');
}

define('DOL_DOCUMENT_ROOT', 'D:/dolibarr/www/dolibarr/htdocs');

// Minimal Dolibarr bootstrap to read decrypted constants
global $conf, $db;
@include DOL_DOCUMENT_ROOT.'/filefunc.inc.php';

// Fallback: read conf.php manually
if (empty($db) || !is_object($db)) {
	$confFile = 'D:/dolibarr/www/dolibarr/htdocs/conf/conf.php';
	$cont = file_get_contents($confFile);
	preg_match('/\$dolibarr_main_db_host=\'([^\']+)\'/', $cont, $m1);
	preg_match('/\$dolibarr_main_db_name=\'([^\']+)\'/', $cont, $m2);
	preg_match('/\$dolibarr_main_db_user=\'([^\']+)\'/', $cont, $m3);
	preg_match('/\$dolibarr_main_db_pass=\'([^\']*)\'/', $cont, $m4);
	$db = mysqli_connect($m1[1], $m3[1], $m4[1], $m2[1]);
}

require 'D:/dolibarr/www/dolibarr/htdocs/custom/wecom/class/wecomcrypt.class.php';

$WEBHOOK_URL = 'http://localhost/dolibarr/custom/wecom/wecom/webhook.php';

// Read config constants, transparently decrypting dolcrypt: values
// (same algorithm as dolDecrypt(): aes-256-cbc? no - MAIN_SECURITY_REVERSIBLE_ALGO, key = instance unique id)
function dolDecryptMinimal($chain, $key)
{
	if (strncmp($chain, 'dolcrypt:', 9) !== 0) {
		return $chain;
	}
	$parts = explode(':', $chain, 4);
	if (count($parts) < 4) {
		return '';
	}
	$ciphering = $parts[1];
	$ivseed = $parts[2];
	$crypted = $parts[3];
	$plain = openssl_decrypt($crypted, $ciphering, $key, 0, $ivseed);
	return ($plain === false) ? '' : $plain;
}

function getConst($db, $name)
{
	$res = mysqli_query($db, "SELECT value FROM llx_const WHERE name='".$name."'");
	if (!$res || mysqli_num_rows($res) == 0) {
		return '';
	}
	$row = mysqli_fetch_row($res);
	$value = (string) $row[0];
	if (strncmp($value, 'dolcrypt:', 9) === 0) {
		$confFile = 'D:/dolibarr/www/dolibarr/htdocs/conf/conf.php';
		if (preg_match("/\\\$dolibarr_main_instance_unique_id='([^']+)'/", file_get_contents($confFile), $m)) {
			$value = dolDecryptMinimal($value, $m[1]);
		}
	}
	return $value;
}

$token = getenv('WECOM_TOKEN') ?: getConst($db, 'WECOM_TOKEN');
$aesKey = getenv('WECOM_AESKEY') ?: getConst($db, 'WECOM_ENCODING_AES_KEY');
$corpId = getenv('WECOM_CORPID') ?: getConst($db, 'WECOM_CORP_ID');

if (strncmp($token, 'dolcrypt', 8) === 0 || strncmp($aesKey, 'dolcrypt', 8) === 0) {
	fwrite(STDERR, "Token/EncodingAESKey are encrypted in llx_const. Restart with:\n");
	fwrite(STDERR, "  WECOM_TOKEN=xxx WECOM_AESKEY=xxx(43 chars) WECOM_CORPID=xxx php simulate_webhook.php\n");
	exit(1);
}
if ($token === '' || strlen($aesKey) != 43 || $corpId === '') {
	fwrite(STDERR, "Missing or invalid WECOM_TOKEN / WECOM_ENCODING_AES_KEY / WECOM_CORP_ID\n");
	exit(1);
}

$http = function ($method, $url, $body = null) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	if ($method === 'POST') {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
	}
	$resp = curl_exec($ch);
	$hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return array('status' => $status, 'body' => substr($resp, $hsize));
};

$pass = 0; $fail = 0;
$check = function ($label, $ok, $info = '') use (&$pass, &$fail) {
	echo ($ok ? "PASS" : "FAIL")."  ".$label.($info !== '' ? "  [$info]" : '')."\n";
	$ok ? $pass++ : $fail++;
};

echo "== 1. GET URL verification ==\n";
$ts = (string) time();
$nonce = 'nonce123';
$echostrPlain = '1234567890123456';
$echostrEnc = WeComCrypt::encrypt($echostrPlain, $aesKey, $corpId);
$sig = WeComCrypt::signature($token, $ts, $nonce, $echostrEnc);
$url = $WEBHOOK_URL.'?msg_signature='.urlencode($sig).'&timestamp='.$ts.'&nonce='.urlencode($nonce).'&echostr='.urlencode($echostrEnc);
$r = $http('GET', $url);
$check('GET valid signature returns decrypted echostr', $r['status'] == 200 && $r['body'] === $echostrPlain, 'http='.$r['status'].' body='.substr($r['body'], 0, 20));

$r2 = $http('GET', $WEBHOOK_URL.'?msg_signature='.urlencode('deadbeef').'&timestamp='.$ts.'&nonce='.$nonce.'&echostr='.urlencode($echostrEnc));
$check('GET bad signature rejected (403)', $r2['status'] == 403, 'http='.$r2['status']);

echo "\n== 2. POST event receive ==\n";
$eventXml = '<xml><ToUserName><![CDATA['.$corpId.']]></ToUserName>'
	.'<FromUserName><![CDATA[sys]]></FromUserName>'
	.'<CreateTime>'.time().'</CreateTime>'
	.'<MsgType><![CDATA[event]]></MsgType>'
	.'<Event><![CDATA[change_external_contact]]></Event>'
	.'<ChangeType><![CDATA[del_external_contact]]></ChangeType>'
	.'<UserID><![CDATA[ray]]></UserID>'
	.'<ExternalUserID><![CDATA[wmTESTSIMULATED0001]]></ExternalUserID>'
	.'</xml>';
$enc = WeComCrypt::encrypt($eventXml, $aesKey, $corpId);
$envelope = '<xml><Encrypt><![CDATA['.$enc.']]></Encrypt></xml>';
$ts2 = (string) time();
$nonce2 = 'nonce456';
$sig2 = WeComCrypt::signature($token, $ts2, $nonce2, $enc);
$postUrl = $WEBHOOK_URL.'?msg_signature='.urlencode($sig2).'&timestamp='.$ts2.'&nonce='.urlencode($nonce2);
$r3 = $http('POST', $postUrl, $envelope);
$check('POST valid event accepted (success)', $r3['status'] == 200 && trim($r3['body']) === 'success', 'http='.$r3['status'].' body='.trim($r3['body']));

echo "\n== 3. Replay (idempotency) ==\n";
$r4 = $http('POST', $postUrl, $envelope);
$check('POST replay returns success', $r4['status'] == 200 && trim($r4['body']) === 'success', 'http='.$r4['status']);

$row = mysqli_query($db, "SELECT process_status, process_message FROM llx_wecom_event_log ORDER BY rowid DESC LIMIT 1");
$last = mysqli_fetch_row($row);
$check('event logged in llx_wecom_event_log', $last !== null, $last ? 'status='.$last[0].' msg='.$last[1] : 'no row');

$r5 = $http('POST', $WEBHOOK_URL.'?msg_signature='.urlencode('badsig').'&timestamp='.$ts2.'&nonce='.$nonce2, $envelope);
$check('POST bad signature rejected (403)', $r5['status'] == 403, 'http='.$r5['status']);

echo "\nResult: $pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);
