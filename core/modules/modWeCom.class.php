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
 *  \defgroup   wecom     Module WeCom
 *  \brief      WeCom (Enterprise WeChat) integration module descriptor.
 *
 *  \file       htdocs/custom/wecom/core/modules/modWeCom.class.php
 *  \ingroup    wecom
 *  \brief      Description and activation file for module WeCom
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module WeCom
 */
class modWeCom extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		// Id for module (must be unique). Reserved for external module modWeCom.
		$this->numero = 501550;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'wecom';

		// Family used to group modules by family in module setup page
		$this->family = "crm";
		$this->module_position = '90';

		// Module label (no space allowed)
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description
		$this->description = "WeComIntegrationDescription";
		$this->descriptionlong = "WeComIntegrationDescriptionLong";

		// Author
		$this->editor_name = 'modWeCom';
		$this->editor_url = 'https://example.com';

		// Possible values: 'development', 'experimental' or a version string
		$this->version = '0.1.0';

		// Key used in llx_const table to save module status enabled/disabled
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		$this->picto = 'generic';

		// Define some features supported by module
		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(
				'mainloginpage',
			),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0,
		);

		// Data directories to create when module is enabled
		$this->dirs = array("/wecom/temp");

		// Config pages
		$this->config_page_url = array("setup.php@wecom");

		// Dependencies
		$this->hidden = getDolGlobalInt('MODULE_WECOM_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		// The language file dedicated to this module
		$this->langfiles = array("wecom@wecom");

		// Prerequisites
		$this->phpmin = array(7, 1);
		$this->need_dolibarr_version = array(19, -3);
		$this->need_javascript_ajax = 0;

		// Messages at activation
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants
		$this->const = array();

		if (!isModEnabled("wecom")) {
			$conf->wecom = new stdClass();
			$conf->wecom->enabled = 0;
		}

		// Array to add new pages in new tabs
		$this->tabs = array();
		// WeCom tab on thirdparty card (spec §17)
		$this->tabs[] = array('data' => 'thirdparty:+wecom:WeCom:wecom@wecom:$user->hasRight(\'wecom\', \'read\'):/wecom/wecom_contact_tab.php?id=__ID__');

		// Dictionaries
		$this->dictionaries = array();

		// Boxes/Widgets
		$this->boxes = array();

		// Cronjobs (V0.2: hourly external contact refresh, disabled by default)
		$this->cronjobs = array(
			0 => array(
				'label' => 'WeComExternalContactSync',
				'jobtype' => 'method',
				'class' => '/wecom/class/wecomsync.class.php',
				'objectname' => 'WeComSync',
				'method' => 'doScheduledJob',
				'parameters' => '',
				'comment' => 'Hourly incremental sync of WeCom external contacts',
				'frequency' => 1,
				'unitfrequency' => 3600,
				'status' => 0,
				'test' => 'isModEnabled("wecom")',
				'priority' => 50,
			),
		);

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero . 11; // id
		$this->rights[$r][1] = 'Read WeCom data'; // label
		$this->rights[$r][4] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . 21;
		$this->rights[$r][1] = 'Create/Update WeCom mappings';
		$this->rights[$r][4] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . 31;
		$this->rights[$r][1] = 'Administer WeCom configuration';
		$this->rights[$r][4] = 'admin';
		$r++;
		$this->rights[$r][0] = $this->numero . 41;
		$this->rights[$r][1] = 'Run WeCom synchronization';
		$this->rights[$r][4] = 'sync';
		$r++;
		$this->rights[$r][0] = $this->numero . 51;
		$this->rights[$r][1] = 'Send WeCom application messages';
		$this->rights[$r][4] = 'message';
		$r++;

		// Main menu entries
		$this->menu = array();
		$r = 0;

		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'ModuleWeComName',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'wecom',
			'leftmenu' => '',
			'url' => '/wecom/wecomindex.php',
			'langs' => 'wecom@wecom',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("wecom")',
			'perms' => '$user->hasRight("wecom", "read")',
			'target' => '',
			'user' => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=wecom',
			'type' => 'left',
			'titre' => 'WeComSetup',
			'mainmenu' => 'wecom',
			'leftmenu' => 'wecom_setup',
			'url' => '/wecom/admin/setup.php',
			'langs' => 'wecom@wecom',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("wecom")',
			'perms' => '$user->hasRight("wecom", "admin")',
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 *  Function called when module is enabled.
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		// Create tables of module at module activation
		$result = $this->_load_tables('/wecom/sql/');
		if ($result < 0) {
			return -1;
		}

		// Permissions
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions.
	 *	WeCom tables and Dolibarr business data are NOT deleted.
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
