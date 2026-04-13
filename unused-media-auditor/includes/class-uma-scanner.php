<?php

if (! defined('ABSPATH')) {
    exit;
}

class UMA_Scanner
{
    const UNUSED_CACHE_KEY = 'uma_unused_images_cache';
    const ARCHIVED_CACHE_KEY = 'uma_archived_images_cache';

    /**
     * @var array<string, string>|null
     */
    private $reference_haystacks = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_unused_images()
    {
        $cached = get_transient(self::UNUSED_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $attachments = $this->get_image_attachments(false);
        $unused = array();

        foreach ($attachments as $attachment) {
            if (! $this->is_attachment_used($attachment)) {
                $unused[] = $this->build_attachment_payload($attachment, false);
            }
        }

        set_transient(self::UNUSED_CACHE_KEY, $unused, 10 * MINUTE_IN_SECONDS);

        return $unused;
    }

    /**
     * @param int $attachment_id
     * @return bool
     */
    public function is_attachment_unused($attachment_id)
    {
        $attachment = get_post((int) $attachment_id);

        if (! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image($attachment_id)) {
            return false;
        }

        if ($this->is_attachment_archived($attachment_id)) {
            return false;
        }

        return ! $this->is_attachment_used($attachment);
    }

    /**
     * @param int $attachment_id
     * @return bool
     */
    public function is_attachment_archived($attachment_id)
    {
        return (bool) get_post_meta((int) $attachment_id, UMA_ARCHIVE_META, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_archived_images()
    {
        $cached = get_transient(self::ARCHIVED_CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $attachments = $this->get_image_attachments(true);
        $archived = array();

        foreach ($attachments as $attachment) {
            $archived[] = $this->build_attachment_payload($attachment, true);
        }

        set_transient(self::ARCHIVED_CACHE_KEY, $archived, 10 * MINUTE_IN_SECONDS);

        return $archived;
    }

    /**
     * @return void
     */
    public function flush_cache()
    {
        delete_transient(self::UNUSED_CACHE_KEY);
        delete_transient(self::ARCHIVED_CACHE_KEY);
        $this->reference_haystacks = null;
    }

    /**
     * @param bool $archived_only
     * @return array<int, WP_Post>
     */
    private function get_image_attachments($archived_only)
    {
        $meta_query = array();

        if ($archived_only) {
            $meta_query[] = array(
                'key' => UMA_ARCHIVE_META,
                'compare' => 'EXISTS',
            );
        } else {
            $meta_query[] = array(
                'key' => UMA_ARCHIVE_META,
                'compare' => 'NOT EXISTS',
            );
        }

        return get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => $meta_query,
            'post_mime_type' => 'image',
            'suppress_filters' => false,
        ));
    }

    /**
     * @param WP_Post $attachment
     * @return bool
     */
    private function is_attachment_used($attachment)
    {
        global $wpdb;

        $attachment_id = (int) $attachment->ID;
        $attachment_url = wp_get_attachment_url($attachment_id);
        $relative_file = get_post_meta($attachment_id, '_wp_attached_file', true);

        if ($attachment->post_parent && get_post($attachment->post_parent)) {
            return true;
        }

        $featured_match = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_thumbnail_id' AND meta_value = %d
                LIMIT 1",
                $attachment_id
            )
        );

        if ($featured_match > 0) {
            return true;
        }

        $needles = array_filter(array_unique(array_merge(
            array(
                'wp-image-' . $attachment_id,
                $attachment->guid,
                $attachment_url,
                $relative_file,
            ),
            $this->build_generated_file_needles($attachment_id, $relative_file)
        )));

        foreach ($needles as $needle) {
            if ($this->value_exists_anywhere($needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $attachment_id
     * @param string $relative_file
     * @return array<int, string>
     */
    private function build_generated_file_needles($attachment_id, $relative_file)
    {
        $metadata = wp_get_attachment_metadata($attachment_id);
        $needles = array();
        $directory = '';

        if ($relative_file) {
            $directory = trailingslashit(pathinfo($relative_file, PATHINFO_DIRNAME));

            if ('./' === $directory) {
                $directory = '';
            }
        }

        if (! empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_data) {
                if (empty($size_data['file'])) {
                    continue;
                }

                $this->append_generated_file_needles($needles, $directory . $size_data['file']);
            }
        }

        if (! empty($metadata['original_image']) && is_string($metadata['original_image'])) {
            $this->append_generated_file_needles($needles, $directory . $metadata['original_image']);
        }

        if (! empty($metadata['backup_sizes']) && is_array($metadata['backup_sizes'])) {
            foreach ($metadata['backup_sizes'] as $backup_size) {
                if (empty($backup_size['file']) || ! is_string($backup_size['file'])) {
                    continue;
                }

                $this->append_generated_file_needles($needles, $directory . $backup_size['file']);
            }
        }

        return array_values(array_unique(array_filter($needles)));
    }

    /**
     * @param array<int, string> $needles
     * @param string             $relative_path
     * @return void
     */
    private function append_generated_file_needles(&$needles, $relative_path)
    {
        $relative_path = ltrim((string) $relative_path, '/');

        if ('' === $relative_path) {
            return;
        }

        $upload_dir = wp_get_upload_dir();

        $needles[] = $relative_path;
        $needles[] = trailingslashit($upload_dir['baseurl']) . $relative_path;
    }

    /**
     * @param string $needle
     * @return bool
     */
    private function value_exists_anywhere($needle)
    {
        $needle = (string) $needle;

        if ('' === trim($needle)) {
            return false;
        }

        foreach ($this->get_reference_haystacks() as $haystack) {
            if ('' !== $haystack && false !== strpos($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param WP_Post $attachment
     * @param bool $archived
     * @return array<string, mixed>
     */
    private function build_attachment_payload($attachment, $archived)
    {
        $attachment_id = (int) $attachment->ID;
        $manifest = get_post_meta($attachment_id, UMA_ARCHIVE_META, true);
        $thumbnail = wp_get_attachment_image_src($attachment_id, 'medium');
        $attached_file = get_attached_file($attachment_id);
        $stored_relative_file = get_post_meta($attachment_id, '_wp_attached_file', true);

        if ($archived && is_array($manifest) && ! empty($manifest['items'][0]['archive_rel'])) {
            $thumbnail = array($this->build_archive_url($manifest['items'][0]['archive_rel']), 300, 300);
        }

        return array(
            'id' => $attachment_id,
            'title' => get_the_title($attachment_id),
            'filename' => $attached_file ? wp_basename($attached_file) : wp_basename($stored_relative_file),
            'date' => get_the_date(get_option('date_format'), $attachment_id),
            'thumbnail' => $thumbnail ? $thumbnail[0] : wp_mime_type_icon($attachment_id),
            'edit_link' => get_edit_post_link($attachment_id, ''),
            'archived_at' => $archived ? get_post_meta($attachment_id, UMA_ARCHIVED_AT_META, true) : '',
        );
    }

    /**
     * @param string $archive_rel
     * @return string
     */
    private function build_archive_url($archive_rel)
    {
        $upload_dir = wp_get_upload_dir();

        return trailingslashit($upload_dir['baseurl']) . ltrim($archive_rel, '/');
    }

    /**
     * @return array<string, string>
     */
    private function get_reference_haystacks()
    {
        global $wpdb;

        if (null !== $this->reference_haystacks) {
            return $this->reference_haystacks;
        }

        $like_fragments = array('%wp-image-%', '%/uploads/%', '%uploads/%');
        $params = $like_fragments;

        $posts = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts}
                WHERE post_type <> 'attachment'
                    AND (" . implode(' OR ', array(
                        'post_content LIKE %s',
                        'post_content LIKE %s',
                        'post_content LIKE %s',
                    )) . ')',
                $params
            )
        );

        $postmeta = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT pm.meta_value
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type <> 'attachment'
                    AND pm.meta_key NOT IN ('" . esc_sql(UMA_ARCHIVE_META) . "', '" . esc_sql(UMA_ARCHIVED_AT_META) . "')
                    AND (" . implode(' OR ', array(
                        'pm.meta_value LIKE %s',
                        'pm.meta_value LIKE %s',
                        'pm.meta_value LIKE %s',
                    )) . ')',
                $params
            )
        );

        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options}
                WHERE option_name NOT LIKE 'uma_%'
                    AND (" . implode(' OR ', array(
                        'option_value LIKE %s',
                        'option_value LIKE %s',
                        'option_value LIKE %s',
                    )) . ')',
                $params
            )
        );

        $termmeta = array();

        if (! empty($wpdb->termmeta)) {
            $termmeta = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->termmeta}
                    WHERE " . implode(' OR ', array(
                        'meta_value LIKE %s',
                        'meta_value LIKE %s',
                        'meta_value LIKE %s',
                    )),
                    $params
                )
            );
        }

        $usermeta = array();

        if (! empty($wpdb->usermeta)) {
            $usermeta = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->usermeta}
                    WHERE " . implode(' OR ', array(
                        'meta_value LIKE %s',
                        'meta_value LIKE %s',
                        'meta_value LIKE %s',
                    )),
                    $params
                )
            );
        }

        $this->reference_haystacks = array(
            'posts' => $this->collapse_haystack($posts),
            'postmeta' => $this->collapse_haystack($postmeta),
            'options' => $this->collapse_haystack($options),
            'termmeta' => $this->collapse_haystack($termmeta),
            'usermeta' => $this->collapse_haystack($usermeta),
        );

        return $this->reference_haystacks;
    }

    /**
     * @param array<int, mixed> $values
     * @return string
     */
    private function collapse_haystack($values)
    {
        $values = array_filter(array_map('strval', $values));

        return implode("\n", $values);
    }
}
