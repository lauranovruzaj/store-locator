<?php
/**
 * Uninstall: remove plugin options only. CPT posts and meta are intentionally
 * left untouched so the client doesn't lose data on an accidental uninstall.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sl_settings' );
delete_transient( 'sl_geocode_throttle' );
