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
//
// There are two ways to set it up, and both end in the same two settings:
//
//   - "Connect to DailyStory" signs in to DailyStory and reads the Site ID
//     and Site URL from the account (see class-dailystory-connect.php)
//   - typing the Site ID, or pasting the tracking code, which carries both
// ──────────────────────────────────────────────
class DailyStoryPluginAdmin {

    // Where to find the tracking code in DailyStory
    const TRACKING_CODE_URL = 'https://app.dailystory.com/admin/setup/website/';

    // Result of the last Site ID check, shown on the settings page
    const TRACKING_CHECK_OPTION = 'dailystory_tracking_check';

    // Constructor for the class
    function __construct () {

        add_action('admin_menu', array($this, 'add_menu_items'));
        add_action('admin_init', array($this, 'build_settings_page'));
        add_action('admin_init', array($this, 'activation_redirect'));
        add_action('admin_notices', array($this, 'setup_notice'));
        add_filter('plugin_action_links_' . DAILYSTORY_PLUGIN_SLUG . '/dailystory.php', array($this, 'build_settings_links'));

        // Connect, disconnect and check, all posted to admin-post.php
        add_action('admin_post_dailystory_connect', array($this, 'handle_connect'));
        add_action('admin_post_dailystory_oauth_callback', array($this, 'handle_oauth_callback'));
        add_action('admin_post_nopriv_dailystory_oauth_callback', 'auth_redirect');
        add_action('admin_post_dailystory_confirm_switch', array($this, 'handle_confirm_switch'));
        add_action('admin_post_dailystory_disconnect', array($this, 'handle_disconnect'));
        add_action('admin_post_dailystory_verify', array($this, 'handle_verify'));
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
        $settings_link = '<a href="' . esc_url(self::settings_url()) . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function settings_url ( $args = array() )
    {
        return add_query_arg($args, admin_url('options-general.php?page=' . DAILYSTORY_ADMIN_PAGE));
    }

    // ──────────────────────────────────────────────
    // True once both values the tracking pixel needs are set
    // ──────────────────────────────────────────────
    public static function is_configured ()
    {
        $options = get_option('dailystory_settings');

        return ! empty($options['dailystory_tenant_uid']) && ! empty($options['dailystory_tenant_url']);
    }

    // ──────────────────────────────────────────────
    // After activation, open the settings page so setup starts right away.
    // Skipped for bulk and network activation, and for sites that are already
    // set up (a reactivation).
    // ──────────────────────────────────────────────
    function activation_redirect ()
    {
        if ( ! get_transient('dailystory_activation_redirect') )
            return;

        delete_transient('dailystory_activation_redirect');

        if ( wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi']) )
            return;

        if ( ! current_user_can(DAILYSTORY_ADMIN_CAPABILITY) || self::is_configured() )
            return;

        wp_safe_redirect(self::settings_url());
        exit;
    }

    // ──────────────────────────────────────────────
    // Until DailyStory is set up, say so on the Dashboard and the Plugins
    // screen. Not on every admin page: an unconfigured plugin does nothing
    // harmful, so it does not need to follow the user around.
    // ──────────────────────────────────────────────
    function setup_notice ()
    {
        if ( self::is_configured() || ! current_user_can(DAILYSTORY_ADMIN_CAPABILITY) )
            return;

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ( ! $screen || ! in_array($screen->id, array('dashboard', 'plugins'), true) )
            return;

        printf(
            '<div class="notice notice-warning"><p><b>DailyStory isn\'t connected yet.</b> <a href="%s">Connect your DailyStory account</a> to add popups, web forms and tracking to this site.</p></div>',
            esc_url(self::settings_url())
        );
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
            'Tracking settings',
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
        if ( DailyStoryConnect::is_connected() )
            echo '<p>These were set by connecting your DailyStory account. You only need to change them if DailyStory support asks you to.</p>';
    }

    function dailystory_woocommerce_section_heading ( )
    {
        echo '<p>WooCommerce was detected on this site. Store tracking sends product views, cart activity and completed orders to DailyStory so you can build abandoned cart and post-purchase automations.</p>';
    }

    // ──────────────────────────────────────────────
    // Renders the Settings Page for editing the DailyStory plugin.
    //
    // Three states: a confirmation when a connection would replace another
    // account's tracking, setup for a site that is not configured yet, and
    // status plus settings for one that is.
    // ──────────────────────────────────────────────
    function render_settings_ux ()
    {
        if ( ! current_user_can(DAILYSTORY_ADMIN_CAPABILITY) )
            wp_die('You do not have sufficient permissions to access this page.');

        echo '<div class="wrap">';
        echo '<h1>DailyStory</h1>';

        $this->render_flash();

        $pending = get_transient(self::switch_key());

        if ( ! empty($pending) && isset($_GET['dailystory_confirm']) )
            $this->render_confirm_switch($pending);
        elseif ( self::is_configured() )
            $this->render_configured();
        else
            $this->render_setup();

        echo '</div>';
    }

    // ──────────────────────────────────────────────
    // Not set up yet: connect, or enter the Site ID by hand
    // ──────────────────────────────────────────────
    private function render_setup ()
    {
        $options = get_option('dailystory_settings');
        $url     = isset($options['dailystory_tenant_url']) ? $options['dailystory_tenant_url'] : '';
        ?>
        <div class="card" style="max-width: 640px;">
            <h2>Connect DailyStory</h2>
            <?php if ( DailyStoryConnect::can_connect() ) : ?>
                <p>Sign in to DailyStory and choose the account to connect. Your Site ID and Site URL are filled in for you.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="dailystory_connect" />
                    <?php wp_nonce_field('dailystory_connect'); ?>
                    <?php submit_button('Connect to DailyStory', 'primary large', 'submit', false); ?>
                </form>
                <p>Don't have an account? <a target="_blank" href="https://dailystory.com/pricing">Sign up for free</a>, then come back and connect.</p>
            <?php else : ?>
                <p>One-click connect needs your WordPress admin to use https. Enter your Site ID below instead.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width: 640px;">
            <h2><?php echo DailyStoryConnect::can_connect() ? 'Or enter your Site ID' : 'Enter your Site ID'; ?></h2>
            <p>Paste your Site ID, or the whole tracking code, from <a target="_blank" href="<?php echo esc_url(self::TRACKING_CODE_URL); ?>">Settings &rarr; Website</a> in DailyStory. The tracking code includes your Site URL too.</p>
            <form method="post" action="options.php">
                <?php settings_fields('dailystory_settings_options'); ?>
                <p>
                    <label for="dailystory_tenant_uid" class="screen-reader-text">Site ID or tracking code</label>
                    <textarea id="dailystory_tenant_uid" name="dailystory_settings[dailystory_tenant_uid]" rows="3" class="large-text code" placeholder="Site ID or tracking code"></textarea>
                </p>
                <details<?php echo '' !== $url ? ' open' : ''; ?>>
                    <summary>Advanced: Site URL</summary>
                    <p>Only needed when you enter a Site ID on its own. It's the address in your tracking code, such as https://us-1.dailystory.com.</p>
                    <input id="dailystory_tenant_url" type="text" name="dailystory_settings[dailystory_tenant_url]" class="regular-text code" value="<?php echo esc_attr($url); ?>" placeholder="https://us-1.dailystory.com" />
                </details>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────
    // Set up: connection status, then the settings themselves
    // ──────────────────────────────────────────────
    private function render_configured ()
    {
        $options    = get_option('dailystory_settings');
        $tenant_uid = $options['dailystory_tenant_uid'];
        $connection = DailyStoryConnect::get_connection();
        $connected  = ! empty($connection['tenant_uid']);
        $app_url    = ( $connected && ! empty($connection['app_url']) ) ? $connection['app_url'] : DailyStoryConnect::app_url();
        ?>
        <div class="card" style="max-width: 640px;">
            <h2>Status</h2>
            <?php if ( $connected ) : ?>
                <p>Connected to <strong><?php echo esc_html($connection['account_name'] ? $connection['account_name'] : $connection['tenant_uid']); ?></strong>.</p>
                <?php if ( DailyStoryConnect::STATUS_RECONNECT === $connection['status'] ) : ?>
                    <div class="notice notice-warning inline"><p>
                        <b>This site needs to be reconnected.</b> <?php echo esc_html($connection['last_error']); ?>
                        Tracking keeps working in the meantime.
                    </p></div>
                <?php else : ?>
                    <p><span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> Connection checked <?php echo esc_html(human_time_diff((int) $connection['verified_at'])); ?> ago.</p>
                <?php endif; ?>
                <?php if ( $connection['tenant_uid'] !== $tenant_uid ) : ?>
                    <p class="description">The Site ID below was changed by hand and no longer matches the connected account (<?php echo esc_html($connection['tenant_uid']); ?>).</p>
                <?php endif; ?>
            <?php else : ?>
                <p>Tracking is set up with Site ID <code><?php echo esc_html($tenant_uid); ?></code>.</p>
                <?php $this->render_tracking_check($options); ?>
            <?php endif; ?>

            <p>
                <a class="button" target="_blank" href="<?php echo esc_url($app_url); ?>">Open DailyStory</a>
                <?php if ( DailyStoryConnect::can_connect() && ( ! $connected || DailyStoryConnect::STATUS_RECONNECT === $connection['status'] ) ) : ?>
                    <?php $this->render_action_button('dailystory_connect', $connected ? 'Reconnect' : 'Connect your DailyStory account', 'primary'); ?>
                <?php endif; ?>
                <?php if ( $connected && DailyStoryConnect::STATUS_RECONNECT !== $connection['status'] ) : ?>
                    <?php $this->render_action_button('dailystory_verify', 'Check connection'); ?>
                <?php endif; ?>
            </p>

            <?php if ( ! $connected && DailyStoryConnect::can_connect() ) : ?>
                <p class="description">Connecting your account confirms these settings and keeps them checked.</p>
            <?php endif; ?>

            <details>
                <summary>Disconnect</summary>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Disconnect this site from DailyStory?');">
                    <input type="hidden" name="action" value="dailystory_disconnect" />
                    <?php wp_nonce_field('dailystory_disconnect'); ?>
                    <p><label><input type="checkbox" name="remove_tracking" value="1" /> Also remove the Site ID and stop tracking on this site</label></p>
                    <?php submit_button($connected ? 'Disconnect' : 'Remove Site ID', 'secondary', 'submit', false); ?>
                </form>
            </details>
        </div>

        <div class="card" style="max-width: 640px;">
            <form method="POST" action="options.php">
                <?php
                settings_fields('dailystory_settings_options');
                do_settings_sections(DAILYSTORY_ADMIN_PAGE);
                submit_button('Save Settings');
                ?>
            </form>
        </div>

        <div class="card" style="max-width: 640px;">
            <h2>Add DailyStory to your pages</h2>
            <p>Use these shortcodes in any post, page or widget:</p>
            <ul>
                <li><code>[ds-webform id="FORM_ID"]</code> embeds a DailyStory web form</li>
                <li><code>[ds-popup id="POPUP_ID"]</code> shows a popup when the page loads</li>
                <li><code>[ds-exitintent id="POPUP_ID"]</code> shows a popup when a visitor is about to leave</li>
            </ul>
            <p><a target='_blank' href='https://www.dailystory.com/integrations/wordpress'>Learn more about DailyStory's WordPress integration</a></p>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────
    // Connecting signed in to a different account than the one this site
    // tracks into. Ask before moving tracking to it.
    // ──────────────────────────────────────────────
    private function render_confirm_switch ( $pending )
    {
        $options = get_option('dailystory_settings');
        $website = $pending['website'];
        ?>
        <div class="card" style="max-width: 640px;">
            <h2>Switch DailyStory accounts?</h2>
            <p>This site currently tracks into Site ID <code><?php echo esc_html($options['dailystory_tenant_uid']); ?></code>.</p>
            <p>You signed in to <strong><?php echo esc_html($website['name']); ?></strong> (Site ID <code><?php echo esc_html($website['tenantUid']); ?></code>). Connecting it moves this site's tracking, popups and web forms to that account.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dailystory_confirm_switch" />
                <?php wp_nonce_field('dailystory_confirm_switch'); ?>
                <button type="submit" name="decision" value="switch" class="button button-primary">Switch to <?php echo esc_html($website['name']); ?></button>
                <button type="submit" name="decision" value="cancel" class="button">Cancel</button>
            </form>
            <p class="description">To connect a different DailyStory account, cancel, switch accounts in DailyStory, and connect again.</p>
        </div>
        <?php
    }

    // ──────────────────────────────────────────────
    // Result of the last Site ID check, for a site set up by hand
    // ──────────────────────────────────────────────
    private function render_tracking_check ( $options )
    {
        $check = get_option(self::TRACKING_CHECK_OPTION);

        if ( ! is_array($check) || $check['uid'] !== $options['dailystory_tenant_uid'] || $check['url'] !== $options['dailystory_tenant_url'] )
            return;

        if ( 'ok' === $check['result'] )
            echo '<p><span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> DailyStory recognized this Site ID.</p>';
        else
            echo '<p class="description">This Site ID could not be checked when it was saved. Save the settings again to retry.</p>';
    }

    private function render_action_button ( $action, $label, $type = 'secondary' )
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
            <input type="hidden" name="action" value="<?php echo esc_attr($action); ?>" />
            <?php wp_nonce_field($action); ?>
            <?php submit_button($label, $type, 'submit', false); ?>
        </form>
        <?php
    }

    // ──────────────────────────────────────────────
    // One-time messages from the admin-post handlers, which redirect back here
    // ──────────────────────────────────────────────
    private function render_flash ()
    {
        $flash = get_transient(self::flash_key());

        if ( empty($flash) )
            return;

        delete_transient(self::flash_key());

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($flash['type']),
            esc_html($flash['message'])
        );
    }

    private static function flash ( $type, $message )
    {
        set_transient(self::flash_key(), array( 'type' => $type, 'message' => $message ), 5 * MINUTE_IN_SECONDS);
    }

    private static function flash_key ()
    {
        return 'dailystory_flash_' . get_current_user_id();
    }

    private static function switch_key ()
    {
        return 'dailystory_switch_' . get_current_user_id();
    }

    // ──────────────────────────────────────────────
    // "Connect to DailyStory": off to DailyStory to sign in and approve
    // ──────────────────────────────────────────────
    function handle_connect ()
    {
        self::check_request('dailystory_connect');

        $url = DailyStoryConnect::start();

        if ( is_wp_error($url) ) {
            self::flash('error', $url->get_error_message());
            wp_safe_redirect(self::settings_url());
            exit;
        }

        // DailyStory is not in the safe redirect list, and does not need to
        // be: this URL is built here from the configured app URL.
        wp_redirect($url);
        exit;
    }

    // ──────────────────────────────────────────────
    // Back from DailyStory. This is a GET from an external redirect, so it has
    // no nonce: the OAuth state, which only this user's session holds, is what
    // proves the request was started here.
    //
    // A user whose WordPress session ran out while they were signing in to
    // DailyStory arrives logged out; auth_redirect (hooked for nopriv) sends
    // them through the login page and back here with the code intact.
    // ──────────────────────────────────────────────
    function handle_oauth_callback ()
    {
        if ( ! current_user_can(DAILYSTORY_ADMIN_CAPABILITY) )
            wp_die('You do not have sufficient permissions to access this page.');

        $result = DailyStoryConnect::complete(wp_unslash($_GET));

        if ( is_wp_error($result) ) {
            self::flash('error', $result->get_error_message());
            wp_safe_redirect(self::settings_url());
            exit;
        }

        $options = get_option('dailystory_settings');
        $current = isset($options['dailystory_tenant_uid']) ? $options['dailystory_tenant_uid'] : '';

        // Signed in to a different account than this site already tracks into:
        // hold the result and ask first
        if ( '' !== $current && $current !== $result['website']['tenantUid'] ) {
            set_transient(self::switch_key(), $result, 15 * MINUTE_IN_SECONDS);
            wp_safe_redirect(self::settings_url(array( 'dailystory_confirm' => 1 )));
            exit;
        }

        DailyStoryConnect::apply($result);

        self::flash('success', 'Connected to ' . ( $result['website']['name'] ? $result['website']['name'] : 'DailyStory' ) . '.');
        wp_safe_redirect(self::settings_url());
        exit;
    }

    function handle_confirm_switch ()
    {
        self::check_request('dailystory_confirm_switch');

        $pending = get_transient(self::switch_key());

        delete_transient(self::switch_key());

        if ( empty($pending) ) {
            self::flash('error', 'This request has expired. Please connect again.');
        }
        elseif ( isset($_POST['decision']) && 'switch' === $_POST['decision'] ) {
            DailyStoryConnect::apply($pending);
            self::flash('success', 'Connected to ' . ( $pending['website']['name'] ? $pending['website']['name'] : 'DailyStory' ) . '.');
        }
        else {
            DailyStoryConnect::discard($pending);
            self::flash('info', 'Nothing was changed. This site still tracks into the same account.');
        }

        wp_safe_redirect(self::settings_url());
        exit;
    }

    function handle_disconnect ()
    {
        self::check_request('dailystory_disconnect');

        $remove_tracking = ! empty($_POST['remove_tracking']);

        DailyStoryConnect::disconnect($remove_tracking);

        if ( $remove_tracking )
            self::flash('success', 'Disconnected. DailyStory tracking is off on this site.');
        else
            self::flash('success', 'Disconnected. This site still tracks with the same Site ID.');

        wp_safe_redirect(self::settings_url());
        exit;
    }

    function handle_verify ()
    {
        self::check_request('dailystory_verify');

        $result = DailyStoryConnect::verify();

        if ( is_wp_error($result) )
            self::flash('error', $result->get_error_message());
        else
            self::flash('success', 'The connection to ' . ( $result['name'] ? $result['name'] : 'DailyStory' ) . ' is working.');

        wp_safe_redirect(self::settings_url());
        exit;
    }

    private static function check_request ( $action )
    {
        if ( ! current_user_can(DAILYSTORY_ADMIN_CAPABILITY) )
            wp_die('You do not have sufficient permissions to access this page.');

        check_admin_referer($action);
    }

    // ──────────────────────────────────────────────
    // Read the Site ID and Site URL out of a pasted tracking code.
    //
    // The tracking code on DailyStory's Settings > Website page ends with
    // })(window,document,'SITEID') and loads //DATACENTER.dailystory.com/ds/ds.
    // Returns array(site id, site url) - the url is '' if it is not there - or
    // null when the text is not a tracking code.
    // ──────────────────────────────────────────────
    public static function parse_tracking_code ( $text )
    {
        if ( ! preg_match('#\(\s*window\s*,\s*document\s*,\s*[\'"]([A-Za-z0-9_-]+)[\'"]\s*\)#', $text, $uid) )
            return null;

        $url = '';

        if ( preg_match('#[\'"]((?:https?:)?//[^\'"\s]+?)/ds/ds[\'"]#i', $text, $src) )
            $url = $src[1];

        return array( $uid[1], $url );
    }

    // ──────────────────────────────────────────────
    // Ask DailyStory whether it knows a Site ID.
    //
    // The tracking JSON for an unknown Site ID is an empty object, so that is
    // a definite "not found". Anything else that is not a JSON object (a
    // timeout, a firewall blocking outbound requests, a typo in the Site URL)
    // only means the check could not be made.
    // ──────────────────────────────────────────────
    public static function check_tracking ( $uid, $url )
    {
        $response = wp_remote_get(
            DailyStoryTrackingPixel::normalize_tenant_url($url) . '/ds/' . rawurlencode($uid) . '.json',
            array( 'timeout' => 8 )
        );

        $result = 'unverified';

        if ( ! is_wp_error($response) && 200 === (int) wp_remote_retrieve_response_code($response) ) {

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ( is_array($body) )
                $result = empty($body) ? 'not_found' : 'ok';
        }

        update_option(self::TRACKING_CHECK_OPTION, array(
            'result'     => $result,
            'uid'        => $uid,
            'url'        => $url,
            'checked_at' => time(),
        ), false);

        return $result;
    }

    // ──────────────────────────────────────────────
    // Sanitize the submitted settings.
    //
    // Merges into the stored settings rather than rebuilding them: the setup
    // form only posts the Site ID and Site URL, and a rebuild would drop the
    // rest, including switching WooCommerce store tracking off.
    // ──────────────────────────────────────────────
    public function sanitize ( $input )
    {
        // update_option() runs this filter twice when the option does not exist
        // yet (once itself, once from add_option), and the second pass gets the
        // first pass's output. Keep the Site ID check to one request.
        static $checked = array();

        $existing = get_option('dailystory_settings');

        if ( ! is_array($existing) )
            $existing = array();

        if ( ! is_array($input) )
            return $existing;

        $output = $existing;

        $snippet_url = null;

        if ( isset($input['dailystory_tenant_uid']) ) {

            $raw = trim( (string) $input['dailystory_tenant_uid'] );

            $parsed = self::parse_tracking_code($raw);

            if ( null !== $parsed ) {
                $output['dailystory_tenant_uid'] = $parsed[0];

                if ( '' !== $parsed[1] )
                    $snippet_url = $parsed[1];
            }
            elseif ( '' === $raw || preg_match('/^[A-Za-z0-9_-]{4,64}$/', $raw) ) {
                $output['dailystory_tenant_uid'] = $raw;
            }
            else {
                add_settings_error('dailystory_settings', 'dailystory_tenant_uid',
                    'That doesn\'t look like a Site ID or a DailyStory tracking code. Copy it from Settings > Website in DailyStory and try again.');
            }
        }

        // A tracking code carries its own Site URL, which wins over the field
        if ( null !== $snippet_url )
            $output['dailystory_tenant_url'] = self::sanitize_tenant_url($snippet_url);
        elseif ( isset($input['dailystory_tenant_url']) )
            $output['dailystory_tenant_url'] = self::sanitize_tenant_url($input['dailystory_tenant_url']);

        $output['dailystory_installed'] = 1;
        $output['dailystory_version']   = DAILYSTORY_PLUGIN_VERSION;

        // Checkbox: absent means off, but only when the form that has the
        // checkbox was submitted
        if ( ! empty($input['dailystory_woo_tracking_present']) )
            $output['dailystory_woo_tracking'] = ! empty($input['dailystory_woo_tracking']) ? 1 : 0;
        elseif ( array_key_exists('dailystory_woo_tracking', $input) )
            $output['dailystory_woo_tracking'] = ! empty($input['dailystory_woo_tracking']) ? 1 : 0;

        unset($output['dailystory_woo_tracking_present']);

        $uid = isset($output['dailystory_tenant_uid']) ? $output['dailystory_tenant_uid'] : '';
        $url = isset($output['dailystory_tenant_url']) ? $output['dailystory_tenant_url'] : '';

        if ( '' !== $uid && '' === $url ) {
            add_settings_error('dailystory_settings', 'dailystory_tenant_url',
                'Your Site ID was saved, but DailyStory also needs your Site URL. Paste the whole tracking code, or enter the Site URL under Advanced.', 'warning');
        }

        // Check a Site ID and Site URL that have changed. Only a definite "not
        // found" stops the save; a check that could not be made never does.
        $changed = $uid !== ( isset($existing['dailystory_tenant_uid']) ? $existing['dailystory_tenant_uid'] : '' )
            || $url !== ( isset($existing['dailystory_tenant_url']) ? $existing['dailystory_tenant_url'] : '' );

        if ( $changed && '' !== $uid && '' !== $url && ! isset($checked[ $uid . '|' . $url ]) ) {

            $checked[ $uid . '|' . $url ] = self::check_tracking($uid, $url);

            if ( 'not_found' === $checked[ $uid . '|' . $url ] ) {
                add_settings_error('dailystory_settings', 'dailystory_tenant_uid',
                    'DailyStory doesn\'t recognize Site ID ' . $uid . '. Check it in Settings > Website in DailyStory and try again. Your previous settings were kept.');

                $output['dailystory_tenant_uid'] = isset($existing['dailystory_tenant_uid']) ? $existing['dailystory_tenant_uid'] : '';
                $output['dailystory_tenant_url'] = isset($existing['dailystory_tenant_url']) ? $existing['dailystory_tenant_url'] : '';
            }
            elseif ( 'unverified' === $checked[ $uid . '|' . $url ] ) {
                add_settings_error('dailystory_settings', 'dailystory_tenant_uid',
                    'Settings saved, but DailyStory could not be reached to check your Site ID. Check the Site URL if tracking does not start.', 'warning');
            }
        }

        return $output;
    }

    // ──────────────────────────────────────────────
    // Store an absolute https Site URL.
    //
    // This used to be ltrim($url, 'https:'), which treats its second
    // argument as a *character set*, not a prefix - so any host
    // beginning with h, t, p, s or : lost those characters
    // ("sts.dailystory.com" became "ts.dailystory.com").
    // ──────────────────────────────────────────────
    private static function sanitize_tenant_url ( $url )
    {
        $url = trim( (string) $url );

        // Strip the scheme if one was supplied, along with the leading slashes
        // of a protocol-relative url
        $url = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $url);
        $url = ltrim($url, '/');
        $url = rtrim($url, '/');

        return ( '' === $url ) ? '' : esc_url_raw('https://' . $url);
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

        // The marker tells sanitize() this form had the checkbox, so an
        // unticked box (which posts nothing) can be told apart from a form
        // without it
        echo '<input type="hidden" name="dailystory_settings[dailystory_woo_tracking_present]" value="1"/>';

        printf(
            '<label><input id="dailystory_woo_tracking" type="checkbox" name="dailystory_settings[dailystory_woo_tracking]" value="1" %s/> Send product views, cart activity and completed orders to DailyStory</label>',
            checked($enabled, true, false)
        );
    }
}
