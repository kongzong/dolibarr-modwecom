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
 * \file    htdocs/custom/wecom/admin/events.php
 * \ingroup wecom
 * \brief   WeCom webhook event log page.
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

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/wecom.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "wecom@wecom"));

if (!$user->hasRight('wecom', 'read')) {
	accessforbidden();
}

llxHeader('', $langs->trans("WeComEvents"));

$head = wecomAdminPrepareHead();
print dol_get_fiche_head($head, 'events', $langs->trans("ModuleWeComName"), -1, 'wecom@wecom');

print load_fiche_titre($langs->trans("WeComEvents"), '', 'wecom@wecom');

// Paginated event list
$page = (int) GETPOST('page', 'int');
$limit = (int) GETPOST('limit', 'int');
$limit = $limit > 0 ? $limit : 25;
$offset = $limit * $page;

$sqlcount = "SELECT COUNT(*) AS nb FROM ".$db->prefix()."wecom_event_log";
$sqlcount .= " WHERE entity = ".((int) $conf->entity);
$resqlcount = $db->query($sqlcount);
$nbtotal = ($resqlcount ? ($db->fetch_object($resqlcount)->nb ?? 0) : 0);

$sql = "SELECT rowid, event_id, event_type, event_time, payload_hash, process_status, process_message, retry_count, date_creation";
$sql .= " FROM ".$db->prefix()."wecom_event_log";
$sql .= " WHERE entity = ".((int) $conf->entity);
$sql .= " ORDER BY date_creation DESC";
$sql .= " LIMIT ".((int) $limit)." OFFSET ".((int) $offset);
$resql = $db->query($sql);
$num = ($resql ? $db->num_rows($resql) : 0);

print_barre_liste($langs->trans("WeComEvents"), $page, $_SERVER["PHP_SELF"], '', '', '', '', $nbtotal, $nbtotal, '', 0, '', '', $limit, 0, 0, 1);

$statusLabels = array(
	0 => $langs->trans("WeComEventNew"),
	1 => $langs->trans("WeComEventProcessed"),
	2 => $langs->trans("WeComEventDuplicate"),
	3 => $langs->trans("Error"),
);

if (!$resql) {
	dol_print_error($db);
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Date").'</td>';
	print '<td>'.$langs->trans("EventType").'</td>';
	print '<td>'.$langs->trans("EventId").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td>'.$langs->trans("Result").'</td>';
	print '</tr>';
	if ($num == 0) {
		print '<tr><td colspan="5"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}
	$i = 0;
	while ($i < $num) {
		$obj = $db->fetch_object($resql);
		print '<tr class="oddeven">';
		print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
		print '<td>'.dol_escape_htmltag($obj->event_type).'</td>';
		print '<td><span class="opacitymedium small">'.dol_escape_htmltag(dol_trunc($obj->event_id, 20)).'</span></td>';
		print '<td class="center">'.($statusLabels[$obj->process_status] ?? $obj->process_status).'</td>';
		print '<td>'.dol_escape_htmltag(dol_trunc($obj->process_message, 80)).'</td>';
		print '</tr>';
		$i++;
	}
	print '</table>';
	$db->free($resql);
}

print dol_get_fiche_end();

llxFooter();
$db->close();
