<?php
// ──────────────────────────────────────────────
// Server-side WooCommerce events: what starts the DailyStory store automations.
//
// Checkouts and orders are reported from WordPress itself, not from the
// visitor's browser. The browser's events (class-dailystory-woocommerce.php)
// can be blocked, can be lost when a tab closes, and are unauthenticated, so
// DailyStory will not let them start "Cart ordered" or any other store
// automation. These events go to DailyStory's generic event API with the
// connection's website:track token, and DailyStory maps the reserved
// commerce_* actions onto its built-in triggers:
//
//   commerce_checkout_started  -> Cart abandoned (the automation waits, and
//                                 its Cart ordered exit goal removes buyers)
//   commerce_order_placed      -> Cart ordered    (processing, on-hold, completed)
//   commerce_order_fulfilled   -> Cart fulfilled  (completed)
//   commerce_order_declined    -> Cart declined   (failed)
//
// Cancelled and refunded orders are sent as plain custom events. DailyStory
// fires each stage once per order, so an order reported here and again by the
// WooCommerce sync job only starts its automations once.
//
// Events are queued (Action Scheduler, which WooCommerce bundles) so checkout
// never waits on DailyStory, and retried when DailyStory cannot be reached.
// ──────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) exit; // Do not allow direct access

class DailyStoryStoreEvents {

    // Reserved actions DailyStory maps onto its built-in store triggers
    const CHECKOUT_STARTED = 'commerce_checkout_started';
    const ORDER_PLACED     = 'commerce_order_placed';
    const ORDER_FULFILLED  = 'commerce_order_fulfilled';
    const ORDER_DECLINED   = 'commerce_order_declined';

    // Queue
    const HOOK         = 'dailystory_send_store_event';
    const GROUP        = 'dailystory';
    const MAX_ATTEMPTS = 4;

    // The visitor's DailyStory id, handed from the browser to the server
    const VISITOR_COOKIE     = 'dailystory_dsid';
    const ORDER_VISITOR_META = '_dailystory_visitor_id';

    // Cart recovery links: ?dailystory_cart=TOKEN rebuilds a saved cart
    const RECOVERY_PARAM = 'dailystory_cart';
    const RECOVERY_DAYS  = 30;

    // Lines of a cart sent to DailyStory, matching what its cart email shows
    const MAX_CART_LINES = 10;

    // ──────────────────────────────────────────────
    // True when store events can be sent: WooCommerce store tracking is on and
    // the connection has been granted website:track.
    // ──────────────────────────────────────────────
    public static function is_enabled () {
        return DailyStoryWooCommerce::is_enabled() && DailyStoryConnect::can_track();
    }

    // ──────────────────────────────────────────────
    // The queue handler is registered on every request, whatever the settings,
    // so an event queued before tracking was switched off is still drained
    // (and dropped) rather than left failing in the queue.
    // ──────────────────────────────────────────────
    public static function register_handler () {
        add_action(self::HOOK, array(__CLASS__, 'send'), 10, 1);
    }

    // ──────────────────────────────────────────────
    // Recovery links keep working whatever the settings, as long as
    // WooCommerce is active: they are already in shoppers' inboxes.
    // ──────────────────────────────────────────────
    public static function register_recovery () {

        if ( ! is_admin() )
            add_action('template_redirect', array(__CLASS__, 'restore_cart'), 5);
    }

