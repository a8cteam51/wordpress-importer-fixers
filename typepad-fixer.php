<?php
/**
 * Plugin Name: T51 Typepad Fixer
 * Plugin URI: https://github.com/a8cteam51/wordpress-importer-fixers/
 * Description: Typescript image import subcommands for WP CLI
 * Author: Spencer Cameron-Morin, Dan Robert, Automattic
 * Version: 1.1
 *
 * @package wp-cli
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Removes popups and imports external images from Typepad site
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class T51_Typepad_Fixer extends WP_CLI_Command {
	/**
	 * Sets configurations that are shared across each subcommand and pass
	 */
	private function config() {
		ini_set( 'display_errors', true );
		error_reporting( E_ALL );
		define( 'UPDATE_REMOTE_MEMCACHED', false );
		define( 'ECLIPSE_SUNRISE_REDIRECT', true );
		define( 'WP_IMPORTING', true );
		set_time_limit( 0 );
		ini_set( 'memory_limit', '1024M' );
	}


	/**
	 * Removes Typepad popups
	 *
	 * @subcommand remove_typepad_popups
	 *
	 * ## OPTIONS
	 *
	 * [--typepad_url=<typepad_url>]
	 * : URL of the Typepad site
	 *
	 * [--pass_number=<pass_number>]
	 * : Current pass - each command needs to be run three times
	 *
	 * [--protocol=<protocol>]
	 * : Protocol of the Typepad site
	 * ---
	 * default: "http://"
	 * options:
	 *   - "http://"
	 *   - "https://"
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp t51-typepad-fixer remove_typepad_popups --typepad_url="www.metagrrrl.com" --pass_number="one" --protocol="http://"
	 *
	 * @when after_wp_load
	 */
	public function remove_typepad_popups( $args, $assoc_args ) {
		$typepad_url = $assoc_args['typepad_url'];
		$pass_number = $assoc_args['pass_number'];
		$protocol    = $assoc_args['protocol'] ?: 'http://';

		$this->config();

		WP_CLI::line( ' -- Replacing Typepad popups for ' . home_url() . PHP_EOL );

		global $wpdb;

		$post_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'post'" );
		WP_CLI::line( ' -- Processed ' . count( $post_ids ) . " posts!\n" );
		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$count++;
			WP_CLI::line( "Processing post $count (#$post_id)\n" );
			$post_content = get_post( $post_id )->post_content;

			if ( empty( $post_content ) ) {
				WP_CLI::line( "	 -- Skipping #$post_id. No post content.\n" );
				continue;
			}

			switch ( $pass_number ) {
				case 'one':
					preg_match_all( '#<a href="' . $protocol . $typepad_url . '/(.*?)</a>#s', $post_content, $popups );
					break;
				case 'two':
					preg_match_all( '#<a class="asset-img-link" href="' . $protocol . $typepad_url . '/(.*?)</a>#s', $post_content, $popups );
					break;
				case 'three':
					preg_match_all( '#<a(.*?)</a>#s', $post_content, $popups );
					break;
				default:
					WP_CLI::line( 'Please set a --pass_number' );
					break;
			}

			foreach ( $popups[0] as $popup ) {
				if ( 'three' === $pass_number ) {
					if ( false === strpos( $popup, 'href="' . $protocol . $typepad_url ) ) {
						WP_CLI::line( " -- Skipping. Wrong href reference.\n" );
						continue;
					}
				}

				$matches    = array();
				$popup_html = $popup;
				preg_match( '#<img (.*?)/>#s', $popup_html, $image_html );
				$image_html = $image_html[0];

				if ( empty( $image_html ) ) {
					WP_CLI::line( "	 -- Skipping #$post_id. No image here.\n" );
					continue;
				}

				WP_CLI::line( " -- Replacing content for post #$post_id\n" );
				WP_CLI::line( $popup_html . PHP_EOL );
				WP_CLI::line( " -- \n" );
				WP_CLI::line( $image_html . PHP_EOL );
				$post_content = str_replace( $popup_html, $image_html, $post_content );
				$wpdb->update( $wpdb->posts, array( 'post_content' => $post_content ), array( 'ID' => $post_id ) );
			}
			usleep( 5000 );
		}

		WP_CLI::success( "Complete!\n" );
	}

	/**
	 * Imports external images from Typepad site
	 *
	 * @subcommand get_external_images
	 *
	 * ## OPTIONS
	 *
	 * [--typepad_url=<typepad_url>]
	 * : URL of the Typepad site
	 *
	 * [--pass_number=<pass_number>]
	 * : Current pass - each command needs to be run three times
	 *
	 * [--protocol=<protocol>]
	 * : Protocol of the Typepad site
	 * ---
	 * default: "http://"
	 * options:
	 *   - "http://"
	 *   - "https://"
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp t51-typepad-import get_external_images --typepad_url="www.metagrrrl.com" --pass_number="one" --protocol="http://"
	 *
	 * @when after_wp_load
	 */
	public function get_external_images( $args, $assoc_args ) {
		$typepad_url = $assoc_args['typepad_url'];
		$pass_number = $assoc_args['pass_number'];
		$protocol    = $assoc_args['protocol'] ?: 'http://';

		$this->config();

		WP_CLI::line( ' -- Replacing Typepad popups for ' . home_url() . PHP_EOL );

		global $wpdb;

		$post_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'post'" );
		WP_CLI::line( ' -- Processed ' . count( $post_ids ) . " posts!\n" );
		$count = 0;

		foreach ( $post_ids as $post_id ) {
			$count++;

			if ( 'one' === $pass_number ) {
				WP_CLI::line( "Processing post $count\n" );
			} else {
				WP_CLI::line( "Processing post $count (#$post_id)\n" );
			}

			$post_content = get_post( $post_id )->post_content;

			if ( empty( $post_content ) ) {
				WP_CLI::line( "	 -- Skipping #$post_id. No post content.\n" );
				continue;
			}

			switch ( $pass_number ) {
				case 'one':
					preg_match_all( '#<img(.*?)>#si', $post_content, $images );
					break;
				case 'two':
					preg_match_all( '#<a(\s+)href="' . $protocol . $typepad_url . '/(.*?)</a>#s', $post_content, $images );
					break;
				case 'three':
					preg_match_all( '#<a class="asset-img-link" href="' . $protocol . $typepad_url . '/(.*?)</a>#s', $post_content, $images );
					break;
				default:
					echo 'Please set a --pass_number';
					break;
			}

			if ( empty( $images[0] ) ) {
				WP_CLI::line( "	 -- Skipping #$post_id. No image here.\n" );
				continue;
			}

			// Workaround for content using INPUT tag.
			if ( 'one' === $pass_number ) {
				if ( empty( $images[0] ) ) {
					preg_match_all( '#<input(.*?)>#si', $post_content, $images );
				}
			}

			foreach ( $images[0] as $image ) {
				$matches    = array();
				$image_html = $image;

				if ( 'one' === $pass_number ) {
					preg_match( '#src="(.*?)"#i', $image_html, $image_src );
				} else {
					preg_match( '#href="(.*?)"#i', $image_html, $image_src );
				}

				$image_src = $image_src[1];

				if ( parse_url( $image_src, PHP_URL_HOST ) !== $typepad_url ) {
					WP_CLI::line( " -- Wrong domain. Skipping #$post_id.\n" );
					continue;
				}

				if ( 'one' !== $pass_number ) {
					if ( strpos( $image_src, '.html' ) ) {
						WP_CLI::line( " -- This is an html file\n" );
					}

					$whitelist = array(
						$protocol . $typepad_url . '/.shared/',
						$protocol . $typepad_url . '/.a/',
						$protocol . $typepad_url . '/files/',
						$protocol . $typepad_url . '/images/',
					);

					$good_to_go = false;
					foreach ( $whitelist as $fragment ) {
						if ( false !== strpos( $image_src, $fragment ) ) {
							$good_to_go = true;
						}
					}

					if ( ! $good_to_go ) {
						WP_CLI::line( " -- Skipping. These are not the droids you're looking for.\n" );
						continue;
					}

					if ( '-popup' === substr( $image_src, -6 ) ) {
						$image_src = substr( $image_src, 0, -6 );
					}
				}

				add_filter(
					'upload_mimes',
					function ( $mimes ) {
						$mimes['placeholder'] = 'image/placeholder';
						return $mimes;
					}
				);

				$file_array['tmp_name'] = download_url( $image_src );

				if ( empty( wp_check_filetype( $image_src )['ext'] ) ) {
					$image_src .= '.placeholder';
				}
				$file_array['name'] = basename( $image_src );
				$attachment_id      = media_handle_sideload( $file_array, $post_id );

				$uploaded_image_src = wp_get_attachment_url( $attachment_id );

				if ( empty( $uploaded_image_src ) ) {
					WP_CLI::line( " -- Image download failed for '$image_src' on post #$post_id\n" );
					if ( is_wp_error( $attachment_id ) ) {
						WP_CLI::log( $attachment_id );
					}
					continue;
				}

				update_post_meta(
					$attachment_id,
					'_added_via_script_backup_meta',
					array(
						'old_url' => $image_src,
						'new_url' => $uploaded_image_src,
					)
				);

				if ( false !== strpos( $image_src, '.placeholder' ) ) {
					$image_src = str_replace( '.placeholder', '', $image_src );
				}
				WP_CLI::line( " -- Replacing content for post #$post_id\n" );
				WP_CLI::line( $image_src . PHP_EOL );
				WP_CLI::line( " -- \n" );
				WP_CLI::line( $uploaded_image_src . PHP_EOL );
				$post_content = str_replace( $image_src, $uploaded_image_src, $post_content );

				if ( 'one' === $pass_number ) {
					$post_content = str_replace( $uploaded_image_src . '-popup', $uploaded_image_src, $post_content );
				}

				$wpdb->update( $wpdb->posts, array( 'post_content' => $post_content ), array( 'ID' => $post_id ) );
			}
			usleep( 5000 );
		}

		WP_CLI::success( "Complete!\n" );
	}
}

$instance = new T51_Typepad_Fixer();
WP_CLI::add_command( 't51-typepad-fixer', $instance );
