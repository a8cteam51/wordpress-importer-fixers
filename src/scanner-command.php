<?php

namespace A8C\HttpStatusCheck;

use GuzzleHttp\RequestOptions;
use Spatie\Crawler\CrawlAllUrls;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlInternalUrls;
use WP_CLI;


class Scan_Command extends \WP_CLI_Command {

	/**
	 * Scan broken links from site.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : Site URL to scan.
	 *
	 * [--output]
	 * : Log output in file.
	 *
	 * [--skip-external]
	 * : Dont crawl external links.
	 *
	 * [--concurrent-connections]
	 * : Number of concurrent connections.
	 *
	 * default: 10
	 *
	 * [--timeout]
	 * : Timeout for the request.
	 *
	 * default: 10
	 *
	 * [--user-agent]
	 * : The User Agent to pass for the request.
	 *
	 * [--skip-ssl]
	 * : Skips checking the SSL certificate.
	 *
	 * [--ignore-robots]
	 * : Ignore robots checks.
	 *
	 */
	public function scan( $args, $assoc_args ) {

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please provide site link' );
		}

		$baseUrl = $args[0];

		$skip_external = isset( $assoc_args['skip-external'] );
		$timeout       = \WP_CLI\Utils\get_flag_value( $assoc_args, 'timeout', 10 );


		$crawlProfile = $skip_external ? new CrawlInternalUrls( $baseUrl ) : new CrawlAllUrls();

		WP_CLI::log( "Start scanning {$baseUrl}" );
		WP_CLI::log( '' );

		$crawlLogger = new CrawlLogger();

		// @todo add csv output options.
		if ( isset( $assoc_args['output'] ) ) {
			$outputFile = \WP_CLI\Utils\get_flag_value( $assoc_args, 'output', 'linker.log' );

			if ( file_exists( $outputFile ) ) {
				$question = WP_CLI::confirm(
					"The output file `{$outputFile}` already exists. Overwrite it?",
					false
				);
				unlink( $outputFile );
			}

			$crawlLogger->setOutputFile( $outputFile );
		}

		$clientOptions = [
			RequestOptions::TIMEOUT         => $timeout,
			RequestOptions::VERIFY          => ! $skip_external,
			RequestOptions::ALLOW_REDIRECTS => [
				'track_redirects' => true,
			],
		];

//		@todo: Set additional options
//		$clientOptions = array_merge($clientOptions, $input->getOption('options'));

		if ( isset( $assoc_args['user-agent'] ) ) {
			$clientOptions[ RequestOptions::HEADERS ]['user-agent'] = \WP_CLI\Utils\get_flag_value( $assoc_args, 'user-agent' );
		}

		$concurrent_connections = \WP_CLI\Utils\get_flag_value( $assoc_args, 'concurrent-connections', 10 );

		$crawler = Crawler::create( $clientOptions )
			->setConcurrency( $concurrent_connections )
			->setCrawlObserver( $crawlLogger )
			->setCrawlProfile( $crawlProfile );

		if ( isset( $assoc_args['ignore-robots'] ) ) {
			$crawler->ignoreRobots();
		}

		$crawler->startCrawling( $baseUrl );

	}
}

WP_CLI::add_command( 'linker', '\A8C\HttpStatusCheck\Scan_Command' );
