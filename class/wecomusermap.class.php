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
 * \file    htdocs/custom/wecom/class/wecomusermap.class.php
 * \ingroup wecom
 * \brief   DAO for table llx_wecom_user_map (WeCom user <-> Dolibarr user).
 */

/**
 * WeCom user mapping
 */
class WeComUserMap
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var int Rowid
	 */
	public $id;

	/**
	 * @var array<int,int> Mapping: wecom_userid -> fk_user
	 */
	public $cacheByWeComUserId = array();

	public $entity;
	public $fk_user;
	public $wecom_userid;
	public $wecom_unionid;
	public $wecom_openid;
	public $wecom_department_ids;
	public $status;
	public $date_creation;

	const STATUS_ACTIVE = 1;
	const STATUS_DISABLED = 0;

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
	 * Load one mapping by WeCom user id.
	 *
	 * @param	string	$wecomUserId	WeCom user id
	 * @return	int						1 if found, 0 if not found, <0 on error
	 */
	public function fetchByWeComUserId($wecomUserId)
	{
		$sql = "SELECT rowid, entity, fk_user, wecom_userid, wecom_unionid, wecom_openid, wecom_department_ids, status";
		$sql .= " FROM ".$this->db->prefix()."wecom_user_map";
		$sql .= " WHERE wecom_userid = '".$this->db->escape($wecomUserId)."'";
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
		$this->fk_user = $obj->fk_user;
		$this->wecom_userid = $obj->wecom_userid;
		$this->wecom_unionid = $obj->wecom_unionid;
		$this->wecom_openid = $obj->wecom_openid;
		$this->wecom_department_ids = $obj->wecom_department_ids;
		$this->status = $obj->status;
		return 1;
	}

	/**
	 * Create a mapping (idempotent: caller must check uniqueness via fetchByWeComUserId).
	 *
	 * @param	int		$fkUser			Dolibarr user rowid
	 * @param	string	$wecomUserId	WeCom user id
	 * @param	array	$extra			Optional extra fields (wecom_unionid, wecom_openid, wecom_department_ids)
	 * @return	int						Rowid (>0) or <0 on error
	 */
	public function create($fkUser, $wecomUserId, $extra = array())
	{
		global $conf;

		$now = dol_now();
		$sql = "INSERT INTO ".$this->db->prefix()."wecom_user_map";
		$sql .= " (entity, fk_user, wecom_userid, wecom_unionid, wecom_openid, wecom_department_ids, status, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $fkUser).", '".$this->db->escape($wecomUserId)."',";
		$sql .= " ".(empty($extra['wecom_unionid']) ? 'NULL' : "'".$this->db->escape($extra['wecom_unionid'])."'").",";
		$sql .= " ".(empty($extra['wecom_openid']) ? 'NULL' : "'".$this->db->escape($extra['wecom_openid'])."'").",";
		$sql .= " ".(empty($extra['wecom_department_ids']) ? 'NULL' : "'".$this->db->escape($extra['wecom_department_ids'])."'").",";
		$sql .= " ".self::STATUS_ACTIVE.", '".$this->db->idate($now)."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$this->id = $this->db->last_insert_id($this->db->prefix()."wecom_user_map");
		return $this->id;
	}

	/**
	 * Update mapping fields.
	 *
	 * @param	array	$extra	Optional fields to update (wecom_unionid, wecom_openid, wecom_department_ids, status)
	 * @return	int				1 on success, <0 on error
	 */
	public function update($extra = array())
	{
		$sets = array();
		foreach (array('wecom_unionid', 'wecom_openid', 'wecom_department_ids') as $field) {
			if (array_key_exists($field, $extra)) {
				$sets[] = $field." = ".(empty($extra[$field]) ? 'NULL' : "'".$this->db->escape((string) $extra[$field])."'");
			}
		}
		if (array_key_exists('status', $extra)) {
			$sets[] = "status = ".((int) $extra['status']);
		}
		if (empty($sets) || empty($this->id)) {
			return -1;
		}
		$sql = "UPDATE ".$this->db->prefix()."wecom_user_map SET ".implode(', ', $sets)." WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		return $resql ? 1 : -1;
	}

	/**
	 * Load all mappings (wecom_userid -> fk_user) for current entity.
	 *
	 * @return	array<string,int>	or empty array on error
	 */
	public function loadAllByWeComUserId()
	{
		global $conf;

		$sql = "SELECT rowid, fk_user, wecom_userid, wecom_department_ids, status";
		$sql .= " FROM ".$this->db->prefix()."wecom_user_map";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return array();
		}
		$result = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$result[$obj->wecom_userid] = array('rowid' => $obj->rowid, 'fk_user' => $obj->fk_user, 'status' => $obj->status);
		}
		return $result;
	}
}
