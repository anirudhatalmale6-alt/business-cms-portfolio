<?php
/**
 * Plugin Name:       Business CMS Core
 * Plugin URI:        https://github.com/anirudhatalmale6-alt/business-cms-portfolio
 * Description:       Portfolio engine for the corporate CMS: projects, industries, case-study blocks, enquiry capture with spam filtering, analytics hooks, CRM hand-off and SEO output. Front-end presentation lives in the theme; everything structural lives here so the site survives a theme change.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Anirudha Talmale
 * License:           GPL-2.0-or-later
 * Text Domain:       business-cms
 *
 * @package BusinessCMS
 */

defined( 'ABSPATH' ) || exit;

define( 'BCMS_VERSION', '1.0.0' );
define( 'BCMS_FILE', __FILE__ );
define( 'BCMS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BCMS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Everything is loaded through this one manifest so a future module
 * (shop, blog taxonomy, careers) is a single line, not a hunt through hooks.
 */
final class BCMS_Plugin {

	private static ?BCMS_Plugin $instance = null;

	/** @var array<string,object> */
	private array $modules = array();

	public static function instance(): BCMS_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load();
		add_action( 'init', array( $this, 'load_textdomain' ) );
		register_activation_hook( BCMS_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( BCMS_FILE, array( __CLASS__, 'deactivate' ) );
	}

	private function load(): void {
		$files = array(
			'settings'   => 'class-bcms-settings.php',
			'post-types' => 'class-bcms-post-types.php',
			'meta'       => 'class-bcms-meta.php',
			'roles'      => 'class-bcms-roles.php',
			'blocks'     => 'class-bcms-blocks.php',
			'forms'      => 'class-bcms-forms.php',
			'crm'        => 'class-bcms-crm.php',
			'analytics'  => 'class-bcms-analytics.php',
			'seo'        => 'class-bcms-seo.php',
			'admin'      => 'class-bcms-admin.php',
		);

		foreach ( $files as $key => $file ) {
			$path = BCMS_DIR . 'includes/' . $file;
			if ( ! file_exists( $path ) ) {
				continue;
			}
			require_once $path;
			$class = 'BCMS_' . str_replace( '-', '_', ucwords( $key, '-' ) );
			if ( class_exists( $class ) ) {
				$this->modules[ $key ] = new $class();
			}
		}
	}

	public function module( string $key ): ?object {
		return $this->modules[ $key ] ?? null;
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'business-cms', false, dirname( plugin_basename( BCMS_FILE ) ) . '/languages' );
	}

	/**
	 * Activation has to register post types itself: rewrite rules are flushed
	 * from the rules that exist *at this moment*, not the ones init will add later.
	 */
	public static function activate(): void {
		require_once BCMS_DIR . 'includes/class-bcms-post-types.php';
		require_once BCMS_DIR . 'includes/class-bcms-roles.php';
		( new BCMS_Post_Types() )->register();
		BCMS_Roles::install();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}

BCMS_Plugin::instance();
