<?php
/**
 * Uninstall Anti Card Testing for WooCommerce.
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$options = array(
	'wact_enabled',
	'wact_max_attempts',
	'wact_window_minutes',
	'wact_error_message',
	'wact_honeypot',
	'wact_js_proof',
	'wact_min_seconds',
	'wact_guard_message',
	'wact_global_limit',
	'wact_global_max',
	'wact_turnstile_site',
	'wact_turnstile_secret',
	'wact_turnstile_checkout',
	'wact_turnstile_comments',
	'wact_protect_comments',
	'wact_close_comments',
	'wact_comment_message',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
