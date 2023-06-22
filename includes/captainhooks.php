<?php
/**
 * Main CaptainHooks class file
 *
 * @package QuickAL
 * @subpackage Core
 * @since 1.0.0
 */
namespace CAPTAINHOOKS;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

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
		// Refresh hooks.
		register_rest_route(
			'captainhooks/v1',
			'/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_refresh' ),
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
		$hooks = $this->get_path_hooks( $path );

		return rest_ensure_response( $hooks );
	}

	public function rest_refresh( $request ) {
		$path = $request->get_param( 'path' );
		$hooks = $this->get_path_hooks( $path, true );

		return rest_ensure_response( $hooks );
	}

	public function get_path_hooks( $path, $force_refresh = false, $to_cache = true ) {
		$hooks = $force_refresh ? [] : $this->get_cached_hooks( $path );
		if( $force_refresh || ( empty( $hooks['actions'] ) && empty( $hooks['filters'] ) ) ) {
			$hooks = $this->generate_path_hooks( $path );
			if( $to_cache ) {
				$this->cache_hooks( $path, $hooks );
			}
		}

		$actions = $this->reduce_and_sort( $hooks['actions'] );
		$filters = $this->reduce_and_sort( $hooks['filters'] );

		return [
			'actions' => $actions,
			'filters' => $filters,
		];
	}

	public function get_cached_hooks( $path ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'captainhooks_hooks';

		$actions = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE folder = '$path' AND type = 'action'" ),
			ARRAY_A
		);
		$filters = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE folder = '$path' AND type = 'filter'" ),
			ARRAY_A
		);

		return [
			'actions' => $actions,
			'filters' => $filters,
		];
	}

	public function cache_hooks( $path, $hooks ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'captainhooks_hooks';

		$wpdb->query( 
			$wpdb->prepare( "DELETE FROM $table_name WHERE folder = '$path'" )
		);

		$actions = $hooks['actions'];
		$filters = $hooks['filters'];

		$actions = array_map( function( $action ) use ( $path ) {
			return [
				'hook' => $action['hook'],
				'type' => 'action',
				'line' => $action['line'],
				'code' => $action['code'],
				'file' => $action['file'],
				'folder' => $path
			];
		}, $actions );

		$filters = array_map( function( $filter ) use ( $path ) {
			return [
				'hook' => $filter['hook'],
				'type' => 'filter',
				'line' => $filter['line'],
				'code' => $filter['code'],
				'file' => $filter['file'],
				'folder' => $path
			];
		}, $filters );

		$hooks = array_merge( $actions, $filters );

		foreach( $hooks as $hook ) {
			$wpdb->insert( $table_name, $hook );
		}
	}

	public function generate_path_hooks( $path ) {
		$files = $this->get_folder_phps( $path );
		$actions = array();
		$filters = array();

		foreach( $files as $file ) {
			$code = file_get_contents( $file['full_path'] );
			$hooks = $this->get_hooks( $code );

			$actions_new = array_map( function( $action ) use ( $file ) {
				$action['file'] = $file['relative_path'];
				return $action;
			}, $hooks['actions'] );
			$actions = array_merge( $actions, $actions_new );

			$filters_new = array_map( function( $filter ) use ( $file ) {
				$filter['file'] = $file['relative_path'];
				return $filter;
			}, $hooks['filters'] );
			$filters = array_merge( $filters, $filters_new );
		}

		return [
			'actions' => $actions,
			'filters' => $filters,
		];
	}

	public function get_hooks( $code ) {
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );
		$stmts = $parser->parse( $code );
		$visitor = new CaptainhooksVisitor;
		$traverser = new NodeTraverser;
		$traverser->addVisitor( $visitor );
		$traverser->traverse( $stmts );

		return [
			'actions' => $visitor->actions,
			'filters' => $visitor->filters
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
