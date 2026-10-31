<?php
// ──────────────────────────────────────────────
// One-click connection between this site and a DailyStory account.
//
// "Connect to DailyStory" is an OAuth 2.0 authorization code flow with PKCE
// against the DailyStory app. The plugin is a public client: it registers
// itself once per site (Dynamic Client Registration, RFC 7591), the user signs
// in to DailyStory and approves the "website:connect" scope, and the plugin
// trades the code for tokens and reads back the account's Site ID and Site URL.
//
// The connection only fills in the same two settings a user can still type by
// hand, so the tracking pixel, shortcodes and store tracking do not care how
// they got there. The tokens are kept for checking the connection is still
// live and for features that will need to call the DailyStory API.
//
// Two things in the server's token rules shape this class:
//
//   - refresh tokens last 30 days and rotate on every use, so the connection
//     is refreshed daily by cron to stay alive;
//   - presenting a refresh token twice revokes the whole connection, so a
//     refresh only ever runs while holding a lock.
// ──────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) exit; // Do not allow direct access

class DailyStoryConnect {

    // The scopes the plugin asks for. Both are unlisted on the server, so they
    // have to be named in every request:
    //
    //   - website:connect reads the account's name, Site ID and Site URL
    //   - website:track creates contacts and records store events, which is
    //     what starts the store automations (see class-dailystory-store-events.php)
    const SCOPE = 'website:connect website:track';

    // The scope that store events need
    const TRACK_SCOPE = 'website:track';

    // Options. Neither is autoloaded: they are only needed on the settings
    // page, the OAuth callback and the daily check.
    const CLIENT_OPTION     = 'dailystory_oauth_client';
    const CONNECTION_OPTION = 'dailystory_connection';
    const LOCK_OPTION       = 'dailystory_oauth_lock';

    // Daily connection check, which also keeps the refresh token rotating
    const CRON_HOOK = 'dailystory_verify_connection';

    // Status values stored on the connection
    const STATUS_CONNECTED = 'connected';
    const STATUS_RECONNECT = 'reconnect';

    // ──────────────────────────────────────────────
    // The DailyStory app the connection is made with.
    // DAILYSTORY_APP_URL can point it at staging.
    // ──────────────────────────────────────────────
    public static function app_url () {

        $url = defined('DAILYSTORY_APP_URL') ? DAILYSTORY_APP_URL : 'https://app.dailystory.com';

        return untrailingslashit($url);
    }

    // ──────────────────────────────────────────────
    // Where DailyStory sends the browser back to. Has to match the registered
    // redirect_uri character for character.
    // ──────────────────────────────────────────────
    public static function redirect_uri () {
        return admin_url('admin-post.php?action=dailystory_oauth_callback');
    }

    // ──────────────────────────────────────────────
    // DailyStory only redirects to https, or to http on loopback for local
    // development. A site whose admin is on plain http has to connect by
    // entering its Site ID instead.
    // ──────────────────────────────────────────────
    public static function can_connect () {

        $parts = wp_parse_url(self::redirect_uri());

        if ( empty($parts['scheme']) || empty($parts['host']) )
            return false;

        if ( 'https' === strtolower($parts['scheme']) )
            return true;

        return in_array(strtolower($parts['host']), array('localhost', '127.0.0.1', '[::1]'), true);
    }

    // ──────────────────────────────────────────────
    // The stored connection, or an empty array when not connected
    // ──────────────────────────────────────────────
    public static function get_connection () {

        $connection = get_option(self::CONNECTION_OPTION);

        return is_array($connection) ? $connection : array();
    }

    public static function is_connected () {

        $connection = self::get_connection();

        return ! empty($connection['tenant_uid']);
    }

    // ──────────────────────────────────────────────
    // True when the connection may record contacts and store events. A site
    // connected by 2.3.0 was only granted website:connect and has to reconnect
    // once to approve website:track.
    // ──────────────────────────────────────────────
    public static function can_track () {

        $connection = self::get_connection();

        if ( empty($connection['tenant_uid']) || self::STATUS_CONNECTED !== $connection['status'] )
            return false;

        $granted = isset($connection['scope']) ? explode(' ', $connection['scope']) : array();

        return in_array(self::TRACK_SCOPE, $granted, true);
    }

