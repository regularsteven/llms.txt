<?php
/**
 * HTML head comment injection.
 */
class AIVM_Head_Comment {

    /**
     * Default comment template.
     */
    public const DEFAULT_TEMPLATE = <<<'TPL'
════════════════════════════════════════════
  AI AGENT NOTICE — {site_name}
════════════════════════════════════════════
  Clean Markdown available: append {markdown_param} to any URL
  Site index:  {llms_url}
  Full index:  {llms_full_url}
  This site supports token-efficient Markdown retrieval.
  Maintained by WP AI Visibility Manager.
════════════════════════════════════════════
TPL;

    public function register(): void {
        add_action('wp_head', [$this, 'inject_comment'], 1);
    }

    /**
     * Echo the rendered comment into wp_head.
     */
    public function inject_comment(): void {
        $settings = get_option('wp_aivm_settings', []);
        $output = $this->render($settings);

        if ($output !== '') {
            echo $output;
        }
    }

    /**
     * Render the head comment from settings.
     *
     * @param array $settings Plugin settings.
     * @return string The rendered HTML comment, or empty string if disabled/empty.
     */
    public function render(array $settings): string {
        if (empty($settings['enable_head_comment'])) {
            return '';
        }

        $template = $settings['head_comment_body'] ?? '';

        if (trim($template) === '') {
            return '';
        }

        // Step 1: Resolve tokens.
        $param_key   = $settings['markdown_param_key'] ?? 'format';
        $param_value = $settings['markdown_param_value'] ?? 'markdown';

        $tokens = [
            '{site_name}'      => get_bloginfo('name'),
            '{home_url}'       => home_url('/'),
            '{llms_url}'       => home_url('/llms.txt'),
            '{llms_full_url}'  => home_url('/llms-full.txt'),
            '{markdown_param}' => '?' . $param_key . '=' . $param_value,
        ];

        $body = str_replace(array_keys($tokens), array_values($tokens), $template);

        // Step 2: Normalise line endings to \n.
        $body = str_replace(["\r\n", "\r"], "\n", $body);

        // Step 3: Strip all occurrences of --> to prevent comment injection.
        $body = str_replace('-->', '', $body);

        // Step 4: Trim leading and trailing whitespace.
        $body = trim($body);

        // Step 5: Suppress output if result is empty.
        if ($body === '') {
            return '';
        }

        return "<!--\n" . $body . "\n-->\n";
    }
}
