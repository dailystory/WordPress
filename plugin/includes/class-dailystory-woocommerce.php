<?php
// ──────────────────────────────────────────────
// WooCommerce support for the DailyStory plugin.
//
// The DailyStory WooCommerce integration is otherwise a server-side polling
// job, which structurally cannot see a cart that never became an order, a
// product view, or anything at all in less than a sync interval. This class
// covers that gap from the visitor's browser:
//
//   - product views, add-to-cart and cart changes, as custom DailyStory events
//     (woocommerce_product_viewed, etc) for segments and custom automations
//   - a conversion on the order-received page, so revenue is attributable
//     to the marketing that produced it
//
// Everything here uses the documented DailyStory JavaScript API (sendEvent /
// conversion). The built-in store automations - Cart abandoned, ordered,
// fulfilled and declined - are started from the server instead, by
// class-dailystory-store-events.php: browser events are unauthenticated, so
// DailyStory does not let them start those.
// ──────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) exit; // Do not allow direct access

class DailyStoryWooCommerce {

    // The WooCommerce conversion funnel installed by the DailyStory integration.
    // Matches WooCommerce.UUID on the DailyStory side.
    const FUNNEL_UUID = 'e4da5976-df0e-43c4-8dd7-77523836f3ff';

    // ──────────────────────────────────────────────
    // True when WooCommerce is active in this WordPress install
    // ──────────────────────────────────────────────
    public static function is_woocommerce_active () {
        return class_exists('WooCommerce');
    }

    // ──────────────────────────────────────────────
    // True when the merchant has not switched store tracking off
    // ──────────────────────────────────────────────
    public static function is_enabled () {

        if ( ! self::is_woocommerce_active() )
            return false;

        $options = get_option('dailystory_settings');

        if ( empty($options['dailystory_tenant_uid']) || empty($options['dailystory_tenant_url']) )
            return false;

        // Default on once WooCommerce is detected
        $enabled = ! isset($options['dailystory_woo_tracking']) || $options['dailystory_woo_tracking'];

        /**
         * Filter whether DailyStory WooCommerce store tracking runs.
         *
         * @param bool $enabled
         */
        return (bool) apply_filters('dailystory_woocommerce_tracking_enabled', $enabled);
    }

    function __construct () {

        // HPOS compatibility is declared from the bootstrap, because
        // before_woocommerce_init fires earlier than the 'init' hook this
        // class is constructed on.

        if ( is_admin() )
            return;

        if ( ! self::is_enabled() )
            return;

        // Onsite behaviour
        add_action('wp_footer', array($this, 'render_product_view'));
        add_action('woocommerce_add_to_cart', array($this, 'on_add_to_cart'), 10, 6);
        add_action('wp_footer', array($this, 'render_cart_state'));

        // Revenue attribution
        add_action('woocommerce_thankyou', array($this, 'render_conversion'), 10, 1);
    }

    // ──────────────────────────────────────────────
    // HPOS (High-Performance Order Storage) compatibility.
    //
    // Without this declaration WooCommerce lists the plugin as incompatible
    // and warns merchants off enabling HPOS. This class only reaches orders
    // through wc_get_order() and the CRUD API, never through post meta, so
    // both storage modes work.
    //
    // Blocks cart/checkout compatibility is deliberately not declared yet -
    // it needs testing against the Blocks checkout before we claim it.
    // ──────────────────────────────────────────────
    public static function declare_hpos_compatibility () {

        if ( ! class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil') )
            return;

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            DAILYSTORY_PLUGIN_FILE,
            true
        );
    }

    // ──────────────────────────────────────────────
    // Emit a DailyStory event once the JavaScript API is ready.
    //
    // The action is what DailyStory automations match on, and DailyStory event
    // names are shared by every source in an account, so they are prefixed
    // woocommerce_. The value has to be a string: Ds.sendEvent URL-encodes it
    // as it is, so an object arrived as the text "[object Object]".
    // ──────────────────────────────────────────────
    private static function emit_event ( $action, $value = null ) {

        $action  = 'woocommerce_' . $action;
        $payload = ( null === $value ) ? '""' : wp_json_encode(wp_json_encode($value));

        echo "\n<!-- DailyStory WooCommerce: " . esc_html($action) . " -->\n";
        echo '<script type="text/javascript">' . "\n";
        echo '(function(){' . "\n";
        echo '    var send = function () {' . "\n";
        echo '        window.Ds && window.Ds.sendEvent("woocommerce", ' . wp_json_encode($action) . ', ' . $payload . ');' . "\n";
        echo '    };' . "\n";
        echo '    if (window.Ds) { send(); } else { window.addEventListener("ds_ready", send); }' . "\n";
        echo '})();' . "\n";
        echo '</script>' . "\n";
    }

    // ──────────────────────────────────────────────
    // Describe a product for an event payload
    // ──────────────────────────────────────────────
    private static function describe_product ( $product ) {

        if ( ! $product || ! is_a($product, 'WC_Product') )
            return array();

        $categories = array();
        $terms      = get_the_terms($product->get_id(), 'product_cat');

        if ( $terms && ! is_wp_error($terms) ) {
            foreach ( $terms as $term )
                $categories[] = $term->name;
        }

        $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');

        return array(
            'productId'  => $product->get_id(),
            'sku'        => $product->get_sku(),
            'name'       => $product->get_name(),
            'price'      => wc_get_price_to_display($product),
            'currency'   => get_woocommerce_currency(),
            'url'        => get_permalink($product->get_id()),
            'imageUrl'   => $image ? $image : '',
            'categories' => $categories,
            'inStock'    => $product->is_in_stock(),
        );
    }

