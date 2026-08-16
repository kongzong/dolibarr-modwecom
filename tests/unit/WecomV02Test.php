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
 * \file    htdocs/custom/wecom/tests/unit/WecomV02Test.php
 * \ingroup wecom
 * \brief   V0.2 local batch tests: markdown messages, trigger guards, cron entry.
 */

use PHPUnit\Framework\TestCase;

require_once dirname(dirname(__DIR__)).'/class/wecomapi.class.php';

/**
 * Class WecomV02Test
 */
class WecomV02Test extends TestCase
{
	/**
	 * sendApplicationMessage now accepts markdown but still rejects other types.
	 */
	public function testMarkdownAccepted()
	{
		$api = new WeComApi(null);
		$rc = new ReflectionMethod('WeComApi', 'sendApplicationMessage');
		// Calling with markdown would hit the network, so we only validate the guard logic:
		try {
			$api->sendApplicationMessage('x', '**bold**', 'markdown');
			$this->assertTrue(true); // guard passed (will fail later at HTTP, not our concern here)
		} catch (WeComApiException $e) {
			// Guard passed if the exception is NOT about msgtype
			$this->assertStringNotContainsString('Unsupported msgtype', $e->getMessage());
		}
	}

	public function testOtherTypesStillRejected()
	{
		$api = new WeComApi(null);
		$this->expectException('WeComApiException');
		$this->expectExceptionMessage('Unsupported msgtype');
		$api->sendApplicationMessage('x', 'hi', 'image');
	}

	/**
	 * Trigger must be opt-in (constant check) and never fatal.
	 */
	public function testTriggerGuards()
	{
		$content = file_get_contents(__DIR__.'/../../core/triggers/interface_99_modWeCom_WeComTriggers.class.php');
		$this->assertStringContainsString("WECOM_TRIGGER_NOTIFY", $content, 'trigger must be disabled unless configured');
		$this->assertStringContainsString('COMPANY_CREATE', $content);
		$this->assertStringContainsString('CONTRACT_CREATE', $content);
		$this->assertStringContainsString('return 0;', $content, 'trigger must return 0 (never block business flow)');
	}

	/**
	 * Cron entry declared and scheduled job method exists.
	 */
	public function testCronDeclared()
	{
		$content = file_get_contents(__DIR__.'/../../core/modules/modWeCom.class.php');
		$this->assertStringContainsString("'triggers' => 1", $content, 'module must declare its triggers directory');
		$this->assertStringContainsString('doScheduledJob', $content, 'cron job entry must exist (disabled by default)');
		$this->assertStringContainsString("'status' => 0", $content);
		$this->assertTrue(method_exists('WeComSync', 'doScheduledJob'));
	}
}
