# DailyStory WordPress Plugin: Gap Analysis vs. ConvesioConvert

| | DailyStory | ConvesioConvert |
|---|---|---|
| Version reviewed | 2.2.0 (`plugin/`) | 3.7.1 (`convertapp/`) |
| PHP source (excluding vendor) | 5 files, about 1,100 lines | about 100 files, about 8,500 lines |
| Minimum WP / PHP | 5.0 / 7.2 | 6.3 / 7.4 |
| WooCommerce headers | none | `WC requires at least: 7.0`, `WC tested up to: 11.1` |
| Commerce platforms | WooCommerce | WooCommerce, Easy Digital Downloads 2.x and 3.x |

Date: 2026-10-30. Analysis only. No code was changed.

---

## 1. Summary

The two plugins are built differently.

**DailyStory is a thin client.**
- It loads the beacon and offers three shortcodes.
- Since 2.2.0 it also sends WooCommerce browser events (product viewed, added to cart, cart updated, order completed and a conversion) through the JavaScript `Ds.sendEvent` and `Ds.conversion` calls.
- Authoritative store data is pulled by a separate DailyStory server-side polling job (see the comments in `class-dailystory-woocommerce.php`).

**ConvesioConvert is a two-way connector.** The plugin itself does most of the integration work:
1. **Connect flow.** A one-click redirect handshake replaces pasting IDs, and the backend is given a site token and an HMAC secret.
2. **Pull API.** The plugin exposes a token-protected REST API (`convesioconvert/v1/sync/*`). The backend uses it to pull products, orders (HPOS and legacy), users, guest customers, posts, pages, terms and site status.
3. **Incremental sync.** It records modification timestamps on every product, order, user and term, so the backend can sync only what changed.
4. **Server-side events.** Checkout, order status changes, user registration, login and comments are pushed server to server. They do not depend on the visitor's browser.
5. **Coupons.** The backend can create, update and delete WooCommerce and EDD coupons, which gives it unique per-contact discount codes.
6. **On-site data layer.** Every page carries the visitor's type (guest, lead or customer), order count, purchased products, last order and cart, so pop-ups and personalization can target shoppers.
7. **Form-plugin integrations.** Submissions are captured server-side from Contact Form 7, Elementor Pro, Jupiter X (Raven), Gravity Forms, Ninja Forms, WPForms and Divi/Bloom, with field-mapping UIs.
8. **Marketing email consent.** Consent checkboxes appear on WP registration, WooCommerce registration, classic and Blocks checkout, My Account, and EDD checkout.
9. **Operational polish.**
   - Backend health status shown in wp-admin.
   - Pause and resume.
   - Warnings when a caching plugin is detected, plus exclusions for those plugins' script optimizers.
   - A deactivation survey and a rating prompt.
   - "Erase data", and a Blocks-checkout compatibility declaration.

**The largest gaps, ordered by business impact:**
1. Server-side order and checkout events. Our conversion depends on JavaScript and can be lost.
2. Guest checkout capture for abandoned cart. Today we can't reach a guest who abandons a cart.
3. Form-plugin lead capture.
4. Marketing consent capture.
5. One-click connect and health status.
6. A shopper-context data layer for pop-up targeting.

**What not to copy.** ConvesioConvert gets several things wrong that we should not reproduce:
- a pre-checked consent box;
- an `uninstall.php` that does nothing;
- writing arbitrary coupon meta without an allow-list;
- shopper personal data in cacheable HTML;
- no exclusion of admins from tracking.

Section 7 lists these and the places where we can do better.

---

## 2. How each plugin is built

