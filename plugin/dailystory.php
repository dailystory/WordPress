<?php
/*
Plugin Name: DailyStory Marketing Automation
Plugin URI: https://docs.dailystory.com/article/8omibw2171-integrations-wordpress
Description: The DailyStory plugin simplifies the process of adding popups, web forms, conversion tracking, WooCommerce store tracking, and other features to your WordPress website.
Version: 2.2.0
Requires at least: 5.0
Requires PHP: 7.2
Author: DailyStory
Author URI: https://www.dailystory.com/integrations/wordpress/
License: GPL v2 or later
Text Domain: dailystory
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Do not allow direct access

class DailyStoryPlugin {
	private static $instance = null;

	private function __construct() {

		// ──────────────────────────────────────────────
		// Define constants used to access required files
		// ──────────────────────────────────────────────
		if ( !defined('DAILYSTORY_SHORTCODE_PATH') )
			define('DAILYSTORY_SHORTCODE_PATH', untrailingslashit(plugins_url('', __FILE__ )));

		if ( !defined('DAILYSTORY_PLUGIN_PATH') )
			define('DAILYSTORY_PLUGIN_PATH', untrailingslashit(dirname( __FILE__ )));

		if ( !defined('DAILYSTORY_PLUGIN_SLUG') )
			define('DAILYSTORY_PLUGIN_SLUG', basename(dirname(__FILE__)));

		// Absolute path to this file. WooCommerce feature declarations need it
		// to identify the plugin, and it must be the main plugin file.
		if ( !defined('DAILYSTORY_PLUGIN_FILE') )
			define('DAILYSTORY_PLUGIN_FILE', __FILE__);

		// Keep in step with the Version header above. This value cache-busts
		// every script and stylesheet the plugin enqueues, so when it drifts
		// behind the header (as it did at 2.1.4 through 2.1.6) asset updates
		// ship under a stale version string and stay cached.
		if ( !defined('DAILYSTORY_PLUGIN_VERSION') )
			define('DAILYSTORY_PLUGIN_VERSION', '2.2.0');

		// ──────────────────────────────────────────────
		// Load required files
		// ──────────────────────────────────────────────
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-shortcodes.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-tracking-pixel.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-woocommerce.php');

		// The admin class pulls in wp-admin/includes/plugin.php, so it must not
		// load on front-end requests.
		if ( is_admin() )
			require_once(DAILYSTORY_PLUGIN_PATH . '/admin/dailystory-admin.php');

		// Load this after other WordPress plugins with a priority of 10
		// this performs the initialization of the plug in
		add_action( 'init', array($this,'dailystory_init'), 10 );

		// WooCommerce feature declarations must be registered before
		// WooCommerce initializes, which is earlier than 'init'.
		add_action( 'before_woocommerce_init', array('DailyStoryWooCommerce', 'declare_hpos_compatibility') );

		// handle uninstall requests
		register_uninstall_hook(__FILE__, array('DailyStoryPlugin', 'dailystory_uninstall'));
	}

	// ──────────────────────────────────────────────
	// Used to create an instance of this class
	// ──────────────────────────────────────────────
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	// ──────────────────────────────────────────────
	// Initialize the plug in, this is called once all
	// the plugs in are loaded
	// ──────────────────────────────────────────────
	public static function dailystory_init () {

		// Initialize admin settings
		if ( is_admin() && class_exists('DailyStoryPluginAdmin') ) {
			$ds_admin = new DailyStoryPluginAdmin();
		}

		// Initialize tracking pixel
		$ds_trackingpixel = new DailyStoryTrackingPixel();

		// Initialize shortcodes
		$ds_shortcodes = new DailyStoryShortCodes();

		// Initialize WooCommerce store tracking when WooCommerce is present
		if ( DailyStoryWooCommerce::is_woocommerce_active() ) {
			$ds_woocommerce = new DailyStoryWooCommerce();
		}
	}

	public static function dailystory_uninstall() {
		// Remove any options we stored
		delete_option('dailystory_settings');
	}
}
DailyStoryPlugin::get_instance();	// create an instance
