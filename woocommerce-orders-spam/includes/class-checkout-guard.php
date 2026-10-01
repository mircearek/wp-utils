<?php
/**
 * Checkout bot traps: honeypot, JS proof, minimum time, Turnstile.
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stops bots that skip the checkout page or skip JavaScript.
 */
class WACT_Checkout_Guard {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_wact_boot', array( __CLASS__, 'ajax_boot' ) );
		add_action( 'wp_ajax_nopriv_wact_boot', array( __CLASS__, 'ajax_boot' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'mark_checkout_start' ), 1 );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'mark_checkout_start' ), 1 );
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'render_fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 4, 2 );
	}

	/**
	 * Front-end scripts on classic checkout.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() || is_checkout_pay_page() ) {
			return;
		}

		$deps = array( 'jquery' );

		if ( WACT_Settings::protect_checkout_with_turnstile() ) {
			WACT_Turnstile::enqueue_api();
			$deps[] = 'wact-turnstile-api';
		}

		wp_enqueue_style(
			'wact-guard',
			plugins_url( 'assets/css/guard.css', WACT_PLUGIN_FILE ),
			array(),
			WACT_VERSION
		);

		wp_enqueue_script(
			'wact-guard',
			plugins_url( 'assets/js/guard.js', WACT_PLUGIN_FILE ),
			$deps,
			WACT_VERSION,
			true
		);

		wp_localize_script(
			'wact-guard',
			'wactGuard',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'wact_boot' ),
				'isCheckout'       => true,
				'turnstileSiteKey' => WACT_Settings::protect_checkout_with_turnstile()
					? WACT_Settings::get_turnstile_site_key()
					: '',
			)
		);
	}

	/**
	 * Remember when this customer first hit checkout (PHP or AJAX).
	 */
	public static function mark_checkout_start() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}

		if ( ! WC()->session->get( 'wact_checkout_started' ) ) {
			WC()->session->set( 'wact_checkout_started', time() );
		}
	}

	/**
	 * Session ping from checkout JavaScript.
	 */
	public static function ajax_boot() {
		check_ajax_referer( 'wact_boot', 'nonce' );
		self::mark_checkout_start();
		wp_send_json_success();
	}

	/**
	 * Fields inside form.checkout so they are serialized with place-order AJAX.
	 */
	public static function render_fields() {
		if ( WACT_Settings::honeypot_enabled() ) {
			echo '<p class="wact-hp" aria-hidden="true">';
			echo '<label for="wact_website">' . esc_html__( 'Website', 'anti-card-testing-for-woocommerce' ) . '</label>';
			echo '<input type="text" name="wact_website" id="wact_website" value="" tabindex="-1" autocomplete="off" />';
			echo '</p>';
		}

		if ( WACT_Settings::js_proof_enabled() ) {
			echo '<input type="hidden" name="wact_js_proof" class="wact-js-proof" value="" autocomplete="off" />';
		}

		if ( WACT_Settings::protect_checkout_with_turnstile() ) {
			WACT_Turnstile::render_widget( 'wact-turnstile-widget' );
		}
	}

	/**
	 * Reject bot-like checkout submissions before an order is created.
	 *
	 * @param array    $data   Posted checkout data.
	 * @param WP_Error $errors Checkout validation errors.
	 */
	public static function validate( $data, $errors ) {
		if ( ! $errors instanceof WP_Error ) {
			return;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$reason = self::get_block_reason();

		if ( '' === $reason ) {
			return;
		}

		$errors->add( 'wact_checkout_guard', WACT_Settings::get_guard_message() );

		WACT_Logger::warning(
			'Checkout blocked by bot trap.',
			array(
				'reason'         => $reason,
				'payment_method' => isset( $data['payment_method'] ) ? sanitize_key( $data['payment_method'] ) : 'unknown',
			)
		);
	}

	/**
	 * Empty string if allowed, otherwise a log reason.
	 *
	 * @return string
	 */
	private static function get_block_reason() {

		if ( WACT_Settings::honeypot_enabled() && ! empty( $_POST['wact_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return 'honeypot';
		}

		if ( WACT_Settings::js_proof_enabled() ) {
			$proof = isset( $_POST['wact_js_proof'] ) ? sanitize_text_field( wp_unslash( $_POST['wact_js_proof'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '1' !== $proof ) {
				return 'js_proof';
			}
		}

		if ( WACT_Settings::min_checkout_seconds() > 0 ) {
			$started = ( function_exists( 'WC' ) && WC()->session )
				? (int) WC()->session->get( 'wact_checkout_started' )
				: 0;

			if ( $started < 1 ) {
				return 'no_checkout_session';
			}

			if ( ( time() - $started ) < WACT_Settings::min_checkout_seconds() ) {
				return 'too_fast';
			}
		}

		if ( WACT_Settings::protect_checkout_with_turnstile() ) {
			$token = WACT_Turnstile::get_posted_token();
			if ( ! WACT_Turnstile::verify( $token ) ) {
				return 'turnstile';
			}
		}

		if ( WACT_Settings::global_limit_enabled() ) {
			$key      = 'wact_global_checkout';
			$attempts = (int) get_transient( $key );
			$attempts++;
			set_transient( $key, $attempts, MINUTE_IN_SECONDS );

			if ( $attempts > WACT_Settings::get_global_max_per_minute() ) {
				return 'global_flood';
			}
		}

		return '';
	}
}
