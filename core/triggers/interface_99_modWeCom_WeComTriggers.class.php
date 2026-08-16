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
 *      \file       htdocs/custom/wecom/core/triggers/interface_99_modWeCom_WeComTriggers.class.php
 *      \ingroup    wecom
 *      \brief      Trigger file for WeCom notifications (spec §23: system notifications).
 *
 *      Sends a WeCom application message to the sales owner of a thirdparty
 *      when a thirdparty or contract is created. Disabled unless
 *      WECOM_TRIGGER_NOTIFY is set. Never blocks the business flow: any
 *      error is logged and swallowed.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class InterfaceWeComTriggers
 */
class InterfaceWeComTriggers extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "crm";
		$this->description = "Triggers of the WeCom module";
		$this->version = '0.2.0';
		$this->picto = 'generic';
	}

	/**
	 * Function called when a Dolibarr business event occurs.
	 *
	 * @param	string		$action		Event action label
	 * @param	CommonObject	$object		Object
	 * @param	string		$user		User
	 * @param	Translate	$langs		Lang object
	 * @param	Conf		$conf		Config
	 * @return	int						0 if OK, <0 if KO
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->wecom->enabled)) {
			return 0;
		}
		if (!getDolGlobalString('WECOM_TRIGGER_NOTIFY')) {
			return 0; // feature disabled by admin
		}

		if ($action == 'COMPANY_CREATE') {
			$this->notifyOwnerOfThirdparty($object, $langs->transnoentities("WeComTriggerNewThirdparty"), $object->name);
			return 0;
		}
		if ($action == 'CONTRACT_CREATE') {
			$this->notifyOwnerOfThirdparty($object, $langs->transnoentities("WeComTriggerNewContract"), $object->ref_customer ? $object->ref_customer : $object->ref);
			return 0;
		}

		// Other events: not handled by this trigger
		return 0;
	}

	/**
	 * Notify the WeCom sales owner mapped to the thirdparty of the object.
	 *
	 * @param	CommonObject	$object		Object with a socid (or being a societe)
	 * @param	string			$title		Message title
	 * @param	string			$label		Object label
	 * @return	void
	 */
	protected function notifyOwnerOfThirdparty($object, $title, $label)
	{
		global $langs;

		try {
			$fkSoc = isset($object->socid) ? (int) $object->socid : (isset($object->id) ? (int) $object->id : 0);
			if ($fkSoc <= 0) {
				return;
			}

			// Resolve the owner of the latest active mapping of this thirdparty
			$sql = "SELECT owner_wecom_userid, wecom_name FROM ".$this->db->prefix()."wecom_contact_map";
			$sql .= " WHERE fk_soc = ".((int) $fkSoc)." AND status = 1 AND owner_wecom_userid IS NOT NULL AND owner_wecom_userid <> ''";
			$sql .= " ORDER BY tms DESC LIMIT 1";
			$resql = $this->db->query($sql);
			if (!$resql || !($obj = $this->db->fetch_object($resql))) {
				return; // no WeCom mapping for this thirdparty: nothing to do
			}

			dol_include_once('/wecom/class/wecomapi.class.php');
			$api = new WeComApi($this->db);

			$content = "**".$title."**\n";
			$content .= ($label !== '' ? $langs->transnoentities("Label").": ".$label."\n" : '');
			$content .= date('Y-m-d H:i');
			$api->sendApplicationMessage($obj->owner_wecom_userid, $content, 'markdown');
			dol_syslog('WeCom trigger notification sent to '.$obj->owner_wecom_userid.' for '.$title, LOG_INFO);
		} catch (WeComApiException $e) {
			// Never block the business action because of a notification failure
			dol_syslog('WeCom trigger notification failed: '.$e->getMessage().' errcode='.$e->getErrorCode(), LOG_WARNING);
		} catch (Exception $e) {
			dol_syslog('WeCom trigger notification error: '.$e->getMessage(), LOG_WARNING);
		}
	}
}
