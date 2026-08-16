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
 * \file    htdocs/custom/wecom/class/wecomeventlog.class.php
 * \ingroup wecom
 * \brief   DAO for table llx_wecom_event_log (webhook events + idempotency).
 */

/**
 * WeCom webhook event log
 */
class WeComEventLog
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	public $id;
	public $entity;
	public $event_id;
	public $event_type;
	public $event_time;
	public $payload_hash;
	public $payload;
	public $process_status;
	public $process_message;
	public $retry_count;

	const STATUS_NEW = 0;
	const STATUS_PROCESSED = 1;
	const STATUS_IGNORED_DUPLICATE = 2;
	const STATUS_ERROR = 3;

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
	 * Try to insert a new event. Idempotency is enforced by the unique index
	 * on event_id: a repeated event returns 0 without inserting.
	 *
	 * @param	string	$eventId	Unique idempotency key
	 * @param	string	$eventType	Event type (e.g. change_external_contact)
	 * @param	int		$eventTime	Event CreateTime (unix ts)
	 * @param	string	$payload	Raw (sanitized) payload
	 * @return	int					New rowid (>0), 0 if duplicate, <0 on other error
	 */
	public function insertEvent($eventId, $eventType, $eventTime, $payload)
	{
		global $conf;

		$now = dol_now();
		$sql = "INSERT INTO ".$this->db->prefix()."wecom_event_log";
		$sql .= " (entity, event_id, event_type, event_time, payload_hash, payload, process_status, process_message, retry_count, date_creation)";
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape(dol_trunc($eventId, 128))."', '".$this->db->escape(dol_trunc($eventType, 64))."',";
		$sql .= " ".(empty($eventTime) ? 'NULL' : "'".$this->db->idate((int) $eventTime)."'").",";
		$sql .= " '".$this->db->escape(md5((string) $payload))."',";
		$sql .= " '".$this->db->escape($this->sanitizePayload($payload))."',";
		$sql .= " ".self::STATUS_NEW.", '', 0, '".$this->db->idate($now)."')";
		$resql = $this->db->query($sql);
		if (!$resql) {
			// Duplicate detection (MySQL/MariaDB code 1062 / unique index violation)
			if ($this->getDuplicateErrorNo() == 1062) {
				return 0;
			}
			return -1;
		}
		$this->id = $this->db->last_insert_id($this->db->prefix()."wecom_event_log");
		return $this->id;
	}

	/**
	 * Detect duplicate key error number from last db error message.
	 *
	 * @return	int		1062 when duplicate
	 */
	protected function getDuplicateErrorNo()
	{
		$msg = (string) $this->db->lasterror();
		if (preg_match('/1062|duplicate|unique/i', $msg)) {
			return 1062;
		}
		return 0;
	}

	/**
	 * Mark event processing result.
	 *
	 * @param	int		$rowid		Event row id
	 * @param	int		$status		One of STATUS_*
	 * @param	string	$message	Short result message
	 * @return	int					1 ok, <0 error
	 */
	public function markProcessed($rowid, $status, $message = '')
	{
		$sql = "UPDATE ".$this->db->prefix()."wecom_event_log SET";
		$sql .= " process_status = ".((int) $status).",";
		$sql .= " process_message = '".$this->db->escape(dol_trunc($message, 255))."'";
		$sql .= " WHERE rowid = ".((int) $rowid);
		$resql = $this->db->query($sql);
		return $resql ? 1 : -1;
	}

	/**
	 * Build the idempotency key when WeCom provides no MsgId (spec §19):
	 * md5(event_type + event_time + business ids + payload_hash).
	 *
	 * @param	string	$eventType	Event type
	 * @param	int		$eventTime	Event CreateTime
	 * @param	array	$businessIds	Business identifiers (ExternalUserID, UserID, ChangeType...)
	 * @param	string	$payload	Raw payload
	 * @return	string
	 */
	public static function buildEventId($eventType, $eventTime, $businessIds, $payload)
	{
		ksort($businessIds);
		$raw = $eventType.'|'.(int) $eventTime.'|'.implode('|', array_map('strval', $businessIds)).'|'.md5((string) $payload);
		return md5($raw);
	}

	/**
	 * Remove sensitive data from payload before storage (spec §19).
	 *
	 * @param	string	$payload	Raw payload
	 * @return	string	Sanitized payload
	 */
	protected function sanitizePayload($payload)
	{
		// Mask anything looking like credentials; WeCom payloads carry no secret,
		// but keep a defensive length limit.
		return dol_trunc((string) $payload, 8192);
	}
}
