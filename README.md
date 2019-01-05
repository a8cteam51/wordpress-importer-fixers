# WordPress Import Fixers

This plugin creates several WP CLI subcommands to assist with the fixing and cleanup from a non-trivial WordPress import.

## Installation

The plugin is not currently available in the WordPress.org plugin directory. The best way to install it is to download a ZIP version of the plugin from this GitHub repository, and then upload it to the `plugins` directory of your site. From there, activate the plugin.

Because this plugin only adds WP CLI subcommands, there are no user-facing tools in wp-admin.

## Commands

Here are the WP CLI commands provided by this plugin. See below for more information about the "origin" concept that is relied on heavily.

### fix-thumbnails-contextually

#### Usage

`wp import-fixer fix-thumbnails-contextually --origin=<origin>`

#### Description

This command helps with the scenario where:

* An import WXR file contains posts and attachments
* Some of the attachments are associated with some of the posts as featured images / thumbnails
* The unique IDs that connect posts and attachments in the WXR are not the IDs that result in the target site after import, usually because of existing content.
* Posts are displaying the wrong image as their thumbnail/featured image.

The command updates the posts to use the correct thumbnail ID.

It assumes that:

* all posts and attachments have `_original_import_origin` post meta defined
* all attachments that are thumbnails have `_original_post_id` post meta defined
* all posts that have thumbnails have `_original_thumbnail_id` post meta defined

### fix-galleries-contextually

#### Usage

`wp import-fixer fix-galleries-contextually --origin=<origin>`

#### Description

This command helps with the scenario where:

* An import WXR file contains posts that have `[gallery]` shortcodes in the content, referencing attachments also in the WXR file.
* The unique IDs that connect posts and gallery images in the WXR are not the IDs that result in the target site after import, usually because of existing content.
* Post galleries are displayed with the wrong images.

The command updates the posts to use a correct set of attachment IDs in the gallery shortcode. It also stores the original version of the gallery shortcode in the post meta field `_old_gallery_1` (incrementing the value at the end as needed).

It assumes that:

* all posts and attachments have `_original_import_origin` post meta defined
* all attachments that are used in galleries have `_original_post_id` post meta defined

### fix-media-urls

#### Usage

`wp import-fixer fix-media-urls --origin=<origin>`

#### Description

This command helps with the scenario where:

* An import WXR file contains posts and attachments.
* During the resulting import for whatever reason, the post content is not updated to use the new media URL for the imported attachment, instead still referencing the media URL as it existed on the source site.
* Posts display images as hosted on the source site (or, if said site is down, broken images).

The command scans through all of the attachments imported for the given origin, looks for post content referencing the original media URL, and updates it to the new URL.

It assumes that:

* all posts and attachments have `_original_import_origin` post meta defined
* all attachments have `_original_import_url` post meta defined with the media URL as it existed on the source site

### import-external-images

#### Usage

`wp import-fixer import-external-images [ --all-domains | --domain=mydomain.com ]`

#### Description

This command helps with the scenario where:

* An import WXR file contains post content that references image URLs not in the media library.
* Posts display images as hosted on the original source site (or, if said site is down, broken images).

The command searches post content for external image URLs, attempts to download them into the media library as attachments, and then updates post content to use the new local attachment URL. 

You can use these parameters to control its behavior:

* `--list` to list all image domains found in post content, but don't take any action.
* `--all-domains` or `--domain=mydomain.com` to have the importer use all image domains found, or to specify a single domain for importing.
* `--post_type=myposttype` or `--post_type=any` to have the importer only operate on a specific post type, or to have it operate on all public, non-attachment posts. Default is "post".
* `--rewind` (experimental!): Reverse image source replacements and delete any imported images previously added with this command.

Note: this command does not yet support the use of origins.

### fix-post-hierarchy-contextually

#### Usage

`wp import-fixer fix-post-hierarchy-contextually --origin=<origin>`

#### Description

This command helps with the scenario where:

* An import WXR file contains posts that have parent/child relationships
* The unique IDs that connect posts with their parents/children in the WXR are not the IDs that result in the target site after import, usually because of existing content.
* Posts end up with incorrect parent/child posts displayed.

This command updates the posts to use the correct parent/child post ID.

It assumes that:

* all posts have `_original_import_origin` post meta defined
* all posts have `_original_post_id` post meta defined
* all posts with a parent post have `_original_parent_id` post meta defined

### fix-art19-embeds

###

## About Origins

The concept of an origin here describes a set of data that was exported from the source site at the same time. We use it to update, fix and reconnect information that might otherwise be disconnected or lost during an import into a WordPress site.

This plugin assumes that the export WXR files being generated for importing will include some essential post meta fields in the entries it generates for posts, pages, attachments and other data. This plugin does not handle the process of generating an export with this and related post meta fields - we leave that as an exercise to the reader, although other tools to help with that are forthcoming. The most important is the origin field, which the plugin expects to be in `_original_import_origin`.

The value of the origin field can be any string you want, but we suggest using something that indicates the date and even time of the export it represents, and for delta exports, the period of time that the included content covers. An example might be `20180831154400-myoldsite-201012to201512`.

We recommend always specifying an origin when running the above commands. Otherwise, they will likely cycle through all available origins, which could take a very long time on large sites.