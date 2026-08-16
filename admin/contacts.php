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
 * \file    htdocs/custom/wecom/admin/contacts.php
 * \ingroup wecom
 * \brief   WeCom external contact synchronization page.
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
dol_include_once('/wecom/class/wecomcontactmap.class.php');

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

llxHeader('', $langs->trans("WeComContactSync"));

$head = wecomAdminPrepareHead();
print dol_get_fiche_head($head, 'contactssync', $langs->trans("ModuleWeComName"), -1, 'wecom@wecom');

if ($action == 'synccontacts') {
	// Full contact sync performs many sequential WeCom API calls, allow it to run long
	set_time_limit(0);

	// Live progress: the page header is already sent, flush progress lines during the run
	print '<div class="wecom-sync-progress" style="margin:8px 0;padding:10px;border:1px solid #ccc;background:#fafafa;">';
	print '<div id="wecom-progress-line">'.$langs->trans("WeComSyncRunning").'</div>';
	print '</div>';
	if (function_exists('flush')) {
		@flush();
	}

	$sync = new WeComSync($db);
	$langsThis = $langs;
	$stats = $sync->syncExternalContacts(function ($label, $done, $total) use ($langsThis) {
		$text = dol_htmlentities($langsThis->trans("WeComSyncProgress", $done, $total, dol_trunc($label, 40)));
		echo '<script type="text/javascript">document.getElementById("wecom-progress-line").innerHTML = '.json_encode($text).';</script>';
		if (ob_get_level() > 0) {
			@ob_flush();
		}
		@flush();
	});

	print '<script type="text/javascript">document.getElementById("wecom-progress-line").innerHTML = '.json_encode($langs->trans("Finished")).';</script>';
	$msg = $langs->trans("WeComExternalContacts").' - '.$langs->trans("WeComSyncResult", $stats['created'], $stats['updated'], $stats['skipped'], $stats['failed']);
	if (!empty($sync->errors)) {
		setEventMessages($msg, array_map('dol_htmlentities', $sync->errors), 'warnings');
	} elseif ($stats['failed'] > 0) {
		setEventMessages($msg, null, 'errors');
	} else {
		setEventMessages($msg, null, 'mesgs');
	}
	$action = '';
}

print load_fiche_titre($langs->trans("WeComContactSync"), '', 'wecom@wecom');

print '<div class="tabsAction">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=synccontacts&token='.newToken().'">'.$langs->trans("SyncExternalContacts").'</a>';
print '</div>';

// Contact mapping list (paginated)
$page = (int) GETPOST('page', 'int');
$limit = (int) GETPOST('limit', 'int'); $limit = $limit > 0 ? $limit : 25;
$offset = $limit * $page;

$sqlcount = "SELECT COUNT(*) AS nb FROM ".$db->prefix()."wecom_contact_map AS m";
$sqlcount .= " WHERE m.entity = ".((int) $conf->entity);
$resqlcount = $db->query($sqlcount);
$nbtotal = ($resqlcount ? ($db->fetch_object($resqlcount)->nb ?? 0) : 0);

$sql = "SELECT m.rowid, m.external_userid, m.wecom_type, m.wecom_state, m.wecom_name, m.wecom_corp_name, m.wecom_tags, m.owner_wecom_userid, m.status, m.tms";
$sql .= ", s.rowid AS socid, s.nom AS socname, c.rowid AS cid, c.lastname, c.firstname";
$sql .= ", ow.lastname AS owner_lastname, ow.firstname AS owner_firstname";
$sql .= " FROM ".$db->prefix()."wecom_contact_map AS m";
$sql .= " LEFT JOIN ".$db->prefix()."societe AS s ON s.rowid = m.fk_soc";
$sql .= " LEFT JOIN ".$db->prefix()."socpeople AS c ON c.rowid = m.fk_contact";
$sql .= " LEFT JOIN ".$db->prefix()."wecom_user_map AS oum ON oum.wecom_userid = m.owner_wecom_userid";
$sql .= " LEFT JOIN ".$db->prefix()."user AS ow ON ow.rowid = oum.fk_user";
$sql .= " WHERE m.entity = ".((int) $conf->entity);
$sql .= " ORDER BY m.tms DESC";
$sql .= " LIMIT ".((int) $limit)." OFFSET ".((int) $offset);
$resql = $db->query($sql);
$num = ($resql ? $db->num_rows($resql) : 0);

// Form must open before print_barre_liste: the limit selector submits its parent form
print '<form method="GET" action="'.dol_escape_htmltag(basename($_SERVER["PHP_SELF"])).'">';
print_barre_liste($langs->trans("WeComContactMappings"), $page, $_SERVER["PHP_SELF"], '&limit='.(int) $limit, '', '', '', $nbtotal, $nbtotal, '', 0, '', '', $limit, 0, 0, 1);

if (!$resql) {
	dol_print_error($db);
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("WeComContact").'</td>';
	print '<td>'.$langs->trans("Company").'</td>';
	print '<td>'.$langs->trans("Contact").'</td>';
	print '<td>'.$langs->trans("Owner").'</td>';
	print '<td>'.$langs->trans("WeComTags").'</td>';
	print '<td>'.$langs->trans("Type").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td>'.$langs->trans("LastModification").'</td>';
	print '</tr>';
	if ($num == 0) {
		print '<tr><td colspan="8"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}
	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resql);
		$soclink = $obj->socid > 0 ? '<a href="'.DOL_URL_ROOT.'/societe/card.php?id='.$obj->socid.'">'.dol_escape_htmltag($obj->socname).'</a>' : $langs->trans("DeletedSoc");
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($obj->wecom_name).'<br><span class="opacitymedium small">'.dol_escape_htmltag($obj->external_userid).'</span></td>';
		print '<td>'.$soclink.'</td>';
		print '<td>'.($obj->cid > 0 ? dol_escape_htmltag(trim($obj->lastname.' '.$obj->firstname)) : '-').'</td>';
		$ownerCell = dol_escape_htmltag($obj->owner_wecom_userid);
		if ($obj->owner_lastname) {
			$ownerCell .= '<br><span class="opacitymedium small">'.dol_escape_htmltag(trim($obj->owner_lastname.' '.$obj->owner_firstname)).'</span>';
		}
		print '<td>'.$ownerCell.'</td>';
		print '<td>'.dol_escape_htmltag(dol_trunc($obj->wecom_tags, 60)).'</td>';
		print '<td>'.($obj->wecom_type == 2 ? $langs->trans("WeComTypeEnterprise") : $langs->trans("WeComTypePersonal")).'</td>';
		print '<td class="center">'.($obj->status ? $langs->trans("Active") : $langs->trans("Unbound")).'</td>';
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
