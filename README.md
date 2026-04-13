# Unused Media Auditor

Unused Media Auditor is a WordPress plugin that helps administrators review image attachments that appear unused in standard WordPress data.

The plugin is designed to give site owners peace of mind:

- It runs entirely inside WordPress.
- It does not require extra server tools or shell access.
- It helps surface likely cleanup candidates, but the user decides what to archive, restore, or delete.
- When permanent deletion is chosen, WordPress handles the attachment removal using its native media deletion flow.

## What It Does

The plugin adds three admin experiences under `Media`:

- `Unused Images`: shows image attachments that do not appear to be referenced in common WordPress content and data sources.
- `Archived Images`: shows files that were moved into the plugin archive for safe review before permanent deletion.
- `Unused Media Settings`: lets administrators choose how long archived files should be retained before automatic cleanup.

## How Detection Works

Unused Media Auditor looks for common WordPress usage patterns, including:

- Featured images via `_thumbnail_id`
- Attachment parent relationships
- Post content
- Post meta
- Options
- Term meta
- User meta

This means the plugin is mostly database-driven, but it is still intentionally conservative in how it presents results. If a theme, page builder, plugin, or custom integration stores image references in unusual formats or uses hardcoded file paths, the plugin may not detect those references automatically.

Because of that, the plugin should be treated as a review tool, not an automatic truth engine.

## Safety Model

The plugin is built around administrator control:

- Results are advisory.
- Users choose what to archive.
- Users choose what to restore.
- Users choose what to delete permanently.
- Archiving provides a rollback window before files are removed.

When an image is archived, the plugin moves the attachment files into a dedicated archive folder inside WordPress uploads and stores enough information to restore them later. If the user chooses permanent deletion, WordPress performs the underlying attachment delete operation.

## Installation

### Option 1: Manual Upload

1. Download or clone this repository.
2. Copy the `unused-media-auditor` folder into `wp-content/plugins/`.
3. In WordPress admin, go to `Plugins`.
4. Activate `Unused Media Auditor`.
5. Open `Media > Unused Images`.

### Option 2: ZIP Upload

1. Create a ZIP that contains the `unused-media-auditor` folder.
2. In WordPress admin, go to `Plugins > Add New > Upload Plugin`.
3. Upload the ZIP file.
4. Activate `Unused Media Auditor`.
5. Open `Media > Unused Images`.

## Recommended Usage

1. Open `Media > Unused Images`.
2. Review the list rather than deleting in bulk immediately.
3. Archive items first when you want a safer cleanup path.
4. Check `Media > Archived Images` and restore anything questionable.
5. Only use `Delete Permanently` when you are confident the media is truly unused.
6. Set a retention period in `Media > Unused Media Settings` that gives you enough review time.

## Good Fit

This plugin is especially useful when you want to:

- clean up old uploads after redesigns
- review years of media-library growth
- spot likely orphaned images
- reduce clutter before manual media audits

## Important Limitations

No unused-media detector can guarantee perfect results across every WordPress site.

Please review carefully if your site depends on:

- hardcoded image URLs or filesystem paths
- custom builders or page composers
- theme settings stored in unusual formats
- plugin-specific metadata structures
- external systems that reference media indirectly

## Development Notes

- Plugin bootstrap: `unused-media-auditor/unused-media-auditor.php`
- Admin UI: `unused-media-auditor/includes/class-uma-admin.php`
- Scanner logic: `unused-media-auditor/includes/class-uma-scanner.php`
- Archive and restore logic: `unused-media-auditor/includes/class-uma-archiver.php`

## License

GPLv2 or later. See [LICENSE](./LICENSE).
