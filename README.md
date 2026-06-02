# WordPress Import Fixers

WordPress Import Fixers is a WordPress plugin that registers `wp import-fixer`
WP-CLI subcommands for cleaning up non-trivial WordPress imports, including WXR
imports used for site merges and partial imports.

The plugin has no wp-admin user interface. It exits unless it is loaded in a
WP-CLI request.

## Repository Contents

- `import-fixer.php` contains the plugin header, the `Import_Fixer`
  `WP_CLI_Command` class, and the `wp import-fixer` command registration.
- `README.md` documents the command behavior and the import metadata each
  command expects.
- `.gitignore` excludes local operating-system and IDE files.

There are no Composer, npm, CI, lint, test, build, or deployment files tracked
in this repository.

## Requirements

- WordPress with WP-CLI available.
- A WordPress environment where this repository can be installed as a plugin.
- Imported content metadata required by the command being run, as described
  below.

## Installation

This plugin is not available in the WordPress.org plugin directory. Install it
by placing this repository in a site's `plugins` directory, then activate the
plugin.

Because the plugin only registers WP-CLI commands, there are no user-facing
tools in wp-admin after activation.

## WP-CLI Commands

All commands are registered under `wp import-fixer`.

### `fix-thumbnails-contextually`

```sh
wp import-fixer fix-thumbnails-contextually [--origin=<import-origin>]
```

Fixes featured image references after an import where original post and
attachment IDs no longer match the IDs assigned on the target site.

The command expects:

- Posts and attachments to have `_original_import_origin` post meta.
- Thumbnail attachments to have `_original_post_id` post meta.
- Posts with thumbnails to have `_original_thumbnail_id` post meta.

When `--origin` is supplied, only that import origin is processed. Without it,
the command processes all origins found in `_original_import_origin` post meta.

### `fix-galleries-contextually`

```sh
wp import-fixer fix-galleries-contextually [--origin=<import-origin>]
```

Rewrites `[gallery]` shortcode `ids` values so gallery attachments point to the
attachments imported into the target site. Before replacing a gallery shortcode,
the command stores the original shortcode in `_old_gallery_1`, incrementing the
suffix for additional galleries in the same post.

The command expects:

- Posts and attachments to have `_original_import_origin` post meta.
- Gallery attachments to have `_original_post_id` post meta.

When `--origin` is supplied, only that import origin is processed. Without it,
the command processes all origins found in `_original_import_origin` post meta.

### `fix-media-urls`

```sh
wp import-fixer fix-media-urls [--origin=<import-origin>]
```

Replaces source-site media URLs in post content with the current attachment URLs
for attachments already imported into the target site. It also handles image URL
variants with WordPress dimension suffixes such as `-123x456`.

The command expects:

- Posts and attachments to have `_original_import_origin` post meta.
- Attachments to have `_original_import_url` post meta containing the media URL
  from the source site.

When `--origin` is supplied, only that import origin is processed. Without it,
the command processes all origins found in `_original_import_origin` post meta.

### `import-external-images`

```sh
wp import-fixer import-external-images --list [--post_type=<post-type|any>]
wp import-fixer import-external-images --domain=<domain> [--protocol=<http|https>] [--post_type=<post-type|any>]
wp import-fixer import-external-images --all-domains [--post_type=<post-type|any>]
wp import-fixer import-external-images --rewind
```

Searches post content for external image URLs, sideloads matching images into
the media library, and replaces the source URLs with the new local attachment
URLs. It records imported image replacements in `_added_via_script_backup_meta`
so `--rewind` can reverse replacements and delete attachments imported by this
command.

Options:

- `--list` lists external image domains found in post content without importing
  images.
- `--domain=<domain>` imports images from one domain.
- `--protocol=<http|https>` supplies the protocol used when relative image URLs
  are made absolute for a specified domain. The default is `https`.
- `--all-domains` imports images from any external domain.
- `--post_type=<post-type|any>` limits processing to one post type, or uses all
  public non-attachment post types when set to `any`. The default is `post`.
- `--rewind` reverses replacements made by this command and deletes attachments
  imported by it.

Use either `--domain` or `--all-domains` when importing. The command does not
support import origins.

### `fix-post-hierarchy-contextually`

```sh
wp import-fixer fix-post-hierarchy-contextually
```

Fixes post parent references after an import where original parent and child IDs
no longer match the IDs assigned on the target site.

The command expects:

- Posts to have `_original_import_origin` post meta.
- Posts to have `_original_post_id` post meta.
- Posts with parents to have `_original_parent_id` post meta.

This command processes all origins found in `_original_import_origin` post meta.

### `fix-comment-count`

```sh
wp import-fixer fix-comment-count
wp import-fixer fix-comment-count --url=example.test
```

Recalculates comment counts for published posts in batches of 500. Use WP-CLI's
standard `--url` option when running against a specific site in a multisite
installation.

## Import Origins

Several commands use an import origin to scope fixes to a specific set of data.
The origin is the `_original_import_origin` post meta value attached to imported
posts and attachments.

The value can be any string, but it should identify the export batch clearly.
For example:

```text
20180831154400-myoldsite-201012to201512
```

Using `--origin` where supported is recommended for large sites because it
limits processing to one import batch. Commands that support `--origin` process
all discovered origins when the option is omitted.

## Maintenance Notes

- Keep command documentation aligned with the docblocks and implementations in
  `import-fixer.php`.
- The repository does not include automated tests or linting configuration.
- No license file or package metadata is tracked in this repository.
