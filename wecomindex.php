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
 * \file    htdocs/custom/wecom/wecomindex.php
 * \ingroup wecom
 * \brief   Home page of WeCom module (phase 1: placeholder overview).
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
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

require_once './lib/wecom.lib.php';

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("wecom@wecom"));

if (!$user->hasRight('wecom', 'read')) {
	accessforbidden();
}

llxHeader('', $langs->trans("ModuleWeComName"));

print load_fiche_titre($langs->trans("ModuleWeComName"), '', 'wecom@wecom');

print '<div class="fichecenter">';
print $langs->trans("WeComIndexIntro");
print '</div>';

// Admin quick link
if ($user->hasRight('wecom', 'admin')) {
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.dol_buildpath('/wecom/admin/setup.php', 1).'">'.$langs->trans("WeComSetup").'</a>';
	print '</div>';
}

llxFooter();
$db->close();
