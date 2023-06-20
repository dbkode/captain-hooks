<?php
/**
 * Plugin Name: Captain Hooks
 * Plugin URI:  captain-hooks.com
 * Description: A description
 * Version:     1.0.0
 * Author:      dbeja
 * Text Domain: captain-hooks
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'CAPTAINHOOKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAPTAINHOOKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CAPTAINHOOKS_VERSION', '1.0.0' );

require_once CAPTAINHOOKS_PLUGIN_DIR . '/vendor/autoload.php';

register_activation_hook( __FILE__, 'captainhooks_activate' );
register_deactivation_hook( __FILE__, 'captainhooks_deactivate' );

/**
 * Activation hook
 *
 * @since 1.0.0
 */
function captainhooks_activate() {
	// set default captainhooks settings.
	$options = get_option( 'captainhooks_settings' );
	if ( false === $options ) {
		$default = array();
		update_option( 'captainhooks_settings', $default );
	}
}

/**
 * Deactivation hook
 *
 * @since 1.0.0
 */
function captainhooks_deactivate() {}

$captainhooks = new CAPTAINHOOKS\Captainhooks();
$captainhooks->init();
