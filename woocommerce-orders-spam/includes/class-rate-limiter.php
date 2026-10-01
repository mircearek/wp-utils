<?php
/**
 * Checkout fingerprint rate limiter for classic WooCommerce checkout.
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blocks repeated checkout attempts before WooCommerce creates an order.
 */
class WACT_Rate_Limiter {

	/**
	 * Register checkout hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'limit' ), 5, 2 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'limit_store_api' ), 10, 3 );
	}

	/**
	 * Rate-limit classic checkout submissions.
	 *
	 * Designed for [woocommerce_checkout]. Runs before WooCommerce creates
	 * the order or sends the payment to the gateway.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Checkout validation errors.
	 */
	public static function limit( $data, $errors ) {
		if ( ! WACT_Settings::is_enabled() ) {
			return;
		}

		// Don't interfere with administrators working in wp-admin.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $errors instanceof WP_Error ) {
			return;
		}

		$result = self::record_attempt( $data );

		if ( $result['blocked'] ) {
			$errors->add(
				'wact_checkout_rate_limit',
				WACT_Settings::get_error_message()
			);

			self::log_block( $result['fingerprint'], $result['attempts'], self::get_payment_method( $data ) );
		}
	}

	/**
	 * Rate-limit Checkout Blocks / Store API POSTs as well.
	 *
	 * @param mixed            $result  Dispatch result.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 * @return mixed
	 */
	public static function limit_store_api( $result, $server, $request ) {
		unset( $server );

		if ( is_wp_error( $result ) || ! WACT_Settings::is_enabled() ) {
			return $result;
		}

		if ( ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		$route = $request->get_route();
		if ( ! is_string( $route ) || ! preg_match( '#^/wc/store(?:/v[0-9]+)?/checkout$#', $route ) ) {
			return $result;
		}

		if ( ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return $result;
		}

		$data   = array( 'payment_method' => $request->get_param( 'payment_method' ) );
		$record = self::record_attempt( $data );

		if ( $record['blocked'] ) {
			self::log_block( $record['fingerprint'], $record['attempts'], self::get_payment_method( $data ) );

			return new WP_Error(
				'wact_checkout_rate_limit',
				WACT_Settings::get_error_message(),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	/**
	 * Increment the fingerprint counter.
	 *
	 * @param array $data Checkout-like data.
	 * @return array{blocked:bool,attempts:int,fingerprint:string}
	 */
	private static function record_attempt( $data ) {
		$fingerprint   = self::build_fingerprint( $data );
		$transient_key = 'wact_checkout_' . substr( $fingerprint, 0, 32 );
		$attempts      = (int) get_transient( $transient_key );
		$attempts++;

		set_transient(
			$transient_key,
			$attempts,
			WACT_Settings::get_window_seconds()
		);

		return array(
			'blocked'      => $attempts > WACT_Settings::get_max_attempts(),
			'attempts'     => $attempts,
			'fingerprint'  => $fingerprint,
		);
	}

	/**
	 * Build a fingerprint that is not based on IP, email, phone, or address.
	 *
	 * Card-testing bots commonly rotate those values.
	 *
	 * @param array $data Posted checkout data.
	 * @return string
	 */
	private static function build_fingerprint( $data ) {
		$parts = array(
			self::get_server_header( 'HTTP_USER_AGENT' ),
			self::get_server_header( 'HTTP_ACCEPT_LANGUAGE' ),
			self::get_server_header( 'HTTP_ACCEPT_ENCODING' ),
			self::get_payment_method( $data ),
		);

		/**
		 * Filter the values used to build the checkout fingerprint.
		 *
		 * @param array $parts Fingerprint parts.
		 * @param array $data  Posted checkout data.
		 */
		$parts = apply_filters( 'wact_fingerprint_parts', $parts, $data );

		return hash( 'sha256', implode( '|', (array) $parts ) );
	}

	/**
	 * Sanitized payment method from posted checkout data.
	 *
	 * @param array $data Posted checkout data.
	 * @return string
	 */
	private static function get_payment_method( $data ) {
		if ( ! empty( $data['payment_method'] ) ) {
			return sanitize_key( $data['payment_method'] );
		}

		return 'unknown';
	}

	/**
	 * Sanitized server header value.
	 *
	 * @param string $key $_SERVER key.
	 * @return string
	 */
	private static function get_server_header( $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
	}

	/**
	 * Write a WooCommerce log entry when a checkout is blocked.
	 *
	 * Find entries at: WooCommerce > Status > Logs (source: anti-card-testing).
	 *
	 * @param string $fingerprint    Full fingerprint hash.
	 * @param int    $attempts       Attempt count including the blocked one.
	 * @param string $payment_method Payment method id.
	 */
	private static function log_block( $fingerprint, $attempts, $payment_method ) {
		WACT_Logger::warning(
			'Checkout blocked by anti-card-testing rate limiter.',
			array(
				'fingerprint'    => substr( $fingerprint, 0, 12 ),
				'attempts'       => $attempts,
				'payment_method' => $payment_method,
			)
		);
	}
}
