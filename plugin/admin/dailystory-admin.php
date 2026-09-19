<?php
// ──────────────────────────────────────────────
// Prevent direct access to this file
// ──────────────────────────────────────────────
if ( ! defined( 'ABSPATH' ) || ! defined('DAILYSTORY_PLUGIN_VERSION') )
{
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// ──────────────────────────────────────────────
// Page slug for the settings screen. Previously this was
// untrailingslashit(__FILE__) via two constants that held the same value, so
// the same section and its hidden fields were registered twice.
// ──────────────────────────────────────────────
if ( !defined('DAILYSTORY_ADMIN_PAGE') )
    define('DAILYSTORY_ADMIN_PAGE', 'dailystory-admin.php');

// Capability required to view and change DailyStory settings. The Site ID and
// Site URL decide where the beacon JavaScript is loaded from, so this is an
// administrator-level setting, not an author-level one.
if ( !defined('DAILYSTORY_ADMIN_CAPABILITY') )
    define('DAILYSTORY_ADMIN_CAPABILITY', 'manage_options');

// ──────────────────────────────────────────────
// DailyStoryPluginAdmin is used to enable end users to easily
// set their DailyStory site id for integration with WordPress - this
// site id is required to write the tracking pixel and for using shortcodes
// ──────────────────────────────────────────────
class DailyStoryPluginAdmin {

    // Constructor for the class
    function __construct () {

        add_action('admin_menu', array($this, 'add_menu_items'));
        add_action('admin_init', array($this, 'build_settings_page'));
        add_filter('plugin_action_links_' . DAILYSTORY_PLUGIN_SLUG . '/dailystory.php', array($this, 'build_settings_links'));
    }

    // ──────────────────────────────────────────────
    // Add the menu item 'DailyStory Settings' items
    // so users can set their DailyStory Site ID
    // ──────────────────────────────────────────────
    function add_menu_items ()
    {
        add_submenu_page(
            'options-general.php',
            'DailyStory',
            'DailyStory',
            DAILYSTORY_ADMIN_CAPABILITY,
            DAILYSTORY_ADMIN_PAGE,
            array($this, 'render_settings_ux')
        );
    }

    // ──────────────────────────────────────────────
    // Build a list of Settings links displayed in
    // the plugin UX
    // ──────────────────────────────────────────────
    function build_settings_links ( $links )
    {
        $url = admin_url('options-general.php?page=' . DAILYSTORY_ADMIN_PAGE);
        $settings_link = '<a href="' . esc_url($url) . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // ──────────────────────────────────────────────
    // Register the setting, its section and its fields
    // ──────────────────────────────────────────────
    function build_settings_page ()
    {
        register_setting(
            'dailystory_settings_options',
            'dailystory_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize'),
            )
        );

        add_settings_section(
            'dailystory_settings_section',
            '',
            array($this, 'dailystory_settings_section_heading'),
            DAILYSTORY_ADMIN_PAGE
        );

        // Site ID
        add_settings_field(
            'dailystory_tenant_uid',
            'DailyStory Site ID',
            array($this, 'dailystory_tenant_uid_callback'),
            DAILYSTORY_ADMIN_PAGE,
            'dailystory_settings_section'
        );

        // Site URL
        add_settings_field(
            'dailystory_tenant_url',
            'DailyStory Site Url',
            array($this, 'dailystory_tenant_url_callback'),
            DAILYSTORY_ADMIN_PAGE,
            'dailystory_settings_section'
        );

        // WooCommerce store tracking, only when WooCommerce is installed
        if ( class_exists('DailyStoryWooCommerce') && DailyStoryWooCommerce::is_woocommerce_active() ) {

            add_settings_section(
                'dailystory_woocommerce_section',
                'WooCommerce',
                array($this, 'dailystory_woocommerce_section_heading'),
                DAILYSTORY_ADMIN_PAGE
            );

            add_settings_field(
                'dailystory_woo_tracking',
                'Store tracking',
                array($this, 'dailystory_woo_tracking_callback'),
                DAILYSTORY_ADMIN_PAGE,
                'dailystory_woocommerce_section'
            );
        }
    }

    function dailystory_settings_section_heading ( )
    {
        $this->print_hidden_settings_fields();
    }

    function dailystory_woocommerce_section_heading ( )
    {
        echo '<p>WooCommerce was detected on this site. Store tracking sends product views, cart activity and completed orders to DailyStory so you can build abandoned cart and post-purchase automations.</p>';
    }

    function print_hidden_settings_fields ()
    {
        // Hacky solution to solve the Settings API overwriting the default values
        $options = get_option('dailystory_settings');

        $dailystory_installed = ( isset($options['dailystory_installed']) ? $options['dailystory_installed'] : 1 );
        $dailystory_version   = ( isset($options['dailystory_version']) ? $options['dailystory_version'] : DAILYSTORY_PLUGIN_VERSION );

        printf(
            '<input id="dailystory_installed" type="hidden" name="dailystory_settings[dailystory_installed]" value="%d"/>',
            (int) $dailystory_installed
        );

        printf(
            '<input id="dailystory_version" type="hidden" name="dailystory_settings[dailystory_version]" value="%s"/>',
            esc_attr($dailystory_version)
        );
    }

    // ──────────────────────────────────────────────
    // Renders the Settings Page for editing the DailyStory plugin
    // this includes allowing entry of the Site Id along with some
    // helpful links to other resources / docs.
    // ──────────────────────────────────────────────
    function render_settings_ux ()
    {
        if ( ! current_user_can(DAILYSTORY_ADMIN_CAPABILITY) )
            wp_die('You do not have sufficient permissions to access this page.');
        ?>
        <div class="wrap">
            <h2>DailyStory Settings</h2>
            <div id="wpcom-stats-meta-box-container" class="metabox-holder">
                <div class="postbox-container" style="width: 55%;margin-right: 10px;">
                    <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                        <div id="referrers" class="postbox">
                            <h3 class="hndle ui-sortable-handle"><span>Settings</span></h3>
                            <form method="POST" action="options.php">
                                <div class="inside">
                                Enter your DailyStory Site ID and Site URL below to enable DailyStory's WordPress integration.
                                <?php
                                settings_fields('dailystory_settings_options');
                                do_settings_sections(DAILYSTORY_ADMIN_PAGE);
                                ?>
                                </div>
                                <div id="major-publishing-actions">
                                    <div id="publishing-action">
            							<?php submit_button('Save Settings'); ?>
                                    </div>
                                    <div class="clear"></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="postbox-container" style="width:44%;">
                    <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                        <div class="postbox">
                            <h3 class="hndle ui-sortable-handle"><span>Setting up DailyStory's WordPress Integration</span></h3>
                            <div class="inside">
                                <p>
                                <b>I'm already using DailyStory</b>
                                <br>If you are already using DailyStory <a target='_blank' href='https://docs.dailystory.com/article/8omibw2171-word-press-and-dailystory-integration'>see the instructions</a> for setting up WordPress integration.
                                </p>
								<p><b>I'm not using DailyStory</b>
                                <br>First, <a target='_blank' href='https://dailystory.com/pricing'>sign up for free</a>. Then follow the instructions above to get your Site ID and Url.</p>
								<p><b>Learn more about WordPress Integration</b><br><a target='_blank' href='https://www.dailystory.com/integrations/wordpress'>Learn more about DailyStory's WordPress integration</a>, such as the available shortcodes.</p>
                            </div>
                        </div> <!-- end postbox -->
                    </div> <!-- end meta-box -->
                </div> <!-- end post-box -->
            </div> <!-- end container -->
        </div> <!-- end wrap -->
        <?php
    }

    // ──────────────────────────────────────────────
    // Sanitize the submitted settings
    // ──────────────────────────────────────────────
    public function sanitize ( $input )
    {
        $new_input = array();

        if ( ! is_array($input) )
            return $new_input;

        if ( isset($input['dailystory_tenant_uid']) )
            $new_input['dailystory_tenant_uid'] = sanitize_text_field($input['dailystory_tenant_uid']);

        if ( isset($input['dailystory_tenant_url']) ) {

            $url = trim( (string) $input['dailystory_tenant_url'] );

            // Strip the scheme if one was supplied, then store an absolute
            // https URL.
            //
            // This used to be ltrim($url, 'https:'), which treats its second
            // argument as a *character set*, not a prefix - so any host
            // beginning with h, t, p, s or : lost those characters
            // ("sts.dailystory.com" became "ts.dailystory.com").
            $url = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $url);
            $url = ltrim($url, '/');
            $url = rtrim($url, '/');

            $new_input['dailystory_tenant_url'] = ( '' === $url ) ? '' : esc_url_raw('https://' . $url);
        }

        if ( isset($input['dailystory_installed']) )
            $new_input['dailystory_installed'] = (int) $input['dailystory_installed'];

        if ( isset($input['dailystory_version']) )
            $new_input['dailystory_version'] = sanitize_text_field($input['dailystory_version']);

        // Checkbox: absent means off
        $new_input['dailystory_woo_tracking'] = ! empty($input['dailystory_woo_tracking']) ? 1 : 0;

        return $new_input;
    }

    // ──────────────────────────────────────────────
    // Gets the input for the tenant uid
    // ──────────────────────────────────────────────
    function dailystory_tenant_uid_callback ()
    {
        $options = get_option('dailystory_settings');
        $value   = isset($options['dailystory_tenant_uid']) ? $options['dailystory_tenant_uid'] : '';

        printf(
            '<input id="dailystory_tenant_uid" type="text" name="dailystory_settings[dailystory_tenant_uid]" size="15" class="regular-text code active" value="%s"/>',
            esc_attr($value)
        );
    }

    // ──────────────────────────────────────────────
    // Gets the input for the tenant url
    // ──────────────────────────────────────────────
    function dailystory_tenant_url_callback ()
    {
        $options = get_option('dailystory_settings');
        $value   = isset($options['dailystory_tenant_url']) ? $options['dailystory_tenant_url'] : '';

        printf(
            '<input id="dailystory_tenant_url" type="text" name="dailystory_settings[dailystory_tenant_url]" size="15" class="regular-text code active" value="%s" placeholder="https://us-1.dailystory.com"/>',
            esc_attr($value)
        );
    }

    // ──────────────────────────────────────────────
    // Gets the checkbox for WooCommerce store tracking
    // ──────────────────────────────────────────────
    function dailystory_woo_tracking_callback ()
    {
        $options = get_option('dailystory_settings');

        // Default on once WooCommerce is detected
        $enabled = ! isset($options['dailystory_woo_tracking']) || $options['dailystory_woo_tracking'];

        printf(
            '<label><input id="dailystory_woo_tracking" type="checkbox" name="dailystory_settings[dailystory_woo_tracking]" value="1" %s/> Send product views, cart activity and completed orders to DailyStory</label>',
            checked($enabled, true, false)
        );
    }
}
