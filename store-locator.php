<?php
/**
 * Plugin Name: Store Locator
 * Description: Custom store locator with Google Map, search, radius filter and carousel of store cards. Includes a CSV importer for TeamSystem exports.
 * Version:     1.0.0
 * Author:      Biokyma
 * Text Domain: store-locator
 * Requires PHP: 8.1
 * Requires at least: 6.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SL_PLUGIN_FILE', __FILE__ );
define( 'SL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SL_VERSION', '1.0.0' );

require_once SL_PLUGIN_DIR . 'includes/class-cpt.php';
require_once SL_PLUGIN_DIR . 'includes/class-settings.php';
require_once SL_PLUGIN_DIR . 'includes/class-geocoder.php';
require_once SL_PLUGIN_DIR . 'includes/class-importer.php';
require_once SL_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once SL_PLUGIN_DIR . 'includes/class-shortcode.php';

add_action( 'plugins_loaded', static function () {
	SL_CPT::init();
	SL_Settings::init();
	SL_Importer::init();
	SL_REST_API::init();
	SL_Shortcode::init();
} );

register_activation_hook( __FILE__, static function () {
	SL_CPT::register();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, static function () {
	flush_rewrite_rules();
} );