| Concern | DailyStory 2.2.0 | ConvesioConvert 3.7.1 |
|---|---|---|
| Bootstrap | Singleton plus `init` hook; `require_once` of 4 classes | Composer PSR-4 autoload, namespaced classes. Most features load only after the site is connected (`convesioconvert.php:47-72`) |
| Connect to SaaS | Paste Site ID and Site URL | Redirect handshake with a single-use consent key. The backend returns a site token, site ID and verification secret (`admin/class-integration.php:35-136`) |
| Data to SaaS | Browser JavaScript (`Ds.sendEvent`, `Ds.conversion`) plus the DailyStory backend polling WooCommerce | (a) the backend pulls from the plugin's REST API; (b) the plugin pushes events through GraphQL (`/graphql`, Bearer site token) and `/v1/outstand`; (c) the browser executor script |
| Identity | `window.dsIdentity` (email, name, phone) plus a `sendEvent("wordpress","identify")`. Not signed | User ID plus an HMAC-SHA256 identity token keyed by the verification secret (`includes/functions.php:44-50`), passed to the automations script and the data layer |
| Admin | One settings page (Settings → DailyStory) | Settings page that shows "welcome" or "connected" states, notices framework, health check, survey, rating prompt, data manager |
| Extensibility | 2 filters (`dailystory_woocommerce_tracking_enabled`, `dailystory_identify_visitor`) | About 15 filters/actions (`convesioconvert_modification_post_types`, `…_data_layer_commerce_entry`, `…_attach_user_ecommerce_data`, etc.) |

**Our architecture is a legitimate choice, but it costs reliability in specific places.** Anything sent only from the browser is lost when:
- the beacon is blocked by an ad blocker or consent tool;
- a caching or optimizer plugin delays the script;
- the visitor closes the tab before `ds_ready` fires.

Polling can't see carts that never become orders, and it adds latency to every trigger.

---

## 3. Feature gap matrix

Legend: ✅ has it, 🟡 partial, ❌ missing. **Backend?** marks whether closing the gap also needs work in the DailyStory app or API.

### 3.1 Connection, setup and admin

| # | Capability | DailyStory | ConvesioConvert | Backend? |
|---|---|---|---|---|
| A1 | Connect | 🟡 Paste Site ID and Site URL by hand | ✅ One-click redirect handshake, single-use consent key, nonce | Yes: authorize endpoint that returns tenant UID, data-center URL and a site secret/token |
| A2 | Redirect to settings on activation | ❌ | ✅ 60-second transient, skips bulk and network activation | No |
| A3 | Connected-status screen (account, site, sync state, deep link to app) | ❌ Form fields only | ✅ `admin/views/integrated.php` | Partly: needs a status API |
| A4 | Disconnect / remove integration | ❌ Clear the fields by hand | ✅ Confirmation dialog; options cleared (backend not notified) | Optional |
| A5 | Pause / resume tracking without disconnecting | ❌ | ✅ AJAX toggle plus backend `sitePause` | Optional |
| A6 | Backend health check shown in wp-admin | ❌ | ✅ GraphQL `siteHealthStatus`, polled, with KB-linked notices | Yes |
| A7 | Caching-plugin detection notice | ❌ | ✅ Detects 8 caching plugins | No |
| A8 | Script-optimizer exclusions (WP Rocket, LiteSpeed, Autoptimize, `data-minify="0"`) | ❌ | ✅ `includes/hooks.php:19-69` | No |
| A9 | WP Site Health tests | ❌ | ❌ | No (we could lead here) |
| A10 | Deactivation feedback survey | ❌ | ✅ 10 reasons, posted to the backend | Yes: an endpoint to receive it |
| A11 | Rating prompt | ❌ | ✅ Shown only if the owner visited the app in the last 30 days and has 3 or more rules | Optional |
| A12 | "Erase all plugin data" button | ❌ | ✅ `admin/class-data-manager.php` | No |
| A13 | Uninstall cleanup | 🟡 Deletes the `dailystory_settings` option only. Order meta `_dailystory_conversion_sent` stays | ❌ `uninstall.php` is empty | No |
| A14 | i18n (text domain loaded, strings wrapped, `.pot`) | ❌ Header only; no strings are translatable | 🟡 Text domain loaded, 113-string `.pot`, no translations | No |
| A15 | Minified admin assets with `SCRIPT_DEBUG` switch | n/a (no admin assets) | ✅ | No |
| A16 | Environment override constants (staging API) | ❌ | ✅ `CONVESIOCONVERT_SUFFIX`, `API_URL`, `APP_URL` | No |
| A17 | WooCommerce version headers (`WC requires at least` / `WC tested up to`) | ❌ (WooCommerce flags the plugin as "untested") | ✅ | No |
| A18 | Readme accuracy | 🟡 FAQ says "tested up to 6.1"; header says 6.8; WooCommerce features are barely described | ✅ Extensive marketing copy | No |

### 3.2 Tracking and identity

