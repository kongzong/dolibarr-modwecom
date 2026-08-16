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
 * \file    htdocs/custom/wecom/wecom/oauth.php
 * \ingroup wecom
 * \brief   WeCom OAuth login (spec §12): redirect to WeCom, map userid to a
 *          Dolibarr user via llx_wecom_user_map, start a Dolibarr session.
 *
 * Public page, no core modification: session is established with the same
 * $_SESSION keys main.inc.php sets on a successful classic login.
 * Unmapped users are rejected, never auto-created (spec §12).
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
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
// NOTE: no NOSESSION here - the OAuth flow needs $_SESSION for the CSRF state
// and to store the dol_login keys that establish the Dolibarr session.

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

require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
dol_include_once('/wecom/class/wecomapi.class.php');
dol_include_once('/wecom/class/wecomusermap.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 */

$langs->loadLangs(array("main", "wecom@wecom"));

$corpId = getDolGlobalString('WECOM_CORP_ID');
$agentId = (int) getDolGlobalString('WECOM_AGENT_ID');
if ($corpId === '' || $agentId <= 0) {
	dol_htmloutput_errors("WeCom OAuth is not configured (corp id / agent id missing)", '', 1);
	exit;
}

/**
 * Print a minimal error page and stop.
 *
 * @param	string	$message	Message (already translated)
 * @return	void
 */
function wecom_oauth_error($message)
{
	global $langs;
	top_htmlhead('', $langs->trans("WeComLogin"));
	print '<body class="bodyloginpage">'."\n";
	print '<div style="text-align:center; margin-top:80px; max-width:420px; margin-left:auto; margin-right:auto;">'."\n";
	print '<div class="login">'.$langs->trans("WeComLogin").'</div><br>';
	print '<div class="warning">'.$message.'</div><br>';
	print '<a class="button" href="'.DOL_URL_ROOT.'">'.$langs->trans("BackToLogin").'</a>';
	print '</div>'."\n";
	print '</body></html>';
	exit;
}

// ---------------------------------------------------------------- step 1: no code -> redirect to WeCom
$code = isset($_GET['code']) ? (string) $_GET['code'] : '';
$state = isset($_GET['state']) ? (string) $_GET['state'] : '';

if ($code === '') {
	// Build callback URL (same page)
	$callbackUrl = DOL_MAIN_URL_ROOT.'/custom/wecom/wecom/oauth.php';
	$oauthState = dolGetRandomBytes(16);
	$_SESSION['wecom_oauth_state'] = $oauthState;

	// Remember the target page the user wanted (V0.2), validated to stay on this Dolibarr
	$backToPage = GETPOST('backtopage', 'alpha');
	if ($backToPage !== '' && preg_match('/^https?:\/\/'.preg_quote(parse_url(DOL_MAIN_URL_ROOT, PHP_URL_HOST), '/').'/i', $backToPage)) {
		$_SESSION['wecom_oauth_backtopage'] = $backToPage;
	} elseif ($backToPage !== '' && strncmp($backToPage, '/', 1) === 0 && strncmp($backToPage, '//', 2) !== 0) {
		$_SESSION['wecom_oauth_backtopage'] = DOL_MAIN_URL_ROOT.$backToPage;
	}

	// V0.2: inside the WeCom built-in browser use silent web authorization
	// (no QR code); in a normal browser use the scan-code SSO portal.
	$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	if (strpos($userAgent, 'wxwork') !== false) {
		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize'
			.'?appid='.urlencode($corpId)
			.'&redirect_uri='.urlencode($callbackUrl)
			.'&response_type=code&scope=snsapi_base'
			.'&agentid='.urlencode((string) $agentId)
			.'&state='.urlencode($oauthState)
			.'#wechat_redirect';
	} else {
		$url = 'https://login.work.weixin.qq.com/wwlogin/sso/login?login_type=CorpApp'
			.'&appid='.urlencode($corpId)
			.'&agentid='.urlencode((string) $agentId)
			.'&redirect_uri='.urlencode($callbackUrl)
			.'&state='.urlencode($oauthState);
	}
	header('Location: '.$url);
	exit;
}

// ---------------------------------------------------------------- step 2: callback with code
if ($state === '' || empty($_SESSION['wecom_oauth_state']) || !hash_equals($_SESSION['wecom_oauth_state'], $state)) {
	dol_syslog('WeCom OAuth: bad state (possible CSRF)', LOG_WARNING);
	$langs->load("errors");
	wecom_oauth_error($langs->trans("ErrorBadValue", 'state'));
}
unset($_SESSION['wecom_oauth_state']);

// 2a. code -> wecom userid
try {
	$wecomApi = new WeComApi($db);
	$wecomUserId = $wecomApi->getUserIdByOAuthCode($code);
} catch (WeComApiException $e) {
	dol_syslog('WeCom OAuth: code exchange failed errcode='.$e->getErrorCode(), LOG_WARNING);
	wecom_oauth_error(dol_htmlentities($e->getMessage()));
}

// 2b. userid -> dolibarr user (never auto-create, spec §12)
$map = new WeComUserMap($db);
$found = $map->fetchByWeComUserId($wecomUserId);
if ($found <= 0 || !$map->status) {
	wecom_oauth_error($langs->trans("WeComLoginNotMapped"));
}

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
$duser = new User($db);
if ($duser->fetch((int) $map->fk_user) <= 0 || $duser->statut != 1) {
	wecom_oauth_error($langs->trans("WeComLoginUserDisabled"));
}

// 2c. start the Dolibarr session (same keys as main.inc.php on classic login)
$_SESSION["dol_login"] = $duser->login;
$_SESSION["dol_logindate"] = dol_now('gmt');
$_SESSION["dol_authmode"] = 'wecom';
$_SESSION["dol_tz"] = isset($_POST["tz"]) ? $_POST["tz"] : '';
$_SESSION["dol_tz_string"] = isset($_POST["tz_string"]) ? $_POST["tz_string"] : '';
$_SESSION["dol_dst"] = isset($_POST["dst"]) ? $_POST["dst"] : '';
$_SESSION["dol_dst_observed"] = isset($_POST["dst_observed"]) ? $_POST["dst_observed"] : '';
$_SESSION["dol_dst_first"] = isset($_POST["dst_first"]) ? $_POST["dst_first"] : '';
$_SESSION["dol_dst_second"] = isset($_POST["dst_second"]) ? $_POST["dst_second"] : '';
$_SESSION["dol_screenwidth"] = isset($_POST["screenwidth"]) ? $_POST["screenwidth"] : '';
$_SESSION["dol_screenheight"] = isset($_POST["screenheight"]) ? $_POST["screenheight"] : '';
$_SESSION["dol_company"] = getDolGlobalString("MAIN_INFO_SOCIETE_NOM");
$_SESSION["dol_entity"] = $conf->entity;

$duser->update_last_login_date();
dol_syslog('WeCom OAuth login: '.$duser->login.' (wecom_userid='.$wecomUserId.')', LOG_INFO);

// Return to the remembered target page or the home page
$target = DOL_URL_ROOT.'/';
if (!empty($_SESSION['wecom_oauth_backtopage'])) {
	$target = $_SESSION['wecom_oauth_backtopage'];
	unset($_SESSION['wecom_oauth_backtopage']);
}
header('Location: '.$target);
exit;
