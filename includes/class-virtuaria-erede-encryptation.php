<?php
/**
 * Crypt class.
 *
 * @package erede.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Classe definition.
 */
final class Virtuaria_Erede_Encryptation {
	const METHOD = 'aes-256-ctr';

	/**
	 * Encrypts a message
	 *
	 * @param string  $message plaintext message.
	 * @param string  $key     encryption key (raw binary expected).
	 * @param boolean $encode  set to TRUE to return a base64-encoded.
	 * @return string (raw binary)
	 */
	public static function encrypt( $message, $key, $encode = false ) {
		$nonce_size = openssl_cipher_iv_length( self::METHOD );
		$nonce      = openssl_random_pseudo_bytes( $nonce_size );

		$ciphertext = openssl_encrypt(
			$message,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$nonce
		);

		// Now let's pack the IV and the ciphertext together.
		if ( $encode ) {
			return base64_encode( $nonce . $ciphertext );
		}
		return $nonce . $ciphertext;
	}

	/**
	 * Decrypts a message.
	 *
	 * @param string  $message ciphertext message.
	 * @param string  $key     encryption key (raw binary expected).
	 * @param boolean $encoded are we expecting an encoded string?.
	 * @return string
	 * @throws Exception Encryption failure.
	 */
	public static function decrypt( $message, $key, $encoded = false ) {
		if ( $encoded ) {
			$message = base64_decode( $message, true );
			if ( false === $message ) {
				return false;
			}
		}

		$nonce_size = openssl_cipher_iv_length( self::METHOD );
		$nonce      = mb_substr( $message, 0, $nonce_size, '8bit' );
		$ciphertext = mb_substr( $message, $nonce_size, null, '8bit' );

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$nonce
		);

		return $plaintext;
	}
}