| # | Capability | DailyStory | ConvesioConvert | Backend? |
|---|---|---|---|---|
| T1 | Beacon / tracking script | ✅ Inline loader in `<head>` at priority 5 | ✅ `wp_register_script`, head, `strategy => async`, `dns-prefetch` | No |
| T2 | Identify logged-in user | 🟡 Plain email, name and phone, sent through `sendEvent` | ✅ User ID plus HMAC identity token, so others can't forge an identity | Yes: the beacon or API must accept and verify a signed identity |
| T3 | Visitor-type / shopper data layer (guest, lead or customer; order count; purchased products; last order; cart) | ❌ | ✅ `window._convesioconvert` (`public/class-init.php:21-75`) | Yes: popup and automation rules must consume it |
| T4 | Page context (page type, product, categories, checkout, thank-you) | 🟡 Only on product view, as an event | ✅ `Page_Content_Details` | Yes, for targeting |
| T5 | Guest promoted to known customer after checkout (session override) | ❌ | ✅ `Session_Manager::override_user_props` | Yes |
| T6 | Session and client ID stored on the order (attribution) | ❌ Only the JavaScript conversion ties the order to the session | ✅ Order meta `_convesioconvert_{site}_session_id` / `client_id` | Yes: the beacon must expose its session ID |
| T7 | Server-side WP events (user registered, login, comment) | ❌ | ✅ `class-event-handler.php` | Yes: needs our REST Event endpoint plus an API token |
| T8 | Exclude admins / staff from tracking | ❌ | ❌ | No (we could lead here) |
| T9 | Cookie-consent gating (WP Consent API or CMP) | ❌ | ❌ | No (we could lead here) |
| T10 | Web push service worker | 🟡 `assets/js/dailystory_sw.js` ships, but **nothing serves or registers it**. A service worker must be served from the site root (or with a `Service-Worker-Allowed` header) to control the site. Needs checking | ❌ | Check how the beacon registers it |

### 3.3 WooCommerce

| # | Capability | DailyStory | ConvesioConvert | Backend? |
|---|---|---|---|---|
| W1 | HPOS compatibility declared | ✅ | ✅ | No |
| W2 | Cart and Checkout Blocks compatibility declared | ❌ Deliberately not declared yet | ✅ Declared, with a Blocks-native consent field | No |
| W3 | Product view event | ✅ JavaScript `product_viewed` | ✅ Via data layer and executor | No |
| W4 | Add-to-cart event | ✅ Session-queued, sent on the next page render | 🟡 Cart is in the data layer; no separate event | No |
| W5 | Live cart state | ✅ `cart_updated` on **every** page load while the cart has items | ✅ Data layer plus persistent-cart meta during sync | No |
| W6 | Checkout-started / capture the guest's email before the order exists | ❌ | ✅ "Checkout Started" trigger (changelog 2.7.0, 3.2.8). Reset on order placed | Yes |
| W7 | Cart recovery link that restores the cart on another device | ❌ `recoveryUrl` is just `wc_get_cart_url()` | ❌ | Partly: we could lead here |
| W8 | Order conversion / attribution | 🟡 Browser-only `Ds.conversion` on the thank-you page. **The "sent" flag is saved server-side before the JavaScript runs**, so a blocked beacon loses the conversion and it is never retried | ✅ Server-side checkout event, once per order, plus session IDs on the order | Yes: server-side conversion API (exists, needs an API key) |
| W9 | Order status changes in real time (processing, completed, refunded, cancelled, failed, on-hold) | ❌ Depends on the polling interval | ✅ `woocommerce_order_status_changed` → GraphQL `orderStatusChangeTrigger` | Yes |
| W10 | New customer created event | ❌ | ✅ `wp_customer_created` | Yes |
| W11 | Product catalog sync | 🟡 Through the backend poller (outside this plugin) | ✅ `sync/products/woo` with incremental modification stamps, up-sells and cross-sells | Depends on the poller |
| W12 | Order sync with incremental changes (including trash and untrash, customer re-association) | 🟡 Backend poller | ✅ HPOS and legacy controllers, `_last_modification` meta | Depends on the poller |
| W13 | Historical guest customer back-fill | 🟡 Backend poller (orders) | ✅ `sync/guest-customers/woo` | Depends on the poller |
| W14 | Dynamic, unique per-contact coupons | ❌ | ✅ REST create, update, delete, plus notifications when coupons change, plus a "managed" badge in the coupon list | Yes |
| W15 | Store info (currency, gateways, WooCommerce version) reported to the backend | ❌ | ✅ `class-info.php` via status and extra endpoints | Optional |
| W16 | Product review / rating changes | ❌ | ✅ Marks the product as modified when a review is posted | Depends on the poller |
| W17 | Shopper context for on-site targeting (has bought X, number of orders) | ❌ | ✅ Cached per user and per guest email | Yes |

