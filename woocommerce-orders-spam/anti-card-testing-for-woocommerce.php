<?php
/**
 * Plugin Name: Woo Security
 * Description: WooCommerce security options for card-testing orders and comment spam. Settings live under WooCommerce → Woo Security.
 * Version:     1.2.0
 * Author:      Mircea
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Plugin URI:  https://github.com/mircearek/wp-utils
 * Text Domain: anti-card-testing-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.3
 *
 * @package AntiCardTestingForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'WACT_VERSION', '1.2.0' );
define( 'WACT_PLUGIN_FILE', __FILE__ );
define( 'WACT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once WACT_PLUGIN_DIR . 'includes/class-logger.php';
require_once WACT_PLUGIN_DIR . 'includes/class-settings.php';
require_once WACT_PLUGIN_DIR . 'includes/class-turnstile.php';
require_once WACT_PLUGIN_DIR . 'includes/class-rate-limiter.php';
require_once WACT_PLUGIN_DIR . 'includes/class-checkout-guard.php';
require_once WACT_PLUGIN_DIR . 'includes/class-comments-guard.php';

/**
 * Declare WooCommerce feature compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WACT_PLUGIN_FILE, true );
		}
	}
);

/**
 * Boot the plugin after WooCommerce has loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'wact_missing_woocommerce_notice' );
			return;
		}

		load_plugin_textdomain(
			'anti-card-testing-for-woocommerce',
			false,
			dirname( plugin_basename( WACT_PLUGIN_FILE ) ) . '/languages'
		);

		WACT_Settings::init();
		WACT_Rate_Limiter::init();
		WACT_Checkout_Guard::init();
		WACT_Comments_Guard::init();
	}
);

/**
 * Admin notice when WooCommerce is not active.
 */
function wact_missing_woocommerce_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Woo Security requires WooCommerce to be installed and active.', 'anti-card-testing-for-woocommerce' );
	echo '</p></div>';
}
