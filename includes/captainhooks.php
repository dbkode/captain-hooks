<?php
/**
 * Main CaptainHooks class file
 *
 * @package CaptainHooks
 * @subpackage Core
 * @since 1.0.0
 */
namespace CAPTAINHOOKS;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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
		load_plugin_textdomain( 'captain-hooks', false, CAPTAINHOOKS_PLUGIN_DIR . '/languages' );

		// Enqueue admin scripts.
		// add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'defer_parsing_of_js' ), 10 );

		// Register rest functions.
		add_action( 'rest_api_init', array( $this, 'register_api_routes' ) );

		// Add settings page.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add a settings link to the plugins page.
		$plugin_dir_name = basename( CAPTAINHOOKS_PLUGIN_DIR );
		add_filter( 'plugin_action_links_' . $plugin_dir_name . '/captainhooks.php', array( $this, 'add_settings_link' ) );

		// Load live mode hooks
		$this->load_live_mode_hooks();
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
		// Preview file.
		register_rest_route(
			'captainhooks/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_preview' ),
				'permission_callback' => function () {
					return current_user_can('manage_options');
				},
			)
		);
		// Live mode.
		register_rest_route(
			'captainhooks/v1',
			'/livemode',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_livemode' ),
				'permission_callback' => function () {
					return current_user_can('manage_options');
				},
			)
		);
		// Live mode logs.
		register_rest_route(
			'captainhooks/v1',
			'/livemode/logs',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_livemode_logs' ),
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
		$path = urldecode( $path );
		$hooks = $this->get_path_hooks( $path );

		return rest_ensure_response( $hooks );
	}

	/**
	 * Refresh hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_refresh( $request ) {
		$path = $request->get_param( 'path' );
		$path = urldecode( $path );
		$hooks = $this->get_path_hooks( $path, true );

		return rest_ensure_response( $hooks );
	}

	/**
	 * Preview file.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_preview( $request ) {
		$path = $request->get_param( 'path' );
		$path = urldecode( $path );
		$file = $request->get_param( 'file' );
		$file = urldecode( $file );

		$full_path = $path . $file;
		$code_raw = file_get_contents( $full_path );
		$code = htmlspecialchars( $code_raw );

		return rest_ensure_response(
			[
				'code' => $code,
				'file' => $file
			]
		);
	}

	/**
	 * Live mode.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_livemode( $request ) {
		global $wpdb;
		$hook = $request->get_param( 'hook' );
		$type = $request->get_param( 'type' );
		$num_args = $request->get_param( 'num_args' );

		$current_time = current_time('mysql');
		$date = new \DateTime($current_time);
		$date->modify('+10 minutes');
		$future_time = $date->format('Y-m-d H:i:s');

		// check if hook already exists
		$table_name = $wpdb->prefix . 'captainhooks_livemode';
		$hook_exists = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE hook = %s AND type = %s", $hook, $type ),
			ARRAY_A
		);
		if( empty( $hook_exists ) ) {
			$record = [
				'hook' => $hook,
				'type' => $type,
				'num_args' => $num_args,
				'expiry' => $future_time
			];
			$wpdb->insert( $table_name, $record );
		} else {
			$wpdb->update( 
				$table_name, 
				[ 'expiry' => $future_time ],
				[ 'hook' => $hook, 'type' => $type ]
			);
		}

		return rest_ensure_response(true);
	}

	/**
	 * Live mode logs.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function rest_livemode_logs( $request ) {
		global $wpdb;
		$hook = $request->get_param( 'hook' );
		$type = $request->get_param( 'type' );
		$latest = $request->get_param( 'latest' );

		$table_name = $wpdb->prefix . 'captainhooks_livemode_logs';
		if( $latest ) {
			$logs = $wpdb->get_results( 
				$wpdb->prepare( "SELECT * FROM $table_name WHERE hook = %s AND type = %s AND date > %s ORDER BY date DESC LIMIT 1", $hook, $type, $latest ),
				ARRAY_A
			);
		} else {
			$logs = $wpdb->get_results( 
				$wpdb->prepare( "SELECT * FROM $table_name WHERE hook = %s AND type = %s ORDER BY date DESC LIMIT 20", $hook, $type ),
				ARRAY_A
			);
		}

		return rest_ensure_response( $logs );
	}

	/**
	 * Get hooks for a path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to get hooks for.
	 * @param bool $force_refresh Whether to force refresh hooks.
	 * @param bool $to_cache Whether to cache hooks.
	 * @return array Hooks.
	 */
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
		$shortcodes = $this->reduce_and_sort( $hooks['shortcodes'] );

		return [
			'actions' => $actions,
			'filters' => $filters,
			'shortcodes' => $shortcodes
		];
	}

	/**
	 * Get cached hooks for a path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to get cached hooks for.
	 * @return array Hooks.
	 */
	public function get_cached_hooks( $path ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'captainhooks_hooks';

		$actions = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE folder = %s AND type = 'action'", $path ),
			ARRAY_A
		);
		// map actions and convert params to array
		$actions = array_map( function( $action ) {
			$action['params'] = json_decode( $action['params'] );
			return $action;
		}, $actions );

		$filters = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE folder = %s AND type = 'filter'", $path ),
			ARRAY_A
		);
		// map filters and convert params to array
		$filters = array_map( function( $filter ) {
			$filter['params'] = json_decode( $filter['params'] );
			return $filter;
		}, $filters );

		$shortcodes = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE folder = %s AND type = 'shortcode'", $path ),
			ARRAY_A
		);
		// map shortcodes and convert params to array
		$shortcodes = array_map( function( $shortcode ) {
			$shortcode['params'] = json_decode( $shortcode['params'] );
			return $shortcode;
		}, $shortcodes );

		return [
			'actions' => $actions,
			'filters' => $filters,
			'shortcodes' => $shortcodes
		];
	}

	/**
	 * Cache hooks for a path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to cache hooks for.
	 * @param array $hooks Hooks to cache.
	 */
	public function cache_hooks( $path, $hooks ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'captainhooks_hooks';

		$wpdb->query( 
			$wpdb->prepare( "DELETE FROM $table_name WHERE folder = %s", $path )
		);

		$actions = $hooks['actions'];
		$filters = $hooks['filters'];
		$shortcodes = $hooks['shortcodes'];

		$actions = array_map( function( $action ) use ( $path ) {
			return [
				'hook' => $action['hook'],
				'type' => 'action',
				'line_start' => $action['line_start'],
				'line_end' => $action['line_end'],
				'code' => $action['code'],
				'doc_block' => $action['doc_block'],
				'file' => $action['file'],
				'params' => json_encode( $action['params'] ),
				'folder' => $path
			];
		}, $actions );

		$filters = array_map( function( $filter ) use ( $path ) {
			return [
				'hook' => $filter['hook'],
				'type' => 'filter',
				'line_start' => $filter['line_start'],
				'line_end' => $filter['line_end'],
				'code' => $filter['code'],
				'doc_block' => $filter['doc_block'],
				'file' => $filter['file'],
				'params' => json_encode( $filter['params'] ),
				'folder' => $path
			];
		}, $filters );

		$shortcodes = array_map( function( $shortcode ) use ( $path ) {
			return [
				'hook' => $shortcode['hook'],
				'type' => 'shortcode',
				'line_start' => $shortcode['line_start'],
				'line_end' => $shortcode['line_end'],
				'code' => $shortcode['code'],
				'doc_block' => $shortcode['doc_block'],
				'file' => $shortcode['file'],
				'params' => json_encode( $shortcode['params'] ),
				'folder' => $path
			];
		}, $shortcodes );

		$hooks = array_merge( $actions, $filters, $shortcodes );

		foreach( $hooks as $hook ) {
			$wpdb->insert( $table_name, $hook );
		}
	}

	/**
	 * Generate hooks for a path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to generate hooks for.
	 * @return array Hooks.
	 */
	public function generate_path_hooks( $path ) {
		$files = $this->get_folder_phps( $path );
		$actions = array();
		$filters = array();
		$shortcodes = array();

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

			$shortcodes_new = array_map( function( $shortcode ) use ( $file ) {
				$shortcode['file'] = $file['relative_path'];
				return $shortcode;
			}, $hooks['shortcodes'] );
			$shortcodes = array_merge( $shortcodes, $shortcodes_new );
		}

		return [
			'actions' => $actions,
			'filters' => $filters,
			'shortcodes' => $shortcodes
		];
	}

	/**
	 * Get hooks from code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Code to get hooks for.
	 * @return array Hooks.
	 */
	public function get_hooks( $code ) {
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );
		$stmts = $parser->parse( $code );
		$visitor = new CaptainhooksVisitor;
		$traverser = new NodeTraverser;
		$traverser->addVisitor( $visitor );
		$traverser->traverse( $stmts );

		return [
			'actions' => $visitor->actions,
			'filters' => $visitor->filters,
			'shortcodes' => $visitor->shortcodes
		];
	}

	/**
	 * Get all PHP files in a folder.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to get PHP files for.
	 * @return array PHP files.
	 */
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

			if (preg_match('/wp-content\/(themes|plugins)\/[^\/]+\/(vendor|test|tests)\//', $full_path)) {
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

	/**
	 * Reduce and sort hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $hooks Hooks to reduce and sort.
	 * @return array Reduced and sorted hooks.
	 */
	public function reduce_and_sort( $hooks ) {
		// add sample
		$hooks = array_map( function( $hook ) {
			if( 'shortcode' === $hook['type'] ) {
				$hook['sample'] = '';
				$hook['num_args'] = 1;
				return $hook;
			}
			$params = $hook['params'];
			$args = array_slice( $params, 1 );
			$args_str = implode( ', ', $args );
			if( ! empty( $args_str ) ) {
				$args_str = ' ' . $args_str . ' ';
			}
			$num_args = count( $args );
			$cmd = 'action' === $hook['type'] ? 'add_action' : 'add_filter';

			$sample = "<?php\n";
			$sample .= "{$cmd}( {$params[0]}, 'my_function', 10, {$num_args} );\n\n";
			$sample .= "function my_function({$args_str}) {\n";
			$sample .= "\t// your code\n";
			if( 'filter' === $hook['type'] ) {
				$sample .= "\treturn \$something;\n";
			}
			$sample .= "}\n"; 

			$hook['sample'] = htmlspecialchars( $sample );
			$hook['num_args'] = $num_args;
			return $hook;
		}, $hooks );

		// sort by hook
		usort( $hooks, function( $a, $b ) {
			$cmp = strcmp($a['hook'], $b['hook']);
			if ($cmp != 0) {
					return $cmp;
			} else {
					return $a['line_start'] - $b['line_start'];
			}
		});

		// group by hook with same name
		$hooks = array_reduce( $hooks, function( $carry, $item ) {
			$carry[ $item['hook'] ][] = $item;
			return $carry;
		}, [] );

		// convert actions to array
		$hooks = array_map( function( $key, $value ) {
			// check if has docblock
			$doc_block = '';
			foreach( $value as $item ) {
				if( ! empty( $item['doc_block'] ) ) {
					$doc_block = $item['doc_block'];
					break;
				}
			}
			return [ 
				'hook' => $key,
				'type' => $value[0]['type'],
				'num_args' => $value[0]['num_args'],
				'doc_block' => $doc_block,
				'sample' => $value[0]['sample'],
				'usages' => $value,
				'visible' => true,
				'expand' => false
			];
		}, array_keys( $hooks ), $hooks );

		return $hooks;
	}

	/**
	 * Adds a settings page.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		$hook_sufix = add_management_page(
			__( 'Captain Hooks', 'captain-hooks' ),
			__( 'Captain Hooks', 'captain-hooks' ),
			'manage_options',
			'captainhooks-page',
			array( $this, 'render_page' )
		);
		add_action( 'admin_print_scripts-' . $hook_sufix, array( $this, 'admin_scripts' ) );
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
		$settings_link = '<a href="tools.php?page=captainhooks-page">' . __( 'Settings', 'captain-hooks' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}

	/**
	 * Load live mode hooks.
	 *
	 * @since 1.0.0
	 */
	public function load_live_mode_hooks() {
		global $wpdb;
		// get all hooks where expiry is in the future
		$current_time = current_time('mysql');
		$table_name = $wpdb->prefix . 'captainhooks_livemode';
		$hooks = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE expiry > %s", $current_time ),
			ARRAY_A
		);

		foreach( $hooks as $hook ) {
			if( 'action' === $hook['type'] ) {
				add_action( $hook['hook'], array( $this, 'live_mode_action_callback' ), 10, $hook['num_args'] );
			} else if( 'filter' === $hook['type'] ) {
				add_filter( $hook['hook'], array( $this, 'live_mode_filter_callback' ), 10, $hook['num_args'] );
			}
		}
	}

	/**
	 * Live mode action/filter callback.
	 *
	 * @since 1.0.0
	 */
	public function live_mode_action_callback() {
		global $wpdb;

		$hook = current_filter();
		$args = func_get_args();
		$log = [];

		$count = 0;
		foreach( $args as $arg ) {
			$count++;
			$log["arg{$count}"] = $arg;
		}

		$record = [
			'hook' => $hook,
			'type' => 'action',
			'log' => json_encode( $log )
		];
		$table_name = $wpdb->prefix . 'captainhooks_livemode_logs';
		$wpdb->insert( $table_name, $record );
	}

	/**
	 * Live mode filter callback.
	 *
	 * @since 1.0.0
	 */
	public function live_mode_filter_callback() {
		global $wpdb;

		$hook = current_filter();
		$args = func_get_args();
		$log = [];

		$count = 0;
		foreach( $args as $arg ) {
			$count++;
			$log["arg{$count}"] = $arg;
		}

		$record = [
			'hook' => $hook,
			'type' => 'filter',
			'log' => json_encode( $log )
		];
		$table_name = $wpdb->prefix . 'captainhooks_livemode_logs';
		$wpdb->insert( $table_name, $record );

		return $args[0];
	}
}