### 3.4 Lead capture and forms

| # | Capability | DailyStory | ConvesioConvert | Backend? |
|---|---|---|---|---|
| F1 | Embed hosted forms | ✅ `[ds-webform]`, rendered server-side | ❌ (their forms are in-app pop-ups) | n/a |
| F2 | Pop-up / exit-intent triggers | ✅ `[ds-popup]`, `[ds-exitintent]` | ✅ Through the automation engine | n/a |
| F3 | Gutenberg block for forms and pop-ups | ❌ | ❌ | No (we could lead here) |
| F4 | Elementor Pro form action, with field-mapping UI and tags | ❌ | ✅ | Partly: needs a list of fields and tags |
| F5 | Gravity Forms field mapping | ❌ | ✅ Including splitting name and address | Partly |
| F6 | WPForms / Ninja Forms field mapping | ❌ | ✅ | Partly |
| F7 | Contact Form 7 | ❌ | ✅ Fixed field-name convention, no UI | No (WebToLead exists) |
| F8 | Divi / Bloom opt-in provider | ❌ | ✅ | No |
| F9 | Jupiter X (Raven) forms | ❌ | ✅ | No |
| F10 | Caching the remote form HTML | ❌ `[ds-webform]` makes a blocking HTTP call (10-second timeout) on **every** render | n/a | No |

> Note: DailyStory's Magic Forms may already capture some forms from the browser. Server-side form integrations are still worth building: they keep working when the beacon is blocked, and they give explicit field mapping and campaign or tag assignment.

### 3.5 Consent and privacy

| # | Capability | DailyStory | ConvesioConvert | Backend? |
|---|---|---|---|---|
| C1 | Marketing-consent checkbox at WooCommerce checkout (classic and Blocks) | ❌ | ✅ (pre-checked; see §7) | Yes: consent field on the contact |
| C2 | Consent on WP and WooCommerce registration and My Account | ❌ | ✅ | Yes |
| C3 | Consent field in form mappings (EU consent, marketing consent) | ❌ | ✅ Value normalized to `granted`, `denied` or `unknown` | Yes |
| C4 | Configurable consent text | ❌ | 🟡 One global string; `{{sitename}}` is replaced at save time | No |
| C5 | Consent stored with timestamp and where it was given | ❌ | ✅ `{consent, timestamp, occasion, order_id}` | Yes, to show it in the app |
| C6 | WP personal-data exporter / eraser | ❌ | ❌ | No (we could lead here) |
| C7 | Suggested privacy-policy text (`wp_add_privacy_policy_content`) | ❌ | ❌ | No (we could lead here) |
| C8 | Unsubscribe sync back to WP | ❌ | ❌ | (we could lead here) |

### 3.6 Other platforms

