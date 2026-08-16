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
 * \file    htdocs/custom/wecom/class/wecomcontactmap.class.php
 * \ingroup wecom
 * \brief   DAO for table llx_wecom_contact_map (WeCom external contact <-> Dolibarr thirdparty/contact).
 */

/**
 * WeCom external contact mapping
 */
class WeComContactMap
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	public $id;
	public $entity;
	public $external_userid;
	public $fk_soc;
	public $fk_contact;
	public $wecom_type;
	public $wecom_state;
	public $wecom_name;
	public $wecom_avatar;
	public $wecom_corp_name;
	public $owner_wecom_userid;
	public $status;
	public $date_creation;

	const STATUS_ACTIVE = 1;
	const STATUS_UNBOUND = 0;

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
	 * Load one mapping by external user id.
	 *
	 * @param	string	$externalUserId	WeCom external_userid
	 * @return	int						1 if found, 0 if not, <0 on error
	 */
	public function fetchByExternalUserId($externalUserId)
	{
		$sql = "SELECT rowid, entity, external_userid, fk_soc, fk_contact, wecom_type, wecom_state, wecom_name, wecom_avatar, wecom_corp_name, owner_wecom_userid, status";
		$sql .= " FROM ".$this->db->prefix()."wecom_contact_map";
		$sql .= " WHERE external_userid = '".$this->db->escape($externalUserId)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}
		$this->id = $obj->rowid;
		$this->entity = $obj->entity;
		$this->external_userid = $obj->external_userid;
		$this->fk_soc = $obj->fk_soc;
		$this->fk_contact = $obj->fk_contact;
		$this->wecom_type = $obj->wecom_type;
		$this->wecom_state = $obj->wecom_state;
		$this->wecom_name = $obj->wecom_name;
		$this->wecom_avatar = $obj->wecom_avatar;
		$this->wecom_corp_name = $obj->wecom_corp_name;
		$this->owner_wecom_userid = $obj->owner_wecom_userid;
		$this->status = $obj->status;
		return 1;
	}

	/**
	 * Load one mapping by rowid.
	 *
	 * @param	int		$rowid	Mapping row id
	 * @return	int				1 if found, 0 if not, <0 on error
	 */
	public function fetchByRowId($rowid)
	{
		$sql = "SELECT rowid, entity, external_userid, fk_soc, fk_contact, wecom_type, wecom_state, wecom_name, wecom_avatar, wecom_corp_name, owner_wecom_userid, status";
		$sql .= " FROM ".$this->db->prefix()."wecom_contact_map";
		$sql .= " WHERE rowid = ".((int) $rowid);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}
		$this->id = $obj->rowid;
		$this->entity = $obj->entity;
		$this->external_userid = $obj->external_userid;
		$this->fk_soc = $obj->fk_soc;
		$this->fk_contact = $obj->fk_contact;
		$this->wecom_type = $obj->wecom_type;
		$this->wecom_state = $obj->wecom_state;
		$this->wecom_name = $obj->wecom_name;
		$this->wecom_avatar = $obj->wecom_avatar;
		$this->wecom_corp_name = $obj->wecom_corp_name;
		$this->owner_wecom_userid = $obj->owner_wecom_userid;
		$this->status = $obj->status;
		return 1;
	}

	/**
	 * Load mapping for a Dolibarr thirdparty.
	 *
	 * @param	int		$fkSoc	Thirdparty id
	 * @return	array<int,array>	List of mapping rows
	 */
	public function fetchAllBySoc($fkSoc)
	{
		$sql = "SELECT rowid, external_userid, fk_soc, fk_contact, wecom_type, wecom_state, wecom_name, wecom_avatar, wecom_corp_name, owner_wecom_userid, status, tms";
		$sql .= " FROM ".$this->db->prefix()."wecom_contact_map";
		$sql .= " WHERE fk_soc = ".((int) $fkSoc)." AND entity = ".((int) $this->entityOfRecord());
		$sql .= " ORDER BY status DESC, tms DESC";
		return $this->executeAndCollect($sql);
	}

	/**
	 * Create a mapping.
	 *
	 * @param	string	$externalUserId	WeCom external_userid
	 * @param	int		$fkSoc			Thirdparty id
	 * @param	int		$fkContact		Contact id (0 if none)
	 * @param	array	$extra			wecom_type, wecom_state, wecom_name, wecom_avatar, wecom_corp_name, owner_wecom_userid
	 * @return	int						Rowid (>0) or <0 on error
	 */
	public function create($externalUserId, $fkSoc, $fkContact, $extra = array())
	{
		global $conf;

		$now = dol_now();
		$sql = "INSERT INTO ".$this->db->prefix()."wecom_contact_map";
		$sql .= " (entity, external_userid, fk_soc, fk_contact, wecom_type, wecom_state, wecom_name, wecom_avatar, wecom_corp_name, owner_wecom_userid, status, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($externalUserId)."', ".($fkSoc > 0 ? (int) $fkSoc : 'NULL').", ".($fkContact > 0 ? (int) $fkContact : 'NULL').",";
		$sql .= " ".(isset($extra['wecom_type']) ? (int) $extra['wecom_type'] : 'NULL').",";
		$sql .= " ".(empty($extra['wecom_state']) ? 'NULL' : "'".$this->db->escape(dol_trunc($extra['wecom_state'], 32))."'").",";
		$sql .= " ".(empty($extra['wecom_name']) ? 'NULL' : "'".$this->db->escape(dol_trunc($extra['wecom_name'], 255))."'").",";
		$sql .= " ".(empty($extra['wecom_avatar']) ? 'NULL' : "'".$this->db->escape(dol_trunc($extra['wecom_avatar'], 512))."'").",";
		$sql .= " ".(empty($extra['wecom_corp_name']) ? 'NULL' : "'".$this->db->escape(dol_trunc($extra['wecom_corp_name'], 255))."'").",";
		$sql .= " ".(empty($extra['wecom_tags']) ? 'NULL' : "'".$this->db->escape(dol_trunc($extra['wecom_tags'], 512))."'").",";
		$sql .= " ".(empty($extra['owner_wecom_userid']) ? 'NULL' : "'".$this->db->escape(dol_trunc($extra['owner_wecom_userid'], 64))."'").",";
		$sql .= " ".self::STATUS_ACTIVE.", '".$this->db->idate($now)."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$this->id = $this->db->last_insert_id($this->db->prefix()."wecom_contact_map");
		return $this->id;
	}

	/**
	 * Update the WeCom side fields of a mapping.
	 *
	 * @param	array	$extra	Fields to refresh (wecom_state, wecom_name, wecom_avatar, wecom_corp_name, owner_wecom_userid, status)
	 * @return	int				1 on success, <0 on error
	 */
	public function updateWeComFields($extra = array())
	{
		if (empty($this->id) || empty($extra)) {
			return -1;
		}
		$fields = array(
			'wecom_state' => array('string', 32),
			'wecom_name' => array('string', 255),
			'wecom_avatar' => array('string', 512),
			'wecom_corp_name' => array('string', 255),
			'wecom_tags' => array('string', 512),
			'owner_wecom_userid' => array('string', 64),
			'status' => array('int', 0),
		);
		$sets = array();
		foreach ($fields as $field => $def) {
			if (array_key_exists($field, $extra)) {
				if ($def[0] == 'int') {
					$sets[] = $field." = ".((int) $extra[$field]);
				} else {
					$sets[] = $field." = ".(empty($extra[$field]) ? 'NULL' : "'".$this->db->escape(dol_trunc((string) $extra[$field], $def[1]))."'");
				}
			}
		}
		if (empty($sets)) {
			return -1;
		}
		$sql = "UPDATE ".$this->db->prefix()."wecom_contact_map SET ".implode(', ', $sets)." WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		return $resql ? 1 : -1;
	}

	/**
	 * Unbind: keep the row but mark it inactive (never delete Dolibarr data).
	 *
	 * @return	int		1 on success, <0 on error
	 */
	public function unbind()
	{
		if (empty($this->id)) {
			return -1;
		}
		$sql = "UPDATE ".$this->db->prefix()."wecom_contact_map SET status = ".self::STATUS_UNBOUND." WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		return $resql ? 1 : -1;
	}

	/**
	 * Execute SQL and collect all rows as arrays.
	 *
	 * @param	string	$sql	SQL
	 * @return	array<int,array>
	 */
	protected function executeAndCollect($sql)
	{
		$result = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			return $result;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$result[] = (array) $obj;
		}
		return $result;
	}

	/**
	 * Current entity (conf may not be loaded in CLI contexts).
	 *
	 * @return	int
	 */
	protected function entityOfRecord()
	{
		global $conf;
		return isset($conf->entity) ? (int) $conf->entity : 1;
	}
}
