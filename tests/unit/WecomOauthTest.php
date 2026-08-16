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
 * \file    htdocs/custom/wecom/tests/unit/WecomOauthTest.php
 * \ingroup wecom
 * \brief   OAuth login unit tests (structure only - live flow needs a public domain).
 */

use PHPUnit\Framework\TestCase;

/**
 * Class WecomOauthTest
 */
class WecomOauthTest extends TestCase
{
	/**
	 * API client must expose the OAuth code exchange.
	 */
	public function testApiHasOAuthCodeExchange()
	{
		$this->assertTrue(method_exists('WeComApi', 'getUserIdByOAuthCode'));
	}

	/**
	 * OAuth page must: check CSRF state, refuse unmapped users, never auto-create.
	 */
	public function testOauthPageGuards()
	{
		$content = file_get_contents(__DIR__.'/../../wecom/oauth.php');
		$this->assertStringContainsString("hash_equals(\$_SESSION['wecom_oauth_state']", $content, 'CSRF state check required');
		$this->assertStringContainsString('WeComLoginNotMapped', $content, 'unmapped users must be rejected with a message');
		$this->assertStringNotContainsString('->create(', $content, 'OAuth must never auto-create users');
		$this->assertStringContainsString('$_SESSION["dol_login"]', $content, 'session keys must be set like a classic login');
	}

	/**
	 * Hook class must exist and register the login page button.
	 */
	public function testLoginPageHook()
	{
		$content = file_get_contents(__DIR__.'/../../class/actions_wecom.class.php');
		$this->assertStringContainsString('class ActionsWecom', $content);
		$this->assertStringContainsString('getLoginPageOptions', $content);
	}

	/**
	 * Descriptor must declare the mainloginpage hook context.
	 */
	public function testDescriptorDeclaresHook()
	{
		$content = file_get_contents(__DIR__.'/../../core/modules/modWeCom.class.php');
		$this->assertStringContainsString("'mainloginpage'", $content);
	}
}
