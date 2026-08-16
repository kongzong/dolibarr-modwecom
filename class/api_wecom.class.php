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

use Luracast\Restler\RestException;

/**
 * \file    htdocs/custom/wecom/class/api_wecom.class.php
 * \ingroup wecom
 * \brief   REST API of the WeCom module (spec §24). Never exposes secrets.
 */

dol_include_once('/wecom/class/wecomapi.class.php');
dol_include_once('/wecom/class/wecomsync.class.php');
dol_include_once('/wecom/class/wecomusermap.class.php');
dol_include_once('/wecom/class/wecomcontactmap.class.php');

/**
 * API class for WeCom module
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Wecom extends DolibarrApi
{
	/**
	 * @var DoliDB $db Database object
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @url GET /
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * Get WeCom module status
	 *
	 * @url	GET status
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function status()
	{
		global $conf;

		if (!DolibarrApiAccess::$user->hasRight('wecom', 'read')) {
			throw new RestException(403);
		}

		$result = array();
		$result['module'] = 'modWeCom';
		$result['version'] = '0.1.0';
		$result['enabled'] = (isModEnabled('wecom') ? 1 : 0);
		$result['configured'] = (getDolGlobalString('WECOM_CORP_ID') ? 1 : 0);
		$result['entity'] = $conf->entity;

		$sql = "SELECT token_expires_at, last_sync_users, last_sync_contacts";
		$sql .= ", (SELECT COUNT(*) FROM ".$this->db->prefix()."wecom_user_map) AS nbusermaps";
		$sql .= ", (SELECT COUNT(*) FROM ".$this->db->prefix()."wecom_contact_map WHERE status = 1) AS nbcontactmaps";
		$sql .= " FROM ".$this->db->prefix()."wecom_config WHERE entity = ".((int) $conf->entity);
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$result['token_valid'] = (!empty($obj->token_expires_at) && $this->db->jdate($obj->token_expires_at) > dol_now()) ? 1 : 0;
			$result['last_sync_users'] = $obj->last_sync_users;
			$result['last_sync_contacts'] = $obj->last_sync_contacts;
			$result['nb_user_mappings'] = (int) $obj->nbusermaps;
			$result['nb_contact_mappings'] = (int) $obj->nbcontactmaps;
		}
		return $result;
	}

	/**
	 * Trigger a user (employee) synchronization
	 *
	 * @url	POST sync/users
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function syncUsers()
	{
		if (!DolibarrApiAccess::$user->hasRight('wecom', 'sync')) {
			throw new RestException(403);
		}
		$sync = new WeComSync($this->db);
		$stats = $sync->syncUsers();
		return array('stats' => $stats, 'errors' => $sync->errors);
	}

	/**
	 * Trigger an external contact synchronization
	 *
	 * @url	POST sync/external-contacts
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function syncExternalContacts()
	{
		if (!DolibarrApiAccess::$user->hasRight('wecom', 'sync')) {
			throw new RestException(403);
		}
		$sync = new WeComSync($this->db);
		$stats = $sync->syncExternalContacts();
		return array('stats' => $stats, 'errors' => $sync->errors);
	}

	/**
	 * List WeCom user mappings
	 *
	 * @url	GET users
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function getUsers()
	{
		if (!DolibarrApiAccess::$user->hasRight('wecom', 'read')) {
			throw new RestException(403);
		}
		$sql = "SELECT m.wecom_userid, m.wecom_department_ids, m.status, u.rowid AS fk_user, u.login, u.lastname, u.firstname, u.email";
		$sql .= " FROM ".$this->db->prefix()."wecom_user_map AS m";
		$sql .= " LEFT JOIN ".$this->db->prefix()."user AS u ON u.rowid = m.fk_user";
		$sql .= " WHERE m.entity = ".(int) $this->entityOf();
		return $this->collect($sql);
	}

	/**
	 * List WeCom external contact mappings
	 *
	 * @url	GET contacts
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function getContacts()
	{
		if (!DolibarrApiAccess::$user->hasRight('wecom', 'read')) {
			throw new RestException(403);
		}
		$sql = "SELECT m.rowid, m.external_userid, m.wecom_type, m.wecom_name, m.wecom_corp_name, m.owner_wecom_userid, m.status, m.tms";
		$sql .= ", s.rowid AS fk_soc, s.nom AS soc_name";
		$sql .= " FROM ".$this->db->prefix()."wecom_contact_map AS m";
		$sql .= " LEFT JOIN ".$this->db->prefix()."societe AS s ON s.rowid = m.fk_soc";
		$sql .= " WHERE m.entity = ".(int) $this->entityOf();
		return $this->collect($sql);
	}

	/**
	 * Get one WeCom external contact mapping by rowid
	 *
	 * @url	GET contacts/{id}
	 *
	 * @param	int		$id	Mapping rowid
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function getContact($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('wecom', 'read')) {
			throw new RestException(403);
		}
		$map = new WeComContactMap($this->db);
		if ($map->fetchByRowId((int) $id) <= 0) {
			throw new RestException(404, 'Mapping not found');
		}
		return array(
			'rowid' => (int) $map->id,
			'external_userid' => $map->external_userid,
			'fk_soc' => $map->fk_soc ? (int) $map->fk_soc : null,
			'fk_contact' => $map->fk_contact ? (int) $map->fk_contact : null,
			'wecom_type' => $map->wecom_type,
			'wecom_state' => $map->wecom_state,
			'wecom_name' => $map->wecom_name,
			'wecom_corp_name' => $map->wecom_corp_name,
			'owner_wecom_userid' => $map->owner_wecom_userid,
			'status' => (int) $map->status,
		);
	}

	/**
	 * Send an application text message
	 *
	 * Body: { "content": "...", "wecom_userid": "ray" } or { "content": "...", "fk_soc": 123 }
	 * (fk_soc resolves to the mapping owner of that thirdparty)
	 *
	 * @url	POST messages
	 *
	 * @param	array	$request_data	Body
	 * @throws RestException 403 Not allowed
	 * @throws RestException 400 Bad parameters
	 * @throws RestException 500 WeCom API error
	 */
	public function postMessages($request_data = null)
	{
		if (!DolibarrApiAccess::$user->hasRight('wecom', 'message')) {
			throw new RestException(403);
		}
		$content = isset($request_data['content']) ? trim((string) $request_data['content']) : '';
		if ($content === '') {
			throw new RestException(400, 'content is required');
		}

		$wecomUserId = isset($request_data['wecom_userid']) ? trim((string) $request_data['wecom_userid']) : '';
		if ($wecomUserId === '' && isset($request_data['fk_soc']) && (int) $request_data['fk_soc'] > 0) {
			// Resolve the sales owner of the mapped external contacts of this thirdparty
			$sql = "SELECT owner_wecom_userid FROM ".$this->db->prefix()."wecom_contact_map";
			$sql .= " WHERE fk_soc = ".((int) $request_data['fk_soc'])." AND status = 1 AND owner_wecom_userid IS NOT NULL";
			$sql .= " ORDER BY tms DESC LIMIT 1";
			$resql = $this->db->query($sql);
			if ($resql && ($obj = $this->db->fetch_object($resql))) {
				$wecomUserId = $obj->owner_wecom_userid;
			}
		}
		if ($wecomUserId === '') {
			throw new RestException(400, 'wecom_userid or fk_soc is required');
		}

		try {
			$api = new WeComApi($this->db);
			$result = $api->sendApplicationMessage($wecomUserId, $content);
			return array('success' => 1, 'wecom_userid' => $wecomUserId, 'response' => $result);
		} catch (WeComApiException $e) {
			throw new RestException(500, $e->getMessage().' (errcode='.$e->getErrorCode().')');
		}
	}

	/**
	 * List recent webhook events
	 *
	 * @url	GET events
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function getEvents()
	{
		if (!DolibarrApiAccess::$user->hasRight('wecom', 'read')) {
			throw new RestException(403);
		}
		$sql = "SELECT rowid, event_id, event_type, event_time, process_status, process_message, retry_count, date_creation";
		$sql .= " FROM ".$this->db->prefix()."wecom_event_log";
		$sql .= " WHERE entity = ".(int) $this->entityOf();
		$sql .= " ORDER BY date_creation DESC LIMIT 100";
		return $this->collect($sql);
	}

	/**
	 * Run a select and collect all rows.
	 *
	 * @param	string	$sql	SQL
	 * @return	array<int,array<string,mixed>>
	 */
	protected function collect($sql)
	{
		$result = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, 'SQL error');
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$result[] = (array) $obj;
		}
		return $result;
	}

	/**
	 * Current entity.
	 *
	 * @return	int
	 */
	protected function entityOf()
	{
		global $conf;
		return (int) $conf->entity;
	}
}
