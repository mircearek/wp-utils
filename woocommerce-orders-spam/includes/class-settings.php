<?php
/**
 * Plugin settings, stored as WordPress options and shown under WooCommerce.
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings for checkout and comment anti-spam.
 */
class WACT_Settings {

	const OPTION_ENABLED              = 'wact_enabled';
	const OPTION_MAX_ATTEMPTS         = 'wact_max_attempts';
	const OPTION_WINDOW_MINUTES       = 'wact_window_minutes';
	const OPTION_ERROR_MESSAGE        = 'wact_error_message';
	const OPTION_HONEYPOT             = 'wact_honeypot';
	const OPTION_JS_PROOF             = 'wact_js_proof';
	const OPTION_MIN_SECONDS          = 'wact_min_seconds';
	const OPTION_GUARD_MESSAGE        = 'wact_guard_message';
	const OPTION_GLOBAL_LIMIT         = 'wact_global_limit';
	const OPTION_GLOBAL_MAX           = 'wact_global_max';
	const OPTION_TURNSTILE_SITE       = 'wact_turnstile_site';
	const OPTION_TURNSTILE_SECRET     = 'wact_turnstile_secret';
	const OPTION_TURNSTILE_CHECKOUT   = 'wact_turnstile_checkout';
	const OPTION_TURNSTILE_COMMENTS   = 'wact_turnstile_comments';
	const OPTION_PROTECT_COMMENTS     = 'wact_protect_comments';
	const OPTION_CLOSE_COMMENTS       = 'wact_close_comments';
	const OPTION_COMMENT_MESSAGE      = 'wact_comment_message';
	const MENU_SLUG                   = 'woo-security';

	/**
	 * Hook into WooCommerce admin.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_save' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_menu' ) );
		add_action( 'admin_notices', array( __CLASS__, 'turnstile_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
	}

	/**
	 * Whether a yes/no option is enabled.
	 *
	 * @param string $option  Option name.
	 * @param string $default Default value.
	 * @return bool
	 */
	public static function is_yes( $option, $default = 'yes' ) {
		return 'yes' === get_option( $option, $default );
	}

	/**
	 * Whether the fingerprint limiter is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return self::is_yes( self::OPTION_ENABLED, 'yes' );
	}

	/**
	 * Maximum allowed checkout attempts in the window.
	 *
	 * @return int
	 */
	public static function get_max_attempts() {
		$attempts = absint( get_option( self::OPTION_MAX_ATTEMPTS, 3 ) );

		return max( 1, (int) apply_filters( 'wact_max_attempts', $attempts ) );
	}

