<?php
/**
 * Plugin Name: Manual Action Scheduler Runner
 * Description: Temporary admin tool for processing the WooCommerce Action Scheduler queue.
 * Author: Mircea Rechesan
 */

defined( 'ABSPATH' ) || exit;


/**
 * Add page under Tools.
 */
add_action( 'admin_menu', function () {
    add_management_page(
        'Action Scheduler Runner',
        'Action Scheduler Runner',
        'manage_woocommerce',
        'manual-as-runner',
        'manual_as_runner_page'
    );
} );


/**
 * Process queue request.
 */
add_action( 'admin_post_manual_as_run_queue', function () {

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Insufficient permissions.' );
    }

    check_admin_referer( 'manual_as_run_queue' );

    if ( ! class_exists( 'ActionScheduler_QueueRunner' ) ) {
        wp_die( 'Action Scheduler is not available.' );
    }

    /*
     * Let Action Scheduler process its normal due queue.
     * It handles locking, claims and execution itself.
     */
    ActionScheduler_QueueRunner::instance()->run();

    wp_safe_redirect(
        add_query_arg(
            array(
                'page' => 'manual-as-runner',
                'ran'  => '1',
            ),
            admin_url( 'tools.php' )
        )
    );

    exit;
} );


/**
 * Admin page.
 */
function manual_as_runner_page() {

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    ?>
    <div class="wrap">

        <h1>Action Scheduler Runner</h1>

        <?php if ( isset( $_GET['ran'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    Queue runner executed. Check WooCommerce → Status →
                    Scheduled Actions to see how many pending actions remain.
                </p>
            </div>
        <?php endif; ?>

        <p>
            This manually invokes the Action Scheduler queue runner.
            Only actions that are due will be processed.
        </p>

        <p>
            <strong>Do not repeatedly hammer the button.</strong>
            Run it, wait a little, then check the pending queue.
        </p>

        <form method="post"
              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

            <input type="hidden"
                   name="action"
                   value="manual_as_run_queue">

            <?php wp_nonce_field( 'manual_as_run_queue' ); ?>

            <?php
            submit_button(
                'Run Action Scheduler Queue',
                'primary',
                'submit',
                false
            );
            ?>

        </form>

    </div>
    <?php
}