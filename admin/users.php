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
 * \file    htdocs/custom/wecom/admin/users.php
 * \ingroup wecom
 * \brief   WeCom department/user synchronization page.
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
dol_include_once('/wecom/class/wecomsync.class.php');
dol_include_once('/wecom/class/wecomusermap.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "wecom@wecom"));

if (!$user->hasRight('wecom', 'sync')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

llxHeader('', $langs->trans("WeComUserSync"));

$head = wecomAdminPrepareHead();
print dol_get_fiche_head($head, 'userssync', $langs->trans("ModuleWeComName"), -1, 'wecom@wecom');

// Actions
if ($action == 'syncdepartments' || $action == 'syncusers') {
	// Organization sync performs many sequential WeCom API calls, allow it to run long
	set_time_limit(0);
	$sync = new WeComSync($db);
	if ($action == 'syncdepartments') {
		$stats = $sync->syncDepartments();
		$label = $langs->trans("WeComDepartments");
	} else {
		$stats = $sync->syncUsers();
		$label = $langs->trans("WeComUsers");
	}
	$msg = $label.' - '.$langs->trans("WeComSyncResult", $stats['created'], $stats['updated'], $stats['skipped'], $stats['failed']);
	if (!empty($sync->errors)) {
		setEventMessages($msg, array_map('dol_htmlentities', $sync->errors), 'warnings');
	} elseif ($stats['failed'] > 0) {
		setEventMessages($msg, null, 'errors');
	} else {
		setEventMessages($msg, null, 'mesgs');
	}
	$action = '';
}

print load_fiche_titre($langs->trans("WeComUserSync"), '', 'wecom@wecom');

// Sync buttons
print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=syncdepartments&token='.newToken().'">'.$langs->trans("SyncDepartments").'</a>';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=syncusers&token='.newToken().'">'.$langs->trans("SyncUsers").'</a>';
print '</div>';

// User mapping list (paginated)
$page = (int) GETPOST('page', 'int');
$limit = (int) GETPOST('limit', 'int'); $limit = $limit > 0 ? $limit : 25;
$offset = $limit * $page;

$sqlcount = "SELECT COUNT(*) AS nb FROM ".$db->prefix()."wecom_user_map as m";
$sqlcount .= " WHERE m.entity = ".((int) $conf->entity);
$resqlcount = $db->query($sqlcount);
$nbtotal = ($resqlcount ? ($db->fetch_object($resqlcount)->nb ?? 0) : 0);

$sql = "SELECT m.rowid, m.wecom_userid, m.wecom_department_ids, m.status, m.date_creation, m.tms";
$sql .= ", u.rowid as uid, u.lastname, u.firstname, u.login, u.email";
$sql .= " FROM ".$db->prefix()."wecom_user_map as m";
$sql .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = m.fk_user";
$sql .= " WHERE m.entity = ".((int) $conf->entity);
$sql .= " ORDER BY m.tms DESC";
$sql .= " LIMIT ".((int) $limit)." OFFSET ".((int) $offset);

$resql = $db->query($sql);
$num = ($resql ? $db->num_rows($resql) : 0);

// Form must open before print_barre_liste: the limit selector submits its parent form
print '<form method="GET" action="'.dol_escape_htmltag(basename($_SERVER["PHP_SELF"])).'">';
print_barre_liste($langs->trans("WeComUserMappings"), $page, $_SERVER["PHP_SELF"], '&limit='.(int) $limit, '', '', '', $nbtotal, $nbtotal, '', 0, '', '', $limit, 0, 0, 1);

if (!$resql) {
	dol_print_error($db);
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("WeComUserId").'</td>';
	print '<td>'.$langs->trans("DolibarrUser").'</td>';
	print '<td>'.$langs->trans("Login").'</td>';
	print '<td>'.$langs->trans("Email").'</td>';
	print '<td>'.$langs->trans("WeComDepartments").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td>'.$langs->trans("LastModification").'</td>';
	print '</tr>';
	if ($num == 0) {
		print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}
	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resql);
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($obj->wecom_userid).'</td>';
		print '<td>'.($obj->uid > 0 ? dol_escape_htmltag(trim($obj->lastname.' '.$obj->firstname)) : $langs->trans("DeletedUser")).'</td>';
		print '<td>'.dol_escape_htmltag($obj->login).'</td>';
		print '<td>'.dol_escape_htmltag($obj->email).'</td>';
		print '<td>'.dol_escape_htmltag($obj->wecom_department_ids).'</td>';
		print '<td class="center">'.($obj->status ? $langs->trans("Active") : $langs->trans("Disabled")).'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->tms), 'dayhour').'</td>';
		print '</tr>';
		$i++;
	}
	print '</table>';
	print '</form>';
	$db->free($resql);
}

print dol_get_fiche_end();

llxFooter();
$db->close();
