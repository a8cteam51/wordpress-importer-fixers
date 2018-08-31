# WordPress Import Fixers

This plugin creates several WP CLI subcommands to assist with the fixing and cleanup from a non-trivial WordPress import.

## Installation

The plugin is not currently available in the WordPress.org plugin directory. The best way to install it is to download a ZIP version of the plugin from this GitHub repository, and then upload it to the `plugins` directory of your site. From there, activate the plugin.

Because this plugin only adds WP CLI subcommands, there are no user-facing tools in wp-admin.

## Commands

Here are the WP CLI commands provided by this plugin:

### fix-thumbnails-contextually

#### Usage

`wp import-fixer fix-thumbnails-contextually --origin=<origin>`

#### Description

This command helps with the scenario where:

* An import WXR file contains posts and attachments
* Some of the attachments are associated with some of the posts as featured images / thumbnails
* The unique IDs that connect posts and attachments in the WXR are not the IDs that result in the target site, usually because of existing content.
* Posts are displaying the wrong image as their thumbnail/featured image.

The command updates the posts to use the correct thumbnail ID.

It assumes that:
 
 * all posts and attachments have `_original_import_origin` post meta defined
 * all attachments that are thumbnails have `_original_post_id` post meta defined
 * all posts that have thumbnails have `_original_thumbnail_id` post meta defined
 
 