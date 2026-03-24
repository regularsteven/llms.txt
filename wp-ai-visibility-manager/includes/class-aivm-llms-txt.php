<?php
/**
 * llms.txt generation logic.
 */
class AIVM_Llms_Txt {

    public function register(): void {
        // Registration is handled by AIVM_Rewrite::handle_request().
    }

    /**
     * Serve the llms.txt response.
     */
    public function serve(array $settings): void {
        $cached = get_transient('wp_aivm_llms_txt_cache');

        if ($cached !== false) {
            $content = $cached;
        } else {
            $posts = $this->query_posts($settings);
            $content = $this->generate($settings, $posts);
            set_transient('wp_aivm_llms_txt_cache', $content, 12 * HOUR_IN_SECONDS);
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
     * Query posts for inclusion in llms.txt.
     *
     * @return object[]
     */
    public function query_posts(array $settings): array {
        $post_types = $settings['post_types'] ?? ['post', 'page'];
        $days = (int) ($settings['llms_txt_recency_days'] ?? 0);

        $args = [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
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

        // Convert IDs to minimal objects with post_type.
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
     * Generate the llms.txt content.
     *
     * @param array    $settings Plugin settings.
     * @param object[] $posts    Array of post objects with ID and post_type.
     * @return string
     */
    public function generate(array $settings, array $posts): string {
        $lines = [];

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
            $param_key   = $settings['markdown_param_key'] ?? 'format';
            $param_value = $settings['markdown_param_value'] ?? 'markdown';
            $markdown_param = '?' . $param_key . '=' . $param_value;

            $lines[] = '';
            $lines[] = '## Preferred Content Format';
            $lines[] = '';
            $lines[] = 'Markdown versions of all pages are recommended for efficient processing.';
            $lines[] = 'Append ' . $markdown_param . ' to any URL to receive clean Markdown output.';
        }

        // Group posts by type
        $grouped = $this->group_by_type($posts);

        // Pages section first, then Posts, then any custom post types
        $type_labels = [
            'page' => 'Pages',
            'post' => 'Posts',
        ];

        // Output pages first
        if (isset($grouped['page'])) {
            $lines[] = '';
            $lines[] = '## Pages';
            $lines[] = '';
            $lines = array_merge($lines, $this->format_entries($grouped['page'], $settings));
            unset($grouped['page']);
        }

        // Output posts second
        if (isset($grouped['post'])) {
            $lines[] = '';
            $lines[] = '## Posts';
            $lines[] = '';
            $lines = array_merge($lines, $this->format_entries($grouped['post'], $settings));
            unset($grouped['post']);
        }

        // Output any remaining CPTs
        foreach ($grouped as $type => $type_posts) {
            $label = ucfirst($type);
            $lines[] = '';
            $lines[] = '## ' . $label;
            $lines[] = '';
            $lines = array_merge($lines, $this->format_entries($type_posts, $settings));
        }

        // Notes section
        $notes = $settings['notes_section'] ?? '';
        if ($notes !== '') {
            $lines[] = '';
            $lines[] = '## Notes';
            $lines[] = '';
            $lines[] = wp_strip_all_tags($notes);
        }

        // Last updated timestamp
        if (!empty($settings['show_last_updated'])) {
            $lines[] = '';
            $lines[] = 'Last updated: ' . gmdate('c');
        }

        return implode("\n", $lines);
    }

    /**
     * Group posts by post_type.
     *
     * @param object[] $posts
     * @return array<string, object[]>
     */
    private function group_by_type(array $posts): array {
        $grouped = [];
        foreach ($posts as $post) {
            $grouped[$post->post_type][] = $post;
        }
        return $grouped;
    }

    /**
     * Format a list of posts as Markdown link entries.
     *
     * @param object[] $posts
     * @param array    $settings
     * @return string[]
     */
    private function format_entries(array $posts, array $settings): array {
        $lines = [];
        $use_markdown = !empty($settings['enable_markdown_endpoint']);
        $param_key    = $settings['markdown_param_key'] ?? 'format';
        $param_value  = $settings['markdown_param_value'] ?? 'markdown';

        foreach ($posts as $post) {
            $title   = get_the_title($post->ID);
            $url     = esc_url_raw(get_permalink($post->ID));
            $excerpt = get_post_field('post_excerpt', $post->ID);

            if ($use_markdown) {
                $url = add_query_arg($param_key, $param_value, $url);
            }

            $line = '- [' . $title . '](' . $url . ')';
            if (!empty($excerpt)) {
                $line .= ': ' . wp_strip_all_tags($excerpt);
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
