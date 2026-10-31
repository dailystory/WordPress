<?php
/*
Plugin Name: DailyStory Marketing Automation
Plugin URI: https://docs.dailystory.com/article/8omibw2171-integrations-wordpress
Description: Connect WordPress to DailyStory for popups, web forms and visitor tracking, and turn WooCommerce checkouts and orders into email and SMS automations such as abandoned cart recovery.
Version: 2.4.0
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
			define('DAILYSTORY_PLUGIN_VERSION', '2.4.0');

		// ──────────────────────────────────────────────
		// Load required files
		// ──────────────────────────────────────────────
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-shortcodes.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-tracking-pixel.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-woocommerce.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-connect.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-store-events.php');
		require_once(DAILYSTORY_PLUGIN_PATH . '/includes/class-dailystory-feed.php');

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

		// Daily check that the DailyStory connection is still live. Cron runs on
		// front-end requests too, so this is registered outside the admin.
		add_action( DailyStoryConnect::CRON_HOOK, array('DailyStoryConnect', 'cron_verify') );

		// Queued store events are sent from cron and Action Scheduler runs, so
		// their handler is registered on every request
		DailyStoryStoreEvents::register_handler();

		// Send a newly activated plugin to its settings page
		register_activation_hook(__FILE__, array('DailyStoryPlugin', 'dailystory_activate'));

		// handle uninstall requests
		register_uninstall_hook(__FILE__, array('DailyStoryPlugin', 'dailystory_uninstall'));
	}

	// ──────────────────────────────────────────────
	// Change some DailyStory settings and keep the rest.
	//
	// Every write to dailystory_settings outside the settings form goes through
	// here, so a change to one value can never drop another one (for example
	// switching WooCommerce store tracking off by leaving its key out).
	// ──────────────────────────────────────────────
	public static function update_settings ( $changes ) {

		$options = get_option('dailystory_settings');

		if ( ! is_array($options) )
			$options = array();

		update_option('dailystory_settings', array_merge($options, $changes));
	}

	public static function dailystory_activate () {
		set_transient('dailystory_activation_redirect', 1, 30);

		// New installs add featured images to the RSS feed. Sites upgrading
		// from an earlier version keep their feed as it is until they opt in.
		if ( false === get_option('dailystory_settings') )
			self::update_settings(array('dailystory_feed_images' => 1));
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

		// Featured images in the RSS feed
		if ( DailyStoryFeed::is_enabled() )
			$ds_feed = new DailyStoryFeed();

		// Initialize WooCommerce store tracking when WooCommerce is present
		if ( DailyStoryWooCommerce::is_woocommerce_active() ) {
			$ds_woocommerce = new DailyStoryWooCommerce();

			// Cart recovery links from abandoned cart emails
			DailyStoryStoreEvents::register_recovery();

			// Checkouts and orders, reported from the server to start the
			// store automations. Needs a connection granted website:track.
			if ( DailyStoryStoreEvents::is_enabled() )
				$ds_store_events = new DailyStoryStoreEvents();
		}
	}

	public static function dailystory_uninstall() {
		// Revoke the connection in DailyStory, then remove any options we stored
		DailyStoryConnect::disconnect();

		delete_option('dailystory_settings');
		delete_option(DailyStoryConnect::CLIENT_OPTION);
		delete_option(DailyStoryConnect::LOCK_OPTION);
		delete_option('dailystory_tracking_check');

		// Drop any store events still waiting to be sent
		if ( function_exists('as_unschedule_all_actions') )
			as_unschedule_all_actions(DailyStoryStoreEvents::HOOK);

		wp_unschedule_hook(DailyStoryStoreEvents::HOOK);
	}
}
DailyStoryPlugin::get_instance();	// create an instance
