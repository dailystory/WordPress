<?php
// ──────────────────────────────────────────────
// Prevent direct access to this file
// ──────────────────────────────────────────────
if ( !defined('DAILYSTORY_PLUGIN_VERSION') )
{
    header('HTTP/1.0 403 Forbidden');
    die;
}

// Include the required files for the plugin
include_once(ABSPATH . 'wp-admin/includes/plugin.php');

// ──────────────────────────────────────────────
// Constant for accessing the admin file(s)
// ──────────────────────────────────────────────
if ( !defined('DAILYSTORY_ADMIN_PATH') )
    define('DAILYSTORY_ADMIN_PATH', untrailingslashit(__FILE__));

// ──────────────────────────────────────────────
// DailyStoryPluginAdmin is used to enable end users to easily
// set their DailyStory site id for integration with WordPress - this
// site id is required to writ the tracking pixel and for using shortcodes
// ──────────────────────────────────────────────
class DailyStoryPluginAdmin {
    
    // Constructor for the class
    function __construct () {

        // Get the option values for DailyStory
        $options = get_option('dailystory_settings');

        add_action('admin_menu', array(&$this, 'add_menu_items'));
        add_action('admin_init', array(&$this, 'build_settings_page'));
        add_filter('plugin_action_links_' . DAILYSTORY_PLUGIN_SLUG . '/dailystory.php', array($this, 'build_settings_links'));
    }

    // ──────────────────────────────────────────────
    // Add the menu item 'DailyStory Settings' items 
    // so users can set their DailyStory Site ID
    // ──────────────────────────────────────────────
    function add_menu_items ()
    {
    	add_submenu_page('options-general.php', 'DailyStory', 'DailyStory', 'edit_posts', basename(__FILE__), array($this, 'render_settings_ux'));
    }


    // ──────────────────────────────────────────────
    // Build a list of Settings links displayed in
    // the plugin UX
    // ──────────────────────────────────────────────
    function build_settings_links ( $links )
    {
        $url = get_admin_url() . 'options-general.php?page=dailystory-admin.php';
        $settings_link = '<a href="' . $url . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // ──────────────────────────────────────────────
    // Build a list of Settings links displayed in
    // the plugin UX
    // ──────────────────────────────────────────────
    function build_settings_page ()
    {
        global $pagenow;
        $options = get_option('dailystory_settings');

        register_setting(
            'dailystory_settings_options',
            'dailystory_settings',
            array($this, 'sanitize')
        );


        add_settings_section(
            'dailystory_settings_section',
            '',
            array($this, 'dailystory_settings_section_heading'),
            DAILYSTORY_ADMIN_PATH
        );

        add_settings_field(
            'dailystory_tenant_uid',
            'DailyStory Site ID',
            array($this, 'dailystory_tenant_uid_callback'),
            DAILYSTORY_ADMIN_PATH,
            'dailystory_settings_section'
        );
    }

    function dailystory_settings_section_heading ( )
    {
        $this->print_hidden_settings_fields();
    }

    function print_hidden_settings_fields ()
    {
         // Hacky solution to solve the Settings API overwriting the default values
        $options = get_option('dailystory_settings');

        $dailystory_installed = ( isset($options['dailystory_installed']) ? $options['dailystory_installed'] : 1 );
        $dailystory_version   = ( isset($options['dailystory_version']) ? $options['dailystory_version'] : DAILYSTORY_PLUGIN_VERSION );

        printf(
            '<input id="dailystory_installed" type="hidden" name="dailystory_settings[dailystory_installed]" value="%d"/>',
            $dailystory_installed
        );

        printf(
            '<input id="dailystory_version" type="hidden" name="dailystory_settings[dailystory_version]" value="%s"/>',
            $dailystory_version
        );
    }

    // ──────────────────────────────────────────────
    // Renders the Settings Page for editing the DailyStory plugin
    // this includes allowing entry of the Site Id along with some
    // helpful links to other resources / docs.
    // ──────────────────────────────────────────────
    function render_settings_ux ()
    {
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
                                Enter your DailyStory Site ID below to enable DailyStory's WordPress integration.
                                <?php
                                settings_fields('dailystory_settings_options');
                                do_settings_sections(DAILYSTORY_ADMIN_PATH);
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
                                <br>If you are already using DailyStory <a target='_blank' href='https://login.dailystory.com/login?ReturnUrl=%2FAccount%2FTrackCode'>go here to get your Site ID</a> (may require you to login).
                                </p>
								<p><b>I'm not using DailyStory</b>
                                <br>First, <a target='_blank' href='https://login.dailystory.com/trial'>sign up for a free 30-day trial</a>. Then follow the instructions above to get your Site ID.</a></p>
								<p><b>Learn more about WordPress Integration</b><br><a target='_blank' href='https://www.dailystory.com/integrations/wordpress'>Learn more about DailyStory's WordPress integration</a>, such as the available shortcodes.
                            </div>
                        </div> <!-- end postbox -->
                    </div> <!-- end meta-box -->
                </div> <!-- end post-box -->
            </div> <!-- end container -->
        </div> <!-- end wrap -->
        <?php
    }

    // ──────────────────────────────────────────────
    // 
    // ──────────────────────────────────────────────
    public function sanitize ( $input )
    {
        $new_input = array();

        $options = get_option('dailystory_settings');

        if ( isset($input['dailystory_tenant_uid']) )
            $new_input['dailystory_tenant_uid'] = $input['dailystory_tenant_uid'];

        if ( isset($input['dailystory_installed']) )
            $new_input['dailystory_installed'] = $input['dailystory_installed'];

        if ( isset($input['dailystory_version']) )
            $new_input['dailystory_version'] = $input['dailystory_version'];

        return $new_input;
    }

    // ──────────────────────────────────────────────
    // Gets the input for the tenant uid
    // ──────────────────────────────────────────────
    function dailystory_tenant_uid_callback ()
    {
        // Get the option values for DailyStory if we find them
        $options = get_option('dailystory_settings');
        $dailystory_tenant_uid  = ( isset($options['dailystory_tenant_uid']) && $options['dailystory_tenant_uid'] ? $options['dailystory_tenant_uid'] : '' );

        // Render the input textbox with any found value
        printf(
            '<input id="dailystory_tenant_uid" type="text" id="title" name="dailystory_settings[dailystory_tenant_uid]" size="15" class="regular-text code active" value="%s"/>',
            $dailystory_tenant_uid
        );
    }
}

?>