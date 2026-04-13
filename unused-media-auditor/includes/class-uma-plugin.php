<?php

if (! defined('ABSPATH')) {
    exit;
}

class UMA_Plugin
{
    /**
     * @var UMA_Plugin|null
     */
    private static $instance = null;

    /**
     * @var UMA_Scanner
     */
    private $scanner;

    /**
     * @var UMA_Archiver
     */
    private $archiver;

    /**
     * @var UMA_Admin
     */
    private $admin;

    public static function bootstrap()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->scanner = new UMA_Scanner();
        $this->archiver = new UMA_Archiver();
        $this->admin = new UMA_Admin($this->scanner, $this->archiver);

        register_activation_hook(UMA_PLUGIN_FILE, array(__CLASS__, 'activate'));
        register_deactivation_hook(UMA_PLUGIN_FILE, array(__CLASS__, 'deactivate'));

        add_action('admin_menu', array($this->admin, 'register_admin_menu'));
        add_action('admin_init', array($this->admin, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_assets'));
        add_action('admin_post_uma_bulk_unused', array($this->admin, 'handle_unused_bulk_action'));
        add_action('admin_post_uma_bulk_archived', array($this->admin, 'handle_archived_bulk_action'));
        add_action('admin_post_uma_save_settings', array($this->admin, 'handle_settings_submit'));
        add_action(UMA_CRON_HOOK, array($this->archiver, 'cleanup_expired_archives'));
        add_action('uma_archives_changed', array($this->scanner, 'flush_cache'));
    }

    public static function activate()
    {
        if (! get_option(UMA_OPTION_RETENTION_DAYS)) {
            add_option(UMA_OPTION_RETENTION_DAYS, 30);
        }

        if (! wp_next_scheduled(UMA_CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', UMA_CRON_HOOK);
        }
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(UMA_CRON_HOOK);
    }
}
