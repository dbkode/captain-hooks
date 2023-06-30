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
	global $wpdb;
	$table_name = $wpdb->prefix . 'captainhooks_hooks';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			hook text NOT NULL,
			type text NOT NULL,
			line_start int(11) NOT NULL,
			line_end int(11) NOT NULL,
			code text NOT NULL,
			doc_block text NULL,
			file text NOT NULL,
			params text NULL,
			folder text NOT NULL,
			PRIMARY KEY  (id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	// Store version in options table
	add_option( 'captainhooks_version', CAPTAINHOOKS_VERSION );
}

/**
 * Deactivation hook
 *
 * @since 1.0.0
 */
function captainhooks_deactivate() {}

$captainhooks = new CAPTAINHOOKS\Captainhooks();
$captainhooks->init();
