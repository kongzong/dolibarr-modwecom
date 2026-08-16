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
 * \file    htdocs/custom/wecom/class/wecomsync.class.php
 * \ingroup wecom
 * \brief   Synchronization engine: WeCom departments/users -> Dolibarr (one-way, idempotent).
 */

if (function_exists('dol_include_once')) {
	dol_include_once('/wecom/class/wecomapi.class.php');
	dol_include_once('/wecom/class/wecomusermap.class.php');
	dol_include_once('/wecom/class/wecomcontactmap.class.php');
} else {
	require_once __DIR__.'/wecomapi.class.php';
	require_once __DIR__.'/wecomusermap.class.php';
	require_once __DIR__.'/wecomcontactmap.class.php';
}

/**
 * WeCom synchronization engine
 *
 * Direction is always WeCom -> Dolibarr (spec §9.1). Never deletes Dolibarr
 * groups/users; department deletion only invalidates the mapping (spec §11).
 */
class WeComSync
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * @var WeComApi API client
	 */
	protected $api;

	/**
	 * @var array<string> Errors of the last run
	 */
	public $errors = array();

	/**
	 * Constructor
	 *
	 * @param	DoliDB		$db		Database handler
	 * @param	WeComApi	$api	Optional injected client (tests inject a mock)
	 */
	public function __construct($db, $api = null)
	{
		$this->db = $db;
		$this->api = $api ? $api : new WeComApi($db);
	}

	/**
	 * Synchronize departments into Dolibarr user groups.
	 *
	 * @return array{created:int, updated:int, skipped:int, failed:int}
	 */
	public function syncDepartments()
	{
		global $conf, $user;

		$stats = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0);

		try {
			$response = $this->api->getDepartments();
		} catch (WeComApiException $e) {
			$this->errors[] = $e->getMessage();
			$stats['failed'] = 1;
			return $stats;
		}

		$departments = isset($response['department']) && is_array($response['department']) ? $response['department'] : array();
		if (empty($departments)) {
			$this->errors[] = 'No department returned by WeCom';
			return $stats;
		}

		require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';

		// Load existing mappings (idempotency base)
		$map = $this->loadDepartmentMap();

		foreach ($departments as $d) {
			$wecomDeptId = (int) $d['id'];
			$name = isset($d['name']) ? dol_trunc(trim($d['name']), 255) : '';
			if ($wecomDeptId <= 0 || $name === '') {
				$stats['failed']++;
				continue;
			}

			$existing = isset($map[$wecomDeptId]) ? $map[$wecomDeptId] : null;

			if ($existing && $existing['fk_usergroup'] > 0) {
				// Update group label if changed
				if ($existing['wecom_name'] !== $name) {
					$group = new UserGroup($this->db);
					if ($group->fetch($existing['fk_usergroup']) > 0 && $group->name != $name) {
						$group->name = $name;
						$result = $group->update();
						if ($result < 0) {
							$this->errors[] = 'Failed to update group for department '.$name;
							$stats['failed']++;
							continue;
						}
					}
					$this->updateDepartmentMapRow($existing['rowid'], $name, $d);
				}
				$stats['updated']++;
			} else {
				// Create a new Dolibarr user group
				$group = new UserGroup($this->db);
				$group->name = $name;
				$group->entity = $conf->entity;
				$newid = $group->create();
				if ($newid < 0) {
					$this->errors[] = 'Failed to create group for department '.$name.': '.$group->error;
					$stats['failed']++;
					continue;
				}
				$this->insertDepartmentMap($wecomDeptId, $newid, $name, $d);
				$stats['created']++;
			}
		}

		$this->markMissingDepartmentsInactive($departments);

		return $stats;
	}

	/**
	 * Synchronize WeCom users into Dolibarr users + llx_wecom_user_map.
	 *
	 * Matching order (spec §35 idempotency):
	 * 1. existing mapping on wecom_userid -> update
	 * 2. Dolibarr user with same email -> link without modifying its login
	 * 3. otherwise -> create a new Dolibarr user (login = wecom_userid)
	 *
	 * @return array{created:int, updated:int, skipped:int, failed:int}
	 */
	public function syncUsers()
	{
		global $conf, $user;

		$stats = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0);

		try {
			$response = $this->api->getDepartments();
		} catch (WeComApiException $e) {
			$this->errors[] = $e->getMessage();
			$stats['failed'] = 1;
			return $stats;
		}

		$departments = isset($response['department']) && is_array($response['department']) ? $response['department'] : array();
		if (empty($departments)) {
			$this->errors[] = 'No department returned by WeCom';
			return $stats;
		}

		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

		$mapLoader = new WeComUserMap($this->db);
		$maps = $mapLoader->loadAllByWeComUserId();
		$seen = array(); // wecom_userid processed during this run

		foreach ($departments as $d) {
			$departmentId = (int) $d['id'];
			if ($departmentId <= 0) {
				continue;
			}
			try {
				$userResponse = $this->api->getUsers($departmentId, false);
			} catch (WeComApiException $e) {
				$this->errors[] = 'getUsers department '.$departmentId.': '.$e->getMessage();
				$stats['failed']++;
				continue;
			}
			$wecomUsers = isset($userResponse['userlist']) && is_array($userResponse['userlist']) ? $userResponse['userlist'] : array();

			foreach ($wecomUsers as $wu) {
				$wecomUserId = isset($wu['userid']) ? trim($wu['userid']) : '';
				if ($wecomUserId === '' || isset($seen[$wecomUserId])) {
					$stats['skipped']++;
					continue;
				}
				// WeCom returns placeholder userids (md5 of empty string + variants) for users
				// whose real account cannot be exposed (outside app scope): skip them,
				// they cannot be used for OAuth login or messaging.
				if (strncmp($wecomUserId, 'd41d8cd98f00b204e9800998ecf8427e', 32) === 0) {
					$stats['skipped']++;
					continue;
				}
				$seen[$wecomUserId] = 1;

				$result = $this->syncOneUser($wu, $maps, $stats);
				if ($result < 0) {
					$stats['failed']++;
				}
			}
		}

		// Record last sync time
		$this->setLastSync('last_sync_users');

		return $stats;
	}

	/**
	 * Sync a single WeCom user (create link, link by email, or create user).
	 *
	 * @param	array	$wu			WeCom user payload
	 * @param	array	$maps		Existing mappings wecom_userid -> array
	 * @param	array	$stats		Stats array (updated in place)
	 * @return	int					1 ok, <0 failed
	 */
	protected function syncOneUser($wu, &$maps, &$stats)
	{
		global $conf, $user;

		$wecomUserId = trim($wu['userid']);
		$email = isset($wu['email']) ? dol_trunc(trim($wu['email']), 255) : '';
		$name = isset($wu['name']) ? dol_trunc(trim($wu['name']), 100) : '';
		$mobile = isset($wu['mobile']) ? dol_trunc(trim($wu['mobile']), 20) : '';
		$departmentIds = isset($wu['department']) && is_array($wu['department']) ? implode(',', array_map('intval', $wu['department'])) : '';
		$extra = array(
			'wecom_department_ids' => $departmentIds,
		);

		$mapObj = new WeComUserMap($this->db);

		// 1. Existing mapping: only refresh mapping fields (do not overwrite Dolibarr user data)
		if (isset($maps[$wecomUserId])) {
			$mapObj->id = $maps[$wecomUserId]['rowid'];
			$result = $mapObj->update($extra);
			if ($result < 0) {
				$this->errors[] = 'Failed to update mapping for '.$wecomUserId;
				return -1;
			}
			$stats['updated']++;
			return 1;
		}

		$fkUser = 0;

		// 2. Try to link an existing Dolibarr user by email (exact match only, no fuzzy merge - spec §15)
		if ($email !== '') {
			$sql = "SELECT rowid FROM ".$this->db->prefix()."user";
			$sql .= " WHERE email = '".$this->db->escape($email)."' AND entity IN (0, ".((int) $conf->entity).")";
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) {
					$fkUser = (int) $obj->rowid;
				}
			}
		}

		// 3. No match: create a new Dolibarr user (login = wecom_userid, no password - OAuth login will come later)
		if ($fkUser <= 0) {
			$duser = new User($this->db);
			// Sanitize login: keep alnum and . _ -, then truncate to Dolibarr login max length
			$login = preg_replace('/[^a-zA-Z0-9._-]/', '_', $wecomUserId);
			$duser->login = dol_trunc($login, 24);
			$duser->lastname = ($name !== '' ? $name : $wecomUserId);
			$duser->email = $email;
			$duser->user_mobile = $mobile;
			$duser->employee = 1;
			$duser->status = 1;
			$duser->entity = $conf->entity;
			$newid = $duser->create($user, 0); // no password set
			if ($newid < 0) {
				// Login may already exist (e.g. WeCom returns the same placeholder userid
				// d41d8cd98f00b204e9800998ecf8427e... for users outside the app scope):
				// recover by linking to the existing user with the same login.
				$existing = $duser->fetch('', $duser->login);
				if ($existing > 0) {
					$fkUser = (int) $duser->id;
					$stats['skipped']++;
				} else {
					$this->errors[] = 'Failed to create Dolibarr user for '.$wecomUserId.': '.$duser->error;
					return -1;
				}
			} else {
				$fkUser = (int) $newid;
				$stats['created']++;
			}
		} else {
			$stats['updated']++;
		}

		$result = $mapObj->create($fkUser, $wecomUserId, $extra);
		if ($result < 0) {
			$this->errors[] = 'Failed to create mapping for '.$wecomUserId;
			return -1;
		}
		$maps[$wecomUserId] = array('rowid' => $result, 'fk_user' => $fkUser, 'status' => 1);

		// Add user to the groups mapped from his WeCom departments (idempotent)
		$this->addUserToDepartmentGroups($fkUser, $departmentIds);

		return 1;
	}

	/**
	 * Add a user to the Dolibarr groups mapped from WeCom department ids.
	 *
	 * @param	int		$fkUser			Dolibarr user id
	 * @param	string	$departmentIds	Comma separated WeCom department ids
	 * @return	void
	 */
	protected function addUserToDepartmentGroups($fkUser, $departmentIds)
	{
		if (trim((string) $departmentIds) === '') {
			return;
		}
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

		$duser = new User($this->db);
		if ($duser->fetch($fkUser) <= 0) {
			return;
		}

		$deptMap = $this->loadDepartmentMap();
		foreach (explode(',', $departmentIds) as $deptId) {
			$deptId = (int) trim($deptId);
			if ($deptId <= 0 || empty($deptMap[$deptId]) || $deptMap[$deptId]['fk_usergroup'] <= 0) {
				continue;
			}
			$fkGroup = (int) $deptMap[$deptId]['fk_usergroup'];
			// Check membership directly (usergroup_linked is not loaded by User::fetch)
			$sql = "SELECT rowid FROM ".$this->db->prefix()."usergroup_user";
			$sql .= " WHERE fk_user = ".((int) $fkUser)." AND fk_usergroup = ".$fkGroup;
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) == 0) {
				$sql = "INSERT INTO ".$this->db->prefix()."usergroup_user (fk_user, fk_usergroup)";
				$sql .= " VALUES (".((int) $fkUser).", ".$fkGroup.")";
				$this->db->query($sql);
			}
		}
	}

	/**
	 * Load department mappings wecom_department_id -> row
	 *
	 * @return	array<int,array{rowid:int, fk_usergroup:int, wecom_name:string}>
	 */
	protected function loadDepartmentMap()
	{
		global $conf;

		$sql = "SELECT rowid, wecom_department_id, fk_usergroup, wecom_name";
		$sql .= " FROM ".$this->db->prefix()."wecom_department_map";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$resql = $this->db->query($sql);
		$result = array();
		if (!$resql) {
			return $result;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$result[(int) $obj->wecom_department_id] = array(
				'rowid' => (int) $obj->rowid,
				'fk_usergroup' => (int) $obj->fk_usergroup,
				'wecom_name' => (string) $obj->wecom_name,
			);
		}
		return $result;
	}

	/**
	 * Insert a department mapping row.
	 *
	 * @param	int		$wecomDeptId	WeCom department id
	 * @param	int		$fkUsergroup	Dolibarr group id
	 * @param	string	$name			Department name
	 * @param	array	$d				WeCom department payload
	 * @return	void
	 */
	protected function insertDepartmentMap($wecomDeptId, $fkUsergroup, $name, $d)
	{
		global $conf;

		$now = dol_now();
		$sql = "INSERT INTO ".$this->db->prefix()."wecom_department_map";
		$sql .= " (entity, wecom_department_id, fk_usergroup, wecom_name, wecom_parent_id, wecom_order, status, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", ".((int) $wecomDeptId).", ".((int) $fkUsergroup).", '".$this->db->escape($name)."',";
		$sql .= " ".(empty($d['parentid']) ? 'NULL' : (int) $d['parentid']).",";
		$sql .= " ".(empty($d['order']) ? 'NULL' : (int) $d['order']).", 1, '".$this->db->idate($now)."')";
		$this->db->query($sql);
	}

	/**
	 * Update the denormalized name/payload of a department mapping row.
	 *
	 * @param	int		$rowid	Mapping row id
	 * @param	string	$name	New name
	 * @param	array	$d		WeCom department payload
	 * @return	void
	 */
	protected function updateDepartmentMapRow($rowid, $name, $d)
	{
		$sql = "UPDATE ".$this->db->prefix()."wecom_department_map SET";
		$sql .= " wecom_name = '".$this->db->escape($name)."',";
		$sql .= " wecom_parent_id = ".(empty($d['parentid']) ? 'NULL' : (int) $d['parentid']).",";
		$sql .= " wecom_order = ".(empty($d['order']) ? 'NULL' : (int) $d['order']);
		$sql .= " WHERE rowid = ".((int) $rowid);
		$this->db->query($sql);
	}

	/**
	 * Mark mappings of departments no longer returned by WeCom as inactive.
	 * Never deletes Dolibarr groups (spec §11).
	 *
	 * @param	array	$departments	Current departments from WeCom
	 * @return	void
	 */
	protected function markMissingDepartmentsInactive($departments)
	{
		$alive = array();
		foreach ($departments as $d) {
			$alive[(int) $d['id']] = 1;
		}
		foreach ($this->loadDepartmentMap() as $deptId => $row) {
			if (!isset($alive[$deptId])) {
				$sql = "UPDATE ".$this->db->prefix()."wecom_department_map SET status = 0 WHERE rowid = ".((int) $row['rowid']);
				$this->db->query($sql);
			}
		}
	}

	/**
	 * Synchronize WeCom external contacts into Dolibarr thirdparties/contacts + llx_wecom_contact_map.
	 *
	 * Matching order (spec §15):
	 * 1. existing mapping on external_userid -> refresh WeCom fields only
	 * 2. no mapping -> create a new ThirdParty + Contact (NO fuzzy merge of existing customers)
	 *
	 * @param	callable|null	$progress	Optional callback($message, $done, $total) for UI feedback
	 * @return array{created:int, updated:int, skipped:int, failed:int}
	 */
	public function syncExternalContacts($progress = null)
	{
		$stats = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0);

		// Owners: all mapped active WeCom users
		$mapLoader = new WeComUserMap($this->db);
		$owners = array();
		foreach ($mapLoader->loadAllByWeComUserId() as $wecomUserId => $row) {
			if ($row['status']) {
				$owners[] = $wecomUserId;
			}
		}
		if (empty($owners)) {
			$this->errors[] = 'No mapped WeCom user found, run user sync first';
			$stats['failed'] = 1;
			return $stats;
		}

		$mapObj = new WeComContactMap($this->db);
		$seen = array();
		$consecutivePermErrors = 0;
		$doneOwners = 0;
		$totalOwners = count($owners);

		foreach ($owners as $ownerId) {
			if (is_callable($progress)) {
				call_user_func($progress, 'Owner '.$ownerId, $doneOwners, $totalOwners);
			}
			$doneOwners++;
			try {
				$listResponse = $this->api->getExternalContacts($ownerId);
				$consecutivePermErrors = 0;
			} catch (WeComApiException $e) {
				// 84061 = no external contact permission for this user, not fatal
				if ($e->getErrorCode() == 84061) {
					continue;
				}
				// Permission-level errors (48002...) repeated: abort early with guidance
				if (in_array($e->getErrorCode(), array(48002, 60011, 60020))) {
					$consecutivePermErrors++;
					$this->errors[] = 'getExternalContacts '.$ownerId.': '.$e->getMessage();
					if ($consecutivePermErrors >= 3) {
						$this->errors[] = 'Aborted: permission error repeated. Check that the app is declared as an allowed API caller in WeCom admin (客户联系 -> API -> 可调用接口的应用) and trusted IP is configured.';
						break;
					}
					continue;
				}
				$this->errors[] = 'getExternalContacts '.$ownerId.': '.$e->getMessage();
				$stats['failed']++;
				continue;
			}
			$externalIds = isset($listResponse['external_userid']) && is_array($listResponse['external_userid']) ? $listResponse['external_userid'] : array();

			foreach ($externalIds as $externalUserId) {
				if (isset($seen[$externalUserId])) {
					$stats['skipped']++;
					continue;
				}
				$seen[$externalUserId] = 1;

				if (is_callable($progress)) {
					call_user_func($progress, $externalUserId, $doneOwners, $totalOwners);
				}

				try {
					$detail = $this->api->getExternalContactDetail($externalUserId);
				} catch (WeComApiException $e) {
					$this->errors[] = 'getExternalContactDetail '.$externalUserId.': '.$e->getMessage();
					$stats['failed']++;
					continue;
				}

				$result = $this->syncOneExternalContact($externalUserId, $ownerId, $detail, $stats);
				if ($result < 0) {
					$stats['failed']++;
				}
			}
		}

		$this->setLastSync('last_sync_contacts');

		return $stats;
	}

	/**
	 * Sync one external contact.
	 *
	 * @param	string	$externalUserId	WeCom external_userid
	 * @param	string	$ownerId		WeCom userid of the sales owner
	 * @param	array	$detail			Detail payload from /externalcontact/get
	 * @param	array	$stats			Stats array (updated in place)
	 * @return	int						1 ok, <0 failed
	 */
	protected function syncOneExternalContact($externalUserId, $ownerId, $detail, &$stats)
	{
		$external = isset($detail['external_contact']) && is_array($detail['external_contact']) ? $detail['external_contact'] : array();
		$followUsers = isset($detail['follow_user']) && is_array($detail['follow_user']) ? $detail['follow_user'] : array();

		$name = isset($external['name']) ? dol_trunc(trim($external['name']), 255) : '';
		$corpName = isset($external['corp_name']) ? dol_trunc(trim($external['corp_name']), 255) : '';
		$position = isset($external['position']) ? dol_trunc(trim($external['position']), 128) : '';
		$avatar = isset($external['avatar']) ? dol_trunc($external['avatar'], 512) : '';
		$type = isset($external['type']) ? (int) $external['type'] : 0;
		$state = '';
		$tags = array();
		foreach ($followUsers as $fu) {
			if (isset($fu['userid']) && $fu['userid'] == $ownerId) {
				if (!empty($fu['state'])) {
					$state = dol_trunc((string) $fu['state'], 32);
				}
				// Customer tags applied by the owner (V0.2): "group/tag" entries
				if (!empty($fu['tags']) && is_array($fu['tags'])) {
					foreach ($fu['tags'] as $tag) {
						$tagName = isset($tag['tag_name']) ? trim((string) $tag['tag_name']) : '';
						if ($tagName !== '') {
							$tags[] = isset($tag['group_name']) && trim((string) $tag['group_name']) !== ''
								? trim((string) $tag['group_name']).'/'.$tagName
								: $tagName;
						}
					}
				}
				break;
			}
		}
		$extra = array(
			'wecom_type' => $type,
			'wecom_state' => $state,
			'wecom_name' => $name,
			'wecom_avatar' => $avatar,
			'wecom_corp_name' => $corpName,
			'wecom_tags' => implode(', ', array_unique($tags)),
			'owner_wecom_userid' => $ownerId,
		);

		$mapObj = new WeComContactMap($this->db);

		// 1. Existing mapping: refresh WeCom fields only, never touch Dolibarr customer data
		$exists = $mapObj->fetchByExternalUserId($externalUserId);
		if ($exists > 0) {
			$result = $mapObj->updateWeComFields(array_merge($extra, array('status' => WeComContactMap::STATUS_ACTIVE)));
			if ($result < 0) {
				$this->errors[] = 'Failed to update contact mapping for '.$externalUserId;
				return -1;
			}
			$stats['updated']++;
			return 1;
		}

		// 2. No mapping: create ThirdParty + Contact (no fuzzy matching - spec §15)
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

		$actingUser = $this->currentUser();
		if (!$actingUser) {
			$this->errors[] = 'No Dolibarr user in context, cannot create thirdparty';
			return -1;
		}

		$socName = ($corpName !== '' ? $corpName : ($name !== '' ? $name : $externalUserId));
		$soc = new Societe($this->db);
		$soc->name = $socName;
		$soc->client = 0; // prospect until validated by a human
		$soc->status = 1;
		$fkSoc = $soc->create($actingUser);
		if ($fkSoc < 0) {
			$this->errors[] = 'Failed to create thirdparty '.$socName.': '.$soc->error;
			return -1;
		}

		$fkContact = 0;
		if ($name !== '') {
			$contact = new Contact($this->db);
			$contact->socid = $fkSoc;
			$contact->lastname = $name;
			$contact->poste = $position;
			$fkContact = $contact->create($actingUser);
			if ($fkContact < 0) {
				$fkContact = 0; // contact creation is optional, mapping still valid
			}
		}

		$result = $mapObj->create($externalUserId, $fkSoc, $fkContact, $extra);
		if ($result < 0) {
			$this->errors[] = 'Failed to create contact mapping for '.$externalUserId;
			return -1;
		}
		$stats['created']++;

		return 1;
	}

	/**
	 * Current Dolibarr user for record tracking (may be null in CLI/cron contexts).
	 *
	 * @return	User|null
	 */
	protected function currentUser()
	{
		global $user;
		return isset($user) ? $user : null;
	}

	/**
	 * Entry point for the Dolibarr scheduled job (V0.2): incremental refresh of
	 * external contacts. Errors are logged, never fatal for the cron.
	 *
	 * @return	int		0
	 */
	public function doScheduledJob()
	{
		dol_syslog(__METHOD__.' started', LOG_INFO);
		try {
			$stats = $this->syncExternalContacts();
			dol_syslog(__METHOD__.' contacts: created='.$stats['created'].' updated='.$stats['updated'].' skipped='.$stats['skipped'].' failed='.$stats['failed'], LOG_INFO);
			foreach ($this->errors as $err) {
				dol_syslog(__METHOD__.' error: '.$err, LOG_WARNING);
			}
		} catch (Exception $e) {
			dol_syslog(__METHOD__.' exception: '.$e->getMessage(), LOG_ERR);
		}
		return 0;
	}

	/**
	 * Store last sync timestamp into llx_wecom_config.
	 *
	 * @param	string	$field	Column name (last_sync_users / last_sync_contacts)
	 * @return	void
	 */
	protected function setLastSync($field)
	{
		global $conf;

		// Safeguard: only known columns
		if (!in_array($field, array('last_sync_users', 'last_sync_contacts'))) {
			return;
		}
		$now = dol_now();
		$sql = "UPDATE ".$this->db->prefix()."wecom_config SET ".$field." = '".$this->db->idate($now)."'";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$this->db->query($sql);
	}
}
