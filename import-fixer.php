<?php

/*
Plugin Name: Import Fixer
Plugin URI: https://github.com/a8cteam51/wordpress-importer-fixers/
Description: Import fixer subcommands for WP CLI
Author: Spencer Cameron-Morin, Chris Hardie, Automattic
Version: 1.1
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
		$all_post_types = get_post_types();

		$excluded_post_types = array( 'attachment', 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'nav_menu_item' );

		$post_types = array_diff( $all_post_types, $excluded_post_types );

		// Get the IDs of all posts in the specified post types that have a import origin meta field set
		$all_post_ids = get_posts( array(
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_type'      => $post_types,
			'post_status'    => 'any',
			'meta_query'     => array(
				array(
					'key'     => '_original_import_origin',
					'compare' => 'EXISTS',
				),
			),
		) );

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

			// If no origin is set or if there's no index of attachments using it, move on.
			if ( empty( $original_import_origin ) || empty( $all_attachment_ids[ $original_import_origin ] ) ) {
				WP_CLI::debug( "Skipping post #$post_id since there is no origin set or it is different from the one specified." );
				continue;
			}
			// If no original_thumbnail_id is set, move on.
			if ( empty( $original_thumbnail_id ) ) {
				WP_CLI::debug( "Skipping post #$post_id since there is no original thumbnail ID set." );
				continue;
			}

			// get potentially lost thumbnail
			if ( ! empty( $all_attachment_ids[ $original_import_origin ][ $original_thumbnail_id ] ) ) {
				$lost_thumbnail_id = $all_attachment_ids[ $original_import_origin ][ $original_thumbnail_id ];

				if( $lost_thumbnail_id == get_post_meta( $post_id, '_thumbnail_id', true ) ) {
					WP_CLI::debug( "Skipping updating post #$post_id since the thumbnail is already correct." );
					continue;
				}

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
                WP_CLI::debug( "Skipping post #$post_id since there is no origin set." );
                continue;
            }

            // get potentially lost thumbnail
            $lost_parent_id = $post_ids_for_origins[ $original_import_origin ][ $original_parent_id ];
            $current_parent_id = wp_get_post_parent_id( $post_id );

            if( $current_parent_id === $lost_parent_id ) {
                WP_CLI::debug( "Skipping updating post #$post_id to parent id #$current_parent_id since the post parent is already correct (#$current_parent_id)." );
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
	 * Fix up gallery shortcode image IDs
	 * This function works within the context of the origin the posts were imported from.
	 *
	 * @subcommand fix-galleries-contextually
	 * @synopsis [--origin=<import-origin>]
	 */
	public function fix_galleries_contextually( $args, $assoc_args ) {

		// Allow users to specify a single origin to fix.
		$origins = ! empty( $assoc_args['origin'] ) ? array( $assoc_args['origin'] ) : Import_Fixer::_get_origins();

		foreach( $origins as $origin ) {
			WP_CLI::line( "Getting gallery posts for origin: $origin" );

			global $wpdb;
			// Find how many posts have galleries
			$gallery_post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_content LIKE '%%" . esc_sql( $wpdb->esc_like( '[gallery' ) ) . "%%'" );
			$gallery_post_count = count( $gallery_post_ids );

			WP_CLI::line( "Found {$gallery_post_count} posts with galleries." );

			if ( ! $gallery_post_count ) {
				WP_CLI::line( "No work to do! Going on holiday." );
				exit;
			}

			// Remove all shortcodes except [gallery] to de-clutter get_shortcode_regex() later on
			$old_shortcodes = $GLOBALS['shortcode_tags'];
			$GLOBALS['shortcode_tags'] = array( 'gallery' => $old_shortcodes['gallery'] );

			// Fetch 20 posts, fix those up, and repeat.
			while ( $post_ids = array_splice( $gallery_post_ids, 0, 20 ) ) {

				$posts = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'post' AND ID IN (" . implode( ',', $post_ids ) . ')' );
				if ( empty( $posts ) )
					break;

				foreach ( $posts as $the_post ) {
					WP_CLI::debug( "" );
					WP_CLI::debug( "Working on Post ID: {$the_post->ID}" );

					$original_import_origin = get_post_meta( $the_post->ID, '_original_import_origin', true );

					if ( empty( $original_import_origin ) || ( $original_import_origin !== $origin ) ) {
						WP_CLI::debug( "Skipping post #" . $the_post->ID . " since there is no origin set or it is different from the one specified." );
						continue;
					}

					// Find all the galleries
					preg_match_all( '/' . get_shortcode_regex() . '/s', $the_post->post_content, $all_galleries, PREG_SET_ORDER );

					$gallery_count = 0;
					foreach ( $all_galleries as $the_gallery ) {
						$ids_position = null;
						$gallery = array_shift( $the_gallery );
						$gallery_tokens = array();
						$updated_gallery = '';
						$gallery_count++;

						preg_match_all( '/(captiontag|columns|exclude|id|ids|include|icontag|itemtag|link|orderby|size|type)="([^"]+)"/i', trim( $gallery ), $gallery_tokens );

						// If the ids= property isn't set, skip this gallery.
						if ( empty( $gallery_tokens[0] ) || ! in_array( 'ids', $gallery_tokens[1], true ) ) {
							WP_CLI::line( "\tPost ID {$the_post->ID}, gallery #{$gallery_count} does not specify any ids=, so skipping this gallery." );
							continue;
						}

						// Find the numeric position of the key in the array -- @todo Is there a better way to do this?
						for ( $i = 0, $i_count = count( $gallery_tokens[1] ); $i < $i_count; $i++ ) {
							if ( $gallery_tokens[1][ $i ] === 'ids' ) {
								$ids_position = $i;
								break;
							}
						}

						if ( null === $ids_position ) {
							// This should never happen, but in case it does...
							WP_CLI::line( "\tPost ID {$the_post->ID}, gallery #{$gallery_count} does not contain any ids=, so skipping this gallery." );
							continue;
						}

						WP_CLI::line( "\tGallery #{$gallery_count} contains attachment IDs: " . $gallery_tokens[2][ $ids_position ] );

						// Get the correct IDs for this gallery
						$updated_gallery_ids = $this->_get_gallery_attachment_ids( $gallery_tokens[2][ $ids_position ], $origin );
						if ( $updated_gallery_ids === $gallery_tokens[2][ $ids_position ] ) {

							WP_CLI::line( "\tGallery attachment references have not changed; not updating the post." );
							WP_CLI::line(  "\t" );
							continue;
						}

						$gallery_tokens[2][ $ids_position ] = $updated_gallery_ids;
						WP_CLI::line(  "\tFinished checking gallery attachment references; changing to: {$gallery_tokens[2][$ids_position]}" );

						// Reconstruct the gallery shortcode
						for ( $i = 0, $i_count = count( $gallery_tokens[1] ); $i < $i_count; $i++ ) {
							$updated_gallery .= sprintf( ' %1$s="%2$s"', $gallery_tokens[1][ $i ], $gallery_tokens[2][ $i ] );
						}
						$updated_gallery = '[gallery' . $updated_gallery . ']';

						// Store old version of gallery in postmeta for a backup
						update_post_meta( $the_post->ID, '_old_gallery_' . $gallery_count, $gallery );

						// Replace the old gallery shortcode with the new version
						$the_post->post_content = str_replace( $gallery, $updated_gallery, $the_post->post_content );
						$wpdb->update( $wpdb->posts, array( 'post_content' => $the_post->post_content ), array( 'ID' => $the_post->ID ), '%s', '%d' );
						clean_post_cache( $the_post->ID );

						WP_CLI::line( "\tGallery #{$gallery_count} updated in Post ID: {$the_post->ID}" );
						WP_CLI::line( "\t" );

						// Loop back around in case there's more than one gallery in this post
						sleep( 1 );

					}

					sleep( 2 );
				}

				Import_Fixer::stop_the_insanity();

			}
		}

		WP_CLI::line( "Gallery updates completed." );

		// Restore original shortcodes
		$GLOBALS['shortcode_tags'] = $old_shortcodes;

	}

	/**
	 * Fix post content media URLs
	 * Sometimes images are imported but not back-filled correctly. This script looks
	 * at _original_import_url post meta for attachments to get a list of source urls.
	 * It then finds posts with image URLS that match the original source URL and tries
	 * to backfill those images. It also looks for images with the -123x456 dimension suffix
	 * and tries to fix those.
	 *
	 * @subcommand fix-media-urls
	 * @synopsis [--origin=<import-origin>]
	 */
	public function fix_media_urls( $args, $assoc_args ) {

		global $wpdb;

		// Allow users to specify a single origin to fix.
		$origins = ! empty( $assoc_args['origin'] ) ? array( $assoc_args['origin'] ) : Import_Fixer::_get_origins();

		// Which post meta key should we use to determine where the attachment was originally downloaded from?
		$old_url_meta_key = '_original_import_url';
		$origin_meta_key = '_original_import_origin';

		$excluded_post_types = array( 'attachment', 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'nav_menu_item' );

		$excluded_post_types_sql = "'" . implode( "','", array_map( 'esc_sql', $excluded_post_types ) ) . "'";

		foreach( $origins as $origin ) {

			WP_CLI::line( "Fixing media urls for origin $origin:" );

			$counts = array(
				'posts_checked' => 0,
				'posts_updated' => 0,
				'urls'          => 0,
			);

			$urls = array();

			// Get all posts (probably attachments) that have a post meta key matching the import URL key we're using,
			// and that are in the import origin we're working with.
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT pm1.post_id, pm1.meta_value
				FROM $wpdb->postmeta pm1
				INNER JOIN $wpdb->postmeta pm2
					ON ( pm1.post_id = pm2.post_id
					AND pm1.meta_key = %s
					AND pm2.meta_key = %s )
					WHERE pm2.meta_value = %s",
				$old_url_meta_key, $origin_meta_key, $origin ) ) as $row ) {

				// Multiple imports cause multiple metas to be written sometimes. Skip if we find a duplicate.
				if ( array_key_exists( $row->meta_value, $urls ) )
					continue;

				$urls[ $row->meta_value ] = $row->post_id;

			}

			// For each matching attachment we found, use the post ID to get the attachment URL as it exists in WordPress now.
			// Add it to our urls array as the new URL associated with the old/source URL index.
			foreach ( $urls as $old_url => $id ) {
				$urls[ $old_url ] = wp_get_attachment_url( $id );
				$counts['urls']++;
			}

			uksort( $urls, array( $this, '_cmpr_strlen' ) );

			// Search for any post in our target origin, containing an image reference...
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content
				FROM $wpdb->posts
				LEFT JOIN $wpdb->postmeta
					ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id
					AND $wpdb->postmeta.meta_key = %s )
				WHERE
					post_type NOT IN ( $excluded_post_types_sql )
					AND post_status IN ( 'publish', 'draft', 'private' )
					AND $wpdb->postmeta.meta_value = %s
					AND LOWER( post_content ) LIKE '%<img%'",
				$origin_meta_key, $origin
			) ) as $post ) {

				$updated = false;
				$counts['posts_checked']++;

				WP_CLI::debug( "Checking post $post->ID: $post->post_title" );

				$last_post_content = $original_post_content = $post->post_content;

				// For each URL in our array of attachment URLs to check...
				foreach ( $urls as $old_url => $new_url ) {

					// Find and replace the image URL in the post content
					$post->post_content = str_replace( $old_url, $new_url, $post->post_content );

					// Handle dimension suffix alternatives
					$url_exploded_dots = explode( ".", $old_url );
					$file_extension = array_pop( $url_exploded_dots );
					$thumbnail_base = implode( ".", $url_exploded_dots );

					$regex = '!' . preg_quote( $thumbnail_base, "!" ) . '-\d+x\d+\.' . preg_quote( $file_extension, "!" ) . '!';
					$post->post_content = preg_replace( $regex, $new_url, $post->post_content );

					// If the post was updated, make a note of that.
					if ( $post->post_content != $last_post_content ) {
						$updated = true;

						WP_CLI::debug( "\t$old_url => $new_url" );

						$last_post_content = $post->post_content;
					}
				}
				if ( true === $updated ) {
					wp_update_post( $post );
					clean_post_cache( $post->ID );
					$counts['posts_updated']++;
					WP_CLI::debug( "~~~~~~~~~~\n~~POST_ID~~\n$post->ID\n~~ORIGINAL_CONTENT~~\n$original_post_content\n~~NEW_CONTENT~~\n$post->post_content\n~~~~~~~~~~" );

				}
			}

			WP_CLI::success( $counts['urls'] . ' URLs found, ' . $counts['posts_checked'] . ' posts checked, ' . $counts['posts_updated'] . ' posts updated.' );
		}
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

	/**
	 * Using the supplied string of post IDs, check to see if those post IDs still map to the correct image IDs
	 * as the IDs could change during import. Returns a validated list of attachment IDs for the gallery.
	 *
	 * This is done by fetching postmeta and looking at the _original_post_id record that each post has.
	 *
	 * @param string $ids comma-separated post IDs
	 * @param string $origin Origin string to limit post scope
	 * @return string Updated gallery attachment IDs, comma-separated
	 */
	public static function _get_gallery_attachment_ids( $ids, $origin ) {
		global $wpdb;

		$ids     = wp_parse_id_list( trim( $ids ) );
		$loops   = 0;
		$new_ids = array();

		foreach ( $ids as $original_thumbnail_id ) {
			$loops++;

			// Find the correct attachment ID

			$attachments_for_post = get_posts( array(
				'posts_per_page' => 1,
				'fields' => 'ids',
				'post_type' => 'attachment',
				'post_status' => 'any',
				'meta_query' => array(
					array(
						'key' => '_original_import_origin',
						'value' => $origin
					),
					array(
						'key' => '_original_post_id',
						'value' => $original_thumbnail_id,
					)

				)
			) );

			$new_attachment_id = $attachments_for_post[0];

			if ( ! $new_attachment_id ) {
				WP_CLI::line( "\t\tAlternative attachment not found; originally #{$original_thumbnail_id}. Leaving the ID unchanged." );

				$new_ids[] = $original_thumbnail_id;
				continue;
			}

			if ( (int) $new_attachment_id === $original_thumbnail_id ) {
				WP_CLI::line( "\t\tAttachment #{$new_attachment_id} unchanged." );

				$new_ids[] = $original_thumbnail_id;
				continue;
			}

			$new_attachment = get_post( $new_attachment_id );
			if ( ! $new_attachment || $new_attachment->post_type !== 'attachment' ) {
				WP_CLI::line( "\t\tSanity check failed; found post is not an attachment (#{$new_attachment_id}). Leaving the ID unchanged." );

				$new_ids[] = $original_thumbnail_id;
				continue;
			}

			WP_CLI::line( "\t\tUpdated attachment found! Changing ID from #{$original_thumbnail_id} to #{$new_attachment_id}." );

			// Stash the updated attachment ID
			$new_ids[] = $new_attachment_id;

			// Pause 1 second after every 5 loops
			if ( $loops % 5 == 0 )
				sleep( 1 );
		}

		// After some checks, we've found an updated attachment ID, hurray!
		$new_ids = implode( ',', wp_parse_id_list( $new_ids ) );

		return $new_ids;
	}

	/**
	 * Import external images in post content.
	 *
	 * ## OPTIONS
	 *
	 * [--list]
	 * : List domains in post content, but don't do anything else.
	 *
	 * [--domain=<domain-to-import-from>]
	 * : You can specify a single domain to import external images from.
	 *
	 * [--all-domains]
	 * : Import images from any domain.
	 *
	 * [--post_type=<post_type|any>]
	 * : You can provide a single post type here or 'any' (without quotes) to process all public, non-attachment posts. Defaults to 'post'.
	 *
	 * [--rewind]
	 * : Reverse image source replacements and delete any imported images added with this command.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get a list of domains in post content (useful for selectively importing where possible domains are unknown).
	 *     $ wp import-fixer import-external-images --list-domains
	 *
	 *     # Import images from example.com.
	 *     $ wp import-fixer import-external-images --domain=example.com
	 *
	 *     # Import images from www.example.com.
	 *     $ wp import-fixer import-external-images --domain=www.example.com
	 *
	 *     # Import images from any domain.
	 *     $ wp import-fixer import-external-images --all-domains
	 *   
	 * @subcommand import-external-images
	 */
	public function import_external_images( $args, $assoc_args ) {
		global $wpdb;

		if( ! empty( \WP_CLI\Utils\get_flag_value( $assoc_args, 'rewind' ) ) ) {
			WP_CLI::line( "Rewinding previous image import (if there was one)." );
			$this->_import_external_images_rewind();
			WP_CLI::line( "Done!" );
			exit;
		}

		$list_only = \WP_CLI\Utils\get_flag_value( $assoc_args, 'list' );
		$domain_to_import = \WP_CLI\Utils\get_flag_value( $assoc_args, 'domain' );
		$all_domains = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all-domains' );
		$post_type = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type' );

		if( ! empty( $domain_to_import ) && ! empty( $all_domains ) ) {
			WP_CLI::error( "You can't use --domain and --all-domains." );
		}

		if( ( empty( $domain_to_import ) && empty( $all_domains ) ) && empty( $list_only ) ) {
			WP_CLI::error( "You have specify a domain with --domain or use --all-domains." );
		}

		if( in_array( $domain_to_import, array( parse_url( home_url(), PHP_URL_HOST ), parse_url( site_url(), PHP_URL_HOST ) ) ) ) {
			WP_CLI::error( "You almost certainly don't want to import images with the same domain as your site. You'll duplicate files in your media library. If you do want to do this, you'll need to be more precise and take a different approach." );
		}

		if( empty( $list_only ) ) {
			//WP_CLI::confirm( "Make sure you test this on a development site first and have backups in order before running on production. Ready to go?" );
		}

		if( empty( $post_type ) ) {
			$post_type = 'post';
		}
		
		if( $post_type === 'any' ) {
			$post_types = get_post_types( array( 'public' => true ) );

			// Exclude attachments.
			unset( $post_types['attachment'] );

			foreach( $post_types as $key => $post_type ) {
				$post_types[ $key ] = "'$post_type'"; 
			}
			
			$post_types = implode( ',', $post_types );
			// TODO: Build/run the query
		} else {
			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = '%s'", $post_type ) );
		}

		if( empty( $post_ids ) ) {
			WP_CLI::error( "No posts found for post_type: '$post_type'!" );
		}

		$total_posts = count( $post_ids );
		
		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing posts', $total_posts );
		$all_image_domains = array();

		foreach( $post_ids as $post_id ) {
			//$progress->tick();
			$post_content = get_post( $post_id )->post_content;
		 
			if( empty( $post_content ) ) {
				continue;
			}

			preg_match_all( '#<img(.*?)>#si', $post_content, $images );
			if( empty( $images[0] ) ) {
				continue;
			}
		 
			foreach( $images[0] as $image ) {
				$matches = array();
				$image_html = $image;
				preg_match( '#src="(.*?)"#i', $image_html, $image_src );
				$image_src = $image_src[1];

				$current_image_domain = parse_url( $image_src, PHP_URL_HOST );

				if( empty( $current_image_domain ) ) {
					WP_CLI::warning( "Encountered badly formatted image src in post #$post_id: $image_src" );
					continue;
				}

				if( in_array( $current_image_domain, array( parse_url( home_url(), PHP_URL_HOST ), parse_url( site_url(), PHP_URL_HOST ) ) ) ) {
					// Skip importing if the image source domain matches the site's domain.
					continue;
				}

				$all_image_domains[] = $current_image_domain;

				// If all we want is to list all the domains, bail here.
				if( ! empty( $list_only ) ) {
					continue;
				}

				// Bail if this isn't the domain we're looking for or if --all-domains is set.
				if( ! empty( $domain_to_import ) && $domain_to_import !== $current_image_domain ) {
					continue;
				}

				// Make sure the image wasn't already imported.
				$post_exists = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_added_via_script_backup_meta' AND meta_value LIKE '%$image_src%'", $image_src ) );

				if( ! empty( $post_exists ) ) {
					$new_src = get_post_meta( $post_exists, '_added_via_script_backup_meta', true );
					$new_src = ! empty( $new_src['new_url'] ) ? $new_src['new_url'] : '';
					if( empty( $new_src ) ) {
						continue;
					}
					$post_content = str_replace( $image_src, $new_src, $post_content );
					$updated = $wpdb->update( $wpdb->posts, array( 'post_content' => $post_content ), array( 'ID' => $post_id ) );

					if( ! empty( $updated ) ) {
						WP_CLI::line( " -- Found already imported images in post #$post_id. Updating image URLs in post content." );
						WP_CLI::line( "   -- Replaced image source:" );
						WP_CLI::line( "     -- Old image URL: $image_src" );
						WP_CLI::line( "     -- New image URL: $new_src" );
					}

					continue;
				}

				if( empty( parse_url( $image_src, PHP_URL_SCHEME ) ) ) {
					$file_array['tmp_name'] = download_url( "http:$image_src" );
				} else {
					$file_array['tmp_name'] = download_url( $image_src );
				}

				if( false !== strpos( $image_src, 'googleusercontent.com' ) ) {
					$image_src .= '?.jpg';
				}

				$file_array['name'] = basename( $image_src );

				$attachment_id = media_handle_sideload( $file_array, $post_id );
		 
				$uploaded_image_src = wp_get_attachment_url( $attachment_id );
		 
				if( empty( $uploaded_image_src ) ) {
					echo " -- Image download failed for '$image_src' on post #$post_id\n";
					if( is_wp_error( $attachment_id ) ) {
						var_dump( $attachment_id );
					}
					continue;
				}
		 
				update_post_meta( $attachment_id, '_added_via_script_backup_meta', array(
					'old_url' => $image_src,
					'new_url' => $uploaded_image_src,
				));

				$post_content = str_replace( $image_src, $uploaded_image_src, $post_content );
				$updated = $wpdb->update( $wpdb->posts, array( 'post_content' => $post_content ), array( 'ID' => $post_id ) );
				if( ! empty( $updated ) ) {
					WP_CLI::line( " -- Imported images for post #$post_id." );
					WP_CLI::line( "   -- Replaced image source:" );
					WP_CLI::line( "     -- Old image URL: $image_src" );
					WP_CLI::line( "     -- New image URL: $uploaded_image_src" );
				}
			}
			usleep( 5000 );
		}

		if( ! empty( $list_only ) ) {
			foreach( array_unique( $all_image_domains ) as $domain ) {
				WP_CLI::line( $domain );
			}
		}
	}

	public static function _import_external_images_rewind() {
		global $wpdb;

		$attachment_ids = $wpdb->get_col( "SELECT DISTINCT(post_id) FROM $wpdb->postmeta WHERE meta_key = '_added_via_script_backup_meta'" );

		foreach( $attachment_ids as $attachment_id ) {
			$meta_backup_urls = get_post_meta( $attachment_id, '_added_via_script_backup_meta', true );
			var_dump( $meta_backup_urls );

			if( ! empty( $meta_backup_urls['old_url'] ) && ! empty( $meta_backup_urls['new_url'] ) ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s )", $meta_backup_urls['new_url'], $meta_backup_urls['old_url'] ) );
					WP_CLI::line( " -- Reverting URL replacements for attachment #$attachment_id.");
					WP_CLI::line( "   -- Updating {$meta_backup_urls['new_url']}" );
					WP_CLI::line( "   -- With {$meta_backup_urls['old_url']}  " );
					WP_CLI::line( "   -- Deleting attachment #$attachment_id." );
					wp_delete_post( $attachment_id, true );
			}
		}
	}

	// sort by strlen, longest string first
	public static function _cmpr_strlen( $a, $b ) {
		return strlen( $b ) - strlen( $a );
	}


}

WP_CLI::add_command( 'import-fixer', 'Import_Fixer' );