    private static function save_connection ( $connection ) {
        update_option(self::CONNECTION_OPTION, $connection, false);
    }

    // ──────────────────────────────────────────────
    // Register this site as an OAuth client, or reuse the registration.
    //
    // A registration is tied to its redirect_uri and bounded by its scope, so
    // a site whose admin URL has changed (moved domain, switched to https), or
    // one registered by an older version that asked for fewer scopes, registers
    // again.
    // ──────────────────────────────────────────────
    private static function ensure_client () {

        $client       = get_option(self::CLIENT_OPTION);
        $redirect_uri = self::redirect_uri();

        if ( is_array($client) && ! empty($client['client_id'])
            && isset($client['redirect_uri']) && $client['redirect_uri'] === $redirect_uri
            && isset($client['scope']) && $client['scope'] === self::SCOPE )
            return $client['client_id'];

        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        $response = wp_remote_post(self::app_url() . '/oauth/register', array(
            'timeout' => 15,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode(array(
                'client_name'                => 'WordPress: ' . ( $host ? $host : home_url() ),
                'client_uri'                 => home_url(),
                'redirect_uris'              => array( $redirect_uri ),
                'grant_types'                => array( 'authorization_code', 'refresh_token' ),
                'response_types'             => array( 'code' ),
                'token_endpoint_auth_method' => 'none',
                'scope'                      => self::SCOPE,
            )),
        ));

        $body = self::decode($response);

        if ( is_wp_error($body) )
            return $body;

        if ( empty($body['client_id']) )
            return self::error_from($body, 'DailyStory did not accept this site\'s registration.');

        update_option(self::CLIENT_OPTION, array(
            'client_id'    => sanitize_text_field($body['client_id']),
            'redirect_uri' => $redirect_uri,
            'scope'        => self::SCOPE,
        ), false);

        return sanitize_text_field($body['client_id']);
    }

    // ──────────────────────────────────────────────
    // Start connecting: returns the DailyStory URL to send the browser to.
    //
    // The PKCE verifier and state are kept per user, so two administrators
    // connecting at once cannot complete each other's flow.
    // ──────────────────────────────────────────────
    public static function start () {

        if ( ! self::can_connect() )
            return new WP_Error('dailystory_https', 'Connecting requires your WordPress admin to use https. Enter your Site ID instead.');

        $client_id = self::ensure_client();

        if ( is_wp_error($client_id) )
            return $client_id;

        $verifier = self::base64url(random_bytes(32));
        $state    = wp_generate_password(32, false);

        set_transient(self::pending_key(), array(
            'state'        => $state,
            'verifier'     => $verifier,
            'client_id'    => $client_id,
            'redirect_uri' => self::redirect_uri(),
        ), 15 * MINUTE_IN_SECONDS);

        return add_query_arg(array(
            'response_type'         => 'code',
            'client_id'             => rawurlencode($client_id),
            'redirect_uri'          => rawurlencode(self::redirect_uri()),
            'scope'                 => rawurlencode(self::SCOPE),
            'state'                 => rawurlencode($state),
            'code_challenge'        => rawurlencode(self::base64url(hash('sha256', $verifier, true))),
            'code_challenge_method' => 'S256',
        ), self::app_url() . '/oauth/authorize');
    }

    // ──────────────────────────────────────────────
    // Finish connecting: checks the response belongs to the flow this user
    // started, trades the code for tokens and reads the account they approved.
    //
    // Returns array('tokens' => ..., 'website' => ...) for apply(), or a
    // WP_Error. Nothing is saved here, so the caller can ask the user to
    // confirm before replacing a different account's tracking.
    // ──────────────────────────────────────────────
    public static function complete ( $query ) {

        $pending = get_transient(self::pending_key());

        // The state is single use, whatever happens next
        delete_transient(self::pending_key());

        $state = isset($query['state']) ? (string) $query['state'] : '';

        if ( empty($pending['state']) || '' === $state || ! hash_equals($pending['state'], $state) )
            return new WP_Error('dailystory_state', 'This connection request has expired or was not started from this site. Please try connecting again.');

        if ( ! empty($query['error']) ) {

            if ( 'access_denied' === $query['error'] )
                return new WP_Error('dailystory_denied', 'The connection was cancelled in DailyStory.');

            $message = ! empty($query['error_description']) ? $query['error_description'] : $query['error'];

            return new WP_Error('dailystory_oauth', 'DailyStory could not connect this site: ' . sanitize_text_field(wp_unslash($message)));
        }

        if ( empty($query['code']) )
            return new WP_Error('dailystory_oauth', 'DailyStory did not return an authorization code.');

        $tokens = self::request_tokens(array(
            'grant_type'    => 'authorization_code',
            'code'          => (string) $query['code'],
            'redirect_uri'  => $pending['redirect_uri'],
            'code_verifier' => $pending['verifier'],
            'client_id'     => $pending['client_id'],
        ));

        if ( is_wp_error($tokens) )
            return $tokens;

        $tokens['client_id'] = $pending['client_id'];

        $website = self::fetch_website($tokens['access_token']);

        if ( is_wp_error($website) )
            return $website;

        return array( 'tokens' => $tokens, 'website' => $website );
    }

