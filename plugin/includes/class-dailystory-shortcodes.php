<?php
// ──────────────────────────────────────────────
// This class manages all the shortcodes, such as [ds-webform]
// ──────────────────────────────────────────────
class DailyStoryShortCodes {
	
    function __construct () {
    	
    	// Ensure the plugin is configured
    	$options = get_option('dailystory_settings');
		// If we don't have a tenant uid value we can't process the shortcodes
    	if ( ! isset($options['dailystory_tenant_uid']) || ( isset($options['dailystory_tenant_uid']) && ! $options['dailystory_tenant_uid'] ) ) {
    		return;
    	} else {
	        // Add the [ds-webform] shortcode
    	    add_shortcode('ds-webform', array( 'DailyStoryShortCodes', 'dailystory_webform_shortcode' ));
	        // Add the [ds-exitintent] shortcode
    	    add_shortcode('ds-exitintent', array( 'DailyStoryShortCodes', 'dailystory_exit_intent_shortcode' ));
	        // Add the [ds-popup] shortcode
    	    add_shortcode('ds-popup', array( 'DailyStoryShortCodes', 'dailystory_popup_shortcode' ));
        }
    }
	// ──────────────────────────────────────────────
	// Returns the body content from a URL
	//
	// TODO: this is a candidate to move into the common function library
	// ──────────────────────────────────────────────
	public static function get_data($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_POST, 0);
		$cc_result = curl_exec($ch);
		if (curl_errno($ch) || curl_error($ch)) {
			$cc_error = curl_errno($ch) . ": " . curl_error($ch);
		}
		curl_close($ch);
		return(array($cc_result, $cc_error));
	} // end get_data function
	// ──────────────────────────────────────────────
	// Routine for [ds-exitintent] shortcode, this shortcode 
	// forces a dailystory exit intent to display (if it exists)
	// ──────────────────────────────────────────────
	public static function dailystory_exit_intent_shortcode($atts, $content=null) {
    	// normalize attribute keys, lowercase
    	$atts = array_change_key_case((array)$atts, CASE_LOWER);
 
    	// override default attributes with user attributes
    	$popup_id = shortcode_atts(['id' => '0',], $atts, $tag);
		$popup_id = esc_html__($popup_id['id'], 'ds-exitintent');
		add_action('wp_footer', function ( $content ) use ($popup_id) {
			echo '<!-- Added by WordPress shortcode -->' . "\n";
			echo '<script type=\'text/javascript\'>' . "\n";
			echo 'window.addEventListener(\'ds_popup_ready\', function (e) {' . "\n";
			echo '	Ds.Pop.showPopupOnExit('. $popup_id . ');' . "\n";
			echo '});' . "\n";
			echo '</script>' . "\n";
		});
	}
	// ──────────────────────────────────────────────
	// Routine for [ds-popup] shortcode, this shortcode 
	// forces a dailystory popup to display (if it exists)
	// ──────────────────────────────────────────────
	public static function dailystory_popup_shortcode($atts, $content=null) {
    	// normalize attribute keys, lowercase
    	$atts = array_change_key_case((array)$atts, CASE_LOWER);
 
    	// override default attributes with user attributes
    	$popup_id = shortcode_atts(['id' => '0',], $atts, $tag);
		$popup_id = esc_html__($popup_id['id'], 'ds-popup');
		add_action('wp_footer', function ( $content ) use ($popup_id) {
			echo '<!-- Added by WordPress shortcode -->' . "\n";
			echo '<script type=\'text/javascript\'>' . "\n";
			echo 'window.addEventListener(\'ds_popup_ready\', function (e) {' . "\n";
			echo '	Ds.Pop.showPopup('. $popup_id . ');' . "\n";
			echo '});' . "\n";
			echo '</script>' . "\n";
		});
	}
	// ──────────────────────────────────────────────
	// Routine for [ds-webform] shortcode, this shortcode returns
	// the HTML for a form that is rendered inline within a WordPress page
	// control of the UX, postflow, etc. is managed within DaliyStory
	// ──────────────────────────────────────────────
	public static function dailystory_webform_shortcode($atts, $content=null) {
    	// normalize attribute keys, lowercase
    	$atts = array_change_key_case((array)$atts, CASE_LOWER);
 
    	// override default attributes with user attributes
		$atts = shortcode_atts(['id' => '0', 'redirect_url' => ''], $atts);
		$webform_id = esc_html__($atts['id'], 'ds-webform');
		$redirect_url = esc_url_raw($atts['redirect_url']);

		// Add the script reference, pulled from DailyStory, but eventually will be served from a CDN
		wp_register_script('ds-landingpages', 'https://pages.dailystory.com/bundles/dailystory-landingpage', null,'2.0.6', true);
		wp_enqueue_script('ds-landingpages');

		// enqueue css
		wp_enqueue_style('ds-webform','https://forms.dailystory.com/content/hosted-webform-min', null, '2.0.6', 'all');

			// get the tenant uid
    	$options = get_option('dailystory_settings');
		$tenantuid = $options['dailystory_tenant_uid'];

		// Build the URL with query string
		$url = 'https://forms.dailystory.com/webform/' . $tenantuid . '/' . $webform_id;
		if (!empty($redirect_url)) {
			$url = add_query_arg('redirectUrl', $redirect_url, $url);
		}

		// get the contents
		list($cc_result, $cc_error) = self::get_data($url);

		return $cc_result;
	}
}
?>