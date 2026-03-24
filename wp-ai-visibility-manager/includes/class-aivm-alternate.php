<?php
/**
 * <link rel="alternate"> injection and HTTP Link response header.
 *
 * Features E and F — share a toggle, URL generation, and singular-only condition.
 */
class AIVM_Alternate {

    public function register(): void {
        add_action('wp_head', [$this, 'inject_link_tag'], 999);
        add_action('template_redirect', [$this, 'send_link_header']);
    }

    /**
     * Inject <link rel="alternate" type="text/markdown"> into wp_head.
     * Only fires on singular views.
     */
    public function inject_link_tag(): void {
        if (!is_singular()) {
            return;
        }

        $settings = get_option('wp_aivm_settings', []);
        $output = $this->render_link_tag($settings);

        if ($output !== '') {
            echo $output;
        }
    }

    /**
     * Send HTTP Link response header on singular views.
     */
    public function send_link_header(): void {
        if (!is_singular()) {
            return;
        }

        $settings = get_option('wp_aivm_settings', []);
        $header_value = $this->build_link_header_value($settings);

        if ($header_value === '') {
            return;
        }

        if (!headers_sent()) {
            header($header_value, false);
        }
    }

    /**
     * Build the Markdown URL for the current post.
     */
    public function build_markdown_url(array $settings): string {
        $param_key   = $settings['markdown_param_key'] ?? 'format';
        $param_value = $settings['markdown_param_value'] ?? 'markdown';

        $url = esc_url_raw(get_permalink());
        return add_query_arg($param_key, $param_value, $url);
    }

    /**
     * Render the <link rel="alternate"> tag.
     *
     * @return string HTML tag or empty string.
     */
    public function render_link_tag(array $settings): string {
        if (empty($settings['enable_alternate_signals'])) {
            return '';
        }

        if (empty($settings['enable_markdown_endpoint'])) {
            return '';
        }

        $url = $this->build_markdown_url($settings);
        return '<link rel="alternate" type="text/markdown" href="' . esc_url($url) . '">' . "\n";
    }

    /**
     * Build the HTTP Link header value.
     *
     * @return string Full header string or empty string.
     */
    public function build_link_header_value(array $settings): string {
        if (empty($settings['enable_alternate_signals'])) {
            return '';
        }

        if (empty($settings['enable_markdown_endpoint'])) {
            return '';
        }

        $url = $this->build_markdown_url($settings);
        return 'Link: <' . $url . '>; rel="alternate"; type="text/markdown"';
    }
}
