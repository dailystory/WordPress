<?php
// ──────────────────────────────────────────────
// This class manages all the shortcodes, such as [ds-webform]
// ──────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) exit; // Do not allow direct access

class DailyStoryShortCodes {

    function __construct () {

        // Ensure the plugin is configured
        $options = get_option('dailystory_settings');

        // If we don't have a tenant uid value we can't process the shortcodes
        if ( ! isset($options['dailystory_tenant_uid']) || ! $options['dailystory_tenant_uid'] ) {
            return;
        }

        // Add the [ds-webform] shortcode
        add_shortcode('ds-webform', array( 'DailyStoryShortCodes', 'dailystory_webform_shortcode' ));
        // Add the [ds-exitintent] shortcode
        add_shortcode('ds-exitintent', array( 'DailyStoryShortCodes', 'dailystory_exit_intent_shortcode' ));
        // Add the [ds-popup] shortcode
        add_shortcode('ds-popup', array( 'DailyStoryShortCodes', 'dailystory_popup_shortcode' ));
    }

    // ──────────────────────────────────────────────
    // Returns the body content from a URL.
    //
    // Uses the WordPress HTTP API rather than raw cURL so that host proxy
    // settings, filters and non-cURL transports are honoured - and so that TLS
    // certificates are actually verified. The previous implementation set
    // CURLOPT_SSL_VERIFYPEER to 0, which made this response (rendered into the
    // page unescaped) spoofable by anyone on the network path.
    //
    // Returns array($body, $error). $body is '' when the request failed.
    // ──────────────────────────────────────────────
    public static function get_data( $url ) {

        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 10,
                'redirection' => 5,
                'sslverify'   => true,
                'headers'     => array( 'Accept' => 'text/html' ),
            )
        );

        if ( is_wp_error($response) ) {
            return array( '', $response->get_error_code() . ': ' . $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ( $code < 200 || $code >= 300 ) {
            return array( '', 'HTTP ' . $code );
        }

        return array( wp_remote_retrieve_body($response), '' );
    }

    // ──────────────────────────────────────────────
    // Queue a snippet of inline JavaScript for the footer.
    //
    // Shortcodes normally run while the content filter is applied, which is
    // before wp_footer. If one is rendered later than that (a block template
    // part, a REST render, do_shortcode() in a footer widget) the action would
    // never fire, so fall back to echoing in place.
    // ──────────────────────────────────────────────
    private static function enqueue_footer_script( $script ) {

        if ( did_action('wp_footer') ) {
            echo $script;
            return;
        }

        add_action('wp_footer', function () use ( $script ) {
            echo $script;
        });
    }

    // ──────────────────────────────────────────────
    // Routine for [ds-exitintent] shortcode, this shortcode
    // forces a dailystory exit intent to display (if it exists)
    // ──────────────────────────────────────────────
    public static function dailystory_exit_intent_shortcode( $atts, $content = null ) {

        // normalize attribute keys, lowercase
        $atts = array_change_key_case( (array) $atts, CASE_LOWER );

        // override default attributes with user attributes
        $atts = shortcode_atts( array( 'id' => '0' ), $atts );

        // Popup ids are numeric and are written into a JavaScript call, so cast
        // rather than HTML-escape - esc_html() does not make a value safe in a JS
        // context, and the previous esc_html__() also misused the translation API.
        $popup_id = absint( $atts['id'] );

        if ( ! $popup_id )
            return '';

        self::enqueue_footer_script(
            "\n<!-- Added by DailyStory WordPress shortcode -->\n"
            . "<script type='text/javascript'>\n"
            . "window.addEventListener('ds_popup_ready', function (e) {\n"
            . "    Ds.Pop.showPopupOnExit(" . $popup_id . ");\n"
            . "});\n"
            . "</script>\n"
        );

        return '';
    }

    // ──────────────────────────────────────────────
    // Routine for [ds-popup] shortcode, this shortcode
    // forces a dailystory popup to display (if it exists)
    // ──────────────────────────────────────────────
    public static function dailystory_popup_shortcode( $atts, $content = null ) {

        // normalize attribute keys, lowercase
        $atts = array_change_key_case( (array) $atts, CASE_LOWER );

        // override default attributes with user attributes
        $atts = shortcode_atts( array( 'id' => '0' ), $atts );

        $popup_id = absint( $atts['id'] );

        if ( ! $popup_id )
            return '';

        self::enqueue_footer_script(
            "\n<!-- Added by DailyStory WordPress shortcode -->\n"
            . "<script type='text/javascript'>\n"
            . "window.addEventListener('ds_popup_ready', function (e) {\n"
            . "    Ds.Pop.showPopup(" . $popup_id . ");\n"
            . "});\n"
            . "</script>\n"
        );

        return '';
    }

    // ──────────────────────────────────────────────
    // Routine for [ds-webform] shortcode, this shortcode returns
    // the HTML for a form that is rendered inline within a WordPress page
    // control of the UX, postflow, etc. is managed within DailyStory
    // ──────────────────────────────────────────────
    public static function dailystory_webform_shortcode( $atts, $content = null ) {

        // normalize attribute keys, lowercase
        $atts = array_change_key_case( (array) $atts, CASE_LOWER );

        // override default attributes with user attributes
        $atts = shortcode_atts( array( 'id' => '0', 'redirect_url' => '' ), $atts );

        $webform_id   = sanitize_text_field( $atts['id'] );
        $redirect_url = esc_url_raw( $atts['redirect_url'] );

        if ( '' === $webform_id || '0' === $webform_id )
            return '';

        // get the tenant uid
        $options   = get_option('dailystory_settings');
        $tenantuid = isset($options['dailystory_tenant_uid']) ? $options['dailystory_tenant_uid'] : '';

        if ( '' === $tenantuid )
            return '';

        // Add the script reference, pulled from DailyStory, but eventually will be served from a CDN
        wp_enqueue_script('ds-landingpages', 'https://pages.dailystory.com/bundles/dailystory-landingpage.min.js', array(), DAILYSTORY_PLUGIN_VERSION, true);

        // Add the script reference for iti
        wp_enqueue_script('ds-iti-script', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.15/js/intlTelInput.min.js', array(), DAILYSTORY_PLUGIN_VERSION, true);

        // enqueue css
        wp_enqueue_style('ds-webform', 'https://forms.dailystory.com/content/hosted-webform.min.css', array(), DAILYSTORY_PLUGIN_VERSION, 'all');
        wp_enqueue_style('ds-iti', 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css', array(), DAILYSTORY_PLUGIN_VERSION, 'all');

        // Build the URL with query string
        $url = 'https://forms.dailystory.com/webform/' . rawurlencode($tenantuid) . '/' . rawurlencode($webform_id);

        if ( ! empty($redirect_url) ) {
            $url = add_query_arg('redirectUrl', rawurlencode($redirect_url), $url);
        }

        // get the contents
        list( $body, $error ) = self::get_data($url);

        if ( '' !== $error ) {

            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                return '<!-- DailyStory web form ' . esc_html($webform_id) . ' could not be loaded: ' . esc_html($error) . ' -->';
            }

            return '';
        }

        // The body is markup generated by DailyStory for this tenant's form and is
        // meant to render as HTML. It is fetched over a verified TLS connection.
        return $body;
    }
}
