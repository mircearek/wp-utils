<?php
/**
 * Cloudflare Turnstile helpers.
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render and verify Turnstile tokens.
 */
class WACT_Turnstile {

	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Site key and secret are both present.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== WACT_Settings::get_turnstile_site_key()
			&& '' !== WACT_Settings::get_turnstile_secret();
	}

	/**
	 * Enqueue Cloudflare's explicit-render script.
	 */
	public static function enqueue_api() {
		wp_enqueue_script(
			'wact-turnstile-api',
			'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
			array(),
			null,
			true
		);
	}

	/**
	 * Token from our hidden field, or a generic Turnstile field name.
	 *
	 * @return string
	 */
	public static function get_posted_token() {
		$keys = array( 'wact_turnstile_token', 'cf-turnstile-response' );

		foreach ( $keys as $key ) {
			if ( ! empty( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		return '';
	}

	/**
	 * Verify a token with Cloudflare. Fails closed.
	 *
	 * @param string $token Turnstile response token.
	 * @return bool
	 */
	public static function verify( $token ) {
		$token = is_string( $token ) ? trim( $token ) : '';

		if ( '' === $token || ! self::is_configured() ) {
			return false;
		}

		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 10,
				'body'    => array(
					'secret'   => WACT_Settings::get_turnstile_secret(),
					'response' => $token,
					'remoteip' => self::get_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			WACT_Logger::warning(
				'Turnstile siteverify request failed.',
				array( 'error' => $response->get_error_message() )
			);
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Markup: hidden token field + widget mount point. Must sit inside the form.
	 *
	 * @param string $widget_id DOM id for the widget.
	 */
	public static function render_widget( $widget_id ) {
		echo '<input type="hidden" name="wact_turnstile_token" id="wact-turnstile-token" value="" autocomplete="off" />';
		echo '<div id="' . esc_attr( $widget_id ) . '" class="wact-turnstile"></div>';
	}

	/**
	 * Best-effort client IP for Cloudflare (not used as a fingerprint).
	 *
	 * @return string
	 */
	private static function get_ip() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
