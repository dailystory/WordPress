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

        // The image as its own element too, for readers and importers that don't look in the text
        add_action('rss2_item', array($this, 'add_rss_enclosure'));
        add_action('atom_entry', array($this, 'add_atom_enclosure'));
    }

    public function add_rss_enclosure () {

        $image = self::enclosure();

        if ( $image )
            printf("\t\t<enclosure url=\"%s\" length=\"%d\" type=\"%s\" />\n", esc_url($image['url']), $image['length'], esc_attr($image['type']));
    }

    public function add_atom_enclosure () {

        $image = self::enclosure();

        if ( $image )
            printf("\t\t<link rel=\"enclosure\" href=\"%s\" length=\"%d\" type=\"%s\" />\n", esc_url($image['url']), $image['length'], esc_attr($image['type']));
    }

    // ──────────────────────────────────────────────
    // The current post's featured image as an enclosure: url, length in
    // bytes and mime type, or null.
    //
    // Skipped when the post already has an enclosure (a podcast episode):
    // readers take only the first one, and that one is the media.
    // ──────────────────────────────────────────────
    private static function enclosure () {

        $post = get_post();

        if ( ! $post || ! has_post_thumbnail($post) || get_post_meta($post->ID, 'enclosure') )
            return null;

        $image_id = get_post_thumbnail_id($post);
        $size     = apply_filters('dailystory_feed_image_size', self::IMAGE_SIZE);
        $image    = wp_get_attachment_image_src($image_id, $size);

        if ( ! $image )
            return null;

        // WordPress 6.0+ records each size's file size; older sites read it from disk
        $meta   = wp_get_attachment_metadata($image_id);
        $length = 0;
        $type   = get_post_mime_type($image_id);

        if ( is_array($meta) && ! empty($meta['sizes'][$size]['filesize']) && $image[3] ) {
            $length = (int) $meta['sizes'][$size]['filesize'];
            $type   = ! empty($meta['sizes'][$size]['mime-type']) ? $meta['sizes'][$size]['mime-type'] : $type;
        }
        elseif ( is_array($meta) && ! empty($meta['filesize']) && ! $image[3] ) {
            $length = (int) $meta['filesize'];
        }
        else {
            $file = get_attached_file($image_id);

            if ( $file && $image[3] && ! empty($meta['sizes'][$size]['file']) )
                $file = path_join(dirname($file), $meta['sizes'][$size]['file']);

            if ( $file && file_exists($file) )
                $length = (int) filesize($file);
        }

        return array(
            'url'    => $image[0],
            'length' => $length,
            'type'   => $type ? $type : 'image/jpeg',
        );
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
