<?php

namespace OnionWordpressDeveloperToolbox\Services;

use \WP_Post;
use OnionWordpressDeveloperToolbox\Exceptions\WpDatabaseException;

class DatabaseService {

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
     * @param array $post_types
     * @return array
     */
    public function get_posts_by_types( array $post_types ):array {
        $posts = [];
        foreach( $post_types as $post_type ) {
            $posts = array_merge(
                $posts,
                get_posts([
                    'post_type' => trim( $post_type ),
                    'post_status' => 'publish',
                    'numberposts' => -1
                ])
            );
        }

        return $posts;
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
