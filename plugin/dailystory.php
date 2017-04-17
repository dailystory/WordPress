<?php
/*
Plugin Name: DailyStory
Plugin URI: https://www.dailystory.com/integrations/wordpress
Description: DailyStory's WordPress plugin enables DailyStory customers to easily install the DailyStory tracking code and use WordPress shortcodes within existing WordPress blogs and websites.
Version: 1.0.3
Author: DailyStory
Author URI: https://www.dailystory.com/
License: GPL v2 or later
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

		if ( !defined('DAILYSTORY_PLUGIN_VERSION') )
			define('DAILYSTORY_PLUGIN_VERSION', '1.0.2');

		// ──────────────────────────────────────────────
		// Load required files
		// ──────────────────────────────────────────────
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-shortcodes.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-tracking-pixel.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/admin/dailystory-admin.php');

		// Load this after other WordPress plugins with a priority of 10
		// this performs the initialization of the plug in
		add_action( 'init', array($this,'dailystory_init'), 10 );

		if ( is_admin() ) {

			// handle uninstall requests
			register_uninstall_hook(__FILE__, array($this,'dailystory_uninstall'));

		}

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
		// Initialize tracking pixel
		$ds_trackingpixel = new DailyStoryTrackingPixel();

		// Initialize shortcodes
		$ds_shortcodes = new DailyStoryShortCodes();
	}

	private function dailystory_uninstall () {
		// Remove any options we stored
		delete_option('dailystory_settings');
	}
}
DailyStoryPlugin::get_instance();	// create an instance
?>
