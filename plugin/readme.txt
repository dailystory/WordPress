# Email and SMS marketing for WordPress by DailyStory
Contributors: dailystory, roberthoward
Tags: email marketing, sms, woocommerce, popup, marketing automation
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 2.3.0
License: GNU General Public License v2.0 or later

Add DailyStory popups, web forms and visitor tracking to WordPress, and turn WooCommerce store activity into email and SMS automations.

## Description

[DailyStory](https://www.dailystory.com/) is a marketing automation platform for email, text messaging (SMS), push notifications, popups, web forms and landing pages. This plugin connects your WordPress site to your DailyStory account, so you can capture leads on your site and follow up with them automatically.

Setup takes about a minute. Click **Connect to DailyStory**, sign in, and choose your account. The plugin installs your tracking code for you, with nothing to copy and paste.

Watch the WordPress plugin in action:
[youtube https://www.youtube.com/watch?v=P5hjQqR2Ivg]

### What the plugin adds to your site

* **Visitor tracking.** DailyStory's tracking code goes on every page, so you can see which pages contacts visit and start automations from that activity.
* **Popups.** Popups you design in DailyStory appear on your site automatically, and you choose where they show. Use a shortcode to show one on a specific page, or when a visitor is about to leave.
* **Web forms.** Embed DailyStory forms in any post, page or widget. Submissions go straight into DailyStory as contacts and can start an automation.
* **Signed-in visitors.** When a logged-in user visits, the plugin tells DailyStory who they are, so their activity is tied to their contact record.

### WooCommerce

When WooCommerce is active, the plugin also sends store activity to DailyStory:

* **Product views,** for browse-abandonment emails and texts
* **Add to cart and cart contents,** for abandoned-cart automations, including shoppers who never reached checkout
* **Completed orders,** recorded as a conversion so revenue is credited to the email, text or popup that drove it

Store tracking is on by default and can be turned off on the settings page. The plugin is compatible with WooCommerce High-Performance Order Storage (HPOS).

### Shortcodes

* `[ds-webform id="FORM_ID"]` embeds a DailyStory web form. Add `redirect_url="https://..."` to send people to a page after they submit.
* `[ds-popup id="POPUP_ID"]` shows a popup when the page loads.
* `[ds-exitintent id="POPUP_ID"]` shows a popup when a visitor is about to leave the page.

You'll find each form's and popup's ID in DailyStory.

### What you can do with DailyStory

Build drip campaigns, abandoned-cart reminders, post-purchase follow-ups, welcome series and more with DailyStory's drag-and-drop automation builder. Then reach people by:

* Email marketing
* SMS and MMS text messaging
* Web and mobile app push notifications
* Popups, web forms and landing pages
* Segments, lead scoring and QR codes

DailyStory also connects to Shopify, BigCommerce, Square and other platforms. [See all features](https://www.dailystory.com/features/).

## Installation

### From your WordPress dashboard
1. Go to **Plugins > Add New** and search for "DailyStory"
2. Click **Install Now**, then **Activate**
3. The DailyStory settings page opens. Click **Connect to DailyStory**
4. Sign in to DailyStory, choose your account and click **Allow**

That's it. Your popups and tracking are live.

### Prefer not to sign in from WordPress?
On the settings page (**Settings > DailyStory**), paste the tracking code from **Settings > Website** in DailyStory and click **Save Settings**. The plugin reads your Site ID and Site URL from the code and checks them with DailyStory.

### Manual upload
1. Download the plugin and unzip it
2. Upload the `dailystory` folder to `/wp-content/plugins/`
3. Activate the plugin under **Plugins**, then connect as above

See our [WordPress setup guide](https://docs.dailystory.com/article/qfl7mox9nn-install-daily-story-tracking-tag-in-word-press) for more detail.

## Frequently Asked Questions

### Do I need a DailyStory account?
Yes. The plugin connects your site to DailyStory, where you design your popups, forms and automations. [Start for free](https://dailystory.com/pricing/).

### What are the requirements?
WordPress 5.0 or later and PHP 7.2 or later. The plugin is tested up to WordPress 6.8.

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

### Where can I get support?
[Contact us](https://www.dailystory.com/contact-us/) or browse the [DailyStory documentation](https://docs.dailystory.com/).

### Where can I find the source code?
On [GitHub](https://github.com/DailyStory/WordPress/). Bug reports and pull requests are welcome.

## External services

This plugin connects your site to DailyStory, a third-party service. Data is sent only after you connect an account or enter a Site ID. DailyStory's [Terms of Service](https://www.dailystory.com/legal/terms-of-service/) and [Privacy Policy](https://www.dailystory.com/legal/privacy-policy/) apply.

* **DailyStory tracking code.** Loaded on every front-end page from your account's data center (for example `us-1.dailystory.com`). It records the pages visitors view, using a DailyStory cookie. For logged-in users it also sends their email address, first and last name, and (with WooCommerce) billing phone number. With WooCommerce it also sends product views, cart contents, and completed orders (order number, total, currency and items).
* **DailyStory web forms.** The `[ds-webform]` shortcode loads the form from `forms.dailystory.com`. The form's scripts and styles load from `pages.dailystory.com`, `forms.dailystory.com`, `cdnjs.cloudflare.com` and `cdn.jsdelivr.net` (the phone number input). Visitors' form entries go to DailyStory.
* **Connecting your account.** **Connect to DailyStory** uses `app.dailystory.com`. The plugin sends your site's name, address and settings-page address so DailyStory can recognize your site. It receives your account name, Site ID and Site URL. Once a day it checks with DailyStory that the connection is still active.
* **Site ID check.** When you save a Site ID, the plugin asks your DailyStory data center whether that Site ID exists.

You are responsible for telling your visitors about this tracking, for example in your privacy policy or cookie notice.

## Screenshots
1. **DailyStory settings** - Connect your DailyStory account in one click, or paste your tracking code.
2. **Web Form Designer** - Design web forms in DailyStory, then embed them on your WordPress site with a shortcode.

## Changelog

### 2.3.0
* One-click setup: "Connect to DailyStory" signs in to DailyStory and fills in your Site ID and Site URL
* Paste your whole tracking code instead of copying the Site ID and Site URL separately
* Site IDs are checked with DailyStory when saved
* The settings page opens after activation, and shows connection status once set up
* The "not set up" notice now appears only on the Dashboard and Plugins screens
* Disconnect from DailyStory, optionally keeping tracking in place
* Fixed: saving settings without the WooCommerce section could switch store tracking off

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
