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
 * \file    htdocs/custom/wecom/lib/wecom.lib.php
 * \ingroup wecom
 * \brief   Library files with common functions for WeCom module.
 */

/**
 * Prepare admin pages header
 *
 * @return array<head array>
 */
function wecomAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("wecom@wecom");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/wecom/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/wecom/admin/users.php", 1);
	$head[$h][1] = $langs->trans("WeComUserSync");
	$head[$h][2] = 'userssync';
	$h++;

	$head[$h][0] = dol_buildpath("/wecom/admin/contacts.php", 1);
	$head[$h][1] = $langs->trans("WeComContactSync");
	$head[$h][2] = 'contactssync';
	$h++;

	$head[$h][0] = dol_buildpath("/wecom/admin/events.php", 1);
	$head[$h][1] = $langs->trans("WeComEvents");
	$head[$h][2] = 'events';
	$h++;

	$head[$h][0] = dol_buildpath("/wecom/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	return $head;
}
