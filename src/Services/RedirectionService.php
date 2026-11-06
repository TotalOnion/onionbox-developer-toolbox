<?php

namespace OnionWordpressDeveloperToolbox\Services;

/**
 * Read-only lookups against the Redirection plugin's rules.
 *
 * Uses Redirection's own URL matching (Red_Url::is_match) rather than
 * Red_Item::get_match(), because get_match() fires "redirection_visit",
 * which logs a hit to the database.
 */
class RedirectionService {

    public function is_available(): bool {
        return class_exists( 'Red_Item' ) && class_exists( 'Red_Url' ) && class_exists( 'Red_Group' );
    }

    /**
     * Find the enabled Redirection rule that matches a URL, if any
     *
     * @param string $url Full URL or path (with optional query string)
     * @return array|null ['id' => int, 'source' => string, 'code' => int, 'target' => string] or null when nothing matches
     */
    public function find_matching_rule( string $url ): ?array {
        if ( ! $this->is_available() ) {
            return null;
        }

        $parts = parse_url( $url );
        $path  = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );

        // Redirection matches against the decoded URL
        $path = rawurldecode( $path );

        foreach ( \Red_Item::get_for_matched_url( $path ) as $item ) {
            if ( ! $item->is_enabled() || $item->source_flags === null ) {
                continue;
            }

            $group = \Red_Group::get( $item->get_group_id() );
            if ( $group && ! $group->is_enabled() ) {
                continue;
            }

            $rule_url = new \Red_Url( $item->get_url() );
            if ( ! $rule_url->is_match( $path, $item->source_flags ) ) {
                continue;
            }

            $target = $item->get_action_data();

            return [
                'id'     => (int) $item->get_id(),
                'source' => (string) $item->get_url(),
                'code'   => (int) $item->get_action_code(),
                'target' => is_string( $target ) ? $target : '',
            ];
        }

        return null;
    }
}
