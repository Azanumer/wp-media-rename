<?php
/**
 * Uninstall cleanup for Media Rename.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'wmr_settings' );
