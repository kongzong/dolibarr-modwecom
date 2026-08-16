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
 * \file    htdocs/custom/wecom/tests/unit/WecomDescriptorTest.php
 * \ingroup wecom
 * \brief   Phase 1 unit test: module descriptor sanity (no DB, no WeCom connection).
 */

use PHPUnit\Framework\TestCase;

/**
 * Class WecomDescriptorTest
 */
class WecomDescriptorTest extends TestCase
{
	/**
	 * Test that the module descriptor file exists at the expected location
	 * and declares the expected class and rights.
	 */
	public function testModuleDescriptor()
	{
		global $dolibarr_root_document_path;

		$descriptor = __DIR__.'/../../core/modules/modWeCom.class.php';
		$this->assertFileExists($descriptor);

		$content = file_get_contents($descriptor);
		$this->assertStringContainsString('class modWeCom extends DolibarrModules', $content);

		// Required phase 1 elements
		$this->assertStringContainsString("rights_class = 'wecom'", $content);
		foreach (array('read', 'write', 'admin', 'sync', 'message') as $perm) {
			$this->assertStringContainsString("'" . $perm . "'", $content);
		}
		// SQL tables must be loaded at activation
		$this->assertStringContainsString("_load_tables('/wecom/sql/')", $content);
	}

	/**
	 * Test that the 4 module SQL table files exist and use the llx_ prefix
	 * (run_sql will replace llx_ with the real database prefix).
	 */
	public function testSqlFiles()
	{
		$sqlDir = __DIR__.'/../../sql/';
		foreach (array(
			'llx_wecom_config.sql',
			'llx_wecom_user_map.sql',
			'llx_wecom_contact_map.sql',
			'llx_wecom_event_log.sql',
		) as $file) {
			$this->assertFileExists($sqlDir.$file);
			$content = file_get_contents($sqlDir.$file);
			$this->assertStringContainsString('CREATE TABLE llx_wecom_', $content);
		}
	}

	/**
	 * Test language files exist for both locales.
	 */
	public function testLangFiles()
	{
		foreach (array('en_US', 'zh_CN') as $locale) {
			$this->assertFileExists(__DIR__.'/../../langs/'.$locale.'/wecom.lang');
		}
	}
}