    // ──────────────────────────────────────────────
    // Save a completed connection and point tracking at its account
    // ──────────────────────────────────────────────
    public static function apply ( $result ) {

        $tokens  = $result['tokens'];
        $website = $result['website'];

        // Replacing a different connection: revoke the old one first so it
        // does not linger on the account's Connected Apps page
        $previous = self::get_connection();

        if ( ! empty($previous) && ! empty($previous['refresh_token']) )
            self::revoke($previous);

        self::save_connection(array(
            'client_id'      => $tokens['client_id'],
            'access_token'   => self::encrypt($tokens['access_token']),
            'access_expires' => $tokens['access_expires'],
            'refresh_token'  => self::encrypt($tokens['refresh_token']),
            'scope'          => $tokens['scope'],
            'tenant_uid'     => $website['tenantUid'],
            'account_name'   => $website['name'],
            'app_url'        => $website['appUrl'],
            'connected_at'   => time(),
            'connected_by'   => get_current_user_id(),
            'verified_at'    => time(),
            'status'         => self::STATUS_CONNECTED,
            'last_error'     => '',
        ));

        DailyStoryPlugin::update_settings(array(
            'dailystory_tenant_uid' => $website['tenantUid'],
            'dailystory_tenant_url' => $website['trackingUrl'],
        ));

        self::schedule();
    }

    // ──────────────────────────────────────────────
    // Throw away a completed connection that was not applied (the user chose
    // not to switch accounts), so its grant does not linger in DailyStory.
    // ──────────────────────────────────────────────
    public static function discard ( $result ) {

        if ( empty($result['tokens']['refresh_token']) )
            return;

        self::revoke(array(
            'client_id'     => $result['tokens']['client_id'],
            'refresh_token' => self::encrypt($result['tokens']['refresh_token']),
        ));
    }

    // ──────────────────────────────────────────────
    // Disconnect: revoke the grant in DailyStory and forget the tokens.
    //
    // The Site ID and Site URL are left alone unless $remove_tracking is set,
    // so disconnecting the account does not silently turn tracking off.
    // ──────────────────────────────────────────────
    public static function disconnect ( $remove_tracking = false ) {

        $connection = self::get_connection();

        if ( ! empty($connection) )
            self::revoke($connection);

        delete_option(self::CONNECTION_OPTION);
        wp_clear_scheduled_hook(self::CRON_HOOK);

        if ( $remove_tracking ) {
            DailyStoryPlugin::update_settings(array(
                'dailystory_tenant_uid' => '',
                'dailystory_tenant_url' => '',
            ));
        }
    }

    // ──────────────────────────────────────────────
    // Check the connection is still live, refreshing its tokens as needed.
    // Runs daily from cron and when the settings page asks.
    //
    // Only a definite answer from DailyStory marks the connection for
    // reconnecting. A network failure leaves it as it was.
    // ──────────────────────────────────────────────
    public static function verify () {

        $connection = self::get_connection();

        if ( empty($connection) )
            return new WP_Error('dailystory_not_connected', 'This site is not connected to a DailyStory account.');

        $token = self::get_access_token();

        if ( is_wp_error($token) )
            return $token;

        $website = self::fetch_website($token);

        if ( is_wp_error($website) ) {

            if ( in_array($website->get_error_code(), array( 'dailystory_unauthorized', 'dailystory_forbidden' ), true) )
                self::mark_reconnect($website->get_error_message());

            return $website;
        }

        $connection = self::get_connection();

        $connection['account_name'] = $website['name'];
        $connection['app_url']      = $website['appUrl'];
        $connection['tenant_uid']   = $website['tenantUid'];
        $connection['verified_at']  = time();
        $connection['status']       = self::STATUS_CONNECTED;
        $connection['last_error']   = '';

        self::save_connection($connection);

        return $website;
    }

