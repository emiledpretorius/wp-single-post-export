<?php
/*
Plugin Name: Single Post Export
Plugin URI:  https://emilepretorius.com
Description: Exports posts, pages, or custom post types with all associated metadata and taxonomies to a WXR file to reimport to Wordpress.
Version:     1.0
Author:      Emile Pretorius
Author URI:  https://emilepretorius.com
License:     GPL2
*/

add_filter('post_row_actions', 'add_export_link', 10, 2);
add_filter('page_row_actions', 'add_export_link', 10, 2);

function add_export_link($actions, $post) {
    $actions['export_post'] = '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=export_single_post&post_id=' . $post->ID), 'export_post_' . $post->ID) . '">Export This</a>';
    return $actions;
}

add_action('admin_post_export_single_post', 'handle_export_single_post');

function handle_export_single_post() {
    if (!current_user_can('activate_plugins')) {
        wp_die('Permission denied');
    }

    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : false;
    check_admin_referer('export_post_' . $post_id);

    $post = get_post($post_id);
    if (!$post) {
        wp_die('Post not found!');
    }

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/"/>');
    $channel = $xml->addChild('channel');
    $channel->addChild('wp:wxr_version', '1.2', 'http://wordpress.org/export/1.2/');

    $item = $channel->addChild('item');
    $item->addChild('title', $post->post_title);
    $item->addChild('wp:post_type', $post->post_type, 'http://wordpress.org/export/1.2/');
    add_cdata($item->addChild('content:encoded', '', 'http://purl.org/rss/1.0/modules/content/'), $post->post_content);

    add_all_meta_data($post_id, $item);
    add_all_taxonomies($post_id, $item);

    $xml_output = $xml->asXML();
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="exported_post.wxr"');
    echo $xml_output;
    exit;
}

function add_all_meta_data($post_id, $item) {
    $meta_data = get_post_meta($post_id);
    foreach ($meta_data as $key => $values) {
        foreach ($values as $value) {
            $meta_xml = $item->addChild('wp:postmeta', '', 'http://wordpress.org/export/1.2/');
            $meta_xml->addChild('wp:meta_key', htmlspecialchars($key), 'http://wordpress.org/export/1.2/');
            add_cdata($meta_xml->addChild('wp:meta_value', '', 'http://wordpress.org/export/1.2/'), $value);
        }
    }
}

function add_all_taxonomies($post_id, $item) {
    $taxonomies = get_object_taxonomies(get_post_type($post_id), 'objects');
    foreach ($taxonomies as $taxonomy) {
        if ($taxonomy->public) {
            $terms = wp_get_post_terms($post_id, $taxonomy->name);
            foreach ($terms as $term) {
                $term_xml = $item->addChild('category', '', 'http://wordpress.org/export/1.2/');
                add_cdata($term_xml, $term->name);
                $term_xml->addAttribute('domain', $taxonomy->name);
                $term_xml->addAttribute('nicename', $term->slug);
            }
        }
    }
}

function add_cdata($node, $cdata_text) {
    $dom = dom_import_simplexml($node);
    $owner = $dom->ownerDocument;
    $dom->appendChild($owner->createCDATASection($cdata_text));
}
