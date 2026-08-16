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
 * \file    htdocs/custom/wecom/tests/unit/WecomApiTest.php
 * \ingroup wecom
 * \brief   Phase 2 unit tests: WeComApi / WeComApiException pure logic (no network, no DB).
 */

use PHPUnit\Framework\TestCase;

if (!defined('DOL_DOCUMENT_ROOT')) {
	// Minimal environment for running this test outside Dolibarr
	define('DOL_DOCUMENT_ROOT', dirname(dirname(dirname(__DIR__))));
}
require_once dirname(dirname(__DIR__)).'/class/wecomapiexception.class.php';
require_once dirname(dirname(__DIR__)).'/class/wecomapi.class.php';

/**
 * Class WecomApiTest
 */
class WecomApiTest extends TestCase
{
	/**
	 * Exception must carry error code / http status / api name / retry count
	 * and must never embed credentials.
	 */
	public function testExceptionCarriesContext()
	{
		$e = new WeComApiException('invalid secret', 40001, 200, 'gettoken', 1);
		$this->assertSame(40001, $e->getErrorCode());
		$this->assertSame(200, $e->getHttpStatus());
		$this->assertSame('gettoken', $e->getApiName());
		$this->assertSame(1, $e->getRetryCount());
		$this->assertStringNotContainsString('secret=', $e->getMessage());
	}

	/**
	 * getConfig must refuse to return a partial configuration.
	 */
	public function testGetConfigIncomplete()
	{
		// No Dolibarr globals set in this context: configuration incomplete -> empty array
		$api = new WeComApi(null);
		$this->assertSame(array(), $api->getConfig());
	}

	/**
	 * sendApplicationMessage must reject unsupported message types (allowed: text, markdown).
	 */
	public function testSendMessageRejectsUnsupportedType()
	{
		$api = new WeComApi(null);
		$this->expectException('WeComApiException');
		$api->sendApplicationMessage('zhangsan', 'hello', 'news');
	}

	/**
	 * Class structure: all WeCom API endpoints must be declared on the client.
	 */
	public function testApiClientSurface()
	{
		$this->assertTrue(class_exists('WeComApi'));
		foreach (array('getAccessToken', 'getDepartments', 'getUsers', 'getExternalContacts', 'getExternalContactDetail', 'sendApplicationMessage', 'testConnection') as $method) {
			$this->assertTrue(method_exists('WeComApi', $method), 'WeComApi::'.$method.' must exist');
		}
	}
}
