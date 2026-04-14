<?php

if (! defined('ABSPATH')) {
    exit;
}

class UMA_Admin
{
    /**
     * @var UMA_Scanner
     */
    private $scanner;

    /**
     * @var UMA_Archiver
     */
    private $archiver;

    /**
     * @param UMA_Scanner  $scanner
     * @param UMA_Archiver $archiver
     */
    public function __construct($scanner, $archiver)
    {
        $this->scanner = $scanner;
        $this->archiver = $archiver;
    }

    public function register_admin_menu()
    {
        add_media_page(
            __('Unused Images', 'unused-media-auditor'),
            __('Unused Images', 'unused-media-auditor'),
            'upload_files',
            'uma-unused-images',
            array($this, 'render_unused_page')
        );

        add_submenu_page(
            'upload.php',
            __('Archived Images', 'unused-media-auditor'),
            __('Archived Images', 'unused-media-auditor'),
            'upload_files',
            'uma-archived-images',
            array($this, 'render_archived_page')
        );

        add_submenu_page(
            'upload.php',
            __('Unused Media Settings', 'unused-media-auditor'),
            __('Unused Media Settings', 'unused-media-auditor'),
            'manage_options',
            'uma-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting('uma_settings', UMA_OPTION_RETENTION_DAYS, array(
            'type' => 'integer',
            'sanitize_callback' => array($this, 'sanitize_retention_days'),
            'default' => 30,
        ));
    }

    /**
     * @param string $hook_suffix
     * @return void
     */
    public function enqueue_assets($hook_suffix)
    {
        $allowed_hooks = array(
            'media_page_uma-unused-images',
            'media_page_uma-archived-images',
            'media_page_uma-settings',
        );

        if (! in_array($hook_suffix, $allowed_hooks, true)) {
            return;
        }

        wp_enqueue_style(
            'uma-admin',
            UMA_PLUGIN_URL . 'assets/admin.css',
            array(),
            UMA_VERSION
        );

        wp_enqueue_script(
            'uma-admin',
            UMA_PLUGIN_URL . 'assets/admin.js',
            array(),
            UMA_VERSION,
            true
        );

        wp_localize_script('uma-admin', 'umaAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'loadUnusedNonce' => wp_create_nonce('uma_load_unused_images'),
            'messages' => array(
                'loading' => __('Loading images...', 'unused-media-auditor'),
                'loadError' => __('The image list could not be loaded. Please try Refresh Scan.', 'unused-media-auditor'),
            ),
        ));
    }

    public function render_unused_page()
    {
        if (! current_user_can('upload_files')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'unused-media-auditor'));
        }

        if (! empty($_GET['uma_refresh'])) {
            $this->scanner->flush_cache();
        }

        $this->render_notices();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Unused Images', 'unused-media-auditor'); ?></h1>
            <p><?php esc_html_e('These image attachments do not appear to be referenced in posts, metadata, options, or common WordPress image relationships.', 'unused-media-auditor'); ?></p>
            <p><?php esc_html_e('This review runs inside WordPress without requiring extra server tools. Results are advisory only: if a theme, builder, or custom code uses hardcoded file paths or unusual storage patterns, review carefully and choose what to archive or delete yourself.', 'unused-media-auditor'); ?></p>
            <div
                class="uma-async-panel"
                data-uma-async-container="unused-images"
                data-uma-async-context="unused"
                data-uma-async-action="uma_load_unused_images"
            >
                <div class="uma-loading-state" role="status" aria-live="polite">
                    <span class="spinner is-active" aria-hidden="true"></span>
                    <span><?php esc_html_e('Loading images...', 'unused-media-auditor'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_load_unused_images()
    {
        if (! current_user_can('upload_files')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to load these images.', 'unused-media-auditor'),
            ), 403);
        }

        check_ajax_referer('uma_load_unused_images', 'nonce');

        $items = $this->scanner->get_unused_images();

        ob_start();
        $this->render_bulk_form($items, 'unused');

        wp_send_json_success(array(
            'html' => ob_get_clean(),
        ));
    }

    public function render_archived_page()
    {
        if (! current_user_can('upload_files')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'unused-media-auditor'));
        }

        if (! empty($_GET['uma_refresh'])) {
            $this->scanner->flush_cache();
        }

        $items = $this->scanner->get_archived_images();

        $this->render_notices();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Archived Images', 'unused-media-auditor'); ?></h1>
            <p><?php echo esc_html(sprintf(__('Archived files will be permanently deleted after %d days unless restored first.', 'unused-media-auditor'), (int) get_option(UMA_OPTION_RETENTION_DAYS, 30))); ?></p>
            <p><?php esc_html_e('Use this archive as a safety step before deletion. When you choose Delete Permanently, WordPress handles the attachment removal using its normal media deletion behavior.', 'unused-media-auditor'); ?></p>
            <?php $this->render_bulk_form($items, 'archived'); ?>
        </div>
        <?php
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'unused-media-auditor'));
        }

        $retention_days = (int) get_option(UMA_OPTION_RETENTION_DAYS, 30);
        $this->render_notices();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Unused Media Settings', 'unused-media-auditor'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="uma-settings-form">
                <?php wp_nonce_field('uma_save_settings'); ?>
                <input type="hidden" name="action" value="uma_save_settings">
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="uma-retention-days"><?php esc_html_e('Archive retention (days)', 'unused-media-auditor'); ?></label>
                            </th>
                            <td>
                                <input
                                    id="uma-retention-days"
                                    name="uma_retention_days"
                                    type="number"
                                    min="1"
                                    step="1"
                                    class="small-text"
                                    value="<?php echo esc_attr($retention_days); ?>"
                                >
                                <p class="description"><?php esc_html_e('Archived images are automatically deleted after this many days. Detection remains advisory, so choose a retention window that gives you time to review and restore anything referenced outside standard WordPress data.', 'unused-media-auditor'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(__('Save Settings', 'unused-media-auditor')); ?>
            </form>
        </div>
        <?php
    }

    public function handle_unused_bulk_action()
    {
        if (! current_user_can('upload_files')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'unused-media-auditor'));
        }

        check_admin_referer('uma_bulk_unused');

        $ids = $this->sanitize_attachment_ids(isset($_POST['attachment_ids']) ? (array) $_POST['attachment_ids'] : array());
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : 'archive';

        if (empty($ids) || ! in_array($bulk_action, array('archive', 'delete'), true)) {
            $this->redirect_with_notice('uma-unused-images', 'warning', __('Select at least one image and a valid bulk action.', 'unused-media-auditor'));
        }

        $ids = $this->filter_ids_for_action($ids, 'unused', $bulk_action);

        if (empty($ids)) {
            $this->redirect_with_notice('uma-unused-images', 'warning', __('No eligible images were selected for this action.', 'unused-media-auditor'));
        }

        $result = ('archive' === $bulk_action)
            ? $this->archiver->archive_many($ids)
            : $this->archiver->delete_many($ids);

        $this->scanner->flush_cache();

        $message = ('archive' === $bulk_action)
            ? sprintf(__('Archived %d image(s). %d failed.', 'unused-media-auditor'), $result['processed'], $result['failed'])
            : sprintf(__('Deleted %d image(s). %d failed.', 'unused-media-auditor'), $result['processed'], $result['failed']);

        $this->redirect_with_notice('uma-unused-images', 'success', $message);
    }

    public function handle_archived_bulk_action()
    {
        if (! current_user_can('upload_files')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'unused-media-auditor'));
        }

        check_admin_referer('uma_bulk_archived');

        $ids = $this->sanitize_attachment_ids(isset($_POST['attachment_ids']) ? (array) $_POST['attachment_ids'] : array());
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : 'restore';

        if (empty($ids) || ! in_array($bulk_action, array('restore', 'delete'), true)) {
            $this->redirect_with_notice('uma-archived-images', 'warning', __('Select at least one archived image and a valid bulk action.', 'unused-media-auditor'));
        }

        $ids = $this->filter_ids_for_action($ids, 'archived', $bulk_action);

        if (empty($ids)) {
            $this->redirect_with_notice('uma-archived-images', 'warning', __('No eligible archived images were selected for this action.', 'unused-media-auditor'));
        }

        $result = ('restore' === $bulk_action)
            ? $this->archiver->restore_many($ids)
            : $this->archiver->delete_many($ids);

        $this->scanner->flush_cache();

        $message = ('restore' === $bulk_action)
            ? sprintf(__('Restored %d image(s). %d failed.', 'unused-media-auditor'), $result['processed'], $result['failed'])
            : sprintf(__('Deleted %d archived image(s). %d failed.', 'unused-media-auditor'), $result['processed'], $result['failed']);

        $this->redirect_with_notice('uma-archived-images', 'success', $message);
    }

    public function handle_settings_submit()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'unused-media-auditor'));
        }

        check_admin_referer('uma_save_settings');

        $retention_days = isset($_POST['uma_retention_days']) ? $this->sanitize_retention_days(wp_unslash($_POST['uma_retention_days'])) : 30;
        update_option(UMA_OPTION_RETENTION_DAYS, $retention_days);

        $this->redirect_with_notice('uma-settings', 'success', __('Settings saved.', 'unused-media-auditor'));
    }

    /**
     * @param mixed $value
     * @return int
     */
    public function sanitize_retention_days($value)
    {
        return max(1, (int) $value);
    }

    /**
     * @param array<int, string> $raw_ids
     * @return array<int, int>
     */
    private function sanitize_attachment_ids($raw_ids)
    {
        return array_values(array_filter(array_map('absint', $raw_ids)));
    }

    /**
     * @param array<int, int> $ids
     * @param string          $context
     * @param string          $action
     * @return array<int, int>
     */
    private function filter_ids_for_action($ids, $context, $action)
    {
        $allowed_ids = array();

        foreach ($ids as $attachment_id) {
            if (! $this->is_action_allowed_for_attachment($attachment_id, $context, $action)) {
                continue;
            }

            $allowed_ids[] = $attachment_id;
        }

        return $allowed_ids;
    }

    /**
     * @param int $attachment_id
     * @param string $context
     * @param string $action
     * @return bool
     */
    private function is_action_allowed_for_attachment($attachment_id, $context, $action)
    {
        $attachment = get_post($attachment_id);

        if (! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image($attachment_id)) {
            return false;
        }

        if ('delete' === $action && ! current_user_can('delete_post', $attachment_id)) {
            return false;
        }

        if ('delete' !== $action && ! current_user_can('edit_post', $attachment_id)) {
            return false;
        }

        if ('archived' === $context) {
            return $this->scanner->is_attachment_archived($attachment_id);
        }

        return ! $this->scanner->is_attachment_archived($attachment_id);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param string                           $context
     * @return void
     */
    private function render_bulk_form($items, $context)
    {
        $form_action = ('archived' === $context) ? 'uma_bulk_archived' : 'uma_bulk_unused';
        $nonce_action = ('archived' === $context) ? 'uma_bulk_archived' : 'uma_bulk_unused';
        $primary_label = ('archived' === $context) ? __('Restore Selected', 'unused-media-auditor') : __('Archive Selected', 'unused-media-auditor');
        $primary_value = ('archived' === $context) ? 'restore' : 'archive';
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field($nonce_action); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr($form_action); ?>">
            <div class="uma-toolbar">
                <div class="uma-toolbar__actions">
                    <span class="uma-toolbar__label"><?php esc_html_e('Selected items', 'unused-media-auditor'); ?></span>
                    <label class="screen-reader-text" for="uma-bulk-action-<?php echo esc_attr($context); ?>"><?php esc_html_e('Bulk action', 'unused-media-auditor'); ?></label>
                    <select id="uma-bulk-action-<?php echo esc_attr($context); ?>" name="bulk_action" class="uma-bulk-action">
                        <option value="<?php echo esc_attr($primary_value); ?>" selected="selected"><?php echo esc_html($primary_label); ?></option>
                        <option value="delete"><?php esc_html_e('Delete Permanently', 'unused-media-auditor'); ?></option>
                    </select>
                    <?php submit_button(__('Apply', 'unused-media-auditor'), 'primary', 'uma_apply_bulk_action', false, array('class' => 'button button-primary uma-apply-action')); ?>
                    <span class="uma-selection-summary" aria-live="polite"><?php esc_html_e('0 selected', 'unused-media-auditor'); ?></span>
                </div>
                <div class="uma-toolbar__utilities">
                    <label class="uma-select-all">
                        <input type="checkbox" class="uma-toggle-all">
                        <span><?php esc_html_e('Select all on page', 'unused-media-auditor'); ?></span>
                    </label>
                    <a href="<?php echo esc_url(add_query_arg(array('page' => ('archived' === $context) ? 'uma-archived-images' : 'uma-unused-images', 'uma_refresh' => 1), admin_url('upload.php'))); ?>" class="button"><?php esc_html_e('Refresh Scan', 'unused-media-auditor'); ?></a>
                </div>
            </div>
            <?php if (empty($items)) : ?>
                <div class="notice notice-info inline"><p><?php esc_html_e('No images found for this view.', 'unused-media-auditor'); ?></p></div>
            <?php else : ?>
                <div class="uma-grid">
                    <?php foreach ($items as $item) : ?>
                        <article class="uma-card">
                            <div class="uma-card__select">
                                <label>
                                    <input type="checkbox" name="attachment_ids[]" value="<?php echo esc_attr((string) $item['id']); ?>">
                                    <span><?php esc_html_e('Select item', 'unused-media-auditor'); ?></span>
                                </label>
                            </div>
                            <span class="uma-card__preview">
                                <img src="<?php echo esc_url((string) $item['thumbnail']); ?>" alt="">
                            </span>
                            <span class="uma-card__body">
                                <strong><?php echo esc_html((string) ($item['title'] ?: $item['filename'])); ?></strong>
                                <span><?php echo esc_html((string) $item['filename']); ?></span>
                                <span><?php echo esc_html((string) $item['date']); ?></span>
                                <?php if (! empty($item['archived_at'])) : ?>
                                    <span><?php echo esc_html(sprintf(__('Archived: %s', 'unused-media-auditor'), (string) $item['archived_at'])); ?></span>
                                <?php endif; ?>
                                <?php if (! empty($item['edit_link'])) : ?>
                                    <a href="<?php echo esc_url((string) $item['edit_link']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open attachment', 'unused-media-auditor'); ?></a>
                                <?php endif; ?>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>
        <?php
    }

    /**
     * @param string $page
     * @param string $type
     * @param string $message
     * @return void
     */
    private function redirect_with_notice($page, $type, $message)
    {
        $url = add_query_arg(array(
            'page' => $page,
            'uma_notice_type' => $type,
            'uma_notice_message' => $message,
        ), admin_url('upload.php'));

        wp_safe_redirect($url);
        exit;
    }

    private function render_notices()
    {
        if (empty($_GET['uma_notice_message'])) {
            return;
        }

        $type = isset($_GET['uma_notice_type']) ? sanitize_key(wp_unslash($_GET['uma_notice_type'])) : 'info';
        $message = sanitize_text_field(wp_unslash($_GET['uma_notice_message']));
        $allowed_classes = array(
            'warning' => 'notice notice-warning',
            'success' => 'notice notice-success',
            'info' => 'notice notice-info',
        );
        $class = isset($allowed_classes[$type]) ? $allowed_classes[$type] : $allowed_classes['info'];
        ?>
        <div class="<?php echo esc_attr($class); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}
