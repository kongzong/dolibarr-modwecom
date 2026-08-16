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
 * \file    htdocs/custom/wecom/wecom_contact_tab.php
 * \ingroup wecom
 * \brief   Tab page on thirdparty card showing WeCom external contact info (spec §17).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
dol_include_once('/wecom/class/wecomcontactmap.class.php');
dol_include_once('/wecom/class/wecomapi.class.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("companies", "wecom@wecom"));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

// Permission: follow the thirdparty read permission, WeCom info must not bypass it (spec §31)
if (!$user->hasRight('societe', 'lire') || !$user->hasRight('wecom', 'read')) {
	accessforbidden();
}

$object = new Societe($db);
if ($id > 0 || $ref) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		print 'Failed to load thirdparty';
		exit;
	}
	$id = $object->id;
} else {
	accessforbidden('Missing thirdparty id');
}

$hookmanager->initHooks(array('wecomcontacttab'));

// Actions
if ($action == 'unbind' && $user->hasRight('wecom', 'write')) {
	$mapid = GETPOST('mapid', 'int');
	$map = new WeComContactMap($db);
	if ($mapid > 0 && $map->fetchByRowId($mapid) > 0 && $map->fk_soc == $id) {
		if ($map->unbind() > 0) {
			setEventMessages($langs->trans("WeComUnbound"), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("Error"), null, 'errors');
		}
	}
	$action = '';
}

if ($action == 'sendmessage' && $user->hasRight('wecom', 'message')) {
	$mapid = GETPOST('mapid', 'int');
	$content = GETPOST('message_content', 'restricthtml');
	$map = new WeComContactMap($db);
	if ($mapid > 0 && $map->fetchByRowId($mapid) > 0 && $map->fk_soc == $id && !empty($map->owner_wecom_userid) && $content !== '') {
		// Application messages can only be sent to internal users: the owner sales
		try {
			$wecomApi = new WeComApi($db);
			$wecomApi->sendApplicationMessage($map->owner_wecom_userid, $content);
			setEventMessages($langs->trans("WeComMessageSentTo", $map->owner_wecom_userid), null, 'mesgs');
		} catch (WeComApiException $e) {
			setEventMessages($e->getMessage().' (errcode='.$e->getErrorCode().')', null, 'errors');
		}
	} else {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->trans("Content")), null, 'errors');
	}
	$action = '';
}

llxHeader('', $langs->trans("WeCom"));

$head = societe_prepare_head($object);
print dol_get_fiche_head($head, 'wecom', $langs->trans("ThirdParty"), -1, 'company');

$form = new Form($db);

$mapApi = new WeComContactMap($db);
$mappings = $mapApi->fetchAllBySoc($id);

// Resolve the Dolibarr users behind owner wecom accounts (V0.2)
$ownerUsers = array();
$sql = "SELECT m.wecom_userid, u.rowid AS uid, u.lastname, u.firstname FROM ".$db->prefix()."wecom_user_map AS m";
$sql .= " JOIN ".$db->prefix()."user AS u ON u.rowid = m.fk_user";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$ownerUsers[$obj->wecom_userid] = trim($obj->lastname.' '.$obj->firstname);
	}
}

if (empty($mappings)) {
	print '<div class="opacitymedium">'.$langs->trans("WeComNoMappingForThisSoc").'</div>';
} else {
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("WeComContact").'</td>';
	print '<td>'.$langs->trans("ExternalUserId").'</td>';
	print '<td>'.$langs->trans("Type").'</td>';
	print '<td>'.$langs->trans("Owner").'</td>';
	print '<td>'.$langs->trans("WeComTags").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td>'.$langs->trans("LastModification").'</td>';
	print '<td></td>';
	print '</tr>';
	foreach ($mappings as $m) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($m['wecom_name']).'</td>';
		print '<td>'.dol_escape_htmltag($m['external_userid']).'</td>';
		print '<td>'.($m['wecom_type'] == 2 ? $langs->trans("WeComTypeEnterprise") : $langs->trans("WeComTypePersonal")).'</td>';
		$ownerCell = dol_escape_htmltag($m['owner_wecom_userid']);
		if (!empty($ownerUsers[$m['owner_wecom_userid']])) {
			$ownerCell .= '<br><span class="opacitymedium small">'.dol_escape_htmltag($ownerUsers[$m['owner_wecom_userid']]).'</span>';
		}
		print '<td>'.$ownerCell.'</td>';
		print '<td>'.dol_escape_htmltag(dol_trunc((string) $m['wecom_tags'], 60)).'</td>';
		print '<td class="center">'.($m['status'] ? $langs->trans("Active") : $langs->trans("Unbound")).'</td>';
		print '<td>'.dol_print_date($db->jdate($m['tms']), 'dayhour').'</td>';
		print '<td class="right">';
		if ($m['status'] && $user->hasRight('wecom', 'write')) {
			print '<a class="pictodelete" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&mapid='.$m['rowid'].'&action=unbind&token='.newToken().'">'.$langs->trans("WeComUnbind").'</a>';
		}
		print '</td>';
		print '</tr>';
	}
	print '</table>';

	// Send message form (to the owner sales of the active mapping)
	$activeMapping = null;
	foreach ($mappings as $m) {
		if ($m['status'] && !empty($m['owner_wecom_userid'])) {
			$activeMapping = $m;
			break;
		}
	}
	if ($activeMapping && $user->hasRight('wecom', 'message')) {
		print '<br>';
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="sendmessage">';
		print '<input type="hidden" name="mapid" value="'.(int) $activeMapping['rowid'].'">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("WeComSendMessage").'</td></tr>';
		print '<tr><td class="titlefield">'.$langs->trans("Recipient").'</td><td>'.dol_escape_htmltag($activeMapping['owner_wecom_userid']).' ('.$langs->trans("Owner").')</td></tr>';
		print '<tr><td>'.$langs->trans("Content").'</td><td><textarea class="flat minwidth500" name="message_content" rows="3" maxlength="2048" required></textarea></td></tr>';
		print '<tr><td></td><td><input class="button" type="submit" value="'.$langs->trans("Send").'"></td></tr>';
		print '</table>';
		print '</form>';
	}
}

print dol_get_fiche_end();

llxFooter();
$db->close();
