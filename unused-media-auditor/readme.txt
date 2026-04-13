=== Unused Media Auditor ===
Contributors: fabiodemelo
Tags: media, images, cleanup, library, attachments
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Review image files that appear unused in WordPress data, then choose whether to archive, restore, or delete them in bulk.

== Description ==

Unused Media Auditor helps site owners review image attachments that appear unused across the website while keeping the final decision in the administrator's hands.

The plugin is designed for real-world WordPress hosting, including shared hosting environments:

* No shell access is required.
* No extra server-side tools are required for site owners.
* The review runs inside WordPress admin.
* Permanent deletes use WordPress's own attachment deletion behavior.

Features:

* Scans image attachments and excludes files already archived.
* Checks common WordPress usage patterns including featured images, attachment parent relationships, post content, metadata, options, term meta, and user meta.
* Displays unused images in a visual admin grid.
* Runs entirely inside WordPress with no extra server-side tools required for site owners.
* Supports bulk archive and permanent delete actions initiated by the administrator.
* Moves archived files into a dedicated archive folder inside uploads.
* Lets administrators restore archived files before retention expires.
* Uses WordPress's native attachment deletion flow when you choose permanent removal.
* Automatically deletes archived files after the configured number of days.

== Installation ==

1. Upload the `unused-media-auditor` folder to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open `Media > Unused Images` to review files.
4. Open `Media > Unused Media Settings` to choose how many days archived files should be kept.

== How To Use ==

1. Go to `Media > Unused Images`.
2. Review the images shown by the scanner.
3. Archive images first if you want a safer review period.
4. Open `Media > Archived Images` to restore anything that should remain on the site.
5. Only use `Delete Permanently` when you are confident the image is not needed.

== How Detection Works ==

The plugin looks for common WordPress image references in:

* Featured image relationships
* Attachment parent relationships
* Post content
* Post meta
* Options
* Term meta
* User meta

This makes the plugin database-first, but still advisory rather than absolute.

== Safety And Limitations ==

The plugin uses heuristics to determine whether an image is in use by searching common WordPress tables and relationships. If a theme, plugin, page builder, or custom integration stores references in an unusual format or uses hardcoded file paths, review archived images before permanent deletion.

This plugin helps surface likely candidates for cleanup, but the administrator is the one who decides what to archive, restore, or delete.

Archiving is the safest workflow because it gives you time to confirm nothing breaks before permanent deletion.

== Frequently Asked Questions ==

= Does this plugin automatically delete media? =

No. The plugin shows likely-unused images and lets the administrator decide what to archive, restore, or delete.

= Does it require SSH or command-line access? =

No. It is designed to work inside standard WordPress hosting environments, including shared hosting.

= Why might an image still be in use even if it appears unused here? =

Some themes, builders, and plugins store references outside the common WordPress patterns this plugin checks, or they may use hardcoded URLs or paths.
