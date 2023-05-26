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
	 * Register API Routes for QuickAL.
	 *
	 * @since 1.0.0
	 */
	public function register_api_routes() {
	}

	/**
	 * Adds a settings page.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Captain Hooks', 'captainhooks' ),
			__( 'Captain Hooks', 'captainhooks' ),
			'manage_options',
			'captainhooks-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		?>
		<h2 class="captainhooks-settings-title">
			<?php esc_html_e( 'Captain Hooks Settings', 'captainhooks' ); ?>
		</h2>
		<form action="options.php" method="post">
				<?php
				settings_fields( 'captainhooks_settings' );
				do_settings_sections( 'captainhooks_settings' );
				?>
				<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
		</form>
		<?php
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
