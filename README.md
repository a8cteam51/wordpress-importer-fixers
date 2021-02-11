# Link Checker

This plugin adds WP CLI command to scan the site for broken links.
 
## Installation

1. Clone the github link inside plugins directory.
    ```
    git clone git@github.com:a8cteam51/wordpress-importer-fixers.git
    
    cd wordpress-importer-fixers 
    ```

1. Checkout branch.
    ```
    git checkout spatie/cli-crawler
    ```

1. This plugin will require `composer` to install required dependencies.
 
    ```
    composer install
    ```

1. Activate the plugin.

## Commands

Here are the WP CLI commands provided by this plugin. See below for more information about the "origin" concept that is relied on heavily.

### scan

#### Usage

`wp linker scan <site-url>`


#### Description

This command starts scanning site url for broken links. A command will log it's output with link status.

#### Options
[--output=csv-path]
: File path for broken links output in CSV format.

[--skip-external]
: Don't crawl external links.

[--concurrent-connections]
: Number of concurrent connections. default is 10.

[--timeout]
: Timeout for the request. default is 10.

[--user-agent]
: The User Agent to pass for the request.

[--skip-ssl]
: Skips checking the SSL certificate.

[--ignore-robots]
: Ignore robots checks.


### Example
```
# Scan example.com for broken links.
$ wp linker scan http://example.com

# Scan example.com for broken links and output broken links result in result.csv file.
$ wp linker scan http://example.com --output=result.csv

```
