<?php
/**
 * Main CaptainHooks class file
 *
 * @package QuickAL
 * @subpackage Core
 * @since 1.0.0
 */

namespace CAPTAINHOOKS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CaptainHooks class.
 *
 * @since 1.0.0
 */
final class Captainhooks {

	/**
	 * Plugin initializer
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'setup' ) );
	}

	/**
	 * Setup plugin.
	 *
	 * @since 1.0.0
	 */
	public function setup() {
		// Localize plugin.
		load_plugin_textdomain( 'captainhooks', false, CAPTAINHOOKS_PLUGIN_DIR . '/languages' );

		// Enqueue admin scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'defer_parsing_of_js' ), 10 );

		// Register rest functions.
		add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );

		// Add settings page.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add a settings link to the plugins page.
		$plugin_dir_name = basename( CAPTAINHOOKS_PLUGIN_DIR );
		add_filter( 'plugin_action_links_' . $plugin_dir_name . '/captainhooks.php', array( $this, 'add_settings_link' ) );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 */
	public function admin_scripts() {
		wp_enqueue_script( 'captainhooks-js', CAPTAINHOOKS_PLUGIN_URL . '/dist/captainhooks.js', array(), CAPTAINHOOKS_VERSION, false );

		wp_localize_script(
			'captainhooks-js',
			'captainHooksData',
			array(
				'rest'  => esc_url_raw( rest_url( 'captainhooks/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Defer parsing of JS.
	 *
	 * @param string $url URL of script.
	 */
	public function defer_parsing_of_js( $url ) {
		if ( strpos( $url, 'captainhooks.js' ) ) {
			return str_replace( ' src', ' defer src', $url );
		}
		return $url;
	}

	/**
	 * Register API Routes for Captain Hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_api_routes() {
		// Get hooks.
		register_rest_route(
			'captainhooks/v1',
			'/hooks',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_hooks' ),
				'permission_callback' => function () {
					return current_user_can('manage_options');
				},
			)
		);
	}

	/**
	 * Get hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_hooks( $request ) {
		$path = $request->get_param( 'path' );

		$actions = array();
		$filters = array();

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST,
			\RecursiveIteratorIterator::CATCH_GET_CHILD // Handle "Permission denied" errors
		);
		foreach ( $iterator as $file ) {
			$full_path = $file->getPathname();
			$filename = basename( $full_path );
			$relative_path = str_replace( $path, '', $full_path );

			if ( strpos( $full_path, 'vendor/' ) !== false ) {
				continue;
			}

			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$original_content = file_get_contents( $full_path );
				$content = str_replace(["\n", "\r"], ' ', $original_content);
				$content = preg_replace('!\s+!', ' ', $content);

				// get all actions
				preg_match_all( '/do_action\((.*?)\)\;/', $content, $matches, PREG_OFFSET_CAPTURE );
				error_log( print_r( $matches, true ) );
				foreach( $matches[1] as $match ) {
					$index = $match[1];
					if ( strpos ( $match[0], ')' ) === 0 ) {
						continue;
					}
					$params = array_map( 'trim', explode( ",", $match[0] ) );
					$line_number = substr_count( substr( $original_content, 0, $index ), "\n" ) + 1;
					$actions[] = [
						'file' => $filename,
						'hook' => str_replace( ['"', "'"], '', $params[0] ),
						'params' => array_slice( $params, 1 ),
						'file_path' => $relative_path,
						'line' => 'do_' . 'action(' . $match[0] . ');',
						'line_number' => $line_number
					];
				}

				// get all filters
				preg_match_all('/apply_filters\((.*?)\)\;/', $content, $matches);
				foreach( $matches[1] as $match ) {
					if ( strpos ( $match, ')' ) === 0 ) {
						continue;
					}
					$params = array_map( 'trim', explode( ",", $match ) );
					$filters[] = [
						'file' => $filename,
						'hook' => str_replace( ['"', "'"], '', $params[0] ),
						'params' => array_slice( $params, 1 ),
						'file_path' => $relative_path,
						'line' => 'apply_' . 'filters(' . $match . ');'
					];
				}
			}
		}

		// sort actions and filters by hook
		usort($actions, function($a, $b) {
			return strcmp($a['hook'], $b['hook']);
		});
		// group actions by hook with same name
		$actions = array_reduce( $actions, function( $carry, $item ) {
			$carry[$item['hook']][] = $item;
			return $carry;
		}, [] );
		// convert actions to array
		$actions = array_map( function( $key, $value ) {
			return [ 'hook' => $key, 'usages' => $value, 'show' => false ];
		}, array_keys( $actions ), $actions );

		usort($filters, function($a, $b) {
			return strcmp($a['hook'], $b['hook']);
		});
		$filters = array_reduce( $filters, function( $carry, $item ) {
			$carry[$item['hook']][] = $item;
			return $carry;
		}, [] );
		$filters = array_map( function( $key, $value ) {
			return [ 'hook' => $key, 'usages' => $value, 'show' => false ];
		}, array_keys( $filters ), $filters );

		return rest_ensure_response( array(
			'actions' => $actions,
			'filters' => $filters,
		) );
	}

	/**
	 * Adds a settings page.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		add_management_page(
			__( 'Captain Hooks', 'captainhooks' ),
			__( 'Captain Hooks', 'captainhooks' ),
			'manage_options',
			'captainhooks-page',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the page.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		// get all themes
		$themes = wp_get_themes();

		// get all plugins
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		$plugins = get_plugins();

		include CAPTAINHOOKS_PLUGIN_DIR . 'templates/page.php';
	}

	/**
	 * Registers the settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
	}

	/**
	 * Add settings link to plugin page.
	 *
	 * @since 1.0.
	 *
	 * @param array $links Array of links.
	 * @return array Array of links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=captainhooks-settings">' . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}
}
