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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once 'vendor/autoload.php';
} else {
	WP_CLI::error( 'Run `composer install`' );
}

require_once 'src/crawl-logger.php';
require_once 'src/scanner-command.php';