    // ──────────────────────────────────────────────
    // Cron callback
    // ──────────────────────────────────────────────
    public static function cron_verify () {

        if ( self::is_connected() )
            self::verify();
    }

    public static function schedule () {

        if ( ! wp_next_scheduled(self::CRON_HOOK) )
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK);
    }

    // ──────────────────────────────────────────────
    // A usable access token, refreshing it first when it has expired.
    // ──────────────────────────────────────────────
    public static function get_access_token () {

        $connection = self::get_connection();

        if ( empty($connection['refresh_token']) )
            return new WP_Error('dailystory_unauthorized', 'This site needs to be reconnected to DailyStory.');

        if ( ! empty($connection['access_token']) && (int) $connection['access_expires'] > time() + 60 ) {

            $token = self::decrypt($connection['access_token']);

            if ( false !== $token )
                return $token;
        }

        return self::refresh();
    }

    // ──────────────────────────────────────────────
    // Trade the refresh token for a new pair, holding a lock.
    //
    // add_option is the lock: it fails if the option already exists, and the
    // options table has a unique key on option_name, so two requests cannot
    // both take it. A lock older than its expiry is a crashed request and is
    // cleared.
    // ──────────────────────────────────────────────
    private static function refresh () {

        if ( ! add_option(self::LOCK_OPTION, time() + 30, '', 'no') ) {

            $expires = (int) get_option(self::LOCK_OPTION);

            if ( $expires > time() )
                return new WP_Error('dailystory_busy', 'The DailyStory connection is being refreshed. Please try again in a moment.');

            delete_option(self::LOCK_OPTION);

            if ( ! add_option(self::LOCK_OPTION, time() + 30, '', 'no') )
                return new WP_Error('dailystory_busy', 'The DailyStory connection is being refreshed. Please try again in a moment.');
        }

        try {
            // Another request may have refreshed while this one waited for the
            // lock. Read the connection from the database, not the cache, and
            // use its token if it is fresh.
            wp_cache_delete(self::CONNECTION_OPTION, 'options');

            $connection = self::get_connection();

            if ( ! empty($connection['access_token']) && (int) $connection['access_expires'] > time() + 60 ) {

                $token = self::decrypt($connection['access_token']);

                if ( false !== $token )
                    return $token;
            }

            $refresh_token = empty($connection['refresh_token']) ? false : self::decrypt($connection['refresh_token']);

            // The tokens cannot be read, usually because the site's salts in
            // wp-config.php changed. Only reconnecting can fix that.
            if ( false === $refresh_token ) {
                self::mark_reconnect('The stored connection could not be read. Please reconnect.');
                return new WP_Error('dailystory_unauthorized', 'This site needs to be reconnected to DailyStory.');
            }

            $tokens = self::request_tokens(array(
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
                'client_id'     => $connection['client_id'],
            ));

            if ( is_wp_error($tokens) ) {

                if ( 'invalid_grant' === $tokens->get_error_code() ) {
                    self::mark_reconnect('The connection was revoked or expired in DailyStory.');
                    return new WP_Error('dailystory_unauthorized', 'This site needs to be reconnected to DailyStory.');
                }

                return $tokens;
            }

            $connection['access_token']   = self::encrypt($tokens['access_token']);
            $connection['access_expires'] = $tokens['access_expires'];
            $connection['refresh_token']  = self::encrypt($tokens['refresh_token']);

            // The server narrows a grant when its user loses a permission
            if ( '' !== $tokens['scope'] )
                $connection['scope'] = $tokens['scope'];

            self::save_connection($connection);

            return $tokens['access_token'];
        }
        finally {
            delete_option(self::LOCK_OPTION);
        }
    }

    private static function mark_reconnect ( $message ) {

        $connection = self::get_connection();

        if ( empty($connection) )
            return;

        $connection['status']         = self::STATUS_RECONNECT;
        $connection['last_error']     = $message;
        $connection['access_token']   = '';
        $connection['access_expires'] = 0;
        $connection['refresh_token']  = '';

        self::save_connection($connection);
    }

    // ──────────────────────────────────────────────
    // Call the token endpoint. Returns access_token, refresh_token and
    // access_expires, or a WP_Error whose code is the OAuth error.
    // ──────────────────────────────────────────────
    private static function request_tokens ( $params ) {

        $response = wp_remote_post(self::app_url() . '/oauth/token', array(
            'timeout' => 15,
            'body'    => $params,
        ));

        $body = self::decode($response);

        if ( is_wp_error($body) )
            return $body;

        if ( empty($body['access_token']) || empty($body['refresh_token']) )
            return self::error_from($body, 'DailyStory did not return a token.');

        return array(
            'access_token'   => (string) $body['access_token'],
            'refresh_token'  => (string) $body['refresh_token'],
            'access_expires' => time() + ( isset($body['expires_in']) ? (int) $body['expires_in'] : 3600 ),
            'scope'          => isset($body['scope']) ? sanitize_text_field($body['scope']) : '',
        );
    }

    // ──────────────────────────────────────────────
    // Call the DailyStory API with the connection's token.
    //
    // Returns the response's "Response" object, or a WP_Error. A 401 is tried
    // once more with a freshly refreshed token, since an access token can be
    // revoked or expire between being read and being used.
    // ──────────────────────────────────────────────
    public static function api ( $method, $path, $query = array(), $body = null, $retry = true ) {

        $token = self::get_access_token();

        if ( is_wp_error($token) )
            return $token;

        $args = array(
            'method'  => $method,
            'timeout' => 15,
            'headers' => array( 'Authorization' => 'Bearer ' . $token ),
        );

        if ( null !== $body ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request(add_query_arg(array_map('rawurlencode', $query), self::app_url() . $path), $args);

        if ( is_wp_error($response) )
            return new WP_Error('dailystory_unreachable', 'DailyStory could not be reached: ' . $response->get_error_message());

        $code = (int) wp_remote_retrieve_response_code($response);

        if ( 401 === $code && $retry ) {

            $connection = self::get_connection();
            $connection['access_expires'] = 0;
            self::save_connection($connection);

            return self::api($method, $path, $query, $body, false);
        }

        if ( 401 === $code || 403 === $code ) {
            self::mark_reconnect('DailyStory refused this site\'s connection. Please reconnect.');
            return new WP_Error('dailystory_unauthorized', 'This site needs to be reconnected to DailyStory.');
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if ( $code < 200 || $code >= 300 || ! is_array($decoded) )
            return new WP_Error('dailystory_unreachable', 'DailyStory returned an unexpected response (HTTP ' . $code . ').');

        if ( empty($decoded['Status']) )
            return new WP_Error('dailystory_refused', ! empty($decoded['Message']) ? sanitize_text_field($decoded['Message']) : 'DailyStory refused the request.');

        return isset($decoded['Response']) && is_array($decoded['Response']) ? $decoded['Response'] : array();
    }

    // ──────────────────────────────────────────────
    // Read the connected account: name, Site ID and Site URL
    // ──────────────────────────────────────────────
    private static function fetch_website ( $access_token ) {

        $response = wp_remote_get(self::app_url() . '/api/v1/website/connection', array(
            'timeout' => 15,
            'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
        ));

        if ( is_wp_error($response) )
            return new WP_Error('dailystory_unreachable', 'DailyStory could not be reached: ' . $response->get_error_message());

        $code = (int) wp_remote_retrieve_response_code($response);

        if ( 401 === $code )
            return new WP_Error('dailystory_unauthorized', 'The connection was revoked or expired in DailyStory.');

        // 403 is the scope filter: the user who connected no longer holds the
        // permission the connection relies on
        if ( 403 === $code )
            return new WP_Error('dailystory_forbidden', 'The DailyStory user who connected this site no longer has permission to manage integrations.');

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ( $code < 200 || $code >= 300 || ! is_array($body) )
            return new WP_Error('dailystory_unreachable', 'DailyStory returned an unexpected response (HTTP ' . $code . ').');

        if ( empty($body['Status']) ) {
            $message = ! empty($body['Message']) ? sanitize_text_field($body['Message']) : 'DailyStory refused the request.';
            return new WP_Error('dailystory_forbidden', $message);
        }

        $website = isset($body['Response']['website']) ? $body['Response']['website'] : array();

        $tenant_uid   = isset($website['tenantUid']) ? sanitize_text_field($website['tenantUid']) : '';
        $tracking_url = isset($website['trackingUrl']) ? esc_url_raw($website['trackingUrl'], array( 'https' )) : '';

        if ( '' === $tenant_uid || '' === $tracking_url )
            return new WP_Error('dailystory_unreachable', 'DailyStory did not return the account\'s tracking details.');

        return array(
            'tenantUid'   => $tenant_uid,
            'trackingUrl' => untrailingslashit($tracking_url),
            'name'        => isset($website['name']) ? sanitize_text_field($website['name']) : '',
            'appUrl'      => isset($website['appUrl']) ? untrailingslashit(esc_url_raw($website['appUrl'], array( 'https' ))) : '',
        );
    }

    // ──────────────────────────────────────────────
    // Best-effort revoke. Revoking either token revokes the whole connection.
    // ──────────────────────────────────────────────
    private static function revoke ( $connection ) {

        if ( empty($connection['client_id']) )
            return;

        $token = empty($connection['refresh_token']) ? false : self::decrypt($connection['refresh_token']);

        if ( false === $token && ! empty($connection['access_token']) )
            $token = self::decrypt($connection['access_token']);

        if ( false === $token || '' === $token )
            return;

        wp_remote_post(self::app_url() . '/oauth/revoke', array(
            'timeout'  => 10,
            'blocking' => false,
            'body'     => array(
                'token'     => $token,
                'client_id' => $connection['client_id'],
            ),
        ));
    }

    // ──────────────────────────────────────────────
    // Decode a JSON response from the OAuth endpoints. OAuth errors come back
    // as WP_Errors coded with the OAuth error (invalid_grant, etc).
    // ──────────────────────────────────────────────
    private static function decode ( $response ) {

        if ( is_wp_error($response) )
            return new WP_Error('dailystory_unreachable', 'DailyStory could not be reached: ' . $response->get_error_message());

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ( ! is_array($body) )
            return new WP_Error('dailystory_unreachable', 'DailyStory returned an unexpected response (HTTP ' . $code . ').');

        if ( $code >= 400 || ! empty($body['error']) )
            return self::error_from($body, 'DailyStory returned an error (HTTP ' . $code . ').');

        return $body;
    }

    private static function error_from ( $body, $fallback ) {

        $code    = ! empty($body['error']) ? sanitize_key($body['error']) : 'dailystory_oauth';
        $message = ! empty($body['error_description']) ? sanitize_text_field($body['error_description']) : $fallback;

        return new WP_Error($code, $message);
    }

    private static function pending_key () {
        return 'dailystory_oauth_' . get_current_user_id();
    }

    private static function base64url ( $bytes ) {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    // ──────────────────────────────────────────────
    // Tokens are encrypted at rest with a key derived from the site's salts,
    // so a copy of the database alone (a backup, an SQL injection dump) does
    // not carry a usable token. WordPress 5.2+ ships sodium_compat, so this is
    // only skipped on older installs without the sodium extension.
    //
    // If the salts change the tokens cannot be decrypted, and the connection
    // asks to be reconnected. Tracking itself is unaffected.
    // ──────────────────────────────────────────────
    private static function encrypt ( $plain ) {

        if ( ! function_exists('sodium_crypto_secretbox') )
            return 'plain:' . $plain;

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    private static function decrypt ( $stored ) {

        $stored = (string) $stored;

        if ( 0 === strpos($stored, 'plain:') )
            return substr($stored, 6);

        if ( 0 !== strpos($stored, 'v1:') || ! function_exists('sodium_crypto_secretbox_open') )
            return false;

        $raw = base64_decode(substr($stored, 3), true);

        if ( false === $raw || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES )
            return false;

        $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            return sodium_crypto_secretbox_open($cipher, $nonce, self::key());
        }
        catch ( Exception $e ) {
            return false;
        }
    }

    private static function key () {
        return hash('sha256', 'dailystory-connection|' . wp_salt('auth'), true);
    }
}
