<?php

namespace OnionWordpressDeveloperToolbox\Services;

use \WP_Post;
use OnionWordpressDeveloperToolbox\Exceptions\WpDatabaseException;

class DatabaseService {

    const EXCLUDE_HIDDEN_MARKETS = true;
    const INCLUDE_HIDDEN_MARKETS = false;

    /**
     * url -> WP_Post fetcher
     * 
     * @param string $url
     * @return WP_Post
     * @throws WpDatabaseException
     */
    public function get_post_by_url( string $url ):WP_Post {
        $id = url_to_postid( $url );
        if ( ! $id ) {
            throw new WpDatabaseException(
                sprintf( 'Could not determine wp_posts.ID of "%s"', $url )
            );
        }

        $post = get_post( $id );
        if ( ! $post ) {
            throw new WpDatabaseException(
                sprintf( 'Could not determine wp_posts.ID of "%s"', $url )
            );
        }

        return $post;
    }

    /**
     * Takes an array of post type names, and returns all matching WP_Posts
     * 
     * @param array $post_types An array of post types to fetch
     * @param bool $exclude_hidden_markets If true will remove any posts on hidden or suppressed languages/markets
     * @return array An array of WP_Post objects
     */
    public function get_posts_by_types(
        array $post_types,
        bool $exclude_hidden_markets = self::INCLUDE_HIDDEN_MARKETS
    ):array {
        $post_types = array_map( fn( $post_type ) => trim( $post_type ), $post_types );

        $wp_query_args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'numberposts' => -1
        ];

        if ( $exclude_hidden_markets ) {
            $ids_to_ignore = $this->get_post_ids_of_all_suppressed_language_pages( $post_types );
            if ( $ids_to_ignore ) {
                $wp_query_args['post__not_in'] = array_map( 'intval' , $ids_to_ignore );
            }
        }

        return get_posts( $wp_query_args );
    }

    /**
     * Gets an array of post IDs for any post type in $post_types that is in a language that is suppressed
     * 
     * @param array $post_types
     * @return array An array of wp_post.ID values for posts/pages to be excluded
     */
    private function get_post_ids_of_all_suppressed_language_pages( array $post_types ):array {
        global $wpdb;

        $suppressed_languages = get_option('onion_seopress_helper_suppressed_markets') ?: get_option('pr_seopress_helper_suppressed_markets');
        if ( ! $suppressed_languages ) {
            return [];
        }
        $suppressed_languages = array_keys( $suppressed_languages );

        $element_placeholders = implode(', ', array_fill(0, count($post_types), '%s') );
        $language_code_placeholders = implode(', ', array_fill(0, count($suppressed_languages), '%s') );

        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT element_id
                FROM {$wpdb->prefix}icl_translations
                WHERE element_type IN ($element_placeholders)
                AND language_code IN ($language_code_placeholders)
                AND element_id != 0;",
                array_merge(
                    array_map( fn($post_type) => 'post_' . trim( $post_type ), $post_types ),
                    $suppressed_languages
                )
            )
        );
    }

    /**
     * Takes an array of post ids, and returns all matching WP_Posts
     * 
     * @param array $ids
     * @return array
     */
    public function get_posts_by_ids( array $ids ):array {
        $posts = [];
        foreach( $ids as $id ) {
            $post = get_post( $id );
            if ( $post instanceof WP_Post ) {
                $posts[] = $post;
            }
        }

        return $posts;
    }
}
