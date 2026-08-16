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
 * \file    htdocs/custom/wecom/class/wecomapiexception.class.php
 * \ingroup wecom
 * \brief   Exception thrown for all WeCom API errors.
 */

/**
 * Unified exception for WeCom API errors.
 *
 * Never contains the Secret or the full Access Token.
 */
class WeComApiException extends Exception
{
	/**
	 * @var int WeCom error code (errcode)
	 */
	protected $errorCode;

	/**
	 * @var int HTTP status code
	 */
	protected $httpStatus;

	/**
	 * @var string WeCom API endpoint that failed (no query string)
	 */
	protected $apiName;

	/**
	 * @var int Number of retries performed before failing
	 */
	protected $retryCount;

	/**
	 * Constructor
	 *
	 * @param string	$message		Error message (already sanitized, no secret)
	 * @param int		$errorCode		WeCom errcode
	 * @param int		$httpStatus		HTTP status code
	 * @param string	$apiName		API endpoint name
	 * @param int		$retryCount		Retries performed
	 * @param Exception	$previous		Previous exception
	 */
	public function __construct($message, $errorCode = 0, $httpStatus = 0, $apiName = '', $retryCount = 0, Exception $previous = null)
	{
		parent::__construct($message, 0, $previous);
		$this->errorCode = $errorCode;
		$this->httpStatus = $httpStatus;
		$this->apiName = $apiName;
		$this->retryCount = $retryCount;
	}

	/**
	 * @return int
	 */
	public function getErrorCode()
	{
		return $this->errorCode;
	}

	/**
	 * @return int
	 */
	public function getHttpStatus()
	{
		return $this->httpStatus;
	}

	/**
	 * @return string
	 */
	public function getApiName()
	{
		return $this->apiName;
	}

	/**
	 * @return int
	 */
	public function getRetryCount()
	{
		return $this->retryCount;
	}
}
