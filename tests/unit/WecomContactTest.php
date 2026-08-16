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
 * \file    htdocs/custom/wecom/tests/unit/WecomContactTest.php
 * \ingroup wecom
 * \brief   Phase 4 unit tests: contact mapping DAO surface and descriptor tab (no network, no DB).
 */

use PHPUnit\Framework\TestCase;

if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(dirname(dirname(__DIR__))));
}
require_once dirname(dirname(__DIR__)).'/class/wecomcontactmap.class.php';

/**
 * Class WecomContactTest
 */
class WecomContactTest extends TestCase
{
	/**
	 * Contact map DAO must expose the methods used by sync, admin page and tab page.
	 */
	public function testContactMapSurface()
	{
		$this->assertTrue(class_exists('WeComContactMap'));
		foreach (array('fetchByExternalUserId', 'fetchByRowId', 'fetchAllBySoc', 'create', 'updateWeComFields', 'unbind') as $method) {
			$this->assertTrue(method_exists('WeComContactMap', $method), 'WeComContactMap::'.$method.' must exist');
		}
		$this->assertSame(1, WeComContactMap::STATUS_ACTIVE);
		$this->assertSame(0, WeComContactMap::STATUS_UNBOUND);
	}

	/**
	 * Sync engine must expose external contact synchronization.
	 */
	public function testSyncEngineHasContactSync()
	{
		$this->assertTrue(method_exists('WeComSync', 'syncExternalContacts'));
	}

	/**
	 * Descriptor must declare the WeCom tab on thirdparty cards.
	 */
	public function testDescriptorDeclaresTab()
	{
		$content = file_get_contents(__DIR__.'/../../core/modules/modWeCom.class.php');
		$this->assertStringContainsString("thirdparty:+wecom", $content);
	}

	/**
	 * Contact map table SQL must have the unique external_userid index (idempotency, spec §35).
	 */
	public function testContactMapSqlUnique()
	{
		$content = file_get_contents(__DIR__.'/../../sql/llx_wecom_contact_map.sql');
		$this->assertStringContainsString('uk_wecom_contact_map_external_userid', $content);
	}
}
