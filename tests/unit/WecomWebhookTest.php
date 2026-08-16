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
 * \file    htdocs/custom/wecom/tests/unit/WecomWebhookTest.php
 * \ingroup wecom
 * \brief   Phase 5 unit tests: signature, AES encrypt/decrypt roundtrip, idempotency key (offline).
 */

use PHPUnit\Framework\TestCase;

require_once dirname(dirname(__DIR__)).'/class/wecomcrypt.class.php';
require_once dirname(dirname(__DIR__)).'/class/wecomeventlog.class.php';

/**
 * Class WecomWebhookTest
 */
class WecomWebhookTest extends TestCase
{
	/**
	 * Official-style signature: lexicographic sort of the four values then sha1.
	 */
	public function testSignature()
	{
		$sig = WeComCrypt::signature('token', '1409659813', '1372623149', 'encrypted');
		$this->assertRegExp('/^[0-9a-f]{40}$/', $sig);
		$this->assertTrue(WeComCrypt::verify('token', '1409659813', '1372623149', 'encrypted', $sig));
		$this->assertFalse(WeComCrypt::verify('token', '1409659813', '1372623149', 'tampered', $sig));
	}

	/**
	 * Encrypt then decrypt must give back the original message (and reject wrong corp id).
	 */
	public function testEncryptDecryptRoundtrip()
	{
		$aesKey = substr(str_repeat('abcdefghij', 5), 0, 43); // 43 chars
		$corpId = 'wwtestcorp1234567';
		$message = '<xml><ToUserName><![CDATA[to]]></ToUserName><Event>change_external_contact</Event></xml>';

		$cipher = WeComCrypt::encrypt($message, $aesKey, $corpId);
		$this->assertNotEquals($message, $cipher);

		$plain = WeComCrypt::decrypt($cipher, $aesKey, $corpId);
		$this->assertSame($message, $plain);

		// Wrong corp id must be rejected
		$this->expectException('Exception');
		WeComCrypt::decrypt($cipher, $aesKey, 'othercorp');
	}

	/**
	 * Idempotency key: same event -> same key, any change -> different key.
	 */
	public function testEventIdStability()
	{
		$ids = array('ExternalUserID' => 'wmX', 'ChangeType' => 'add_external_contact');
		$payload = '<xml></xml>';
		$id1 = WeComEventLog::buildEventId('change_external_contact', 1700000000, $ids, $payload);
		$id2 = WeComEventLog::buildEventId('change_external_contact', 1700000000, $ids, $payload);
		$id3 = WeComEventLog::buildEventId('change_external_contact', 1700000001, $ids, $payload);

		$this->assertSame($id1, $id2);
		$this->assertNotEquals($id1, $id3);
	}

	/**
	 * Event log DAO surface.
	 */
	public function testEventLogSurface()
	{
		foreach (array('insertEvent', 'markProcessed', 'buildEventId') as $method) {
			$this->assertTrue(method_exists('WeComEventLog', $method), 'WeComEventLog::'.$method.' must exist');
		}
		$this->assertSame(0, WeComEventLog::STATUS_NEW);
		$this->assertSame(1, WeComEventLog::STATUS_PROCESSED);
		$this->assertSame(2, WeComEventLog::STATUS_IGNORED_DUPLICATE);
		$this->assertSame(3, WeComEventLog::STATUS_ERROR);
	}
}
