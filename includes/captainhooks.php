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

	public function rest_preview( $request ) {
		$path = $request->get_param( 'path' );
		$file = $request->get_param( 'file' );

		$full_path = $path . $file;
		$code = file_get_contents( $full_path );

		return rest_ensure_response(
			[
				'code' => $code,
				'file' => $file
			]
		);
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
		// map actions and convert params to array
		$actions = array_map( function( $action ) {
			$action['params'] = json_decode( $action['params'] );
			return $action;
		}, $actions );

		$filters = $wpdb->get_results( 
			$wpdb->prepare( "SELECT * FROM $table_name WHERE folder = '$path' AND type = 'filter'" ),
			ARRAY_A
		);
		// map filters and convert params to array
		$filters = array_map( function( $filter ) {
			$filter['params'] = json_decode( $filter['params'] );
			return $filter;
		}, $filters );

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
		// add sample
		$hooks = array_map( function( $hook ) {
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

			$hook['sample'] = $sample;
			return $hook;
		}, $hooks );

		// sort by hook
		usort( $hooks, function( $a, $b ) {
			return strcmp( $a['hook'], $b['hook'] );
		});

		// group by hook with same name
		$hooks = array_reduce( $hooks, function( $carry, $item ) {
			$carry[ $item['hook'] ][] = $item;
			return $carry;
		}, [] );

		// convert actions to array
		$hooks = array_map( function( $key, $value ) {
			return [ 'hook' => $key, 'usages' => $value, 'visible' => true, 'expand' => false ];
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
			__( 'Captain Hooks', 'captainhooks' ),
			__( 'Captain Hooks', 'captainhooks' ),
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
		$settings_link = '<a href="options-general.php?page=captainhooks-settings">' . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}
}
