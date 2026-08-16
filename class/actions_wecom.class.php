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
 * \file    htdocs/custom/wecom/class/actions_wecom.class.php
 * \ingroup wecom
 * \brief   Hook actions: add the WeCom login button on the Dolibarr login page.
 */

/**
 * Actions hooks for WeCom module
 *
 * Note: HookManager expects the class name 'Actions'.ucfirst('wecom') = ActionsWecom
 * (see htdocs/core/class/hookmanager.class.php:152).
 */
class ActionsWecom
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var array Hook results
	 */
	public $results = array();

	/**
	 * @var string Error string
	 */
	public $error = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add a WeCom login entry on the login page (context mainloginpage).
	 *
	 * @param	array	$parameters	Hook metadata (context, etc.)
	 * @return	int					0 (hook appends output in resPrint)
	 */
	public function getLoginPageOptions($parameters)
	{
		global $langs;

		if (!in_array('mainloginpage', explode(':', (string) $parameters['context']))) {
			return 0;
		}
		if (!getDolGlobalString('WECOM_CORP_ID') || !(int) getDolGlobalString('WECOM_AGENT_ID')) {
			return 0; // OAuth not configured: hide the button
		}

		$langs->load("wecom@wecom");
		$this->resprints = '';
		$this->resprints .= '<br><div style="text-align:center;">';
		$this->resprints .= '<a class="button" href="'.dol_buildpath('/wecom/wecom/oauth.php', 1).'">';
		$this->resprints .= $langs->trans("WeComLogin");
		$this->resprints .= '</a></div>';

		return 0;
	}
}
