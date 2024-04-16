<?php
/**
 * Plugin Name:       Captain Hooks
 * Plugin URI:        https://github.com/dbkode/captain-hooks
 * Description:       Captain Hooks is a plugin that allows you to explore all the hooks (actions, filters and shortcodes) that are available in your WordPress installation.
 * Version:           1.0.1
 * Requires at least: 5.7
 * Requires PHP:      7.2
 * Author:            dbeja
 * Author URI:        https://github.com/dbeja
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       captain-hooks
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'CAPTAINHOOKS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAPTAINHOOKS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CAPTAINHOOKS_VERSION', '1.0.1' );

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

	$table_name = $wpdb->prefix . 'captainhooks_livemode';
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		hook text NOT NULL,
		type text NOT NULL,
		num_args int(11) NOT NULL,
		expiry datetime NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
	dbDelta($sql);

	$table_name = $wpdb->prefix . 'captainhooks_livemode_logs';
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		hook text NOT NULL,
		type text NOT NULL,
		date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		log text NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate;";
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
