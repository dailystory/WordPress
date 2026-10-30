<?php
// ──────────────────────────────────────────────
// This class injects the DailyStory tracking pixel
// into the WordPress head
// ──────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) exit; // Do not allow direct access

class DailyStoryTrackingPixel {

    // ──────────────────────────────────────────────
    // Constructor does the wireup to callback our
    // function to load the script
    // ──────────────────────────────────────────────
    function __construct () {

        // The "not set up yet" notice lives in the admin class
        // (DailyStoryPluginAdmin::setup_notice).
        if ( ! is_admin() )
        {
            // The beacon belongs in <head>, not the footer. Our own documentation
            // tells customers to place it there, and popups, Magic Forms and any
            // store-behaviour tracking need it running before a visitor can
            // navigate away.
            add_action('wp_head', array($this, 'renderPixel'), 5);
        }

    }

    // ──────────────────────────────────────────────
    // Normalize the stored Site URL into a usable script origin.
    //
    // Values stored by earlier versions may be protocol-relative
    // ("//us-1.dailystory.com") or bare ("us-1.dailystory.com") because the
    // settings sanitizer stripped the scheme with ltrim(). Accept all three.
    // ──────────────────────────────────────────────
    public static function normalize_tenant_url ( $url )
    {
        $url = trim( (string) $url );

        if ( '' === $url )
            return '';

        $url = rtrim($url, '/');

        // Already absolute
        if ( preg_match('#^https?://#i', $url) )
            return $url;

        // Protocol-relative or bare host
        return 'https://' . ltrim($url, '/');
    }

    // ──────────────────────────────────────────────
    // Identity of the signed-in visitor.
    //
    // Previously this class called wp_get_current_user() and discarded the
    // result, so nothing about the signed-in customer ever reached DailyStory.
    // Exposing it lets an anonymous beacon session be tied to a known contact,
    // which is what every store-behaviour automation depends on.
    //
    // Only identity fields are emitted, and a site can suppress this entirely
    // by returning an empty array from the dailystory_identify_visitor filter.
    // ──────────────────────────────────────────────
    public static function get_visitor_identity ()
    {
        $identity = array();

        if ( is_user_logged_in() ) {

            $user = wp_get_current_user();

            if ( $user && $user->ID ) {

                $identity = array(
                    'email'     => $user->user_email,
                    'firstName' => $user->user_firstname,
                    'lastName'  => $user->user_lastname,
                );

                // WooCommerce keeps the customer's phone in billing meta
                if ( function_exists('WC') ) {
                    $phone = get_user_meta($user->ID, 'billing_phone', true);

                    if ( ! empty($phone) )
                        $identity['mobilePhone'] = $phone;
                }

                $identity = array_filter($identity, function ( $value ) {
                    return '' !== $value && null !== $value;
                });
            }
        }

        /**
         * Filter the identity handed to the DailyStory beacon.
         *
         * Return an empty array to disable visitor identification entirely.
         *
         * @param array $identity Identity fields (email, firstName, lastName, mobilePhone).
         */
        return apply_filters('dailystory_identify_visitor', $identity);
    }

    // ──────────────────────────────────────────────
    // Callback function called to insert the rendered pixel
    // ──────────────────────────────────────────────
    function renderPixel ()
    {
        $options = get_option('dailystory_settings');

        if ( empty($options['dailystory_tenant_uid']) || empty($options['dailystory_tenant_url']) )
            return;

        $tenant_url = self::normalize_tenant_url($options['dailystory_tenant_url']);
        $tenant_uid = trim($options['dailystory_tenant_uid']);

        if ( '' === $tenant_url || '' === $tenant_uid )
            return;

        // Sanitize for output
        $tenant_url = esc_url($tenant_url);
        $tenant_uid = esc_js($tenant_uid);

        // This is the script that gets injected, this should be identical
        // to what is on https://app.dailystory.com
        echo "\n" . '<!-- DailyStory Tracking Pixel for WordPress v' . esc_html(DAILYSTORY_PLUGIN_VERSION) . ' -->' . "\n";
        echo '<script type="text/javascript">' . "\n";
        echo '(function(d,a,i,l,y,s,t,o,r,y){' . "\n";
        echo '    d._dsSettings=i;' . "\n";
        echo '    r = a.createElement("script");' . "\n";
        echo '    o = a.getElementsByTagName("script")[0];' . "\n";
        echo '    r.src= "' . $tenant_url . '/ds/ds" + i + ".js";' . "\n";
        echo '    r.async = true;' . "\n";
        echo '    r.id = "ds-sitescript";' . "\n";
        echo '    o.parentNode.insertBefore(r, o);' . "\n";
        echo '})(window,document,"' . $tenant_uid . '");' . "\n";
        echo '</script>' . "\n";

        $identity = self::get_visitor_identity();

        if ( empty($identity) )
            return;

        // Published to the page so other DailyStory code (and the store event
        // tracking in class-dailystory-woocommerce.php) can read it, and sent
        // through the documented sendEvent API.
        //
        // NOTE: the DailyStory JavaScript API has no first-class identify()
        // primitive today - sendEvent is the documented way to get this to the
        // platform. If an identify() is added, call it here instead.
        echo '<script type="text/javascript">' . "\n";
        echo '    window.dsIdentity = ' . wp_json_encode($identity) . ';' . "\n";
        echo '    window.addEventListener("ds_ready", function () {' . "\n";
        echo '        window.Ds && window.Ds.sendEvent("wordpress", "identify", window.dsIdentity);' . "\n";
        echo '    });' . "\n";
        echo '</script>' . "\n";
    }
}
