<?php

if (! defined('ABSPATH')) {
    exit;
}

class UMA_Archiver
{
    /**
     * @param int[] $attachment_ids
     * @return array<string, int>
     */
    public function archive_many($attachment_ids)
    {
        $result = array(
            'processed' => 0,
            'failed' => 0,
        );

        foreach ($attachment_ids as $attachment_id) {
            if ($this->archive_attachment((int) $attachment_id)) {
                $result['processed']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @param int[] $attachment_ids
     * @return array<string, int>
     */
    public function restore_many($attachment_ids)
    {
        $result = array(
            'processed' => 0,
            'failed' => 0,
        );

        foreach ($attachment_ids as $attachment_id) {
            if ($this->restore_attachment((int) $attachment_id)) {
                $result['processed']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @param int[] $attachment_ids
     * @return array<string, int>
     */
    public function delete_many($attachment_ids)
    {
        $result = array(
            'processed' => 0,
            'failed' => 0,
        );

        foreach ($attachment_ids as $attachment_id) {
            if ($this->delete_attachment((int) $attachment_id)) {
                $result['processed']++;
            } else {
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * @param int $attachment_id
     * @return bool
     */
    public function archive_attachment($attachment_id)
    {
        if (get_post_meta($attachment_id, UMA_ARCHIVE_META, true)) {
            return true;
        }

        $manifest = $this->build_manifest($attachment_id);

        if (empty($manifest['items'])) {
            return false;
        }

        if (! $this->validate_manifest_files($manifest['items'], 'source_abs', 'archive_abs')) {
            return false;
        }

        $moved_items = array();

        foreach ($manifest['items'] as $item) {
            $this->ensure_directory(dirname($item['archive_abs']));

            if (! @rename($item['source_abs'], $item['archive_abs'])) {
                $this->rollback_moves($moved_items, 'archive_abs', 'source_abs');
                return false;
            }

            $moved_items[] = $item;
        }

        update_post_meta($attachment_id, UMA_ARCHIVE_META, $manifest);
        update_post_meta($attachment_id, UMA_ARCHIVED_AT_META, current_time('mysql'));
        do_action('uma_archives_changed');

        return true;
    }

    /**
     * @param int $attachment_id
     * @return bool
     */
    public function restore_attachment($attachment_id)
    {
        $manifest = get_post_meta($attachment_id, UMA_ARCHIVE_META, true);

        if (! is_array($manifest) || empty($manifest['items'])) {
            return false;
        }

        if (! $this->validate_manifest_files($manifest['items'], 'archive_abs', 'source_abs')) {
            return false;
        }

        $moved_items = array();

        foreach ($manifest['items'] as $item) {
            $this->ensure_directory(dirname($item['source_abs']));

            if (! @rename($item['archive_abs'], $item['source_abs'])) {
                $this->rollback_moves($moved_items, 'source_abs', 'archive_abs');
                return false;
            }

            $moved_items[] = $item;
        }

        delete_post_meta($attachment_id, UMA_ARCHIVE_META);
        delete_post_meta($attachment_id, UMA_ARCHIVED_AT_META);
        do_action('uma_archives_changed');

        return true;
    }

    /**
     * @param int $attachment_id
     * @return bool
     */
    public function delete_attachment($attachment_id)
    {
        $manifest = get_post_meta($attachment_id, UMA_ARCHIVE_META, true);

        if (is_array($manifest) && ! empty($manifest['items'])) {
            foreach ($manifest['items'] as $item) {
                if (file_exists($item['archive_abs'])) {
                    wp_delete_file($item['archive_abs']);
                }
            }

            delete_post_meta($attachment_id, UMA_ARCHIVE_META);
            delete_post_meta($attachment_id, UMA_ARCHIVED_AT_META);
        }

        $deleted = wp_delete_attachment($attachment_id, true);

        if (false !== $deleted && null !== $deleted) {
            do_action('uma_archives_changed');
        }

        return false !== $deleted && null !== $deleted;
    }

    public function cleanup_expired_archives()
    {
        $retention_days = max(1, (int) get_option(UMA_OPTION_RETENTION_DAYS, 30));
        $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($retention_days * DAY_IN_SECONDS));

        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => UMA_ARCHIVED_AT_META,
                    'value' => $cutoff,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ),
            ),
            'fields' => 'ids',
        ));

        foreach ($attachments as $attachment_id) {
            $this->delete_attachment((int) $attachment_id);
        }
    }

    /**
     * @param int $attachment_id
     * @return array<string, mixed>
     */
    private function build_manifest($attachment_id)
    {
        $upload_dir = wp_get_upload_dir();
        $relative_file = get_post_meta($attachment_id, '_wp_attached_file', true);
        $metadata = wp_get_attachment_metadata($attachment_id);
        $items = array();
        $seen_relative_paths = array();

        if (! $relative_file) {
            return array('items' => array());
        }

        $this->append_manifest_item($items, $seen_relative_paths, $relative_file, $upload_dir);

        if (! empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $directory = pathinfo($relative_file, PATHINFO_DIRNAME);

            foreach ($metadata['sizes'] as $size_data) {
                if (empty($size_data['file'])) {
                    continue;
                }

                $relative_size = ('.' === $directory || '' === $directory)
                    ? $size_data['file']
                    : trailingslashit($directory) . $size_data['file'];

                $this->append_manifest_item($items, $seen_relative_paths, $relative_size, $upload_dir);
            }
        }

        if (! empty($metadata['original_image']) && is_string($metadata['original_image'])) {
            $directory = pathinfo($relative_file, PATHINFO_DIRNAME);
            $original_relative = ('.' === $directory || '' === $directory)
                ? $metadata['original_image']
                : trailingslashit($directory) . $metadata['original_image'];

            $this->append_manifest_item($items, $seen_relative_paths, $original_relative, $upload_dir);
        }

        if (! empty($metadata['backup_sizes']) && is_array($metadata['backup_sizes'])) {
            $directory = pathinfo($relative_file, PATHINFO_DIRNAME);

            foreach ($metadata['backup_sizes'] as $backup_size) {
                if (empty($backup_size['file']) || ! is_string($backup_size['file'])) {
                    continue;
                }

                $backup_relative = ('.' === $directory || '' === $directory)
                    ? $backup_size['file']
                    : trailingslashit($directory) . $backup_size['file'];

                $this->append_manifest_item($items, $seen_relative_paths, $backup_relative, $upload_dir);
            }
        }

        return array(
            'created_at' => current_time('mysql'),
            'items' => $items,
        );
    }

    /**
     * @param array<int, array<string, string>> $items
     * @param array<string, bool>               $seen_relative_paths
     * @param string                            $relative_path
     * @param array<string, string>             $upload_dir
     * @return void
     */
    private function append_manifest_item(&$items, &$seen_relative_paths, $relative_path, $upload_dir)
    {
        $item = $this->build_manifest_item($relative_path, $upload_dir);

        if (
            empty($item['source_rel']) ||
            isset($seen_relative_paths[$item['source_rel']]) ||
            empty($item['source_abs']) ||
            ! file_exists($item['source_abs'])
        ) {
            return;
        }

        $seen_relative_paths[$item['source_rel']] = true;
        $items[] = $item;
    }

    /**
     * @param string $relative_path
     * @param array<string, string> $upload_dir
     * @return array<string, string>
     */
    private function build_manifest_item($relative_path, $upload_dir)
    {
        $clean_relative_path = $this->normalize_relative_path($relative_path);

        if ('' === $clean_relative_path) {
            return array(
                'source_rel' => '',
                'source_abs' => '',
                'archive_rel' => '',
                'archive_abs' => '',
            );
        }

        $archive_relative_path = 'unused-media-auditor-archive/' . gmdate('Y/m/d') . '/' . $clean_relative_path;

        return array(
            'source_rel' => $clean_relative_path,
            'source_abs' => trailingslashit($upload_dir['basedir']) . $clean_relative_path,
            'archive_rel' => $archive_relative_path,
            'archive_abs' => trailingslashit($upload_dir['basedir']) . $archive_relative_path,
        );
    }

    /**
     * @param string $directory
     * @return void
     */
    private function ensure_directory($directory)
    {
        if (! file_exists($directory)) {
            wp_mkdir_p($directory);
        }
    }

    /**
     * @param string $relative_path
     * @return string
     */
    private function normalize_relative_path($relative_path)
    {
        $path = ltrim(wp_normalize_path((string) $relative_path), '/');

        if ('' === $path || false !== strpos($path, '../')) {
            return '';
        }

        return $path;
    }

    /**
     * @param array<int, array<string, string>> $items
     * @param string                            $from_key
     * @param string                            $to_key
     * @return bool
     */
    private function validate_manifest_files($items, $from_key, $to_key)
    {
        foreach ($items as $item) {
            if (
                empty($item[$from_key]) ||
                empty($item[$to_key]) ||
                ! file_exists($item[$from_key]) ||
                file_exists($item[$to_key])
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, string>> $items
     * @param string                            $from_key
     * @param string                            $to_key
     * @return void
     */
    private function rollback_moves($items, $from_key, $to_key)
    {
        foreach (array_reverse($items) as $item) {
            if (empty($item[$from_key]) || empty($item[$to_key]) || ! file_exists($item[$from_key])) {
                continue;
            }

            $this->ensure_directory(dirname($item[$to_key]));
            @rename($item[$from_key], $item[$to_key]);
        }
    }
}