	/**
	 * Rate-limit window in seconds.
	 *
	 * @return int
	 */
	public static function get_window_seconds() {
		$minutes = max( 1, absint( get_option( self::OPTION_WINDOW_MINUTES, 2 ) ) );

		return max( MINUTE_IN_SECONDS, (int) apply_filters( 'wact_window_seconds', $minutes * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Checkout error shown when the fingerprint limit is exceeded.
	 *
	 * @return string
	 */
	public static function get_error_message() {
		$default = __( 'Too many payment attempts. Please wait a couple of minutes and try again.', 'anti-card-testing-for-woocommerce' );
		$message = get_option( self::OPTION_ERROR_MESSAGE, $default );
		$message = is_string( $message ) && '' !== trim( $message ) ? $message : $default;

		return (string) apply_filters( 'wact_error_message', $message );
	}

	/**
	 * @return bool
	 */
	public static function honeypot_enabled() {
		return self::is_yes( self::OPTION_HONEYPOT, 'yes' );
	}

	/**
	 * @return bool
	 */
	public static function js_proof_enabled() {
		return self::is_yes( self::OPTION_JS_PROOF, 'yes' );
	}

	/**
	 * Minimum seconds a visitor must spend on checkout before placing an order.
	 *
	 * @return int
	 */
	public static function min_checkout_seconds() {
		return absint( get_option( self::OPTION_MIN_SECONDS, 3 ) );
	}

	/**
	 * Generic bot-trap checkout message.
	 *
	 * @return string
	 */
	public static function get_guard_message() {
		$default = __( 'Unable to process your order. Please reload the page and try again.', 'anti-card-testing-for-woocommerce' );
		$message = get_option( self::OPTION_GUARD_MESSAGE, $default );
		$message = is_string( $message ) && '' !== trim( $message ) ? $message : $default;

		return $message;
	}

	/**
	 * @return bool
	 */
	public static function global_limit_enabled() {
		return self::is_yes( self::OPTION_GLOBAL_LIMIT, 'no' );
	}

	/**
	 * @return int
	 */
	public static function get_global_max_per_minute() {
		return max( 1, absint( get_option( self::OPTION_GLOBAL_MAX, 20 ) ) );
	}

	/**
	 * @return string
	 */
	public static function get_turnstile_site_key() {
		return trim( (string) get_option( self::OPTION_TURNSTILE_SITE, '' ) );
	}

	/**
	 * @return string
	 */
	public static function get_turnstile_secret() {
		return trim( (string) get_option( self::OPTION_TURNSTILE_SECRET, '' ) );
	}

	/**
	 * @return bool
	 */
	public static function protect_checkout_with_turnstile() {
		return self::is_yes( self::OPTION_TURNSTILE_CHECKOUT, 'no' ) && WACT_Turnstile::is_configured();
	}

	/**
	 * @return bool
	 */
	public static function protect_comments_with_turnstile() {
		return self::is_yes( self::OPTION_TURNSTILE_COMMENTS, 'no' ) && WACT_Turnstile::is_configured();
	}

	/**
	 * @return bool
	 */
	public static function protect_comments() {
		return self::is_yes( self::OPTION_PROTECT_COMMENTS, 'yes' );
	}

	/**
	 * @return bool
	 */
	public static function close_comments() {
		return self::is_yes( self::OPTION_CLOSE_COMMENTS, 'no' );
	}

	/**
	 * @return string
	 */
	public static function get_comment_block_message() {
		$default = __( 'Your comment could not be submitted. Please reload the page and try again.', 'anti-card-testing-for-woocommerce' );
		$message = get_option( self::OPTION_COMMENT_MESSAGE, $default );
		$message = is_string( $message ) && '' !== trim( $message ) ? $message : $default;

		return $message;
	}

	/**
	 * Add a submenu under WooCommerce.
	 */
	public static function register_menu() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'Woo Security', 'anti-card-testing-for-woocommerce' ),
			__( 'Woo Security', 'anti-card-testing-for-woocommerce' ),
			'manage_woocommerce',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Send old bookmarks to the Woo Security screen.
	 */
	public static function redirect_legacy_menu() {
		if ( ! isset( $_GET['page'] ) || 'wact-anti-card-testing' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Admin CSS/JS on the Woo Security page.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue_admin( $hook ) {
		unset( $hook );

		if ( ! isset( $_GET['page'] ) || self::MENU_SLUG !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'wact-admin',
			plugins_url( 'assets/css/admin.css', WACT_PLUGIN_FILE ),
			array(),
			WACT_VERSION
		);

		wp_enqueue_script(
			'wact-admin',
			plugins_url( 'assets/js/admin.js', WACT_PLUGIN_FILE ),
			array(),
			WACT_VERSION,
			true
		);
	}

	/**
	 * Persist settings when the admin form is submitted.
	 */
	public static function maybe_save() {
		if ( ! isset( $_POST['wact_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wact_settings_nonce'] ) ), 'wact_save_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$fields = self::get_fields();
		$secret = isset( $_POST[ self::OPTION_TURNSTILE_SECRET ] )
			? trim( wp_unslash( $_POST[ self::OPTION_TURNSTILE_SECRET ] ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: '';

		if ( '' === $secret ) {
			$fields = array_values(
				array_filter(
					$fields,
					static function ( $field ) {
						return empty( $field['id'] ) || self::OPTION_TURNSTILE_SECRET !== $field['id'];
					}
				)
			);
		}

		woocommerce_update_options( $fields );

		add_settings_error(
			'wact_messages',
			'wact_saved',
			__( 'Settings saved.', 'anti-card-testing-for-woocommerce' ),
			'success'
		);
	}

	/**
	 * Warn if Turnstile is toggled on without keys.
	 */
	public static function turnstile_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$want_turnstile = self::is_yes( self::OPTION_TURNSTILE_CHECKOUT, 'no' )
			|| self::is_yes( self::OPTION_TURNSTILE_COMMENTS, 'no' );

		if ( ! $want_turnstile || WACT_Turnstile::is_configured() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Woo Security: Turnstile is enabled but the site key or secret key is missing. Add both keys under WooCommerce → Woo Security.', 'anti-card-testing-for-woocommerce' );
		echo '</p></div>';
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		settings_errors( 'wact_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php esc_html_e( 'All anti-spam options live here. Paste Turnstile keys below, save, then copy the Cloudflare rules at the bottom of this page. You should not need to paste PHP into functions.php.', 'anti-card-testing-for-woocommerce' ); ?>
			</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'wact_save_settings', 'wact_settings_nonce' ); ?>
				<?php woocommerce_admin_fields( self::get_fields() ); ?>
				<?php submit_button(); ?>
			</form>
			<?php self::render_copy_paste_section(); ?>
		</div>
		<?php
	}

	/**
	 * Copy/paste snippets for Cloudflare and related dashboards.
	 */
	private static function render_copy_paste_section() {
		$snippets = self::get_copy_snippets();
		?>
		<hr />
		<h2><?php esc_html_e( 'Copy / paste', 'anti-card-testing-for-woocommerce' ); ?></h2>
		<p>
			<?php esc_html_e( 'These are not saved as plugin settings. Copy them into Cloudflare or your payment dashboard.', 'anti-card-testing-for-woocommerce' ); ?>
		</p>
		<div class="wact-copy-grid">
			<?php foreach ( $snippets as $snippet ) : ?>
				<div class="wact-copy-card">
					<div class="wact-copy-card__head">
						<strong><?php echo esc_html( $snippet['title'] ); ?></strong>
						<button
							type="button"
							class="button wact-copy-btn"
							data-target="<?php echo esc_attr( $snippet['id'] ); ?>"
							data-copied="<?php esc_attr_e( 'Copied', 'anti-card-testing-for-woocommerce' ); ?>"
						>
							<?php esc_html_e( 'Copy', 'anti-card-testing-for-woocommerce' ); ?>
						</button>
					</div>
					<p class="description"><?php echo esc_html( $snippet['help'] ); ?></p>
					<textarea id="<?php echo esc_attr( $snippet['id'] ); ?>" class="large-text code" rows="<?php echo esc_attr( (string) $snippet['rows'] ); ?>" readonly><?php echo esc_textarea( $snippet['value'] ); ?></textarea>
				</div>
			<?php endforeach; ?>
		</div>
		<h2><?php esc_html_e( 'Also do this once', 'anti-card-testing-for-woocommerce' ); ?></h2>
		<ol class="wact-help-list">
			<li><?php esc_html_e( 'Stripe (or your card gateway): require 3-D Secure / SCA for all card payments.', 'anti-card-testing-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Cloudflare: enable Bot Fight Mode, paste the WAF rules above, and do not cache the checkout page.', 'anti-card-testing-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Turnstile: disable checkout and comment protection in any other Turnstile plugin so you only have one widget.', 'anti-card-testing-for-woocommerce' ); ?></li>
			<li><?php esc_html_e( 'Logs: WooCommerce → Status → Logs, source anti-card-testing.', 'anti-card-testing-for-woocommerce' ); ?></li>
		</ol>
		<?php
	}

	/**
	 * Ready-to-copy snippets, including this site’s checkout path.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_copy_snippets() {
		$checkout_path = '/checkout';

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url  = wc_get_page_permalink( 'checkout' );
			$path = is_string( $url ) ? wp_parse_url( $url, PHP_URL_PATH ) : '';
			if ( is_string( $path ) && '' !== $path ) {
				$checkout_path = untrailingslashit( $path );
			}
		}

		$checkout_waf  = '(http.request.uri.query contains "wc-ajax=checkout") or (http.request.uri.path eq "' . $checkout_path . '") or (http.request.uri.path eq "' . $checkout_path . '/")';
		$comments_waf  = '(http.request.uri.path eq "/wp-comments-post.php") or (http.request.uri.path contains "/xmlrpc.php")';
		$store_api_waf = '(http.request.uri.path contains "/wp-json/wc/store") and (http.request.uri.path contains "/checkout")';

		return array(
			array(
				'id'    => 'wact-copy-waf-checkout',
				'title' => __( 'Cloudflare WAF — checkout', 'anti-card-testing-for-woocommerce' ),
				'help'  => __( 'Cloudflare → Security → WAF → Custom rules. Action: Managed Challenge.', 'anti-card-testing-for-woocommerce' ),
				'value' => $checkout_waf,
				'rows'  => 3,
			),
			array(
				'id'    => 'wact-copy-waf-comments',
				'title' => __( 'Cloudflare WAF — comments', 'anti-card-testing-for-woocommerce' ),
				'help'  => __( 'Cloudflare → Security → WAF → Custom rules. Action: Block.', 'anti-card-testing-for-woocommerce' ),
				'value' => $comments_waf,
				'rows'  => 2,
			),
			array(
				'id'    => 'wact-copy-waf-store-api',
				'title' => __( 'Cloudflare WAF — Checkout Blocks / Store API', 'anti-card-testing-for-woocommerce' ),
				'help'  => __( 'Only if you use Checkout Blocks. Action: Managed Challenge.', 'anti-card-testing-for-woocommerce' ),
				'value' => $store_api_waf,
				'rows'  => 2,
			),
			array(
				'id'    => 'wact-copy-turnstile-url',
				'title' => __( 'Cloudflare Turnstile dashboard', 'anti-card-testing-for-woocommerce' ),
				'help'  => __( 'Create a widget, then paste the site key and secret into the fields above.', 'anti-card-testing-for-woocommerce' ),
				'value' => 'https://dash.cloudflare.com/turnstile',
				'rows'  => 1,
			),
			array(
				'id'    => 'wact-copy-log-source',
				'title' => __( 'WooCommerce log source', 'anti-card-testing-for-woocommerce' ),
				'help'  => __( 'WooCommerce → Status → Logs. Open the log whose source is this value.', 'anti-card-testing-for-woocommerce' ),
				'value' => 'anti-card-testing',
				'rows'  => 1,
			),
		);
	}

	/**
	 * WooCommerce admin field definitions.
	 *
	 * @return array
	 */
	private static function get_fields() {
		return array(
			array(
				'title' => __( 'Rate limiter', 'anti-card-testing-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Blocks repeated classic checkout attempts from the same browser fingerprint before WooCommerce creates an order.', 'anti-card-testing-for-woocommerce' ),
				'id'    => 'wact_section_rate',
			),
			array(
				'title'   => __( 'Enable rate limiter', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Limit checkout attempts per browser fingerprint.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_ENABLED,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'             => __( 'Maximum attempts', 'anti-card-testing-for-woocommerce' ),
				'desc'              => __( 'Checkout submissions allowed in the time window before further attempts are blocked.', 'anti-card-testing-for-woocommerce' ),
				'id'                => self::OPTION_MAX_ATTEMPTS,
				'type'              => 'number',
				'default'           => '3',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			array(
				'title'             => __( 'Time window (minutes)', 'anti-card-testing-for-woocommerce' ),
				'desc'              => __( 'How long the attempt counter is kept. Each new attempt refreshes this window.', 'anti-card-testing-for-woocommerce' ),
				'id'                => self::OPTION_WINDOW_MINUTES,
				'type'              => 'number',
				'default'           => '2',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			array(
				'title'   => __( 'Rate-limit message', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_ERROR_MESSAGE,
				'type'    => 'textarea',
				'css'     => 'min-width:400px; height:75px;',
				'default' => __( 'Too many payment attempts. Please wait a couple of minutes and try again.', 'anti-card-testing-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wact_section_rate',
			),

			array(
				'title' => __( 'Bot traps', 'anti-card-testing-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'These stop bots that never load checkout JavaScript. Keep them on even if you use Turnstile.', 'anti-card-testing-for-woocommerce' ),
				'id'    => 'wact_section_traps',
			),
			array(
				'title'   => __( 'Honeypot field', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Hidden field. Real customers never see it; bots that fill it are blocked.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_HONEYPOT,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Require JavaScript', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Reject submissions that did not run this plugin’s checkout/comment script. WooCommerce checkout already requires JavaScript.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_JS_PROOF,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'             => __( 'Minimum checkout time (seconds)', 'anti-card-testing-for-woocommerce' ),
				'desc'              => __( 'Also blocks place-order requests that never visited the checkout page. Set 0 to disable.', 'anti-card-testing-for-woocommerce' ),
				'id'                => self::OPTION_MIN_SECONDS,
				'type'              => 'number',
				'default'           => '3',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			array(
				'title'   => __( 'Bot-trap message', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_GUARD_MESSAGE,
				'type'    => 'textarea',
				'css'     => 'min-width:400px; height:75px;',
				'default' => __( 'Unable to process your order. Please reload the page and try again.', 'anti-card-testing-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Emergency global limit', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Cap total checkout attempts per minute for the whole store. Turn this on during an attack. It can also block real customers on a very busy sale.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_GLOBAL_LIMIT,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'             => __( 'Global max attempts per minute', 'anti-card-testing-for-woocommerce' ),
				'id'                => self::OPTION_GLOBAL_MAX,
				'type'              => 'number',
				'default'           => '20',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wact_section_traps',
			),

			array(
				'title' => __( 'Cloudflare Turnstile', 'anti-card-testing-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Verify tokens on the server during checkout AJAX. Disable checkout/comment protection in your other Turnstile plugin or you will get two widgets and one-time tokens will fail. Get keys at https://dash.cloudflare.com/turnstile', 'anti-card-testing-for-woocommerce' ),
				'id'    => 'wact_section_turnstile',
			),
			array(
				'title'   => __( 'Site key', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_TURNSTILE_SITE,
				'type'    => 'text',
				'default' => '',
				'css'     => 'min-width:360px;',
			),
			array(
				'title'   => __( 'Secret key', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Leave blank to keep the current secret.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_TURNSTILE_SECRET,
				'type'    => 'password',
				'default' => '',
				'css'     => 'min-width:360px;',
			),
			array(
				'title'   => __( 'Protect classic checkout', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Render Turnstile in the checkout form and verify it before an order is created. The widget is re-rendered after WooCommerce’s updated_checkout AJAX.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_TURNSTILE_CHECKOUT,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Protect comments and reviews', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Require a valid Turnstile token on blog comments and product reviews.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_TURNSTILE_COMMENTS,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wact_section_turnstile',
			),

			array(
				'title' => __( 'Comments and reviews', 'anti-card-testing-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Most comment spam never loads the form. It POSTs to wp-comments-post.php or XML-RPC. These checks run on the server.', 'anti-card-testing-for-woocommerce' ),
				'id'    => 'wact_section_comments',
			),
			array(
				'title'   => __( 'Protect comments and product reviews', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Honeypot, JavaScript proof, rate limit, and disable XML-RPC / pingback comments.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_PROTECT_COMMENTS,
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Close all comments and reviews', 'anti-card-testing-for-woocommerce' ),
				'desc'    => __( 'Nuclear option. Use this if you do not need comments or product reviews.', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_CLOSE_COMMENTS,
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Blocked comment message', 'anti-card-testing-for-woocommerce' ),
				'id'      => self::OPTION_COMMENT_MESSAGE,
				'type'    => 'textarea',
				'css'     => 'min-width:400px; height:75px;',
				'default' => __( 'Your comment could not be submitted. Please reload the page and try again.', 'anti-card-testing-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wact_section_comments',
			),
		);
	}
}
