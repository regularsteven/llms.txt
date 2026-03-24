<?php
/**
 * llms-full.txt generation logic.
 */
class AIVM_Llms_Full {

    private const MAX_TOTAL_CHARS = 500000;

    public function register(): void {
        // Registration is handled by AIVM_Rewrite::handle_request().
    }

    /**
     * Serve the llms-full.txt response.
     */
    public function serve(array $settings): void {
        $cached = get_transient('wp_aivm_llms_full_txt_cache');

        if ($cached !== false) {
            $content = $cached;
        } else {
            $post_types = $this->get_post_types($settings);
            $max_posts  = (int) ($settings['llms_full_max_posts'] ?? 200);
            $days       = (int) ($settings['llms_full_recency_days'] ?? 0);

            $posts = $this->query_posts($post_types, $max_posts, $days);
            $content = $this->generate($settings, $posts, $max_posts, 0);

            set_transient('wp_aivm_llms_full_txt_cache', $content, 12 * HOUR_IN_SECONDS);
        }

        $last_modified = get_option('wp_aivm_llms_last_modified', time());

        header('Content-Type: text/plain; charset=utf-8');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) $last_modified) . ' GMT');
        header('ETag: "' . md5($content) . '"');
        header('Cache-Control: public, max-age=43200');
        status_header(200);

        echo $content;
    }

    /**
     * Determine which post types to use.
     *
     * @return string[]
     */
    public function get_post_types(array $settings): array {
        if (!empty($settings['llms_full_inherit_post_types'])) {
            return $settings['post_types'] ?? ['post', 'page'];
        }

        return $settings['llms_full_post_types'] ?? ['post', 'page'];
    }

    /**
     * Query posts for inclusion.
     *
     * @return object[]
     */
    public function query_posts(array $post_types, int $limit, int $days): array {
        $args = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'has_password'   => false,
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ];

        if ($days > 0) {
            $args['date_query'] = [[
                'after'     => $days . ' days ago',
                'inclusive' => true,
            ]];
        }

        $query = new \WP_Query($args);

        $posts = [];
        foreach ($query->posts as $post_id) {
            $posts[] = (object) [
                'ID'        => $post_id,
                'post_type' => get_post_type($post_id),
            ];
        }

        return $posts;
    }

    /**
     * Generate the llms-full.txt content.
     *
     * @param array    $settings Plugin settings.
     * @param object[] $posts    Array of post objects with ID and post_type.
     * @param int      $limit    Maximum number of entries.
     * @param int      $offset   Offset for pagination (future use).
     * @return string
     */
    public function generate(array $settings, array $posts, int $limit, int $offset): string {
        $lines = [];
        $truncation_limit = (int) ($settings['llms_full_truncation'] ?? 500);
        $include_alt_text = !empty($settings['llms_full_include_alt_text']);
        $use_markdown     = !empty($settings['enable_markdown_endpoint']);
        $param_key        = $settings['markdown_param_key'] ?? 'format';
        $param_value      = $settings['markdown_param_value'] ?? 'markdown';

        // Title
        $title = !empty($settings['site_title_override'])
            ? $settings['site_title_override']
            : get_bloginfo('name');
        $lines[] = '# ' . wp_strip_all_tags($title);

        // Description
        $description = $settings['site_description'] ?? '';
        if ($description !== '') {
            $lines[] = '';
            $lines[] = '> ' . wp_strip_all_tags($description);
        }

        // Body text
        $body = $settings['body_text'] ?? '';
        if ($body !== '') {
            $lines[] = '';
            $lines[] = wp_strip_all_tags($body);
        }

        // Preferred Content Format section
        if (!empty($settings['show_preferred_format'])) {
            $markdown_param = '?' . $param_key . '=' . $param_value;
            $lines[] = '';
            $lines[] = '## Preferred Content Format';
            $lines[] = '';
            $lines[] = 'Markdown versions of all pages are recommended for efficient processing.';
            $lines[] = 'Append ' . $markdown_param . ' to any URL to receive clean Markdown output.';
        }

        // Apply offset and limit to posts.
        $posts = array_slice($posts, $offset, $limit);

        // Group posts by type.
        $grouped = [];
        foreach ($posts as $post) {
            $grouped[$post->post_type][] = $post;
        }

        // Track total character count.
        $total_chars = strlen(implode("\n", $lines));
        $truncated = false;

        // Type labels and order.
        $type_order = ['page', 'post'];

        // Add known types first.
        foreach ($type_order as $type) {
            if (!isset($grouped[$type])) {
                continue;
            }

            $result = $this->format_full_entries(
                $grouped[$type], $settings, $truncation_limit,
                $include_alt_text, $use_markdown, $param_key, $param_value,
                $total_chars, self::MAX_TOTAL_CHARS
            );

            if (!empty($result['lines'])) {
                $label = $type === 'page' ? 'Pages' : 'Posts';
                $lines[] = '';
                $lines[] = '## ' . $label;
                $lines = array_merge($lines, $result['lines']);
                $total_chars = $result['total_chars'];
            }

            if ($result['truncated']) {
                $truncated = true;
            }

            unset($grouped[$type]);

            if ($truncated) {
                break;
            }
        }

        // Remaining CPTs.
        if (!$truncated) {
            foreach ($grouped as $type => $type_posts) {
                $result = $this->format_full_entries(
                    $type_posts, $settings, $truncation_limit,
                    $include_alt_text, $use_markdown, $param_key, $param_value,
                    $total_chars, self::MAX_TOTAL_CHARS
                );

                if (!empty($result['lines'])) {
                    $lines[] = '';
                    $lines[] = '## ' . ucfirst($type);
                    $lines = array_merge($lines, $result['lines']);
                    $total_chars = $result['total_chars'];
                }

                if ($result['truncated']) {
                    $truncated = true;
                    break;
                }
            }
        }

        // Truncation notice.
        if ($truncated) {
            $lines[] = '';
            $lines[] = '## Notice';
            $lines[] = '';
            $lines[] = 'Output truncated. Reduce maximum post count or per-entry character limit to include all entries.';
        }

        // Notes section.
        $notes = $settings['notes_section'] ?? '';
        if ($notes !== '') {
            $lines[] = '';
            $lines[] = '## Notes';
            $lines[] = '';
            $lines[] = wp_strip_all_tags($notes);
        }

        // Last updated timestamp.
        if (!empty($settings['show_last_updated'])) {
            $lines[] = '';
            $lines[] = 'Last updated: ' . gmdate('c');
        }

        return implode("\n", $lines);
    }

    /**
     * Format entries with expanded content blocks.
     *
     * @return array{lines: string[], total_chars: int, truncated: bool}
     */
    private function format_full_entries(
        array $posts,
        array $settings,
        int $truncation_limit,
        bool $include_alt_text,
        bool $use_markdown,
        string $param_key,
        string $param_value,
        int $total_chars,
        int $max_total
    ): array {
        $lines             = [];
        $truncated         = false;
        $excluded_raw      = $settings['excluded_urls'] ?? '';
        $excluded_patterns = $excluded_raw !== ''
            ? array_filter(array_map('trim', explode("\n", $excluded_raw)))
            : [];

        foreach ($posts as $post) {
            $title   = html_entity_decode(get_the_title($post->ID), ENT_QUOTES | ENT_HTML5);
            $url     = esc_url_raw(get_permalink($post->ID));

            if ($excluded_patterns && $this->is_url_excluded($url, $excluded_patterns)) {
                continue;
            }
            $excerpt = get_post_field('post_excerpt', $post->ID);

            if ($use_markdown) {
                $url = add_query_arg($param_key, $param_value, $url);
            }

            // Get content for expanded block.
            $content = '';
            if (!empty($excerpt)) {
                $content = wp_strip_all_tags($excerpt);
            } else {
                $raw_content = get_post_field('post_content', $post->ID);
                $content = wp_strip_all_tags($raw_content);
            }

            // Truncate content.
            if (mb_strlen($content) > $truncation_limit) {
                $content = mb_substr($content, 0, $truncation_limit) . '...';
            }

            // Build entry lines.
            $entry_lines = [];
            $entry_lines[] = '';
            $entry_lines[] = '- [' . $title . '](' . $url . ')';

            if (!empty($content)) {
                $entry_lines[] = '  ' . $content;
            }

            // Featured image alt text.
            if ($include_alt_text) {
                $thumbnail_id = get_post_thumbnail_id($post->ID);
                if ($thumbnail_id) {
                    $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                    if (!empty($alt_text)) {
                        $entry_lines[] = '  Featured image: ' . wp_strip_all_tags($alt_text);
                    }
                }
            }

            $entry_text = implode("\n", $entry_lines);
            $entry_chars = strlen($entry_text);

            if ($total_chars + $entry_chars > $max_total) {
                $truncated = true;
                break;
            }

            $lines = array_merge($lines, $entry_lines);
            $total_chars += $entry_chars;
        }

        return [
            'lines'       => $lines,
            'total_chars' => $total_chars,
            'truncated'   => $truncated,
        ];
    }

    /**
     * Check if a URL matches any of the given exclusion patterns.
     * Supports * wildcards (fnmatch-style).
     */
    private function is_url_excluded(string $url, array $patterns): bool {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $url)) {
                return true;
            }
        }
        return false;
    }
}
