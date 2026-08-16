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
 * \file    htdocs/custom/wecom/admin/setup.php
 * \ingroup wecom
 * \brief   WeCom setup page (phase 1: constants only, test connection comes in phase 2).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/wecom.lib.php';
dol_include_once('/wecom/class/wecomapi.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "wecom@wecom"));

// Initialize technical object to manage hooks of page
$hookmanager->initHooks(array('wecomsetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');

// Access control
if (!$user->hasRight('wecom', 'admin')) {
	accessforbidden();
}

$constArray = array(
	'WECOM_CORP_ID' => array('type' => 'string'),
	'WECOM_AGENT_ID' => array('type' => 'string'),
	'WECOM_SECRET' => array('type' => 'password'),
	'WECOM_TOKEN' => array('type' => 'password'),
	'WECOM_ENCODING_AES_KEY' => array('type' => 'password'),
);

// Actions
if ($action == 'update') {
	$error = 0;
	foreach ($constArray as $key => $info) {
		$value = GETPOST($key, 'nohtml');
		$result = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
		if ($result < 0) {
			$error++;
		}
	}
	// Notification feature toggle (checkbox: absent means disabled)
	$result = dolibarr_set_const($db, 'WECOM_TRIGGER_NOTIFY', GETPOST('wecom_trigger_notify', 'alpha') ? '1' : '', 'chaine', 0, '', $conf->entity);
	if ($result < 0) {
		$error++;
	}
	setEventMessages($error ? $langs->trans("Error") : $langs->trans("SetupSaved"), null, $error ? 'errors' : 'mesgs');
	$action = '';
}

if ($action == 'testconnection') {
	$wecomApi = new WeComApi($db);
	$testresult = $wecomApi->testConnection((int) GETPOST('forcerefresh', 'int') ? true : false);
	setEventMessages($testresult['message'], null, $testresult['success'] ? 'mesgs' : 'errors');
	$action = '';
}

llxHeader('', $langs->trans("WeComSetup"));

$head = wecomAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleWeComName"), -1, 'wecom@wecom');

if ($action == 'edit') {
	print load_fiche_titre($langs->trans("WeComSetup"), '', 'wecom@wecom');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="border centpercent">';
	foreach ($constArray as $key => $info) {
		$labelkey = str_replace('WECOM_', '', $key);
		print '<tr><td class="fieldtitle">'.$langs->trans($labelkey).'</td><td>';
		if ($info['type'] == 'password') {
			print '<input type="password" class="flat minwidth300" name="'.$key.'" value="'.dol_escape_htmltag(getDolGlobalString($key)).'" autocomplete="off">';
		} else {
			print '<input type="text" class="flat minwidth300" name="'.$key.'" value="'.dol_escape_htmltag(getDolGlobalString($key)).'">';
		}
		print '</td></tr>';
	}
	print '</table>';

	print '<br>';
	print '<table class="border centpercent">';
	print '<tr><td>'.$langs->trans("WeComTriggerNotify").'</td><td>';
	print '<input type="checkbox" name="wecom_trigger_notify" value="1"'.(getDolGlobalString('WECOM_TRIGGER_NOTIFY') ? ' checked' : '').'> '.$langs->trans("WeComTriggerNotifyHelp");
	print '</td></tr>';
	print '</table>';

	print '<br><div class="center">';
	print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';
	print '</form>';
} else {
	print load_fiche_titre($langs->trans("WeComSetup"), '', 'wecom@wecom');

	print '<table class="border centpercent">';
	foreach ($constArray as $key => $info) {
		$labelkey = str_replace('WECOM_', '', $key);
		$value = getDolGlobalString($key);
		if ($info['type'] == 'password' && $value !== '') {
			$value = preg_replace('/./', '*', $value); // mask secrets on screen
		}
		print '<tr><td class="fieldtitle">'.$langs->trans($labelkey).'</td><td>'.dol_escape_htmltag($value).'</td></tr>';
	}
	print '</table>';

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=testconnection&token='.newToken().'">'.$langs->trans("TestConnection").'</a>';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=testconnection&forcerefresh=1&token='.newToken().'">'.$langs->trans("TestConnectionForceRefresh").'</a>';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
	print '</div>';
}

print dol_get_fiche_end();

// Page end
llxFooter();
$db->close();
