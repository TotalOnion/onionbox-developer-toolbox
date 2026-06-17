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

    public function get_posts_by_type( string $post_type ):array {
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => 1
        ]);

        print_r($posts);
        return $posts;
    }
}
