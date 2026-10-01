<?php
/**
 * WooCommerce logger wrapper.
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes plugin events to WooCommerce > Status > Logs.
 */
class WACT_Logger {

	/**
	 * Log a warning.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public static function warning( $message, $context = array() ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$context['source'] = 'anti-card-testing';
		wc_get_logger()->warning( $message, $context );
	}
}
