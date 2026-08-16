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
 * \file    htdocs/custom/wecom/tests/unit/WecomSyncTest.php
 * \ingroup wecom
 * \brief   Phase 3 unit tests: sync engine structure and idempotency logic (no network, no DB).
 */

use PHPUnit\Framework\TestCase;

if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', dirname(dirname(dirname(__DIR__))));
}
require_once dirname(dirname(__DIR__)).'/class/wecomsync.class.php';

/**
 * Class WecomSyncTest
 */
class WecomSyncTest extends TestCase
{
	/**
	 * Sync engine must be constructible with an injected API client (mock-ready).
	 */
	public function testConstructorAcceptsInjectedApi()
	{
		$this->assertTrue(class_exists('WeComSync'));
		$rc = new ReflectionClass('WeComSync');
		$this->assertTrue($rc->hasProperty('api'), 'WeComSync must allow API injection');
		$this->assertTrue($rc->hasMethod('syncDepartments'));
		$this->assertTrue($rc->hasMethod('syncUsers'));
	}

	/**
	 * Matching policy constants used by syncOneUser must be present on WeComUserMap.
	 */
	public function testUserMapStatusConstants()
	{
		$this->assertSame(1, WeComUserMap::STATUS_ACTIVE);
		$this->assertSame(0, WeComUserMap::STATUS_DISABLED);
	}

	/**
	 * The user map DAO must expose the methods used by sync and admin pages.
	 */
	public function testUserMapSurface()
	{
		foreach (array('fetchByWeComUserId', 'create', 'update', 'loadAllByWeComUserId') as $method) {
			$this->assertTrue(method_exists('WeComUserMap', $method), 'WeComUserMap::'.$method.' must exist');
		}
	}

	/**
	 * Department mapping table SQL must exist with a unique index (idempotency).
	 */
	public function testDepartmentMapSql()
	{
		$file = __DIR__.'/../../sql/llx_wecom_department_map.sql';
		$this->assertFileExists($file);
		$content = file_get_contents($file);
		$this->assertStringContainsString('UNIQUE INDEX', $content);
		$this->assertStringContainsString('wecom_department_id', $content);
	}
}
