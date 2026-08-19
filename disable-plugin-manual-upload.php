<?php
/**
 * Plugin Name: Disable Plugin ZIP Upload
 * Description: Disables manual plugin ZIP uploads while keeping Add Plugins available.
 * Author: Mircea Rechesan
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hide the "Upload Plugin" button on the Add Plugins screen.
 */
add_action( 'admin_head-plugin-install.php', function () {
    ?>
    <style>
        .upload-view-toggle {
            display: none !important;
        }
    </style>
    <?php
} );

/**
 * Block plugin ZIP uploads server-side.
 *
 * This prevents bypassing the hidden button by directly submitting
 * to update.php?action=upload-plugin.
 */
add_action( 'admin_init', function () {
    if (
        isset( $_GET['action'] ) &&
        $_GET['action'] === 'upload-plugin'
    ) {
        wp_die(
            'Manual plugin uploads are disabled.',
            'Plugin Upload Disabled',
            [ 'response' => 403 ]
        );
    }
} );