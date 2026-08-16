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
 * \file    htdocs/custom/wecom/class/wecomapi.class.php
 * \ingroup wecom
 * \brief   Unified WeCom (Enterprise WeChat) API client.
 *
 * All HTTP calls to WeCom must go through this class (spec §7, §51).
 * Never logs the Secret or the full Access Token.
 */

if (function_exists('dol_include_once')) {
	dol_include_once('/wecom/class/wecomapiexception.class.php');
} else {
	// Outside Dolibarr (unit tests)
	require_once __DIR__.'/wecomapiexception.class.php';
}

/**
 * WeCom API client
 */
class WeComApi
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * @var string WeCom API base URL
	 */
	const API_BASE = 'https://qyapi.weixin.qq.com/cgi-bin';

	/**
	 * @var int Refresh the token this many seconds before real expiry
	 */
	const TOKEN_EARLY_REFRESH = 300;

	/**
	 * @var int HTTP timeouts in seconds
	 */
	const CONNECT_TIMEOUT = 5;
	const RESPONSE_TIMEOUT = 10;

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
	 * Return module configuration (from Dolibarr constants)
	 *
	 * @return array{corp_id:string, agent_id:int, secret:string} or empty array if not configured
	 */
	public function getConfig()
	{
		$corpId = getDolGlobalString('WECOM_CORP_ID');
		$secret = getDolGlobalString('WECOM_SECRET');
		if (empty($corpId) || empty($secret)) {
			return array();
		}
		return array(
			'corp_id' => $corpId,
			'agent_id' => (int) getDolGlobalString('WECOM_AGENT_ID'),
			'secret' => $secret,
		);
	}

	/**
	 * Get a valid access token, from cache or from WeCom.
	 *
	 * @param	bool	$forceRefresh	Ignore cache and request a new token
	 * @return	string					Access token
	 * @throws	WeComApiException
	 */
	public function getAccessToken($forceRefresh = false)
	{
		global $conf;

		$config = $this->getConfig();
		if (empty($config)) {
			throw new WeComApiException('WeCom configuration is incomplete (corp id / secret missing)', -1);
		}

		if (!$forceRefresh) {
			$token = $this->getCachedToken();
			if ($token !== '') {
				return $token;
			}
		}

		$url = self::API_BASE.'/gettoken?corpid='.urlencode($config['corp_id']).'&corpsecret='.urlencode($config['secret']);
		$response = $this->httpRequest('gettoken', 'GET', $url);

		if (empty($response['access_token'])) {
			throw new WeComApiException('No access token in WeCom response', -1, 200, 'gettoken');
		}

		$this->saveCachedToken($response['access_token'], (int) $response['expires_in'], $config['corp_id']);

		return $response['access_token'];
	}

	/**
	 * Test the connection: obtain a token (fresh if requested) and call a cheap API.
	 *
	 * @param	bool	$forceRefresh	Request a new token instead of using cache
	 * @return	array{success:bool, message:string}
	 */
	public function testConnection($forceRefresh = false)
	{
		try {
			$token = $this->getAccessToken($forceRefresh);
			// gettoken itself proves credentials are valid; return summary without exposing token
			$message = 'OK - token retrieved ('.substr($token, 0, 6).'...)';
			return array('success' => true, 'message' => $message);
		} catch (WeComApiException $e) {
			return array('success' => false, 'message' => $e->getMessage().' (errcode='.$e->getErrorCode().')');
		}
	}

	/**
	 * Get the list of departments of the WeCom company.
	 *
	 * @return array
	 * @throws WeComApiException
	 */
	public function getDepartments()
	{
		return $this->callWithToken('GET', '/department/list', array());
	}

	/**
	 * Get users of a department.
	 *
	 * @param	int		$departmentId	Department id
	 * @param	bool	$fetchChild		Include child departments
	 * @return array
	 * @throws WeComApiException
	 */
	public function getUsers($departmentId, $fetchChild = true)
	{
		return $this->callWithToken('GET', '/user/list', array('department_id' => $departmentId, 'fetch_child' => $fetchChild ? 1 : 0));
	}

	/**
	 * Get details of one WeCom member (addressbook).
	 *
	 * @param	string	$wecomUserId	WeCom user id
	 * @return	array
	 * @throws	WeComApiException
	 */
	public function getUserDetail($wecomUserId)
	{
		return $this->callWithToken('GET', '/user/get', array('userid' => $wecomUserId));
	}

	/**
	 * Get the list of external contacts of a user.
	 *
	 * @param	string	$wecomUserId	WeCom user id (owner of the contacts)
	 * @return array	List of external_userid
	 * @throws WeComApiException
	 */
	public function getExternalContacts($wecomUserId)
	{
		return $this->callWithToken('GET', '/externalcontact/list', array('userid' => $wecomUserId));
	}

	/**
	 * Get detail of one external contact.
	 *
	 * @param	string	$externalUserId	External user id
	 * @return array
	 * @throws WeComApiException
	 */
	public function getExternalContactDetail($externalUserId)
	{
		return $this->callWithToken('GET', '/externalcontact/get', array('external_userid' => $externalUserId));
	}

	/**
	 * Exchange an OAuth login code for the WeCom userid (scan-code / in-app web authorization).
	 *
	 * @param	string	$code	Code provided by WeCom OAuth callback
	 * @return	string			WeCom userid (throws if not a member or invalid code)
	 * @throws	WeComApiException
	 */
	public function getUserIdByOAuthCode($code)
	{
		$response = $this->callWithToken('GET', '/auth/getuserinfo', array('code' => $code));
		if (empty($response['userid'])) {
			// external contacts return external_userid; members return userid (spec §12: members only)
			$err = isset($response['external_userid']) ? 'OAuth code belongs to an external user, only members can log in' : 'No userid in OAuth response';
			throw new WeComApiException($err, isset($response['errcode']) ? (int) $response['errcode'] : -1, 200, 'auth/getuserinfo');
		}
		return (string) $response['userid'];
	}

	/**
	 * Send an application message to one WeCom user.
	 *
	 * @param	string	$wecomUserId	Recipient WeCom user id (@all allowed)
	 * @param	string	$content		Message content
	 * @param	string	$msgtype		'text' or 'markdown' (V0.2)
	 * @return array
	 * @throws WeComApiException
	 */
	public function sendApplicationMessage($wecomUserId, $content, $msgtype = 'text')
	{
		$config = $this->getConfig();
		if (!in_array($msgtype, array('text', 'markdown'))) {
			throw new WeComApiException('Unsupported msgtype '.$msgtype, -1);
		}
		$body = array(
			'touser' => $wecomUserId,
			'msgtype' => $msgtype,
			'agentid' => $config['agent_id'],
			$msgtype => array('content' => $content),
		);
		return $this->callWithToken('POST', '/message/send', array(), $body);
	}

	/**
	 * Call an API endpoint with the access token, refreshing token on auth errors.
	 *
	 * @param	string	$method		'GET' or 'POST'
	 * @param	string	$path		API path (e.g. '/department/list')
	 * @param	array	$query		Query parameters
	 * @param	array	$body		POST body (JSON encoded)
	 * @return	array				Decoded JSON response
	 * @throws	WeComApiException
	 */
	protected function callWithToken($method, $path, $query = array(), $body = array())
	{
		$token = $this->getAccessToken();
		try {
			return $this->request($method, $path, $query, $body, $token);
		} catch (WeComApiException $e) {
			if (in_array($e->getErrorCode(), array(40014, 42001))) {
				// invalid/expired access token: refresh once and retry once
				$token = $this->getAccessToken(true);
				return $this->request($method, $path, $query, $body, $token, 1);
			}
			throw $e;
		}
	}

	/**
	 * Build URL and perform one authenticated request.
	 *
	 * @param	string	$method			'GET' or 'POST'
	 * @param	string	$path			API path
	 * @param	array	$query			Query parameters
	 * @param	array	$body			POST body
	 * @param	string	$token			Access token
	 * @param	int		$retryCount	Retries already done
	 * @return	array
	 * @throws	WeComApiException
	 */
	protected function request($method, $path, $query, $body, $token, $retryCount = 0)
	{
		$url = self::API_BASE.$path.'?access_token='.urlencode($token);
		foreach ($query as $key => $value) {
			$url .= '&'.urlencode($key).'='.urlencode((string) $value);
		}
		$param = '';
		$postorget = 'GET';
		if ($method == 'POST') {
			$postorget = 'POST';
			$param = json_encode($body);
		}
		return $this->httpRequest($path, $postorget, $url, $param, $retryCount);
	}

	/**
	 * Perform one HTTP request to WeCom and decode/validate the JSON answer.
	 *
	 * @param	string	$path			API name for logs/errors (no query string)
	 * @param	string	$postorget		'GET' or 'POST'
	 * @param	string	$url			Full URL (may contain secret: never log $url)
	 * @param	string	$param			POST body
	 * @param	int		$retryCount	Retries already done
	 * @return	array					Decoded response
	 * @throws	WeComApiException
	 */
	protected function httpRequest($path, $postorget, $url, $param = '', $retryCount = 0)
	{
		require_once DOL_DOCUMENT_ROOT.'/core/lib/geturl.lib.php';

		$response = false;
		$attempt = 0;
		$maxAttempts = ($retryCount > 0) ? 1 : 2; // one fast retry on transport failure only

		while ($attempt < $maxAttempts) {
			$attempt++;
			$result = getURLContent($url, $postorget, $param, 1, array('Accept: application/json'), array('http', 'https'), 0, -1, self::CONNECT_TIMEOUT, self::RESPONSE_TIMEOUT);
			if (isset($result['http_code']) && $result['http_code'] > 0) {
				$response = $result;
				break;
			}
			$response = false; // transport failure (network down, timeout...)
		}

		if ($response === false) {
			dol_syslog('WeComApi::httpRequest transport failure path='.$path.' attempts='.$attempt, LOG_WARNING);
			throw new WeComApiException('HTTP request to WeCom failed (network error or timeout)', -1, 0, $path, $attempt - 1);
		}

		$httpCode = (int) $response['http_code'];
		$raw = isset($response['content']) ? $response['content'] : '';
		$data = json_decode($raw, true);

		if ($httpCode >= 500) {
			// 5xx: no retry policy here beyond loop above (transport only); report
			dol_syslog('WeComApi::httpRequest http '.$httpCode.' path='.$path, LOG_WARNING);
			throw new WeComApiException('WeCom server error', -1, $httpCode, $path);
		}

		if (!is_array($data)) {
			dol_syslog('WeComApi::httpRequest invalid JSON path='.$path.' http='.$httpCode, LOG_WARNING);
			throw new WeComApiException('Invalid JSON response from WeCom', -1, $httpCode, $path);
		}

		if (isset($data['errcode']) && $data['errcode'] != 0) {
			$errcode = (int) $data['errcode'];
			$errmsg = isset($data['errmsg']) ? $data['errmsg'] : '';
			dol_syslog('WeComApi::httpRequest error errcode='.$errcode.' path='.$path.' retried='.($attempt - 1), LOG_WARNING);
			throw new WeComApiException('WeCom API error: '.$errmsg, $errcode, $httpCode, $path, $attempt - 1);
		}

		return $data;
	}

	/**
	 * Read cached token from llx_wecom_config for current entity.
	 *
	 * @return	string	Token or ''
	 */
	protected function getCachedToken()
	{
		global $conf;

		$sql = "SELECT access_token AS tk, token_expires_at AS exp FROM ".$this->db->prefix()."wecom_config WHERE entity = ".((int) $conf->entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return '';
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj || empty($obj->tk)) {
			return '';
		}
		$now = dol_now();
		if (!empty($obj->exp) && ($this->db->jdate($obj->exp) - self::TOKEN_EARLY_REFRESH) > $now) {
			return $obj->tk;
		}
		return '';
	}

	/**
	 * Save token into llx_wecom_config (one row per entity).
	 *
	 * @param	string	$token			Access token
	 * @param	int		$expiresIn		Lifetime in seconds (from WeCom)
	 * @param	string	$corpId			Corp id (stored on insert for traceability)
	 * @return	void
	 */
	protected function saveCachedToken($token, $expiresIn, $corpId = '')
	{
		global $conf;

		$now = dol_now();
		$expiresAt = $now + max(60, $expiresIn);

		$sql = "SELECT rowid FROM ".$this->db->prefix()."wecom_config WHERE entity = ".((int) $conf->entity);
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('WeComApi::saveCachedToken SQL error', LOG_ERR);
			return;
		}
		$obj = $this->db->fetch_object($resql);

		if ($obj) {
			$sql = "UPDATE ".$this->db->prefix()."wecom_config SET";
			$sql .= " access_token = '".$this->db->escape($token)."',";
			$sql .= " corp_id = '".$this->db->escape($corpId)."',";
			$sql .= " token_expires_at = '".$this->db->idate($expiresAt)."',";
			$sql .= " token_updated_at = '".$this->db->idate($now)."'";
			$sql .= " WHERE rowid = ".((int) $obj->rowid);
		} else {
			$sql = "INSERT INTO ".$this->db->prefix()."wecom_config";
			$sql .= " (entity, corp_id, access_token, token_expires_at, token_updated_at, status, date_creation)";
			$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($corpId)."', '".$this->db->escape($token)."', '".$this->db->idate($expiresAt)."', '".$this->db->idate($now)."', 1, '".$this->db->idate($now)."')";
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('WeComApi::saveCachedToken SQL error on write: '.$this->db->lasterror(), LOG_ERR);
		}
	}
}