    function __construct () {

        // Order lifecycle, from checkout and from status changes in wp-admin
        add_action('woocommerce_order_status_changed', array($this, 'on_status_changed'), 10, 4);

        // Keep the browser's visitor id with the order, classic and block checkout
        add_action('woocommerce_checkout_order_created', array($this, 'remember_visitor'));
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'remember_visitor'));

        // Checkout started: block checkout sends the shopper's details to the
        // Store API as they are typed; classic checkout needs a little script
        add_action('woocommerce_store_api_cart_update_customer_from_request', array($this, 'on_store_api_customer'), 10, 2);
        add_action('wp_ajax_dailystory_checkout_email', array($this, 'on_checkout_email'));
        add_action('wp_ajax_nopriv_dailystory_checkout_email', array($this, 'on_checkout_email'));
        add_action('template_redirect', array($this, 'on_checkout_page'));

        if ( ! is_admin() )
            add_action('wp_footer', array($this, 'render_scripts'));
    }

    // ──────────────────────────────────────────────
    // An order changed status
    // ──────────────────────────────────────────────
    public function on_status_changed ( $order_id, $from, $to, $order = null ) {

        if ( ! $order || ! is_a($order, 'WC_Order') )
            $order = wc_get_order($order_id);

        if ( ! $order || ! is_a($order, 'WC_Order') )
            return;

        // Refunds are orders too, but their own status changes say nothing new
        if ( is_a($order, 'WC_Order_Refund') )
            return;

        switch ( $to ) {
            case 'processing':
            case 'on-hold':
                $actions = array( self::ORDER_PLACED );
                break;

            // A digital order can go straight from pending to completed, so it
            // is placed as well as fulfilled. DailyStory fires each once.
            case 'completed':
                $actions = array( self::ORDER_PLACED, self::ORDER_FULFILLED );
                break;

            case 'failed':
                $actions = array( self::ORDER_DECLINED );
                break;

            case 'cancelled':
                $actions = array( 'woocommerce_order_cancelled' );
                break;

            case 'refunded':
                $actions = array( 'woocommerce_order_refunded' );
                break;

            default:
                return;
        }

        $email = $order->get_billing_email();

        if ( empty($email) )
            return;

        foreach ( $actions as $action )
            self::queue($action, $email, $order->get_billing_phone(), self::order_properties($order));
    }

    // ──────────────────────────────────────────────
    // Save the visitor id the browser handed over on the order, so DailyStory
    // can tie the purchase to the visitor's earlier browsing
    // ──────────────────────────────────────────────
    public function remember_visitor ( $order ) {

        if ( ! $order || ! is_a($order, 'WC_Order') )
            return;

        $visitor = self::visitor_id();

        if ( '' === $visitor || $order->get_meta(self::ORDER_VISITOR_META) )
            return;

        $order->update_meta_data(self::ORDER_VISITOR_META, $visitor);
        $order->save();
    }

    // ──────────────────────────────────────────────
    // Checkout started, for a signed-in shopper: their email is already known
    // ──────────────────────────────────────────────
    public function on_checkout_page () {

        if ( ! is_user_logged_in() || ! function_exists('is_checkout') || ! is_checkout() || is_wc_endpoint_url('order-received') )
            return;

        $user = wp_get_current_user();

        if ( $user && $user->user_email )
            self::checkout_started($user->user_email, get_user_meta($user->ID, 'billing_phone', true));
    }

    // ──────────────────────────────────────────────
    // Checkout started, for a guest on block checkout
    // ──────────────────────────────────────────────
    public function on_store_api_customer ( $customer, $request ) {

        if ( ! self::capture_guest_email() || ! $customer )
            return;

        self::checkout_started($customer->get_billing_email(), $customer->get_billing_phone());
    }

    // ──────────────────────────────────────────────
    // Checkout started, for a guest on classic checkout (see render_scripts)
    // ──────────────────────────────────────────────
    public function on_checkout_email () {

        check_ajax_referer('dailystory_checkout', 'nonce');

        if ( self::capture_guest_email() && isset($_POST['email']) )
            self::checkout_started(sanitize_email(wp_unslash($_POST['email'])), '');

        wp_send_json_success();
    }

    // ──────────────────────────────────────────────
    // Report a started checkout once per email and cart. The cart hash changes
    // whenever the cart does, so a shopper who comes back with a different
    // cart is reported again; DailyStory decides whether that starts the
    // abandoned cart automation again.
    // ──────────────────────────────────────────────
    private static function checkout_started ( $email, $phone ) {

        if ( ! is_email($email) || ! function_exists('WC') || ! WC()->cart || WC()->cart->is_empty() || ! WC()->session )
            return;

        $cart = WC()->cart;
        $hash = $cart->get_cart_hash();
        $seen = strtolower($email) . '|' . $hash;

        if ( WC()->session->get('dailystory_checkout_started') === $seen )
            return;

        WC()->session->set('dailystory_checkout_started', $seen);

        $names = array();
        $lines = array();

        foreach ( $cart->get_cart() as $item ) {

            if ( empty($item['data']) )
                continue;

            $product  = $item['data'];
            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $names[]  = $product->get_name();

            if ( count($lines) >= self::MAX_CART_LINES )
                continue;

            // Everything DailyStory's cart email shows, formatted the way this
            // store displays it (currency, decimals, tax display), so the
            // email needs nothing from the store when it is sent
            $image = wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail');

            $lines[] = array(
                'name'       => self::truncate($product->get_name(), 120),
                'url'        => $product->get_permalink(),
                'image'      => $image ? $image : wc_placeholder_img_src('woocommerce_thumbnail'),
                'quantity'   => $quantity,
                'price'      => self::display_price($cart->get_product_price($product)),
                'line_total' => self::display_price($cart->get_product_subtotal($product, $quantity)),
            );
        }

        self::queue(self::CHECKOUT_STARTED, $email, $phone, array(
            'platform'           => 'woocommerce',
            'cart_id'            => $hash,
            'cart_total'         => (string) $cart->get_total('edit'),
            'cart_total_display' => self::display_price($cart->get_total()),
            'currency'           => get_woocommerce_currency(),
            'item_count'         => (string) $cart->get_cart_contents_count(),
            'items'              => self::truncate(implode(', ', $names), 500),
            'cart'               => wp_json_encode($lines, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'recovery_url'       => self::recovery_url($email, $cart, $hash),
            'checkout_url'       => wc_get_checkout_url(),
            'visitor_id'         => self::visitor_id(),
        ));
    }

    // ──────────────────────────────────────────────
    // A link that rebuilds this cart in any browser and opens checkout.
    //
    // A checkout page link is not enough: a shopper who started on their phone
    // and opens the reminder on their laptop would find an empty cart. The
    // cart is saved under an unguessable token for RECOVERY_DAYS, and the
    // same cart keeps the same link.
    // ──────────────────────────────────────────────
    private static function recovery_url ( $email, $cart, $hash ) {

        $tokens = WC()->session->get('dailystory_recovery_tokens');

        if ( ! is_array($tokens) )
            $tokens = array();

        if ( empty($tokens[$hash]) ) {

            $items = array();

            foreach ( $cart->get_cart() as $item ) {

                if ( empty($item['product_id']) )
                    continue;

                $items[] = array(
                    'product_id'   => (int) $item['product_id'],
                    'variation_id' => isset($item['variation_id']) ? (int) $item['variation_id'] : 0,
                    'variation'    => isset($item['variation']) && is_array($item['variation']) ? $item['variation'] : array(),
                    'quantity'     => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                );
            }

            $token = wp_generate_password(32, false);

            set_transient('dailystory_cart_' . $token, array(
                'email' => $email,
                'items' => $items,
            ), self::RECOVERY_DAYS * DAY_IN_SECONDS);

            // Only the latest few carts of a session need remembering
            $tokens = array_slice($tokens, -4, null, true);
            $tokens[$hash] = $token;

            WC()->session->set('dailystory_recovery_tokens', $tokens);
        }

        return add_query_arg(self::RECOVERY_PARAM, $tokens[$hash], home_url('/'));
    }

    // ──────────────────────────────────────────────
    // Someone followed a recovery link: replace their cart with the saved one,
    // fill in the email it was saved for, and open checkout.
    //
    // An expired or unknown link goes to the cart page, rather than to an
    // error. Products that can no longer be bought are skipped, with
    // WooCommerce's own notice saying why.
    // ──────────────────────────────────────────────
    public static function restore_cart () {

        if ( empty($_GET[self::RECOVERY_PARAM]) || ! function_exists('WC') || ! WC()->cart )
            return;

        $token = preg_replace('/[^A-Za-z0-9]/', '', (string) wp_unslash($_GET[self::RECOVERY_PARAM]));
        $saved = ( 32 === strlen($token) ) ? get_transient('dailystory_cart_' . $token) : false;

        if ( ! is_array($saved) || empty($saved['items']) ) {
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        // A guest who has never added anything has no session yet
        if ( WC()->session && ! WC()->session->has_session() )
            WC()->session->set_customer_session_cookie(true);

        WC()->cart->empty_cart();

        foreach ( $saved['items'] as $item ) {
            WC()->cart->add_to_cart(
                (int) $item['product_id'],
                max(1, (int) $item['quantity']),
                (int) $item['variation_id'],
                is_array($item['variation']) ? $item['variation'] : array()
            );
        }

        if ( ! is_user_logged_in() && ! empty($saved['email']) && WC()->customer )
            WC()->customer->set_billing_email($saved['email']);

        // Already reported: arriving at checkout with the same cart is not a new one
        if ( WC()->session && ! empty($saved['email']) )
            WC()->session->set('dailystory_checkout_started', strtolower($saved['email']) . '|' . WC()->cart->get_cart_hash());

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    // ──────────────────────────────────────────────
    // A WooCommerce price as plain text, e.g. "$1,234.50" or "1.234,50 €"
    // ──────────────────────────────────────────────
    private static function display_price ( $html ) {
        return trim(html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES, 'UTF-8'));
    }

    // ──────────────────────────────────────────────
    // Guest checkout emails are reported as soon as they are typed, before the
    // order is placed - that is what makes abandoned cart possible for guests.
    // A site that must not do that (for example, where its privacy policy
    // promises otherwise) can switch it off with this filter.
    // ──────────────────────────────────────────────
    private static function capture_guest_email () {

        /**
         * Filter whether a guest's checkout email is sent to DailyStory before
         * the order is placed.
         *
         * @param bool $capture
         */
        return (bool) apply_filters('dailystory_capture_checkout_email', true);
    }

    // ──────────────────────────────────────────────
    // Footer scripts: hand the visitor id to the server, and report a guest's
    // email on classic checkout
    // ──────────────────────────────────────────────
    public function render_scripts () {

        // The DailyStory script keeps the visitor's id in localStorage, which
        // the server cannot read. Copy it into a first-party cookie so the
        // order can carry it.
        echo "\n<!-- DailyStory WooCommerce: visitor -->\n";
        echo '<script type="text/javascript">' . "\n";
        echo '(function(){' . "\n";
        echo '    var keep = function () {' . "\n";
        echo '        try {' . "\n";
        echo '            var id = window.Ds && window.Ds.getDsId && window.Ds.getDsId();' . "\n";
        echo '            if (id) document.cookie = ' . wp_json_encode(self::VISITOR_COOKIE . '=') . ' + encodeURIComponent(id) + "; path=/; max-age=2592000; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");' . "\n";
        echo '        } catch (e) {}' . "\n";
        echo '    };' . "\n";
        echo '    if (window.Ds) { keep(); } else { window.addEventListener("ds_ready", keep); }' . "\n";
        echo '})();' . "\n";
        echo '</script>' . "\n";

        if ( is_user_logged_in() || ! self::capture_guest_email() || ! is_checkout() || is_wc_endpoint_url('order-received') )
            return;

        echo "\n<!-- DailyStory WooCommerce: checkout -->\n";
        echo '<script type="text/javascript">' . "\n";
        echo '(function(){' . "\n";
        echo '    var sent = "";' . "\n";
        echo '    document.addEventListener("change", function (e) {' . "\n";
        echo '        var t = e.target;' . "\n";
        echo '        if (!t || (t.id !== "billing_email" && t.id !== "email")) return;' . "\n";
        echo '        var email = (t.value || "").trim();' . "\n";
        echo '        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || email === sent) return;' . "\n";
        echo '        sent = email;' . "\n";
        echo '        var data = new FormData();' . "\n";
        echo '        data.append("action", "dailystory_checkout_email");' . "\n";
        echo '        data.append("nonce", ' . wp_json_encode(wp_create_nonce('dailystory_checkout')) . ');' . "\n";
        echo '        data.append("email", email);' . "\n";
        echo '        fetch(' . wp_json_encode(admin_url('admin-ajax.php')) . ', { method: "POST", body: data, credentials: "same-origin" });' . "\n";
        echo '    }, true);' . "\n";
        echo '})();' . "\n";
        echo '</script>' . "\n";
    }

    // ──────────────────────────────────────────────
    // Describe an order. DailyStory stores event properties as strings, and
    // de-duplicates on platform + order_id.
    // ──────────────────────────────────────────────
    private static function order_properties ( $order ) {

        $names = array();

        foreach ( $order->get_items() as $item )
            $names[] = $item->get_name();

        $visitor = (string) $order->get_meta(self::ORDER_VISITOR_META);

        return array(
            'platform'     => 'woocommerce',
            'order_id'     => (string) $order->get_id(),
            'order_number' => (string) $order->get_order_number(),
            'status'       => $order->get_status(),
            'total'        => (string) $order->get_total(),
            'currency'     => $order->get_currency(),
            'item_count'   => (string) $order->get_item_count(),
            'items'        => self::truncate(implode(', ', $names), 500),
            'visitor_id'   => $visitor,
        );
    }

    // ──────────────────────────────────────────────
    // Queue an event. Action Scheduler is used when it is available - it ships
    // with WooCommerce - and WP-Cron otherwise.
    // ──────────────────────────────────────────────
    private static function queue ( $action, $email, $phone, $properties, $attempt = 1, $delay = 0 ) {

        $payload = array(
            'action'     => $action,
            'email'      => $email,
            'phone'      => (string) $phone,
            'properties' => array_map('strval', array_filter($properties, function ( $value ) {
                return '' !== $value && null !== $value;
            })),
            'attempt'    => $attempt,
            // Keeps WP-Cron from merging two identical events queued close together
            'queued_at'  => microtime(true),
        );

        if ( function_exists('as_schedule_single_action') ) {
            as_schedule_single_action(time() + $delay, self::HOOK, array( $payload ), self::GROUP);
            return;
        }

        wp_schedule_single_event(time() + $delay, self::HOOK, array( $payload ));
    }

    // ──────────────────────────────────────────────
    // Queue handler: find or create the contact, then record the event.
    //
    // Only a failure to reach DailyStory is retried. A refusal (the connection
    // was revoked, the account lost a permission) will not fix itself by
    // waiting, and the connection screen already says so.
    // ──────────────────────────────────────────────
    public static function send ( $payload ) {

        if ( ! is_array($payload) || empty($payload['action']) || empty($payload['email']) )
            return;

        if ( ! self::is_enabled() )
            return;

        $query = array( 'email' => $payload['email'] );

        if ( ! empty($payload['phone']) )
            $query['mobilephone'] = $payload['phone'];

        $result = DailyStoryConnect::api('POST', '/api/v1/contact/upsert/', $query);

        $dsid = ( ! is_wp_error($result) && isset($result['contact']['dsId']) ) ? (string) $result['contact']['dsId'] : '';

        if ( ! is_wp_error($result) && '' === $dsid )
            return;

        if ( ! is_wp_error($result) ) {
            $result = DailyStoryConnect::api('POST', '/api/v1/event/track/', array(), array(
                'dsid'       => $dsid,
                'action'     => $payload['action'],
                'properties' => $payload['properties'],
            ));
        }

        if ( is_wp_error($result) && 'dailystory_unreachable' === $result->get_error_code() ) {

            $attempt = isset($payload['attempt']) ? (int) $payload['attempt'] : 1;

            if ( $attempt < self::MAX_ATTEMPTS )
                self::queue($payload['action'], $payload['email'], $payload['phone'], $payload['properties'], $attempt + 1, 5 * MINUTE_IN_SECONDS * $attempt);
        }
    }

    // ──────────────────────────────────────────────
    // The visitor id from the cookie render_scripts sets. DailyStory ids are
    // GUIDs, with or without dashes.
    // ──────────────────────────────────────────────
    private static function visitor_id () {

        if ( empty($_COOKIE[self::VISITOR_COOKIE]) )
            return '';

        $id = sanitize_text_field(wp_unslash($_COOKIE[self::VISITOR_COOKIE]));

        return preg_match('/^[A-Fa-f0-9-]{32,36}$/', $id) ? $id : '';
    }

    private static function truncate ( $text, $length ) {
        return strlen($text) > $length ? substr($text, 0, $length - 3) . '...' : $text;
    }
}
