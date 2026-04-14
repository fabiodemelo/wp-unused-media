<?php
/**
 * Plugin Name: Unused Media Auditor
 * Plugin URI: https://demelos.com
 * Description: Helps administrators review image attachments that appear unused in WordPress data, with archive and WordPress-native delete actions kept under user control.
 * Version: 1.0.3
 * Author: Fabio DeMelo
 * Author URI: https://demelos.com
 * Text Domain: unused-media-auditor
 */

if (! defined('ABSPATH')) {
    exit;
}

define('UMA_VERSION', '1.0.3');
define('UMA_PLUGIN_FILE', __FILE__);
define('UMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UMA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UMA_OPTION_RETENTION_DAYS', 'uma_retention_days');
define('UMA_CRON_HOOK', 'uma_cleanup_archived_media');
define('UMA_ARCHIVE_META', '_uma_archive_manifest');
define('UMA_ARCHIVED_AT_META', '_uma_archived_at');

require_once UMA_PLUGIN_DIR . 'includes/class-uma-scanner.php';
require_once UMA_PLUGIN_DIR . 'includes/class-uma-archiver.php';
require_once UMA_PLUGIN_DIR . 'includes/class-uma-admin.php';
require_once UMA_PLUGIN_DIR . 'includes/class-uma-plugin.php';

UMA_Plugin::bootstrap();
