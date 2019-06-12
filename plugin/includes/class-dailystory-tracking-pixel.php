<?php
// ──────────────────────────────────────────────
// This class injects the DailyStory tracking pixel 
// into the WordPress footer
// ──────────────────────────────────────────────
class DailyStoryTrackingPixel {

	// ──────────────────────────────────────────────
    // Constructor does the wireup to callback our
    // function to load the script into the footer
	// ──────────────────────────────────────────────
    function __construct () {

        if ( is_admin() )
        {
            if ( ! defined('DOING_AJAX') || ! DOING_AJAX )
            {
                $dailystory_wp_admin = new DailyStoryPluginAdmin();
                add_action('admin_notices', array($this, 'activation'));
            }
        }
        else
        {
            add_action('wp_footer', array($this, 'renderPixel'));
        }

    }

    // ──────────────────────────────────────────────
	// Ensure the plugin Site Id is set, if it is not
	// we'll dump an error into the WordPress plugin admin
	// ──────────────────────────────────────────────
    function activation ()
    {
    	$options = get_option('dailystory_settings');

		// do we have an admin page
    	if ( isset($_GET['page']) && $_GET['page'] == 'dailystory-admin.php' )
    		return FALSE;

    	if ( ! isset($options['dailystory_tenant_uid']) || ( isset($options['dailystory_tenant_uid']) && ! $options['dailystory_tenant_uid'] ) || ( isset($options['dailystory_tenant_url']) && ! $options['dailystory_tenant_url'] ))
    	{
        ?>
        <div class="error">
            <p><b>DailyStory for WordPress is disabled Please <a href='options-general.php?page=dailystory-admin.php'>enter your DailyStory Site ID</a> to enable DailyStory integration.</b></p>
        </div>
        <?php
		}
    }

	// ──────────────────────────────────────────────
    // Callback function called to insert the rendered pixel 
    // into the footer
	// ──────────────────────────────────────────────
    function renderPixel ()
    {
        // reset the query in case we pass in any info
        wp_reset_query();

        $current_user = wp_get_current_user();
        $options = array();
        $options = get_option('dailystory_settings');

        if ( isset($options['dailystory_tenant_uid']) && $options['dailystory_tenant_uid'] != '' && $options['dailystory_tenant_url'] != '')
        {
            // This in the script that gets injected, this should be identical
            // to what is on https://app.dailystory.com
            echo "\n".'<!-- DailyStory Tracking Pixel for WordPress v' . DAILYSTORY_PLUGIN_VERSION . ' -->' . "\n";
            echo '<script type="text/javascript">' . "\n";
            echo '(function(d,a,i,l,y,s,t,o,r,y){' . "\n";
            echo '    d._dsSettings=i;' . "\n";
            echo '    r = a.createElement("script");' . "\n";
            echo '    o = a.getElementsByTagName("script")[0];' . "\n";
            echo '    r.src= "' . trim($options['dailystory_tenant_url']) . '/ds/ds" + i + ".js";' . "\n";
            echo '    r.async = true;' . "\n";
            echo '    r.id = "ds-sitescript";' . "\n";
            echo '    o.parentNode.insertBefore(r, o);' . "\n";
            echo '})(window,document,"' . trim($options['dailystory_tenant_uid']) . '");' . "\n";
            echo '</script>' . "\n";
        }
    }
}
?>
