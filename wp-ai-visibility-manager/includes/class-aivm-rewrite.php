<?php
/**
 * Rewrite rules, query vars, and request routing.
 */
class AIVM_Rewrite {

    public function register(): void {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'handle_request']);
    }

    /**
     * Add custom query vars for llms.txt and llms-full.txt.
     *
     * @param string[] $vars Existing query vars.
     * @return string[]
     */
    public function register_query_vars(array $vars): array {
        $vars[] = 'aivm_llms';
        $vars[] = 'aivm_llms_full';
        return $vars;
    }

    /**
     * Register rewrite rules for llms.txt and llms-full.txt.
     */
    public function register_rewrite_rules(): void {
        add_rewrite_rule('^llms\.txt$', 'index.php?aivm_llms=1', 'top');
        add_rewrite_rule('^llms-full\.txt$', 'index.php?aivm_llms_full=1', 'top');
    }

    /**
     * Handle requests for llms.txt and llms-full.txt.
     */
    public function handle_request(): void {
        $settings = get_option('wp_aivm_settings', []);

        if (get_query_var('aivm_llms')) {
            if (empty($settings['enable_llms_txt'])) {
                status_header(404);
                exit;
            }

            $llms_txt = new AIVM_Llms_Txt();
            $llms_txt->serve($settings);
            exit;
        }

        if (get_query_var('aivm_llms_full')) {
            if (empty($settings['enable_llms_full'])) {
                status_header(404);
                exit;
            }

            $llms_full = new AIVM_Llms_Full();
            $llms_full->serve($settings);
            exit;
        }
    }

    /**
     * Activation hook — register rules then flush.
     */
    public static function activate(): void {
        $instance = new self();
        $instance->register_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Deactivation hook — flush rewrite rules.
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
