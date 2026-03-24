<?php
/**
 * Plugin-generated Markdown output for singular posts and pages.
 *
 * When enabled, intercepts requests that include the configured format
 * parameter and serves a plugin-generated Markdown document instead of
 * the normal WordPress template, with no theme dependency.
 */
class AIVM_Auto_Md {

    public function register(): void {
        add_action('template_redirect', [$this, 'handle_request'], 1);
        add_action('save_post',   [$this, 'invalidate_post_cache']);
        add_action('delete_post', [$this, 'invalidate_post_cache']);
    }

    /**
     * Intercept the request and serve Markdown if conditions are met.
     */
    public function handle_request(): void {
        $settings = wp_parse_args(
            get_option('wp_aivm_settings', []),
            AIVM_Admin::get_defaults()
        );

        if (empty($settings['enable_auto_md'])) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        $param_key   = $settings['markdown_param_key']   ?? 'format';
        $param_value = $settings['markdown_param_value'] ?? 'markdown';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET[$param_key]) || $_GET[$param_key] !== $param_value) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $this->serve($post_id);
        exit;
    }

    /**
     * Serve the Markdown response for a single post, using transient cache.
     */
    public function serve(int $post_id): void {
        $cache_key = 'wp_aivm_md_' . $post_id;
        $cached    = get_transient($cache_key);

        if ($cached !== false) {
            $content = $cached;
        } else {
            $content = $this->generate($post_id);
            set_transient($cache_key, $content, 12 * HOUR_IN_SECONDS);
        }

        header('Content-Type: text/markdown; charset=utf-8');
        header('Cache-Control: public, max-age=43200');
        status_header(200);

        echo $content;
    }

    /**
     * Generate Markdown for a single post.
     */
    public function generate(int $post_id): string {
        $title = html_entity_decode(get_the_title($post_id), ENT_QUOTES | ENT_HTML5);
        $html  = apply_filters('the_content', get_post_field('post_content', $post_id));

        $converter = new AIVM_Html_To_Md();
        $body      = $converter->convert((string) $html);

        $lines = ['# ' . $title];

        if ($body !== '') {
            $lines[] = '';
            $lines[] = $body;
        }

        return implode("\n", $lines);
    }

    /**
     * Delete the cached Markdown for a post when it is saved or deleted.
     */
    public function invalidate_post_cache(int $post_id): void {
        delete_transient('wp_aivm_md_' . $post_id);
    }
}
