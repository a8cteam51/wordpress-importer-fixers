<?php

/*
Plugin Name: Import Fixer
Description: Import fixer subcommands for WP CLI
Author: Spencer Cameron-Morin
Version: 1.0
*/

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Implements import fixers.
 */
class Import_Fixer extends WP_CLI_Command {
    /*
     * Fix thumbnail references to use new their new IDs, if the IDs changed.
     * This function works within the context of the origin the posts were imported from.
     *
     * @subcommand fix-thumbnails-contextually
     */
	/**
	 * Fix thumbnail references to use new their new IDs, if the IDs changed.
	 * This function works within the context of the origin the posts were imported from.
	 *
	 * Note: When passing --origin, only the origin you specificy will be fixed. This is much faster than
	 * processing all origins (the default when nothing is passed), especially when there are multiple origins on large sites.
	 *
	 * @subcommand fix-thumbnails-contextually
	 * @synopsis [--origin=<import-origin>]
	 */
	public function fix_thumbnails_contextually( $args, $assoc_args ) {
		$post_types = get_post_types();

		$all_post_ids = get_posts( array( 'posts_per_page' => -1, 'fields' => 'ids', 'post_type' => $post_types, 'post_status' => 'any' ) );

		$all_attachment_ids = array();

		// Allow users to specify a single origin to fix.
		$origins = ! empty( $assoc_args['origin'] ) ? array( $assoc_args['origin'] ) : Import_Fixer::_get_origins();

		foreach( $origins as $origin ) {
			WP_CLI::line( "Getting attachment posts for origin: $origin" );
			$attachments_for_origins = get_posts( array( 'posts_per_page' => -1, 'fields' => 'ids', 'post_type' => 'attachment', 'post_status' => 'any', 'meta_query' => array( array( 'key' => '_original_import_origin', 'value' => $origin ) ) ) );

			if( empty( $attachments_for_origins ) ) {
				WP_CLI::error( 'No attachments found with that origin!' );
			} else {
				WP_CLI::line( " -- Found " . count( $attachments_for_origins ) . " attachments." );
			}

			$count = 0;

			WP_CLI::line( "Building attachment data" );
			foreach( $attachments_for_origins as $attachment_id ) {
				$original_attachment_id = get_post_meta( $attachment_id, '_original_post_id', true );
				$all_attachment_ids[ $origin ][ $original_attachment_id ] = $attachment_id;
				$count++;

				$complete = round( 100 * ( $count / count( $attachments_for_origins ) ) );
				echo " -- Completed $complete%\r";

				if( $count % 100 === 0 )
					Import_Fixer::stop_the_insanity();
			}
			WP_CLI::line();
		}

		$count = 0;
		foreach( $all_post_ids as $post_id ) {
			$original_thumbnail_id = get_post_meta( $post_id, '_original_thumbnail_id', true );

			$original_import_origin = get_post_meta( $post_id, '_original_import_origin', true );

			// If no origin is set, move on.
			if( empty( $original_import_origin ) ) {
				WP_CLI::line( "Skipping post #$post_id since there is no origin set." );
				continue;
			}
			// If no original_thumbnail_id is set, move on.
			if ( empty( $original_thumbnail_id ) ) {
				WP_CLI::line( "Skipping post #$post_id since there is no original thumbnail ID set." );
				continue;
			}

			// get potentially lost thumbnail
			$lost_thumbnail_id = $all_attachment_ids[ $original_import_origin ][ $original_thumbnail_id ];

			if( $lost_thumbnail_id == get_post_meta( $post_id, '_thumbnail_id', true ) ) {
				WP_CLI::line( "Skipping updating post #$post_id since the thumbnail is already correct." );
				continue;
			}

			if( ! empty( $lost_thumbnail_id ) ) {
				WP_CLI::success( "Updating post #$post_id with thumbnail #" . $lost_thumbnail_id . " (currently #" . get_post_meta( $post_id, '_thumbnail_id', true ) . " for origin '$original_import_origin'" );
				update_post_meta( $post_id, '_thumbnail_id', $lost_thumbnail_id );
			}

			$count++;
			if( $count % 100 == 0 ) {
				WP_CLI::line( "Processed $count/" . count( $all_post_ids ) . " posts" );
				Import_Fixer::stop_the_insanity();
			}
		}
		WP_CLI::success( "Finished!" );
	}
        /*
     * Fix post parent references to use new their new IDs, if the IDs changed.
     * This function works within the context of the origin the posts were imported from.
     *
     * @subcommand fix-post-hierarchy-contextually
     */
    public function fix_post_hierarchy_contextually() {

        WP_CLI::line( "Fixing up lost post parents." );
        WP_CLI::line();

        $all_post_ids = array();
        $post_ids_for_origins = array();

        foreach( Import_Fixer::_get_origins() as $origin ) {
            WP_CLI::line( "Getting posts for origin: $origin" );
            $_posts = get_posts( array( 'posts_per_page' => -1, 'fields' => 'ids', 'post_type' => 'any', 'post_status' => 'any', 'meta_query' => array( array( 'key' => '_original_import_origin', 'value' => $origin ) ) ) );

            $all_post_ids = array_merge( $_posts, $all_post_ids );

            WP_CLI::line( " -- Found " . count( $all_post_ids ) . " posts." );

            $count = 0;

            WP_CLI::line( "Building post data" );
            foreach( $_posts as $post_id ) {
                $original_post_id = get_post_meta( $post_id, '_original_post_id', true );
                $post_ids_for_origins[ $origin ][ $original_post_id ] = $post_id;
                $count++;

                $complete = round( 100 * ( $count / count( $_posts ) ) );
                echo " -- Completed $complete%\r";

                if( $count % 100 === 0 )
                    stop_the_insanity();
            }
            WP_CLI::line();
        }

        $count = 0;
        foreach( $all_post_ids as $post_id ) {
            $original_import_origin = get_post_meta( $post_id, '_original_import_origin', true );

            $original_parent_id = get_post_meta( $post_id, '_original_parent_id', true );

            // If no origin is set, move on.
            if( empty( $original_import_origin ) ) {
                WP_CLI::line( "Skipping post #$post_id since there is no origin set." );
                continue;
            }

            // get potentially lost thumbnail
            $lost_parent_id = $post_ids_for_origins[ $original_import_origin ][ $original_parent_id ];
            $current_parent_id = wp_get_post_parent_id( $post_id );

            if( $current_parent_id === $lost_parent_id ) {
                WP_CLI::line( "Skipping updating post #$post_id to parent id #$current_parent_id since the post parent is already correct (#$current_parent_id)." );
                continue;
            }

            if( ! empty( $lost_parent_id ) ) {
                WP_CLI::success( "Updating post #$post_id with post parent #" . $lost_parent_id . " (currently post parent is #" . wp_get_post_parent_id( $post_id ) . " for origin '$original_import_origin'" );
                wp_update_post( array( 'ID' => $post_id, 'post_parent' => $lost_parent_id ) );
            }

            $count++;
            if( $count % 100 == 0 ) {
                WP_CLI::line( "Processed $count/" . count( $all_post_ids ) . " posts" );
                Import_Fixer::stop_the_insanity();
            }
        }
        WP_CLI::success( "Finished!" );
    }
    
    /**
     * Get distinct origins present in the current site.
     */
    public static function _get_origins() {
        global $wpdb;

        $origins = $wpdb->get_col( "SELECT DISTINCT( meta_value ) FROM $wpdb->postmeta WHERE meta_key = '_original_import_origin'" );

        return $origins;
    }
    
    /**
     * Clear all of the caches for memory management
     */
    public static function stop_the_insanity() {
        /**
         * @var \WP_Object_Cache $wp_object_cache
         * @var \wpdb $wpdb
         */
        global $wpdb, $wp_object_cache;

        $wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

        if ( is_object( $wp_object_cache ) ) {
            $wp_object_cache->group_ops = array();
            $wp_object_cache->stats = array();
            $wp_object_cache->memcache_debug = array();
            $wp_object_cache->cache = array();

            if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
                $wp_object_cache->__remoteset(); // important
            }
        }
    }
}

WP_CLI::add_command( 'import-fixer', 'Import_Fixer' );
