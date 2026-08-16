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
 * \file    htdocs/custom/wecom/class/wecomcrypt.class.php
 * \ingroup wecom
 * \brief   WeCom callback message signature check and AES decryption
 *          (algorithm from the official WeCom callback documentation:
 *          sha1(sort(token, timestamp, nonce, encrypt)) + AES-256-CBC with PKCS7).
 */

/**
 * WeCom callback cryptography
 */
class WeComCrypt
{
	const BLOCK_SIZE = 32;

	/**
	 * Compute the callback signature.
	 *
	 * @param	string	$token		Callback Token configured in WeCom admin
	 * @param	string	$timestamp	Unix timestamp
	 * @param	string	$nonce		Random string
	 * @param	string	$encrypted	Encrypted message (empty for signature without payload)
	 * @return	string				SHA1 signature
	 */
	public static function signature($token, $timestamp, $nonce, $encrypted = '')
	{
		$array = array($token, $timestamp, $nonce, $encrypted);
		sort($array, SORT_STRING);
		return sha1(implode('', $array));
	}

	/**
	 * Verify a callback signature.
	 *
	 * @param	string	$token
	 * @param	string	$timestamp
	 * @param	string	$nonce
	 * @param	string	$encrypted
	 * @param	string	$msgSignature	Signature provided by WeCom
	 * @return	bool
	 */
	public static function verify($token, $timestamp, $nonce, $encrypted, $msgSignature)
	{
		$expected = self::signature($token, $timestamp, $nonce, $encrypted);
		return hash_equals($expected, (string) $msgSignature);
	}

	/**
	 * Decrypt a WeCom encrypted message.
	 *
	 * @param	string	$encryptedText	Base64 encoded ciphertext
	 * @param	string	$encodingAesKey	43 chars EncodingAESKey
	 * @param	string	$corpId			Expected receive id (Corp ID)
	 * @return	string					Plaintext XML (throws on any failure)
	 * @throws	Exception
	 */
	public static function decrypt($encryptedText, $encodingAesKey, $corpId)
	{
		if (strlen($encodingAesKey) != 43) {
			throw new Exception('Invalid EncodingAESKey length');
		}
		$key = base64_decode($encodingAesKey.'=', true);
		if ($key === false || strlen($key) != 32) {
			throw new Exception('Invalid EncodingAESKey');
		}
		$ciphertext = base64_decode((string) $encryptedText, true);
		if ($ciphertext === false || strlen($ciphertext) == 0 || strlen($ciphertext) % 16 != 0) {
			throw new Exception('Invalid ciphertext');
		}

		$iv = substr($key, 0, 16);
		$plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
		if ($plaintext === false) {
			throw new Exception('AES decrypt failed');
		}

		// Remove PKCS7 padding (block size 32 for WeCom)
		$pad = ord(substr($plaintext, -1));
		if ($pad < 1 || $pad > self::BLOCK_SIZE) {
			$pad = 0;
		}
		$plaintext = substr($plaintext, 0, (int) (strlen($plaintext) - $pad));

		// Structure: random(16) + msg_len(4, network order) + msg + receiveid
		if (strlen($plaintext) < 20) {
			throw new Exception('Decrypted message too short');
		}
		$msgLen = unpack('N', substr($plaintext, 16, 4));
		$msgLen = (int) reset($msgLen);
		if ($msgLen < 0 || 20 + $msgLen > strlen($plaintext)) {
			throw new Exception('Invalid message length');
		}
		$message = substr($plaintext, 20, $msgLen);
		$receiveId = substr($plaintext, 20 + $msgLen);

		if ($receiveId !== $corpId) {
			throw new Exception('Receive id mismatch');
		}

		return $message;
	}

	/**
	 * Encrypt a plaintext message (used for replies when needed).
	 *
	 * @param	string	$plainText		XML to encrypt
	 * @param	string	$encodingAesKey	43 chars
	 * @param	string	$corpId			Corp ID (receive id)
	 * @return	string					Base64 ciphertext
	 * @throws	Exception
	 */
	public static function encrypt($plainText, $encodingAesKey, $corpId)
	{
		if (strlen($encodingAesKey) != 43) {
			throw new Exception('Invalid EncodingAESKey length');
		}
		$key = base64_decode($encodingAesKey.'=', true);
		$iv = substr($key, 0, 16);

		$random = self::randomBytes(16);
		$body = $random.pack('N', strlen($plainText)).$plainText.$corpId;

		// PKCS7 pad to block size 32
		$pad = self::BLOCK_SIZE - (strlen($body) % self::BLOCK_SIZE);
		$body .= str_repeat(chr($pad), $pad);

		$ciphertext = openssl_encrypt($body, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
		if ($ciphertext === false) {
			throw new Exception('AES encrypt failed');
		}
		return base64_encode($ciphertext);
	}

	/**
	 * Random bytes (PHP 7 compatible: random_bytes exists but keep isolation for tests).
	 *
	 * @param	int		$length
	 * @return	string
	 */
	protected static function randomBytes($length)
	{
		return substr(bin2hex(random_bytes($length)), 0, $length);
	}
}
