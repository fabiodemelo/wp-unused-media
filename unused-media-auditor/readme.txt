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

== Notes ==

The plugin uses heuristics to determine whether an image is in use by searching common WordPress tables and relationships. If a theme, plugin, page builder, or custom integration stores references in an unusual format or uses hardcoded file paths, review archived images before permanent deletion.

This plugin helps surface likely candidates for cleanup, but the administrator is the one who decides what to archive, restore, or delete.
