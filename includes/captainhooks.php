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

		return rest_ensure_response( array(
			'actions' => $actions,
			'filters' => $filters,
		) );
	}

	public function get_path_hooks( $path ) {
		$files = $this->get_folder_phps( $path );
		$actions = array();
		$filters = array();

		foreach( $files as $file ) {
			$code = $this->prepare_code( $file['full_path'] );
			$code_actions = $this->get_actions( $code );
			$code_actions = array_map( function( $action ) use ( $file ) {
				$action['file'] = $file['relative_path'];
				return $action;
			}, $code_actions );
			$actions = array_merge( $actions, $code_actions );

			$code_filters = $this->get_filters( $code );
			$code_filters = array_map( function( $filter ) use ( $file ) {
				$filter['file'] = $file['relative_path'];
				return $filter;
			}, $code_filters );
			$filters = array_merge( $filters, $code_filters );
		}

		$actions = $this->reduce_and_sort( $actions );
		$filters = $this->reduce_and_sort( $filters );

		return [
			'actions' => $actions,
			'filters' => $filters,
		];
	}

	public function get_folder_phps( $path ) {
		$files = [];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST,
			\RecursiveIteratorIterator::CATCH_GET_CHILD // Handle "Permission denied" errors
		);

		foreach ( $iterator as $file ) {
			$full_path = $file->getPathname();
			$relative_path = str_replace( $path, '', $full_path );

			if ( strpos( $full_path, '/vendor/' ) !== false ) {
				continue;
			}

			if ( strpos( $full_path, '/test/' ) !== false ) {
				continue;
			}

			if ( strpos( $full_path, '/tests/' ) !== false ) {
				continue;
			}

			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$files[] = [
					'full_path' => $full_path,
					'relative_path' => $relative_path
				];
			}
		}

		return $files;
	}

	public function prepare_code( $file_path ) {
		$lines = file( $file_path );
		$lines2 = [];
		$comments = false;
		foreach( $lines as $index => $line ) {
			$line_number = $index + 1;
			if ( $comments && strpos( $line, '*/' ) !== false ) {
				$comments = false;
				$line = substr( $line, strpos( $line, '*/' ) + 2 );
			}
			if( $comments ) {
				$line = '';
			} else {
				if ( strpos( $line, '//' ) !== false ) {
					$line = substr( $line, 0, strpos( $line, '//' ) );
				}
				if ( strpos( $line, '/*' ) !== false ) {
					$comments = true;
					$line = substr( $line, 0, strpos( $line, '/*' ) );
				}	
			}
			$line = str_replace( [ "do_action", "apply_filters" ], [ "*{$line_number}*do_action", "*{$line_number}*apply_filters" ], $line );
			$lines2[] = trim( $line );
		}
		$code = implode( " ", $lines2 );
		return $code;
	}

	public function get_actions( $code ) {
		return $this->get_fn_hooks( 'do_action', $code );
	}

	public function get_filters( $code) {
		return $this->get_fn_hooks( 'apply_filters', $code );
	}

	public function get_fn_hooks( $fn_name, $code ) {
		preg_match_all( "/(?<=^|\s|;)\*(\d+)\*{$fn_name}\s*\(\s*([^,]*)\s*(?:,\s*([^;]*))?(?=\)\s*;)/", $code, $matches );

		$hooks = [];
		foreach( $matches[2] as $index => $hook_param ) {
			$line_number = intval( $matches[1][$index] );
			$hook_param = trim( $hook_param );
			$hook = str_replace( [ '"', "'" ], '', $hook_param );
			$args = trim( $matches[3][$index] );
			if( empty( $args ) ) {
				$args = $hook_param;
			} else {
				$args = preg_replace('/,(?!\s)/', ', ', $args );
				$args = preg_replace('/\((?!\s)/', '( ', $args );
				$args = preg_replace('/\[(?!\s)/', '[ ', $args );
				$args = preg_replace('/(?<=\S)\)/', ' )', $args );
				$args = preg_replace('/(?<=\S)\]/', ' ]', $args );
				$args = preg_replace('/\s+/', ' ', $args );
				$args = $hook_param . ', ' . $args;
			}
			$hooks[] = [
				'hook' => $hook,
				'line' => $line_number,
				'code' => "{$fn_name}( {$args} )"
			];
		}
		
		return $hooks;
	}

	public function reduce_and_sort( $hooks ) {
		// sort by hook
		usort( $hooks, function( $a, $b ) {
			return strcmp( $a['hook'], $b['hook'] );
		});

		// group aby hook with same name
		$hooks = array_reduce( $hooks, function( $carry, $item ) {
			$carry[ $item['hook'] ][] = $item;
			return $carry;
		}, [] );

		// convert actions to array
		$hooks = array_map( function( $key, $value ) {
			return [ 'hook' => $key, 'usages' => $value, 'expand' => false ];
		}, array_keys( $hooks ), $hooks );

		return $hooks;
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