    // ──────────────────────────────────────────────
    // Browse abandonment: a product page was viewed
    // ──────────────────────────────────────────────
    public function render_product_view () {

        if ( ! is_product() )
            return;

        global $product;

        if ( ! $product || ! is_a($product, 'WC_Product') )
            $product = wc_get_product(get_the_ID());

        $described = self::describe_product($product);

        if ( empty($described) )
            return;

        self::emit_event('product_viewed', $described);
    }

    // ──────────────────────────────────────────────
    // Add to cart happens server-side (and over AJAX), so record it and let
    // the next rendered page emit it. Using a WooCommerce session value rather
    // than a cookie keeps it working for guests and logged-in customers alike.
    // ──────────────────────────────────────────────
    public function on_add_to_cart ( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

        if ( ! function_exists('WC') || ! WC()->session )
            return;

        $product = wc_get_product( $variation_id ? $variation_id : $product_id );

        $described = self::describe_product($product);

        if ( empty($described) )
            return;

        $described['quantity'] = (int) $quantity;

        WC()->session->set('dailystory_pending_add_to_cart', $described);
    }

    // ──────────────────────────────────────────────
    // Cart contents, value and a recovery URL, whenever the cart changes.
    //
    // A WooCommerce cart that is never checked out produces no order row at
    // all, so the polling sync can never see it. This is sent once per change
    // of cart, not on every page view, which would repeat the same event for
    // as long as the cart has items.
    // ──────────────────────────────────────────────
    public function render_cart_state () {

        if ( ! function_exists('WC') || ! WC()->cart )
            return;

        // Flush any add-to-cart recorded server-side since the last render
        if ( WC()->session ) {
            $pending = WC()->session->get('dailystory_pending_add_to_cart');

            if ( ! empty($pending) ) {
                self::emit_event('added_to_cart', $pending);
                WC()->session->set('dailystory_pending_add_to_cart', null);
            }
        }

        $cart = WC()->cart;

        if ( $cart->is_empty() || ! WC()->session )
            return;

        $hash = $cart->get_cart_hash();

        if ( WC()->session->get('dailystory_cart_hash') === $hash )
            return;

        WC()->session->set('dailystory_cart_hash', $hash);

        $items = array();

        foreach ( $cart->get_cart() as $item ) {

            if ( empty($item['data']) )
                continue;

            // The event travels in a URL and DailyStory keeps 2,048 characters
            // of it, so a large cart is summarized by its first ten lines
            if ( count($items) >= 10 )
                break;

            $product = $item['data'];

            $items[] = array(
                'productId' => $product->get_id(),
                'sku'       => $product->get_sku(),
                'name'      => $product->get_name(),
                'quantity'  => isset($item['quantity']) ? (int) $item['quantity'] : 0,
                'lineTotal' => isset($item['line_total']) ? (float) $item['line_total'] : 0,
            );
        }

        if ( empty($items) )
            return;

        self::emit_event('cart_updated', array(
            'itemCount'   => $cart->get_cart_contents_count(),
            'cartTotal'   => (float) $cart->get_cart_contents_total(),
            'currency'    => get_woocommerce_currency(),
            'recoveryUrl' => wc_get_cart_url(),
            'checkoutUrl' => wc_get_checkout_url(),
            'items'       => $items,
        ));
    }

    // ──────────────────────────────────────────────
    // Conversion on the order-received page.
    //
    // Attributes order revenue to the marketing that produced it, the same way
    // the Shopify integration does, including passing the buyer's email and
    // phone so a guest's purchase is credited to their contact. The polling
    // sync writes the authoritative sale row; this is what ties it to a
    // campaign. The order itself reaches DailyStory from the server
    // (class-dailystory-store-events.php), which is what starts Cart ordered.
    // ──────────────────────────────────────────────
    public function render_conversion ( $order_id ) {

        $order_id = absint($order_id);

        if ( ! $order_id )
            return;

        $order = wc_get_order($order_id);

        if ( ! $order )
            return;

        // Only report each order once, however many times the customer reloads
        // the thank-you page or returns to it from a bookmarked link.
        if ( $order->get_meta('_dailystory_conversion_sent') )
            return;

        $total = (float) $order->get_total();

        $buyer = array_filter(array(
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
        ));

        echo "\n<!-- DailyStory WooCommerce: conversion -->\n";
        echo '<script type="text/javascript">' . "\n";
        echo '(function(){' . "\n";
        echo '    var send = function () {' . "\n";
        echo '        if (!window.Ds) return;' . "\n";
        echo '        window.Ds.conversion(' . wp_json_encode(self::FUNNEL_UUID) . ', ' . wp_json_encode($total) . ', ' . wp_json_encode((object) $buyer) . ');' . "\n";
        echo '    };' . "\n";
        echo '    if (window.Ds) { send(); } else { window.addEventListener("ds_ready", send); }' . "\n";
        echo '})();' . "\n";
        echo '</script>' . "\n";

        $order->update_meta_data('_dailystory_conversion_sent', current_time('mysql'));
        $order->save();
    }
}
