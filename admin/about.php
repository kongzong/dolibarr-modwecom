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
 * \file    htdocs/custom/wecom/admin/about.php
 * \ingroup wecom
 * \brief   About page of WeCom module.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
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
require_once DOL_DOCUMENT_ROOT."/core/lib/functions2.lib.php";
require_once '../lib/wecom.lib.php';

/**
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array("admin", "wecom@wecom"));

if (!$user->hasRight('wecom', 'read')) {
	accessforbidden();
}

llxHeader('', $langs->trans("About"));

$head = wecomAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans("ModuleWeComName"), -1, 'wecom@wecom');

print $langs->trans("WeComAboutText");

print dol_get_fiche_end();

llxFooter();
$db->close();
