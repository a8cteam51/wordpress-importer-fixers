<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Implements Link fixers.
 */
class Link_Scanner extends WP_CLI_Command {

	/**
	 * Timeout in Seconds.
	 * @var int
	 */
	private $timeout = 10;

	/**
	 * Scan the broken links from post content.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type]
	 * : Post type.
	 * ---
	 * default: post
	 * options:
	 *   - post
	 *   - page
	 *   - CPT
	 *
	 * [--skip-images]
	 * : A flag to skip the images.
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 *
	 * ## EXAMPLES
	 *
	 *   wp link-scanner scan
	 *
	 * @subcommand scan
	 */
	public function scan_links( $args, $assoc_args ) {

		global $wpdb;

		$post_type  = 'post';
		$post_types = get_post_types( array( 'public' => true ) );

		if ( ! empty( $assoc_args['post-type'] ) && ( in_array( $assoc_args['post-type'], $post_types, true ) || 'any' === $assoc_args['post-type'] ) ) {
			$post_type = $assoc_args['post-type'];
		}

		$skip_images = false;
		if ( ! empty( $assoc_args['skip-images'] ) && 'true' === $assoc_args['post-type'] ) {
			$skip_images = true;
		}

		if ( 'any' === $post_type ) {

			// Exclude attachments.
			unset( $post_types['attachment'] );

			$post_types = "'" . implode( "','", $post_types ) . "'";

			$post_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type IN ( {$post_types} )" );
		} else {
			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = '%s'", $post_type ) );
		}

		$total_posts = count( $post_ids );

		if ( empty( $post_ids ) ) {
			WP_CLI::error( "No posts found for post_type: '$post_type'!" );
		}
		WP_CLI::line( "$total_posts posts found!" );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Processing posts', $total_posts );

		foreach ( $post_ids as $post_id ) {

			$progress->tick();

			$content = get_post( $post_id )->post_content;

			if ( empty( $content ) ) {
				continue;
			}

			$content  = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );
			$document = new DOMDocument();
			libxml_use_internal_errors( true );
			$document->loadHTML( utf8_decode( $content ) );

			$anchors = $document->getElementsByTagName( 'a' );

			foreach ( $anchors as $anchor ) {

				$link        = $anchor->getAttribute( 'href' );
				$link_status = $this->validate_single_link( $link );
				var_dump( $link_status );
				usleep( 500 );
			}

			if ( ! $skip_images ) {

				$imgs = $document->getElementsByTagName( 'src' );

				foreach ( $imgs as $imgs ) {

					$src_link    = $imgs->getAttribute( 'src' );
					$link_status = $this->validate_single_link( $src_link );
					var_dump( $link_status );
					usleep( 500 );
				}
			}
			usleep( 5000 );
		}
	}

	/**
	 * Validate the independent link.
	 *
	 * @param $url
	 * @param bool $head_request
	 *
	 * @return mixed
	 */
	private function validate_single_link( $url, $head_request = true ) {

		//Init curl.
		$ch              = curl_init();
		$request_headers = array();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		//Masquerade as a recent version of Chrome
		$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.102 Safari/537.36';
		curl_setopt( $ch, CURLOPT_USERAGENT, $ua );

		//Close the connection after the request (disables keep-alive). The plugin rate-limits requests,
		//so it's likely we'd overrun the keep-alive timeout anyway.
		curl_setopt( $ch, CURLOPT_FORBID_REUSE, true );
		$request_headers[] = 'Connection: close';

		//Add a semi-plausible referer header to avoid tripping up some bot traps
		curl_setopt( $ch, CURLOPT_REFERER, home_url() );

		//Redirects don't work when safe mode or open_basedir is enabled.
		if ( ! $this->is_php_running_on_safe_mode() && ! $this->is_ini_open_basedir() ) {
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		}

		//Set maximum redirects
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );

		//Set the timeout
		curl_setopt( $ch, CURLOPT_TIMEOUT, $this->timeout );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $this->timeout );

		if ( $head_request ) {
			//If possible, use HEAD requests for speed.
			curl_setopt( $ch, CURLOPT_NOBODY, true );
		} else {
			//If we must use GET at least limit the amount of downloaded data.
			$request_headers[] = 'Range: bytes=0-2048'; //2 KB
		}

		//Set request headers.
		if ( ! empty( $request_headers ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $request_headers );
		}

		//Record request headers.
		if ( defined( 'CURLINFO_HEADER_OUT' ) ) {
			curl_setopt( $ch, CURLINFO_HEADER_OUT, true );
		}

		//Execute the request
		$start_time                = $this->microtime_float();
		$content                   = curl_exec( $ch );
		$measured_request_duration = $this->microtime_float() - $start_time;

		$info = curl_getinfo( $ch );

		$result['http_code']        = intval( $info['http_code'] );
		$result['final_url']        = $info['url'];
		$result['request_duration'] = $info['total_time'];
		$result['redirect_count']   = $info['redirect_count'];

		//CURL doesn't return a request duration when a timeout happens, so we measure it ourselves.
		//It is useful to see how long the plugin waited for the server to respond before assuming it timed out.
		if ( empty( $result['request_duration'] ) ) {
			$result['request_duration'] = $measured_request_duration;
		}

		//Determine if the link counts as "broken"
		if ( 0 === absint( $result['http_code'] ) ) {
			$result['broken'] = true;

			$error_code = curl_errno( $ch );

			//We only handle a couple of CURL error codes; most are highly esoteric.
			//libcurl "CURLE_" constants can't be used here because some of them have
			//different names or values in PHP.
			switch ( $error_code ) {
				case 6: //CURLE_COULDNT_RESOLVE_HOST
					$result['status_code'] = 'warning';
					$result['status_text'] = 'Server Not Found';
					$result['error_code']  = 'couldnt_resolve_host';
					break;

				case 28: //CURLE_OPERATION_TIMEDOUT
					$result['timeout'] = true;
					break;

				case 7: //CURLE_COULDNT_CONNECT
					//More often than not, this error code indicates that the connection attempt
					//timed out. This heuristic tries to distinguish between connections that fail
					//due to timeouts and those that fail due to other causes.
					if ( $result['request_duration'] >= 0.9 * $this->timeout ) {
						$result['timeout'] = true;
					} else {
						$result['status_code'] = 'warning';
						$result['status_text'] = 'Connection Failed';
						$result['error_code']  = 'connection_failed';
					}
					break;

				default:
					$result['status_code'] = 'warning';
					$result['status_text'] = 'Unknown Error';
			}
		} elseif ( 999 === $result['http_code'] ) {
			$result['status_code'] = 'warning';
			$result['status_text'] = 'Unknown Error';
			$result['warning']     = true;
		} else {
			$result['broken'] = $this->parse_error_code( $result['http_code'] );
		}

		return $result;
	}

	/**
	 * @param $url
	 *
	 * @return string|string[]|null
	 */
	private function clean_url( $url ) {
		$url = html_entity_decode( $url );

		$ltrm = preg_quote( json_decode( '"\u200E"' ), '/' );
		$url  = preg_replace(
			array(
				'/([\?&]PHPSESSID=\w+)$/i', //remove session ID
				'/(#[^\/]*)$/',             //and anchors/fragments
				'/&amp;/',                  //convert improper HTML entities
				'/([\?&]sid=\w+)$/i',       //remove another flavour of session ID
				'/' . $ltrm . '/',          //remove Left-to-Right marks that can show up when copying from Word.
			),
			array( '', '', '&', '', '' ),
			$url
		);
		$url  = trim( $url );

		return $url;
	}

	/**
	 * To Check if PHP is running in safe mode.
	 *
	 * @return bool
	 */
	private function is_php_running_on_safe_mode() {

		// php INI safe_mode available only for < 5.3.0 PHP version.
		if ( version_compare( phpversion(), '5.3.0', '<' ) ) {
			$safe_mode = ini_get( 'safe_mode' );
		} else {
			$safe_mode = false;
		}

		if ( ! $safe_mode ) {
			return false;
		}

		switch ( strtolower( $safe_mode ) ) {
			case 'on':
			case 'true':
			case 'yes':
				return true;

			case 'off':
			case 'false':
			case 'no':
				return false;

			default:
				return (bool) (int) $safe_mode;
		}
	}

	/**
	 * Checks if open_basedir is enabled in php options.
	 *
	 * @return bool
	 */
	private function is_ini_open_basedir() {
		$open_basedir = ini_get( 'open_basedir' );
		return $open_basedir && ( 'none' !== strtolower( $open_basedir ) );
	}


	/**
	 * @param $http_code
	 *
	 * @return bool
	 */
	private function parse_error_code( $http_code ) {
		/*
		 * Allow 2XX range ( like "200 OK" )
		 * Allow the 3XX range ( redirects )
		 * Allow 401 ( Unauthorized request which needs authentication )
		 * Dont allow rest of the codes.
		 */
		return ! ( ( $http_code >= 200 ) && ( $http_code < 400 ) ) || ( 401 === $http_code );
	}

	/**
	 * Get micro time to compare two times.
	 * @return float
	 */
	private function microtime_float() {
		list( $usec, $sec ) = explode( ' ', microtime() );
		return ( (float) $usec + (float) $sec );
	}
}


WP_CLI::add_command( 'link-scanner', 'Link_Scanner' );
