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
 * \file    htdocs/custom/wecom/tests/unit/WecomV02RestTest.php
 * \ingroup wecom
 * \brief   V0.2 second batch tests: tags, addressbook events, OAuth in-app flow.
 */

use PHPUnit\Framework\TestCase;

/**
 * Class WecomV02RestTest
 */
class WecomV02RestTest extends TestCase
{
	/**
	 * Tags column must exist in SQL and be handled by the DAO.
	 */
	public function testTagsSupport()
	{
		$this->assertStringContainsString('wecom_tags', file_get_contents(__DIR__.'/../../sql/llx_wecom_contact_map.sql'));
		$dao = file_get_contents(__DIR__.'/../../class/wecomcontactmap.class.php');
		$this->assertStringContainsString("'wecom_tags'", $dao);
		// Sync extracts follow_user tags
		$this->assertStringContainsString('tag_name', file_get_contents(__DIR__.'/../../class/wecomsync.class.php'));
	}

	/**
	 * Webhook dispatcher handles WeCom addressbook events with the right safety rules.
	 */
	public function testAddressbookEvents()
	{
		$content = file_get_contents(__DIR__.'/../../wecom/webhook.php');
		$this->assertStringContainsString("=== 'change_contact'", $content);
		$this->assertStringContainsString("'delete_user'", $content);
		$this->assertStringContainsString('status = 0', $content, 'delete_user must only disable the mapping');
		$this->assertStringContainsString('getUserDetail', $content, 'update_user must refresh via one API call');
		$this->assertStringContainsString("'create_user'", $content);
	}

	/**
	 * OAuth: in-app browser uses silent snsapi_base, normal browser uses wwlogin QR,
	 * backtopage is validated before storing.
	 */
	public function testOauthInAppAndBacktopage()
	{
		$content = file_get_contents(__DIR__.'/../../wecom/oauth.php');
		$this->assertStringContainsString("strpos(\$userAgent, 'wxwork')", $content, 'UA detection for WeCom built-in browser');
		$this->assertStringContainsString('snsapi_base', $content, 'silent scope for in-app flow');
		$this->assertStringContainsString('wwlogin/sso/login', $content, 'QR flow for normal browsers');
		$this->assertStringContainsString('wecom_oauth_backtopage', $content, 'remember target page');
		$this->assertStringContainsString('preg_match', $content, 'backtopage must be validated (no open redirect)');
	}

	/**
	 * API client must expose getUserDetail for the webhook.
	 */
	public function testGetUserDetailExists()
	{
		$this->assertTrue(method_exists('WeComApi', 'getUserDetail'));
	}
}
