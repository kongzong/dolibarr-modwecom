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
 * \file    htdocs/custom/wecom/tests/unit/WecomApiPhase6Test.php
 * \ingroup wecom
 * \brief   Phase 6 unit tests: REST API surface (all spec §24 endpoints declared).
 */

use PHPUnit\Framework\TestCase;

/**
 * Class WecomApiPhase6Test
 */
class WecomApiPhase6Test extends TestCase
{
	/**
	 * All REST endpoints of spec §24 must be declared with their @url annotation.
	 */
	public function testApiEndpointsDeclared()
	{
		$content = file_get_contents(__DIR__.'/../../class/api_wecom.class.php');

		$expected = array(
			'@url	GET status',
			'@url	POST sync/users',
			'@url	POST sync/external-contacts',
			'@url	GET users',
			'@url	GET contacts',
			'@url	GET contacts/{id}',
			'@url	POST messages',
			'@url	GET events',
		);
		foreach ($expected as $annotation) {
			$this->assertStringContainsString($annotation, $content, 'API must declare: '.$annotation);
		}
	}

	/**
	 * Class naming must follow Dolibarr discovery (api_wecom.class.php -> class Wecom)
	 * so the resource path is /api/index.php/wecom/...
	 */
	public function testApiClassNaming()
	{
		$content = file_get_contents(__DIR__.'/../../class/api_wecom.class.php');
		$this->assertStringContainsString('class Wecom extends DolibarrApi', $content);
	}

	/**
	 * Permission checks: every endpoint must test a wecom right.
	 */
	public function testApiPermissionChecks()
	{
		$content = file_get_contents(__DIR__.'/../../class/api_wecom.class.php');
		$this->assertEquals(8, substr_count($content, "hasRight('wecom'"));
	}

	/**
	 * No secret must ever be returned by the API.
	 */
	public function testNoSecretExposure()
	{
		$content = file_get_contents(__DIR__.'/../../class/api_wecom.class.php');
		foreach (array('WECOM_SECRET', 'WECOM_TOKEN', 'WECOM_ENCODING_AES_KEY', 'access_token') as $forbidden) {
			$this->assertStringNotContainsString($forbidden, $content, 'API code must not reference '.$forbidden);
		}
	}
}
