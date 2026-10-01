<?php
/**
 * Comment and product-review spam protection.
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stops bots posting through wp-comments-post.php, XML-RPC, and reviews.
 */
class WACT_Comments_Guard {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'comment_form_submit_field', array( __CLASS__, 'prepend_fields' ) );
		add_filter( 'preprocess_comment', array( __CLASS__, 'validate' ), 1 );
		add_filter( 'xmlrpc_methods', array( __CLASS__, 'disable_xmlrpc_comments' ) );
		add_filter( 'comments_open', array( __CLASS__, 'maybe_close_comments' ), 99, 2 );
		add_filter( 'rest_allow_anonymous_comments', array( __CLASS__, 'disallow_anonymous_rest_comments' ) );
	}

	/**
	 * Scripts on posts/pages/products that still accept comments.
	 */
	public static function enqueue() {
		if ( is_admin() ) {
			return;
		}

		$needs_form = ( is_singular() && comments_open() )
			|| ( function_exists( 'is_product' ) && is_product() );

		if ( ! $needs_form || ! WACT_Settings::protect_comments() ) {
			return;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		$deps = array( 'jquery' );

		if ( WACT_Settings::protect_comments_with_turnstile() ) {
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
				'isCheckout'       => false,
				'turnstileSiteKey' => WACT_Settings::protect_comments_with_turnstile()
					? WACT_Settings::get_turnstile_site_key()
					: '',
			)
		);
	}

	/**
	 * Inject traps before the comment submit button.
	 *
	 * @param string $submit_field Submit markup.
	 * @return string
	 */
	public static function prepend_fields( $submit_field ) {
		if ( ! WACT_Settings::protect_comments() ) {
			return $submit_field;
		}

		ob_start();
		self::render_fields();
		return ob_get_clean() . $submit_field;
	}

	/**
	 * Honeypot, JS proof, and Turnstile inside the comment/review form.
	 */
	public static function render_fields() {
		if ( ! WACT_Settings::protect_comments() ) {
			return;
		}

		if ( WACT_Settings::honeypot_enabled() ) {
			echo '<p class="wact-hp" aria-hidden="true">';
			echo '<label for="wact_website">' . esc_html__( 'Website', 'anti-card-testing-for-woocommerce' ) . '</label>';
			echo '<input type="text" name="wact_website" id="wact_website" value="" tabindex="-1" autocomplete="off" />';
			echo '</p>';
		}

		if ( WACT_Settings::js_proof_enabled() ) {
			echo '<input type="hidden" name="wact_js_proof" class="wact-js-proof" value="" autocomplete="off" />';
		}

		if ( WACT_Settings::protect_comments_with_turnstile() ) {
			WACT_Turnstile::render_widget( 'wact-turnstile-widget' );
		}
	}

	/**
	 * Reject spam comments before they are inserted.
	 *
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public static function validate( $commentdata ) {
		if ( ! WACT_Settings::protect_comments() ) {
			return $commentdata;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return $commentdata;
		}

		if ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) {
			return $commentdata;
		}

		$type = isset( $commentdata['comment_type'] ) ? $commentdata['comment_type'] : '';
		if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
			self::reject( 'trackback' );
		}

		if ( WACT_Settings::honeypot_enabled() && ! empty( $_POST['wact_website'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			self::reject( 'honeypot' );
		}

		if ( WACT_Settings::js_proof_enabled() ) {
			$proof = isset( $_POST['wact_js_proof'] ) ? sanitize_text_field( wp_unslash( $_POST['wact_js_proof'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( '1' !== $proof ) {
				self::reject( 'js_proof' );
			}
		}

		if ( WACT_Settings::protect_comments_with_turnstile() ) {
			$token = WACT_Turnstile::get_posted_token();
			if ( ! WACT_Turnstile::verify( $token ) ) {
				self::reject( 'turnstile' );
			}
		}

		$ua          = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$fingerprint = substr( hash( 'sha256', $ua ), 0, 32 );
		$key         = 'wact_comment_' . $fingerprint;
		$attempts    = (int) get_transient( $key );
		$attempts++;
		set_transient( $key, $attempts, 10 * MINUTE_IN_SECONDS );

		if ( $attempts > 3 ) {
			self::reject( 'comment_rate_limit' );
		}

		return $commentdata;
	}

	/**
	 * Remove XML-RPC comment and pingback entry points.
	 *
	 * @param array $methods XML-RPC methods.
	 * @return array
	 */
	public static function disable_xmlrpc_comments( $methods ) {
		if ( ! WACT_Settings::protect_comments() ) {
			return $methods;
		}

		unset( $methods['wp.newComment'], $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );

		return $methods;
	}

	/**
	 * Optionally close comments and reviews on the front end.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public static function maybe_close_comments( $open, $post_id ) {
		unset( $post_id );

		if ( WACT_Settings::close_comments() ) {
			return false;
		}

		return $open;
	}

	/**
	 * Block anonymous comments created through the REST API.
	 *
	 * @param bool $allow Whether anonymous REST comments are allowed.
	 * @return bool
	 */
	public static function disallow_anonymous_rest_comments( $allow ) {
		if ( WACT_Settings::protect_comments() ) {
			return false;
		}

		return $allow;
	}

	/**
	 * Stop comment insertion and log the reason.
	 *
	 * @param string $reason Log reason.
	 */
	private static function reject( $reason ) {
		WACT_Logger::warning(
			'Comment blocked by anti-spam guard.',
			array( 'reason' => $reason )
		);

		wp_die(
			esc_html( WACT_Settings::get_comment_block_message() ),
			esc_html__( 'Comment blocked', 'anti-card-testing-for-woocommerce' ),
			array( 'response' => 403 )
		);
	}
}
