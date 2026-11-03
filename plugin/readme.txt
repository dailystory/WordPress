# Email and SMS marketing for WordPress by DailyStory
Contributors: dailystory, roberthoward
Tags: email marketing, sms, woocommerce, popup, marketing automation
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 2.4.1
License: GNU General Public License v2.0 or later

Add DailyStory popups, web forms and visitor tracking to WordPress, and turn WooCommerce store activity into email and SMS automations.

## Description

[DailyStory](https://www.dailystory.com/) is a marketing automation platform for email, text messaging (SMS), push notifications, popups, web forms and landing pages. This plugin connects your WordPress site to your DailyStory account, so you can capture leads on your site and follow up with them automatically.

Setup takes about a minute. Click **Connect to DailyStory**, sign in, and choose your account. The plugin installs your tracking code for you, with nothing to copy and paste.

Watch the WordPress plugin in action:
[youtube https://www.youtube.com/watch?v=P5hjQqR2Ivg]

### What the plugin adds to your site

* **Visitor tracking.** DailyStory's tracking code goes on every page, so you can see which pages contacts visit and start automations from that activity.
* **Popups.** [Popups](https://docs.dailystory.com/article/ptshg9vhir-daily-story-popups) you design in DailyStory appear on your site automatically: when a visitor arrives, after a delay, on specific pages, or as they are about to leave. Use them for newsletter sign-ups, first-order discounts and lead magnets.
* **Web forms.** Embed [DailyStory web forms](https://docs.dailystory.com/article/5itiwxukqb-daily-story-web-forms) in any post, page or widget. Submissions go straight into DailyStory as contacts and can start an automation.
* **Your existing forms.** Already use another form plugin? [Magic Forms](https://docs.dailystory.com/article/dw3bqm0v03-daily-story-magic-forms) capture its submissions into DailyStory without replacing the form.
* **Signed-in visitors.** When a logged-in user visits, the plugin tells DailyStory who they are, so their activity is tied to their contact record.

### WooCommerce

Turn your store's activity into email and text message automations that run on their own. When WooCommerce is active and your account is connected, checkouts and orders start DailyStory's built-in store [automation triggers](https://docs.dailystory.com/article/pffq9y943p-autopilot-triggers#when_cart_abandoned) the moment they happen:

* **When cart abandoned** when a shopper starts checkout, including guests who have only typed their email
* **When cart is purchased** when an order is placed, including bank-transfer orders awaiting payment
* **When order is shipped** when an order is marked completed
* **When payment is declined** when a payment fails

Each trigger starts once per order, and the events come from your server, so ad blockers and closed tabs don't lose them.

**Automations you can build**

* **Abandoned cart recovery.** Wait an hour, send a reminder email or text, then follow up with an offer. Set the automation's [exit goal](https://docs.dailystory.com/article/xrhcd8830x-daily-story-autopilot-guide#exit_an_automation_when_a_goal_is_achieved) to **When a cart is purchased** so anyone who buys stops getting reminders. See the [abandoned cart guide](https://docs.dailystory.com/article/q0pgudbzsg-abandoned-cart-campaign).
* **Order thank-you and onboarding** on **When cart is purchased**: a thank-you text, how-to tips, or a welcome series for first-time buyers.
* **Review requests and cross-sells** on **When order is shipped**: ask for a review a few days after delivery, then suggest related products.
* **Failed payment follow-up** on **When payment is declined**: a quick nudge to try another card.
* **Browse abandonment and win-back.** Product views, add to cart and cart changes arrive as events (`woocommerce_product_viewed`, `woocommerce_added_to_cart`, `woocommerce_cart_updated`) for segments and custom automations, such as "viewed a product twice but didn't buy" or "hasn't ordered in 60 days".

**Abandoned cart emails that show the cart.** The **Woo Products** email block shows each shopper their own cart (products, images, quantities and prices, formatted the way your store shows them), even when they left before an order was created. Its **Complete your order** button is a recovery link that rebuilds the cart in any browser, so it works when the shopper opens the email on another device. Recovery links last 30 days.

**Know what your marketing earns.** Completed orders are recorded as a [conversion](https://docs.dailystory.com/article/conversion-funnels), so revenue is credited to the email, text or popup that drove it.

**Add DailyStory's WooCommerce integration** for the full picture. It syncs your customers, order history and [purchase history](https://docs.dailystory.com/article/8jpx3fj1r4-integrations-woocommerce#purchase_history) into DailyStory for segments like "bought X in the last 30 days" or "spent more than $100", and adds the [Woo Products email block](https://docs.dailystory.com/article/8jpx3fj1r4-integrations-woocommerce#woo_products_email_widget) to the email designer. See [WooCommerce Integration](https://docs.dailystory.com/article/8jpx3fj1r4-integrations-woocommerce).

Store tracking is on by default and can be turned off on the settings page. The plugin is compatible with WooCommerce High-Performance Order Storage (HPOS).

### Shortcodes

* `[ds-webform id="FORM_ID"]` embeds a DailyStory web form. Add `redirect_url="https://..."` to send people to a page after they submit, for example to deliver a download.
* `[ds-popup id="POPUP_ID"]` shows a popup when the page loads.
* `[ds-exitintent id="POPUP_ID"]` shows a popup when a visitor is about to leave the page.

You'll find each form's and popup's ID in DailyStory. See [WordPress Integration](https://docs.dailystory.com/article/8omibw2171-integrations-wordpress) for examples.

### What you can do with DailyStory

**Automate the follow-up.** Build automations in a drag-and-drop designer from triggers (a form is submitted, a page is visited, a cart is abandoned, a contact joins a segment), actions (send an email or text, wait, tag, score) and conditions (did they open, click or buy?). Run them once, such as a welcome series, or [evergreen](https://docs.dailystory.com/article/ever-green-automations), such as form auto-replies and reminders. See the [automation guide](https://docs.dailystory.com/article/xrhcd8830x-daily-story-autopilot-guide).

**Reach people on the channel they use:**

* **Email.** Design with the drag-and-drop [email designer](https://docs.dailystory.com/article/kw4n3vdfqc-using-the-email-designer), or edit the HTML directly.
* **Text messaging.** Two-way [SMS and MMS](https://docs.dailystory.com/article/sf7cdfvdrz-create-or-edit-a-text-message) for reminders, offers and conversations.
* **Push notifications.** [Web push](https://docs.dailystory.com/article/83ecg64apo-web-push-notification) and mobile app push.

**Capture leads.** [Popups](https://docs.dailystory.com/article/ptshg9vhir-daily-story-popups), [web forms](https://docs.dailystory.com/article/5itiwxukqb-daily-story-web-forms), [Magic Forms](https://docs.dailystory.com/article/dw3bqm0v03-daily-story-magic-forms) and hosted [landing pages](https://docs.dailystory.com/article/z2l32rh39p-landing-pages).

**Target the right people.** [Dynamic segments](https://docs.dailystory.com/article/v1v5lti4gf-segments) keep themselves up to date from contact data, site visits and store activity. Add lead scoring and tags to focus on your best prospects.

**Share your blog.** Put your latest WordPress posts into any email with the [RSS widget](https://docs.dailystory.com/article/3xed75mlkd-rss-widget), or send a newsletter automatically when you publish with the [When RSS is updated](https://docs.dailystory.com/article/pffq9y943p-autopilot-triggers#when_rss_is_updated) trigger. The plugin adds each post's featured image to your feed, so your emails show your images instead of a placeholder.

**Measure what works.** Reports for emails, texts, popups and forms, plus [conversion funnels](https://docs.dailystory.com/article/conversion-funnels) that tie revenue back to campaigns.

DailyStory also connects to Shopify, BigCommerce, Square and other platforms. [See all features](https://www.dailystory.com/features/) or browse the [documentation](https://docs.dailystory.com/).

## Installation

### From your WordPress dashboard
1. Go to **Plugins > Add New** and search for "DailyStory"
2. Click **Install Now**, then **Activate**
3. The DailyStory settings page opens. Click **Connect to DailyStory**
4. Sign in to DailyStory, choose your account and click **Allow**

That's it. Your popups and tracking are live.

### Prefer not to sign in from WordPress?
On the settings page (**Settings > DailyStory**), paste the tracking code from **Account Settings > Setup > Website** in DailyStory and click **Save Settings**. The plugin reads your Site ID and Site URL from the code and checks them with DailyStory.

### Manual upload
1. Download the plugin and unzip it
2. Upload the `dailystory` folder to `/wp-content/plugins/`
3. Activate the plugin under **Plugins**, then connect as above

See our [WordPress setup guide](https://docs.dailystory.com/article/qfl7mox9nn-install-daily-story-tracking-tag-in-word-press) for more detail.

## Frequently Asked Questions

### Do I need a DailyStory account?
Yes. The plugin connects your site to DailyStory, where you design your popups, forms and automations. [Start for free](https://dailystory.com/pricing/).

### What are the requirements?
WordPress 5.0 or later and PHP 7.2 or later. The plugin is tested up to WordPress 7.1.

One-click **Connect to DailyStory** needs your WordPress admin to use https. Sites on plain http can paste their tracking code instead.

### I have more than one DailyStory account. Which one gets connected?
The account you're signed in to in DailyStory when you click **Allow**. To connect a different one, switch accounts in DailyStory first. If your site already tracks into another account, the plugin asks before switching.

### What happens if I disconnect?
Disconnecting removes the plugin's access to your DailyStory account. Tracking, popups and forms keep working unless you also tick **Also remove the Site ID**. You can also revoke the connection from DailyStory's Connected Apps page.

### Does it work with my theme?
Yes. DailyStory works with any WordPress theme. If something doesn't look right, [let us know](https://www.dailystory.com/contact-us/) and we'll help.

### What is the DailyStory tracking code?
A short script that loads in the background without slowing your pages. It records page visits for contacts, shows your popups, and runs DailyStory features such as page triggers and surveys.

### Can I turn off visitor identification or store tracking?
Store tracking can be switched off on the settings page. Developers can also use these filters:

* `dailystory_identify_visitor`: return an empty array to stop sending signed-in visitors' details
* `dailystory_woocommerce_tracking_enabled`: return `false` to turn off store tracking
* `dailystory_capture_checkout_email`: return `false` to stop sending a guest's checkout email before their order is placed. Abandoned cart automations then only reach signed-in shoppers
* `dailystory_feed_images`: return `true` or `false` to add or leave out featured images in your RSS feed, whatever the setting says. `dailystory_feed_image_size` picks the image size (default `medium_large`)

### Will the plugin change my RSS feed?
Only if **Add each post's featured image to your RSS feed** is ticked under **Settings > DailyStory**. It is on for new installs and off for sites upgrading from an earlier version. When on, each post's featured image appears at the top of its feed item (unless the item already shows it) and as the item's enclosure (unless the post already has one, such as a podcast episode).

### Where can I get support?
[Contact us](https://www.dailystory.com/contact-us/) or browse the [DailyStory documentation](https://docs.dailystory.com/).

### Where can I find the source code?
On [GitHub](https://github.com/DailyStory/WordPress/). Bug reports and pull requests are welcome.

## External services

This plugin connects your site to DailyStory, a third-party service. Data is sent only after you connect an account or enter a Site ID. DailyStory's [Terms of Service](https://www.dailystory.com/legal/terms-of-service/) and [Privacy Policy](https://www.dailystory.com/legal/privacy-policy/) apply.

* **DailyStory tracking code.** Loaded on every front-end page from your account's data center (for example `us-1.dailystory.com`). It records the pages visitors view, using a DailyStory cookie. For logged-in users it also sends their email address, first and last name, and (with WooCommerce) billing phone number. With WooCommerce it also sends product views, cart contents, and a conversion for completed orders (order total, and the buyer's email and phone number). It copies the visitor's DailyStory ID into a first-party cookie (`dailystory_dsid`) so the store can attach it to their order.
* **DailyStory web forms.** The `[ds-webform]` shortcode loads the form from `forms.dailystory.com`. The form's scripts and styles load from `pages.dailystory.com`, `forms.dailystory.com`, `cdnjs.cloudflare.com` and `cdn.jsdelivr.net` (the phone number input). Visitors' form entries go to DailyStory.
* **Connecting your account.** **Connect to DailyStory** uses `app.dailystory.com`. The plugin sends your site's name, address and settings-page address so DailyStory can recognize your site. It receives your account name, Site ID and Site URL. Once a day it checks with DailyStory that the connection is still active.
* **Store events (WooCommerce, connected accounts).** Your site's server sends checkouts and orders to `app.dailystory.com`: the shopper's email address and phone number, plus the order or cart number, status, total, currency and item names. When checkout starts it also sends the cart's products (name, link, image, quantity and price) and a cart recovery link, which DailyStory keeps on the contact until they place an order. A guest's email is sent when they type it at checkout, before the order is placed, so abandoned cart reminders can reach them. Use the `dailystory_capture_checkout_email` filter to turn that off.
* **Site ID check.** When you save a Site ID, the plugin asks your DailyStory data center whether that Site ID exists.

You are responsible for telling your visitors about this tracking, for example in your privacy policy or cookie notice.

## Screenshots
1. **DailyStory settings** - Connect your DailyStory account in one click, or paste your tracking code.
2. **Web Form Designer** - Design web forms in DailyStory, then embed them on your WordPress site with a shortcode.

## Changelog

### 2.4.1
* Blog: featured images are also added to each RSS and Atom item as an enclosure, for feed readers and importers that do not look for images in the post text

### 2.4.0
* WooCommerce: checkouts and orders now start DailyStory's built-in store automations (cart abandoned, cart purchased, order shipped and payment declined) the moment they happen, sent from your server
* WooCommerce: abandoned cart automations can reach guest shoppers who entered their email at checkout
* WooCommerce: abandoned cart emails show the shopper's live cart, including carts that never became an order
* WooCommerce: cart recovery links rebuild the cart in any browser and open checkout
* WooCommerce: each store automation starts once per order
* WooCommerce: browser events are now named `woocommerce_product_viewed`, `woocommerce_added_to_cart` and `woocommerce_cart_updated`
* WooCommerce: the cart event is sent when the cart changes, not on every page view
* WooCommerce: conversions include the buyer's email and phone, so guest purchases are credited
* Blog: add each post's featured image to your RSS feed, so DailyStory RSS emails and feed readers show it. On for new installs; existing sites can turn it on under Settings > DailyStory
* Fixed: event details were recorded in DailyStory as "[object Object]"
* Sites connected with 2.3.0 are asked to reconnect once to turn on store automations

### 2.3.0
* One-click setup: "Connect to DailyStory" signs in to DailyStory and fills in your Site ID and Site URL
* Paste your whole tracking code instead of copying the Site ID and Site URL separately
* Site IDs are checked with DailyStory when saved
* The settings page opens after activation, and shows connection status once set up
* The "not set up" notice now appears only on the Dashboard and Plugins screens
* Disconnect from DailyStory, optionally keeping tracking in place
* Fixed: saving settings without the WooCommerce section could switch store tracking off
* Tested with WordPress 7.1

### 2.2.0
* WooCommerce: product view, add to cart and cart activity tracking for abandoned cart automations
* WooCommerce: order conversion tracking on the order received page for revenue attribution
* WooCommerce: High-Performance Order Storage (HPOS) compatibility declared
* Identify signed-in visitors to DailyStory so a session ties to a known contact
* Security: web form requests now verify TLS certificates and use the WordPress HTTP API
* The settings page now requires the manage_options capability
* Fixed the Site URL being corrupted when the host began with h, t, p, s or :
* Settings values are now sanitized on save and escaped on output
* Beacon moved from the page footer to the head, matching our documentation
* Fixed an undefined variable warning on PHP 8 in the popup shortcodes
* The plugin version constant now tracks the release version, so asset cache busting works

### 2.1.6
* Paths update for CSS and JavaScript

### 2.1.5
* Updates to handle iti for collection of phone numbers

### 2.1.4
* Updates to handle messages when WordPress is in debug mode

### 2.1.1
* Support for redirect url in web form and new javascript events

### 2.0.6
* Minor updates to support minified CSS

### 2.0.5
* Updated dailystory.com paths

### 2.0.1
* Updated for latest pricing plans

### 1.1.6
* Tested with latest version of WordPress

### 1.1.5
* Update to add support for service worker

### 1.1.2
* Update to support Site Url
* Documentation updates

### 1.0.5
* Doc updates

### 1.0.4
* Doc updates

### 1.0.3
* Minor updates to documentation

### 1.0.2
* Changed to support non-incremental numbers for web forms
* Stubbed in support for new popup shortcode

### 1.0.1
* Fixed typos in readme and cleaned up some comments
* Added support for Google reCAPTCHA

### 1.0.0
* Initial release for DailyStory beta launch