| # | Capability | DailyStory | ConvesioConvert |
|---|---|---|---|
| P1 | Easy Digital Downloads 3.x (orders, customers, discounts, checkout) | ❌ | ✅ |
| P2 | EDD 2.x | ❌ | ✅ (has a bug: `edd2/class-checkout.php:38-40` doesn't return the meta, so the checkout event is re-sent) |
| P3 | Multisite awareness (members of other sites treated as guests) | ❌ | 🟡 |
| P4 | WooCommerce.com Marketplace billing | ❌ | ✅ (billing channel only; not a product feature) |

---

## 4. Issues in our current plugin

These came up during the comparison. They are not parity items, but they should be fixed alongside the parity work.

1. **The conversion can be silently lost.**
   - Where: `render_conversion()` (`class-dailystory-woocommerce.php`).
   - What happens: it writes `_dailystory_conversion_sent` to the order in PHP, before the browser has run `Ds.conversion`. If the beacon is blocked, the conversion is lost and marked as sent, so it is never retried.
   - Fix: send it server-side, or have the browser confirm before the flag is set.
2. **The identity can be forged.**
   - `sendEvent("wordpress","identify", {email})` is unauthenticated. Anyone can call it from the console with any email address and tie their session to that contact.
   - ConvesioConvert prevents this with an HMAC token.
3. **Personal data in cacheable HTML.**
   - `window.dsIdentity` puts the logged-in user's email and phone number into the page markup.
   - Most page caches skip logged-in users, but some (for example LiteSpeed with private cache misconfigured, or Cloudflare APO rules) do not.
   - ConvesioConvert has the same weakness.
   - Recommendation: load identity through a non-cached REST/AJAX call, or send a signed token instead of raw personal data.
4. **`cart_updated` fires on every page view** while the cart has items. This inflates event volume and may re-trigger automations. Fire it only when the cart contents hash changes (store the last hash in the WooCommerce session).
5. **`dailystory_sw.js` is not served.** Nothing in the plugin registers it, rewrites a route to it, or sets `Service-Worker-Allowed`. Confirm whether web push from the plugin actually works.
6. **`[ds-webform]` calls the remote server on every page render,** and nothing is cached. Cache the HTML in a transient for a few minutes, and serve the last good copy if a request fails.
7. **Inline beacon versus optimizers.**
   - WP Rocket "Delay JS", LiteSpeed "JS Delay" and similar features will postpone our inline loader and our `ds_ready` listeners.
   - Nothing excludes them today.
8. **Small items.**
   - The admin notice link is relative (`options-general.php?...`); use `admin_url()`.
   - `$_GET['page']` is read without sanitizing.
   - Admin strings are not translatable.
   - The settings page uses `<h2>` and inline styles instead of `<h1>` and the standard WP layout.
   - The readme FAQ is out of date.
   - The `WC tested up to` header is missing.

---

## 5. Recommended updates (prioritized)

Effort: **S** is under 2 days, **M** is 3 to 7 days, **L** is 1 to 3 weeks (plugin side only). **BE** means it needs DailyStory backend or API work.

### Phase 0: Correctness and trust (do first)

| # | Update | Closes | Effort | BE |
|---|---|---|---|---|
| 0.1 | Send the order conversion **server-side**. On `woocommerce_payment_complete` / `woocommerce_order_status_processing\|completed`, call the DailyStory REST conversion and event API with the order, then set `_dailystory_conversion_sent` only when the call succeeds. Keep the thank-you JavaScript as a best-effort way to tie the order to the browser session. Retry through Action Scheduler (bundled with WooCommerce) | W8, §4.1 | M | Needs an API token in the plugin (see 1.1) |
| 0.2 | Add a session hand-off for attribution. Read the beacon's visitor and session ID (from its cookie, or from a hidden checkout field filled by JavaScript) and save it as order meta, so the server-side conversion can be attributed | T6 | S–M | Confirm the beacon cookie format |
| 0.3 | Fire `cart_updated` only when the cart actually changes (compare a hash) | §4.4 | S | No |
| 0.4 | Cache `[ds-webform]` HTML in a transient, and serve the last good copy on failure | F10, §4.6 | S | No |
| 0.5 | Add script-optimizer exclusions for WP Rocket, LiteSpeed, Autoptimize, SiteGround Optimizer and Perfmatters: `data-no-optimize`, `data-cfasync="false"`, plus their exclusion filters | A8, §4.7 | S | No |
| 0.6 | Fix or remove the service worker. Serve it from the site root through a rewrite rule (or set `Service-Worker-Allowed`), or drop it if the beacon handles push some other way | T10 | S | Check |
| 0.7 | Clean up headers and readme: `WC requires at least`, `WC tested up to`; fix the FAQ; document WooCommerce features | A17, A18 | S | No |

### Phase 1: WooCommerce parity (the core competitive gap)

| # | Update | Closes | Effort | BE |
|---|---|---|---|---|
| 1.1 | **Connect flow.** Replace pasting the Site ID and URL with a "Connect to DailyStory" button. Redirect to `app.dailystory.com/connect/wordpress` with the site URL, return URL, nonce and a single-use state key. The app returns the tenant UID, data-center URL, a scoped API token and an HMAC secret. Keep manual entry as an "Advanced" fallback | A1, A2, A3, A4 | M | **Yes**: connect/authorize endpoint and token issuance (the "Create API Token" API exists) |
| 1.2 | **Real-time order status events.** Hook `woocommerce_order_status_changed` and post to the DailyStory Event API (`woocommerce` / `order_<status>`), including refunds. Make it non-blocking (Action Scheduler) so checkout stays fast | W9 | M | Event endpoint exists; automations need triggers for these statuses |
| 1.3 | **Checkout started and guest email capture.** On the classic checkout (`woocommerce_checkout_update_order_review`, or a JavaScript blur on `#billing_email`) and on Blocks checkout (Store API `woocommerce_store_api_checkout_update_customer_from_request`), send a `checkout_started` event with email, phone and cart contents, after marketing consent where required. Clear it on order placed | W6 | M–L | **Yes**: abandoned-checkout trigger that is reset by the order |
| 1.4 | **Signed identity.** Add an HMAC-SHA256 of the user ID or email, keyed by the secret from 1.1, and pass it to the beacon. Stop putting raw email and phone in the page (or keep them only when no secret is configured) | T2, §4.2, §4.3 | S (plugin) | **Yes**: the beacon and API must verify it |
| 1.5 | **Cart recovery link.** Store the cart contents under a random token (WooCommerce session or custom table) and expose `?ds_cart=<token>`, which rebuilds the cart and redirects to checkout. Put that link in `recoveryUrl` | W7 | M | No (we could lead here) |
| 1.6 | **Declare Cart and Checkout Blocks compatibility** once 1.3 and 3.1 work on Blocks | W2 | S | No |
| 1.7 | **Dynamic coupons.** Add a token-authenticated REST route, `dailystory/v1/coupons` (create, delete), that writes coupons through `WC_Coupon` with an **allow-list** of fields (amount, type, expiry, usage limit, email restriction, products). Tag each coupon with `_dailystory_managed` and mark it in the coupon list | W14 | M | **Yes**: a coupon block in email and SMS that calls it. Alternative: if the backend already holds WooCommerce REST keys with write scope, it can create coupons without the plugin |
| 1.8 | **Shopper data layer.** Publish `window.dsContext = {userType, orderCount, totalSpent, lastOrderAt, purchasedProductIds, cart{…}, page{type, productId, categories}}`. Load it uncached for logged-in users (REST call after load) so page caches can't leak it | T3, T4, W17 | M | **Yes**: pop-up and rule targeting must read it |
| 1.9 | **New customer / registration events.** `user_register` and `woocommerce_created_customer` become a server-side contact upsert (by email), using the existing `/api/v1/contact/upsert` | T7, W10 | S | No (API exists) |

### Phase 2: Lead capture breadth

| # | Update | Closes | Effort | BE |
|---|---|---|---|---|
| 2.1 | **Form integration framework.** One `DailyStory_Form_Integration` base class that maps submitted fields to DailyStory contact fields and posts server-side to the contact upsert / WebToLead API, with campaign ID, tags and consent. Common email, name and phone fields are detected automatically so it works with no setup, and explicit mapping can override that | F4–F9 | M (framework) | Optional: an API that lists campaigns, tags and custom fields to fill the dropdowns |
| 2.2 | Adapters in priority order by install base: **Contact Form 7** (S), **WPForms** (S–M), **Gravity Forms** (M), **Elementor Pro** form action (M), **Ninja Forms** (S–M), **Fluent Forms** (S; ConvesioConvert doesn't support it, so we could lead here), **Divi** (M) | F4–F9 | S–M each | No |
| 2.3 | **Gutenberg blocks** for "DailyStory Form" (form picker, live preview) and "DailyStory Popup trigger". Keep the shortcodes for backward compatibility | F3 | M | Optional: a form-list API for the picker |
| 2.4 | **Elementor widget** for embedding DailyStory forms (separate from 2.2's form action) | (we could lead here) | S–M | No |

### Phase 3: Consent and privacy (use this to stand out from ConvesioConvert)

| # | Update | Closes | Effort | BE |
|---|---|---|---|---|
| 3.1 | Marketing-consent checkbox (email, plus a separate SMS option) on classic checkout, Blocks checkout (`woocommerce_register_additional_checkout_field`), WooCommerce registration, WP registration and My Account. **Unchecked by default** and configurable per location. Store `{consent, channel, timestamp, location, text_shown, ip_hash}` on the user and the order, and send it with the contact upsert | C1–C5 | M | **Yes**: per-channel consent fields on the contact |
| 3.2 | Integrate the WP Consent API: when a consent-management plugin is active, don't load the beacon (or load it in cookieless mode) until the "statistics" or "marketing" category is granted | T9 | S–M | Optional: a cookieless beacon mode |
| 3.3 | Register a WP personal-data exporter and eraser that export and erase local DailyStory meta, and optionally call the DailyStory API to export or delete the contact | C6 | S–M | Optional |
| 3.4 | Add suggested privacy-policy text with `wp_add_privacy_policy_content` | C7 | S | No |
| 3.5 | Setting to **exclude roles from tracking** (default: administrator and shop_manager) | T8 | S | No |

### Phase 4: Admin and operations polish

| # | Update | Closes | Effort | BE |
|---|---|---|---|---|
| 4.1 | Connected-state settings screen: account, data center, status, "Open DailyStory" deep link, Pause, Disconnect | A3–A5 | M | Partly |
| 4.2 | **WP Site Health tests**: connected, beacon reachable, token valid, WooCommerce event queue healthy, caching or optimizer conflicts. Add a `debug_information` section | A6, A9 | S–M | Optional status endpoint |
| 4.3 | Notice when a caching plugin is detected, with a link to docs | A7 | S | No |
| 4.4 | REST endpoint `dailystory/v1/status` (token-protected) so DailyStory support can see the WP, WooCommerce, PHP and plugin versions and active plugins | (support) | S | Optional |
| 4.5 | Full i18n: wrap every string, load the text domain, ship a `.pot` | A14 | S | No |
| 4.6 | Uninstall: remove the option, transients and `_dailystory_*` order/user meta (HPOS-aware). Add a "Reset plugin data" button | A12, A13 | S | No |
| 4.7 | Deactivation survey (optional) and a rating prompt shown only after real use (for example 30 days connected and at least 1 automation) | A10, A11 | S each | Survey endpoint |
| 4.8 | `DAILYSTORY_ENV` / `DAILYSTORY_API_URL` constants for staging | A16 | S | No |

### Phase 5: Optional / market-driven

| # | Update | Notes |
|---|---|---|
| 5.1 | Easy Digital Downloads 3.x support (checkout conversion, order status, customer upsert) | Do it only if the sales pipeline justifies it. ConvesioConvert's EDD code doubles their commerce surface. If we build it, put an e-commerce abstraction (Customer and Order interfaces) under Phase 1 first so EDD is just an adapter |
| 5.2 | Incremental pull API (`dailystory/v1/sync/*`) with modification stamps, like ConvesioConvert's | Only if the backend poller struggles with large stores. WooCommerce's own REST API with `modified_after` covers most cases |
| 5.3 | Multisite: network-level connect and a clear policy for each site | Low demand |

---

## 6. Proposed plugin structure after the updates

```
plugin/
  dailystory.php                     bootstrap, constants, autoload
  uninstall.php                      full cleanup
  includes/
    class-dailystory-api.php         REST client (token, retries, Action Scheduler queue)
    class-dailystory-identity.php    HMAC identity, data layer (uncached REST)
    class-dailystory-tracking-pixel.php
    class-dailystory-shortcodes.php  (+ transient cache)
    class-dailystory-consent.php     checkboxes, WP Consent API, privacy exporter/eraser
    class-dailystory-rest.php        dailystory/v1/{status,coupons,cart-restore}
    woocommerce/
      class-dailystory-woo-events.php    browser events (existing)
      class-dailystory-woo-orders.php    server-side conversion + status events
      class-dailystory-woo-checkout.php  checkout-started, consent, Blocks
      class-dailystory-woo-cart.php      recovery tokens
      class-dailystory-woo-coupons.php
    forms/
      class-dailystory-form-integration.php  base
      cf7.php, wpforms.php, gravityforms.php, elementor.php, ninja-forms.php, fluent-forms.php
    blocks/                          Gutenberg form + popup blocks
  admin/
    class-dailystory-admin.php       connect flow, status screen, settings
    class-dailystory-site-health.php
  languages/dailystory.pot
```

---

## 7. Where ConvesioConvert is weak (don't copy; use these as selling points)

| Weakness | Evidence | Our approach |
|---|---|---|
| The marketing-consent checkbox is **pre-checked** for new visitors, which is questionable under GDPR | `includes/class-email-consent.php:138-143` | Unchecked by default; separate email and SMS consent |
| `uninstall.php` does nothing; data stays after deletion | `uninstall.php:24-26` | Full cleanup |
| The coupon API writes **any** meta key the backend sends | `controller/class-coupon-controller.php:142-146` | Allow-listed fields through `WC_Coupon` |
| Inbound REST tokens are trusted after a remote verify call. The local check reads the JWT `sub` claim without checking the signature. Every new token adds a 15-second outbound request | `authorization/class-rest-token-verifier.php`, `class-rest-token-site-verifier.php` | Verify an HMAC signature locally with the shared secret |
| The shopper's email, name and ID are written into page HTML, so a full-page cache can leak them | `public/class-init.php:21-75` | Uncached REST data layer; signed token instead of personal data |
| Admins are tracked like customers | no exclusion code | Role exclusion setting |
| No cookie-consent integration | no CMP / WP Consent API code | WP Consent API |
| No Site Health, no privacy exporter or eraser | none found | Both |
| Disconnecting does not tell the backend | `admin/class-integration.php:161-178` | Revoke the token when disconnecting |
| Form-plugin errors only go to `error_log`; there is no retry | `form-integration/class-integration.php` | Queued delivery through Action Scheduler |
| EDD2 checkout bug resends events on every thank-you page load | `edd2/class-checkout.php:38-40` | n/a |

**What we already do that they don't:**
- embed hosted forms server-side (`[ds-webform]`);
- shortcodes to trigger specific pop-ups and exit-intent;
- an explicit add-to-cart event;
- support for older WP and PHP versions (5.0 / 7.2 vs. 6.3 / 7.4);
- a much smaller codebase that is easier to maintain;
- SMS and push as channels (the plugin should promote them more, for example with SMS consent at checkout).

---

## 8. Dependencies on the DailyStory backend

These plugin items can't ship until the app or API supports them:

1. **Connect / authorize flow** that returns tenant UID, data-center URL, a scoped API token and an HMAC secret. Needed by 1.1, 0.1, 1.2, 1.4, 1.7 and 2.1.
2. **Signed-identity verification** in the beacon / identify path. Needed by 1.4.
3. **Abandoned-checkout trigger** that is reset when the order is placed. Needed by 1.3.
4. **Order-status triggers** (processing, completed, refunded, cancelled) in the automation builder. Needed by 1.2.
5. **Per-channel consent fields** on the contact, with source and timestamp. Needed by 3.1.
6. **Pop-up and rule targeting on the shopper data layer.** Needed by 1.8.
7. **Dynamic coupon content block** in email and SMS. Needed by 1.7.
8. Optional: list APIs for campaigns, tags, custom fields and forms (for mapping UIs and block pickers), a cookieless beacon mode, and a deactivation-feedback endpoint.

Already available and usable today:
- Contact upsert (`/api/v1/contact/upsert`).
- Event tracking REST endpoint.
- Conversion REST API (with an API key).
- WebToLead (CORS, uses a campaign ID).
- Create API Token.
- `earnLoyalty` in the JavaScript API.

Phase 0, plus 1.5, 1.9, Phase 2 and most of Phases 3 and 4, can start now.

---

## 9. Suggested sequencing

1. **Release 2.3 (Phase 0):** reliability fixes; no backend dependency except the API token for 0.1. If the connect flow isn't ready, the token can be pasted by hand in an "Advanced" field.
2. **Release 2.4:** connect flow (1.1), server-side order and status events (1.2), registration upsert (1.9), signed identity (1.4), consent checkboxes (3.1), role exclusion (3.5), i18n and uninstall cleanup (4.5, 4.6).
3. **Release 2.5:** checkout started and guest capture (1.3), cart recovery links (1.5), Blocks compatibility (1.6), form integrations for CF7, WPForms, Gravity Forms and Elementor (2.1, 2.2).
4. **Release 2.6:** dynamic coupons (1.7), shopper data layer (1.8), Gutenberg blocks (2.3), Site Health and status screen (4.1–4.4), WP Consent API and privacy tools (3.2–3.4).
5. **Later:** EDD, pull-sync API, multisite, depending on demand.
