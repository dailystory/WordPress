# DailyStory integration for WordPress
DailyStory's WordPress plugin enables DailyStory customers to easily install the DailyStory tracking code and use WordPress shortcodes within existing WordPress blogs and websites:

![](dailystory-wordpress-plugin-1.png?raw=true)

Many of DailyStory's customers use WordPress. We created this this plugin to make it as easy as possible to integrate with one of the most popular content management and blogging platforms.

DailyStory's own website is built with WordPress and we use this plugin too - for example, [our contact us page](https://www.dailystory.com/contact-us/) uses the WebForm shortcode to render the contact us form. Submissions made on that form are then routed through DailyStory's workflow. It's like magic.

Using the plugin doesn't require any knowledge of PHP, FTP or editing WordPress files - nothing for you to maintain or manage.

## What is DailyStory?
[DailyStory is an Account Based Marketing platform](https://www.dailystory.com).

### What are the requirements?
This plugin requires a DailyStory Site ID. DailyStory customers can find the Site ID on their Tracking Code page in settings:

![](dailystory-wordpress-plugin-2.png?raw=true)

If you are not a customer you can [sign up for a free 30 day trial](https://www.dailystory.com/trial) to get a DailyStory Site ID and try our WordPress integration.

We recommend using WordPress version 4.0 or greater. We've tested this plugin with WordPress version 4.7.2.

### Setup instructions and plugin guide
Please see the [our setup instructions and guides published on our website](https://www.dailystory.com/integrations/wordpress).

## About this plugin
This plugin uses WordPress APIs to add the DailyStory pixel into the footer of your WordPress site and to process shortcodes specific to DailyStory.

### What is the the DailyStory Pixel?
The DailyStory pixel (about 12 lines of JavaScript) is used by DailyStory to track customer activity as they browse your web or mobile experience. It also adds the ability to render popups, content A/B testing, run page triggers, page covers, surveys and more.

### What are WordPress shortcodes?
This plugin adds support for embedding DailyStory behavior into your WordPress pages or posts using shortcodes.
WordPress shortcodes enable you to easily add functionality when writing content.

Let's look at an example for how a shortcode is used by DailyStory. 

#### The DailyStory Webform shortcode example
The webform shortcode is built to enable you to use DailyStory WebForms in your WordPress pages and posts. Using the webform shortcode you can quickly build landing pages in WordPress and easily get that data into DailyStory. Once the form is submitted DailyStory runs it through its workflow engine.  

Here is how the webform shortcode is added. This assumes you've already configured the plugin with a valid DailyStory Site ID and have created a WebForm in DailyStory.

* Create or edit a WordPress page or post and add <code>[ds-webform id="#"]</code> where # is the id of your webform. 
* Next, publish the page or post and view it in your browser.
* When WordPress receives the request it will see the shortcode <code>[ds-webform id="#"]</code>. It will route that request to the DailyStory plugin to process. The plugin will make an API call to DailyStory, fetch the appropriate form, progressively render its layout, add javascript for validation, add stylesheets, optionally render Google reCAPTCHA and render any call-to-action buttons. 
* Finally, the new HTML form replaces the shortcode and it is displayed in your browser.

When your request completes you won't see <code>[ds-webform id="#"]</code> (unless there was a problem), but you will instead see the HTML form that you designed on DailyStory. Submissions to that form are POSTed to DailyStory and new content is accessible in your lead queue associated with the campaign for that WebForm.

### Current Supported Version
version 1.0.0, Released February 2017

## License
The DailyStory for WordPress plugin is licensed under GNU General Public License v2.0 or later.

## Contributing
If you use this plugin and find bugs or want to add features (or contribute in other ways) we'd love it. Just submit a pull request and we'll review the changes. 

We're also open to suggestions, bug reports and more. Anything we can do to make this plugin more useful for our users.

If you have [questions or ideas about this plugin we would love to talk](https://www.dailystory.com/contact-us).