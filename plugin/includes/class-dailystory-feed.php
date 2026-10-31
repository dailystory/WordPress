<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Do not allow direct access

// ──────────────────────────────────────────────
// Featured images in the site's RSS and Atom feeds.
//
// WordPress feeds carry no image for a post's featured image, so feed
// readers, and DailyStory's RSS email widget and "When RSS is updated"
// newsletters, show a placeholder instead. This puts the featured image at
// the top of each item's summary and full content. DailyStory's RSS widget
// takes the first <img> in the summary as the article's thumbnail.
//
// An <img> is used rather than <media:content>: that needs the xmlns:media
// namespace on the feed, and a second plugin declaring it too makes the
// feed invalid XML for every reader.
// ──────────────────────────────────────────────
class DailyStoryFeed {

    // Wide enough for an email column, small enough to load quickly
    const IMAGE_SIZE = 'medium_large';

    public static function is_enabled () {

        $options = get_option('dailystory_settings');

        $enabled = is_array($options) && ! empty($options['dailystory_feed_images']);

        // Allow developers to switch feed images on or off in code
        return (bool) apply_filters('dailystory_feed_images', $enabled);
    }

    public function __construct () {

        // Late, so the image goes in front of anything other plugins add
        add_filter('the_excerpt_rss', array($this, 'add_featured_image'), 20);
        add_filter('the_content_feed', array($this, 'add_featured_image'), 20);
    }

    public function add_featured_image ( $content ) {

        $post = get_post();

        if ( ! $post || ! has_post_thumbnail($post) )
            return $content;

        $image_id = get_post_thumbnail_id($post);

        $size  = apply_filters('dailystory_feed_image_size', self::IMAGE_SIZE);
        $image = wp_get_attachment_image_src($image_id, $size);

        if ( ! $image )
            return $content;

        // A theme or another plugin may already have put the image in
        if ( self::contains_image($content, $image_id) )
            return $content;

        $alt = trim( wp_strip_all_tags( (string) get_post_meta($image_id, '_wp_attachment_image_alt', true) ) );

        $html = sprintf(
            '<p><a href="%s"><img src="%s" width="%d" height="%d" alt="%s" style="display:block;max-width:100%%;height:auto;" /></a></p>',
            esc_url( get_permalink($post) ),
            esc_url( $image[0] ),
            (int) $image[1],
            (int) $image[2],
            esc_attr( '' !== $alt ? $alt : get_the_title($post) )
        );

        return $html . $content;
    }

    // ──────────────────────────────────────────────
    // True when the content already shows this attachment at any size.
    // WordPress names resized copies photo-768x512.jpg and large uploads
    // photo-scaled.jpg, so match on the original file name.
    // ──────────────────────────────────────────────
    private static function contains_image ( $content, $image_id ) {

        if ( false === stripos($content, '<img') )
            return false;

        $file = wp_get_attachment_url($image_id);

        if ( ! $file )
            return false;

        $name = pathinfo( wp_parse_url($file, PHP_URL_PATH), PATHINFO_FILENAME );
        $name = preg_replace('/-scaled$/', '', $name);

        if ( '' === $name )
            return false;

        return 1 === preg_match('#/' . preg_quote($name, '#') . '(-scaled)?(-\d+x\d+)?\.[a-z0-9]+#i', $content);
    }
}
