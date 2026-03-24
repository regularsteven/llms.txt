<?php
/**
 * Admin page, Settings API registration, notices.
 */
class AIVM_Admin {

    private const OPTION_KEY  = 'wp_aivm_settings';
    private const NONCE_KEY   = 'wp_aivm_settings';
    private const FLUSH_NONCE = 'wp_aivm_flush_cache';

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_wp_aivm_flush_cache', [$this, 'handle_flush_cache']);

        // Cache invalidation hooks.
        $invalidation_hooks = [
            'save_post',
            'delete_post',
            'trash_post',
            'untrash_post',
            'transition_post_status',
            'clean_post_cache',
            'switch_theme',
        ];

        foreach ($invalidation_hooks as $hook) {
            add_action($hook, [$this, 'invalidate_cache']);
        }

        add_action('update_option_' . self::OPTION_KEY, [$this, 'invalidate_cache']);
    }

    /**
     * Add the submenu page under Tools.
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'tools.php',
            __('WP AI Visibility Manager', 'wp-aivm'),
            __('AI Visibility', 'wp-aivm'),
            'manage_options',
            'wp-aivm',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings with the Settings API.
     */
    public function register_settings(): void {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    /**
     * Enqueue admin CSS only on our settings page.
     */
    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'tools_page_wp-aivm') {
            return;
        }

        wp_enqueue_style(
            'wp-aivm-admin',
            AIVM_PLUGIN_URL . 'assets/admin.css',
            [],
            AIVM_VERSION
        );
    }

    /**
     * Sanitize settings on save.
     */
    public function sanitize_settings(array $input): array {
        $sanitized = [];

        // Checkboxes (booleans).
        $checkboxes = [
            'enable_llms_txt',
            'enable_llms_full',
            'enable_head_comment',
            'enable_markdown_endpoint',
            'enable_alternate_signals',
            'enable_auto_md',
            'show_preferred_format',
            'show_last_updated',
            'llms_full_inherit_post_types',
            'llms_full_include_alt_text',
        ];

        foreach ($checkboxes as $key) {
            $sanitized[$key] = !empty($input[$key]);
        }

        // Text inputs.
        $text_fields = [
            'site_title_override',
            'markdown_param_key',
            'markdown_param_value',
        ];

        foreach ($text_fields as $key) {
            $sanitized[$key] = sanitize_text_field($input[$key] ?? '');
        }

        // URL exclusion textarea (one pattern per line — plain text, no HTML).
        $sanitized['excluded_urls'] = sanitize_textarea_field($input['excluded_urls'] ?? '');

        // Textareas (preserved with wp_kses_post for Markdown).
        $textarea_fields = [
            'site_description',
            'body_text',
            'notes_section',
            'head_comment_body',
        ];

        foreach ($textarea_fields as $key) {
            $sanitized[$key] = wp_kses_post($input[$key] ?? '');
        }

        // Post types (array of strings).
        $sanitized['post_types'] = array_map('sanitize_key', (array) ($input['post_types'] ?? ['post', 'page']));
        $sanitized['llms_full_post_types'] = array_map('sanitize_key', (array) ($input['llms_full_post_types'] ?? ['post', 'page']));

        // Numeric fields.
        $sanitized['llms_txt_recency_days']    = absint($input['llms_txt_recency_days'] ?? 0);
        $sanitized['llms_full_recency_days']   = absint($input['llms_full_recency_days'] ?? 0);
        $sanitized['llms_full_truncation']     = absint($input['llms_full_truncation'] ?? 500);
        $sanitized['llms_full_max_posts']      = absint($input['llms_full_max_posts'] ?? 200);

        return $sanitized;
    }

    /**
     * Invalidate both transient caches and update last-modified timestamp.
     */
    public function invalidate_cache(): void {
        delete_transient('wp_aivm_llms_txt_cache');
        delete_transient('wp_aivm_llms_full_txt_cache');
        update_option('wp_aivm_llms_last_modified', time());
    }

    /**
     * Handle the flush cache admin-post action.
     */
    public function handle_flush_cache(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'wp-aivm'));
        }

        check_admin_referer(self::FLUSH_NONCE);

        $this->invalidate_cache();

        wp_safe_redirect(add_query_arg([
            'page'    => 'wp-aivm',
            'flushed' => '1',
        ], admin_url('tools.php')));
        exit;
    }

    /**
     * Get default settings.
     */
    public static function get_defaults(): array {
        return [
            'enable_llms_txt'              => true,
            'enable_llms_full'             => false,
            'enable_head_comment'          => true,
            'enable_markdown_endpoint'     => true,
            'enable_alternate_signals'     => true,
            'enable_auto_md'               => false,
            'show_preferred_format'        => true,
            'show_last_updated'            => true,
            'site_title_override'          => '',
            'site_description'             => '',
            'body_text'                    => '',
            'notes_section'                => '',
            'excluded_urls'                => '',
            'head_comment_body'            => AIVM_Head_Comment::DEFAULT_TEMPLATE,
            'post_types'                   => ['post', 'page'],
            'markdown_param_key'           => 'format',
            'markdown_param_value'         => 'markdown',
            'llms_txt_recency_days'        => 0,
            'llms_full_inherit_post_types' => true,
            'llms_full_post_types'         => ['post', 'page'],
            'llms_full_truncation'         => 500,
            'llms_full_max_posts'          => 200,
            'llms_full_recency_days'       => 0,
            'llms_full_include_alt_text'   => true,
        ];
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = wp_parse_args(
            get_option(self::OPTION_KEY, []),
            self::get_defaults()
        );

        // Check for physical file conflicts.
        $conflicts = [];
        foreach (['llms.txt', 'llms-full.txt'] as $file) {
            if (file_exists(ABSPATH . $file)) {
                $conflicts[] = $file;
            }
        }

        // Check for success/flush notices.
        $flushed = isset($_GET['flushed']);
        $saved   = isset($_GET['settings-updated']);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="aivm-summary-box">
                <p><strong><?php esc_html_e('What this plugin does', 'wp-aivm'); ?></strong></p>
                <p><?php esc_html_e('WP AI Visibility Manager helps AI systems discover your content, access clean Markdown versions, and reduce processing cost and ambiguity. It does this using a combination of standard web signals and advisory files — giving AI agents multiple ways to find the most efficient path to your content.', 'wp-aivm'); ?></p>
            </div>

            <div class="notice notice-info">
                <p><strong><?php esc_html_e('Important:', 'wp-aivm'); ?></strong> <?php esc_html_e('AI crawlers are not required to follow these signals. This plugin improves discoverability and efficiency but does not guarantee crawler behaviour.', 'wp-aivm'); ?></p>
            </div>

            <?php if (!empty($conflicts)) : ?>
                <div class="notice notice-error">
                    <p><strong><?php esc_html_e('File conflict detected:', 'wp-aivm'); ?></strong>
                    <?php
                    printf(
                        esc_html__('Physical file(s) %s exist in your web root and will prevent the plugin from serving dynamic content. Remove or rename them.', 'wp-aivm'),
                        '<code>' . esc_html(implode('</code>, <code>', $conflicts)) . '</code>'
                    );
                    ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($flushed) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cache flushed successfully.', 'wp-aivm'); ?></p></div>
            <?php endif; ?>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'wp-aivm'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_KEY); ?>

                <!-- Section A: llms.txt Settings -->
                <h2><?php esc_html_e('llms.txt Settings', 'wp-aivm'); ?></h2>
                <p><?php esc_html_e('Generate a machine-readable index of your site content. AI agents can read this file to understand what content is available and where to find it.', 'wp-aivm'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable llms.txt', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_llms_txt]" value="1" <?php checked($settings['enable_llms_txt']); ?> class="aivm-master-toggle" data-section="aivm-section-a">
                                <?php esc_html_e('Serve llms.txt at /llms.txt', 'wp-aivm'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><label for="aivm-site-title"><?php esc_html_e('Site Title Override', 'wp-aivm'); ?></label></th>
                        <td>
                            <input type="text" id="aivm-site-title" name="<?php echo esc_attr(self::OPTION_KEY); ?>[site_title_override]" value="<?php echo esc_attr($settings['site_title_override']); ?>" class="regular-text" <?php disabled(!$settings['enable_llms_txt']); ?>>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[site_title_override]" value="<?php echo esc_attr($settings['site_title_override']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><label for="aivm-site-desc"><?php esc_html_e('Site Description', 'wp-aivm'); ?></label></th>
                        <td>
                            <textarea id="aivm-site-desc" name="<?php echo esc_attr(self::OPTION_KEY); ?>[site_description]" rows="3" class="large-text" <?php disabled(!$settings['enable_llms_txt']); ?>><?php echo esc_textarea($settings['site_description']); ?></textarea>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[site_description]" value="<?php echo esc_attr($settings['site_description']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><label for="aivm-body-text"><?php esc_html_e('Body Text', 'wp-aivm'); ?></label></th>
                        <td>
                            <textarea id="aivm-body-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[body_text]" rows="4" class="large-text" <?php disabled(!$settings['enable_llms_txt']); ?>><?php echo esc_textarea($settings['body_text']); ?></textarea>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[body_text]" value="<?php echo esc_attr($settings['body_text']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><label for="aivm-notes"><?php esc_html_e('Notes Section', 'wp-aivm'); ?></label></th>
                        <td>
                            <textarea id="aivm-notes" name="<?php echo esc_attr(self::OPTION_KEY); ?>[notes_section]" rows="3" class="large-text" <?php disabled(!$settings['enable_llms_txt']); ?>><?php echo esc_textarea($settings['notes_section']); ?></textarea>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[notes_section]" value="<?php echo esc_attr($settings['notes_section']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><?php esc_html_e('Post Types to Include', 'wp-aivm'); ?></th>
                        <td>
                            <?php
                            $post_types = get_post_types(['public' => true], 'objects');
                            foreach ($post_types as $pt) :
                                if ($pt->name === 'attachment') continue;
                            ?>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[post_types][]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $settings['post_types'], true)); ?> <?php disabled(!$settings['enable_llms_txt']); ?>>
                                    <?php echo esc_html($pt->label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><label for="aivm-excluded-urls"><?php esc_html_e('URL Exclusions', 'wp-aivm'); ?></label></th>
                        <td>
                            <textarea id="aivm-excluded-urls" name="<?php echo esc_attr(self::OPTION_KEY); ?>[excluded_urls]" rows="5" class="large-text code" <?php disabled(!$settings['enable_llms_txt']); ?>><?php echo esc_textarea($settings['excluded_urls']); ?></textarea>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[excluded_urls]" value="<?php echo esc_attr($settings['excluded_urls']); ?>"><?php endif; ?>
                            <p class="description"><?php esc_html_e('One URL pattern per line. Use * as a wildcard. Example: */my-account/* excludes any URL containing /my-account/. Applied to both llms.txt and llms-full.txt.', 'wp-aivm'); ?></p>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><?php esc_html_e('Show "Preferred Content Format" section', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_preferred_format]" value="1" <?php checked($settings['show_preferred_format']); ?> <?php disabled(!$settings['enable_llms_txt']); ?>>
                            </label>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_preferred_format]" value="<?php echo esc_attr($settings['show_preferred_format']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><?php esc_html_e('Show "Last updated" timestamp', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_last_updated]" value="1" <?php checked($settings['show_last_updated']); ?> <?php disabled(!$settings['enable_llms_txt']); ?>>
                            </label>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[show_last_updated]" value="<?php echo esc_attr($settings['show_last_updated']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-a">
                        <th scope="row"><label for="aivm-recency"><?php esc_html_e('Limit to posts from last N days', 'wp-aivm'); ?></label></th>
                        <td>
                            <input type="number" id="aivm-recency" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_txt_recency_days]" value="<?php echo esc_attr($settings['llms_txt_recency_days']); ?>" min="0" class="small-text" <?php disabled(!$settings['enable_llms_txt']); ?>>
                            <p class="description"><?php esc_html_e('0 = no date filter applied.', 'wp-aivm'); ?></p>
                            <?php if (!$settings['enable_llms_txt']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_txt_recency_days]" value="<?php echo esc_attr($settings['llms_txt_recency_days']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- Section B: llms-full.txt Settings -->
                <h2><?php esc_html_e('llms-full.txt Settings', 'wp-aivm'); ?></h2>
                <p><?php esc_html_e('Serve an expanded version of the index with content snippets for each entry. Useful for AI systems that want a preview before fetching full pages.', 'wp-aivm'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable llms-full.txt', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_llms_full]" value="1" <?php checked($settings['enable_llms_full']); ?> class="aivm-master-toggle" data-section="aivm-section-b">
                                <?php esc_html_e('Serve llms-full.txt at /llms-full.txt', 'wp-aivm'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="aivm-section-b">
                        <th scope="row"><?php esc_html_e('Inherit Post Types from llms.txt', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_inherit_post_types]" value="1" <?php checked($settings['llms_full_inherit_post_types']); ?> <?php disabled(!$settings['enable_llms_full']); ?>>
                                <?php esc_html_e('Use the same post types as llms.txt', 'wp-aivm'); ?>
                            </label>
                            <?php if (!$settings['enable_llms_full']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_inherit_post_types]" value="<?php echo esc_attr($settings['llms_full_inherit_post_types']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-b">
                        <th scope="row"><label for="aivm-full-truncation"><?php esc_html_e('Content Truncation Limit', 'wp-aivm'); ?></label></th>
                        <td>
                            <input type="number" id="aivm-full-truncation" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_truncation]" value="<?php echo esc_attr($settings['llms_full_truncation']); ?>" min="50" class="small-text" <?php disabled(!$settings['enable_llms_full']); ?>>
                            <p class="description"><?php esc_html_e('Characters per entry.', 'wp-aivm'); ?></p>
                            <?php if (!$settings['enable_llms_full']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_truncation]" value="<?php echo esc_attr($settings['llms_full_truncation']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-b">
                        <th scope="row"><label for="aivm-full-max-posts"><?php esc_html_e('Maximum Posts', 'wp-aivm'); ?></label></th>
                        <td>
                            <input type="number" id="aivm-full-max-posts" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_max_posts]" value="<?php echo esc_attr($settings['llms_full_max_posts']); ?>" min="1" class="small-text" <?php disabled(!$settings['enable_llms_full']); ?>>
                            <?php if (!$settings['enable_llms_full']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_max_posts]" value="<?php echo esc_attr($settings['llms_full_max_posts']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-b">
                        <th scope="row"><label for="aivm-full-recency"><?php esc_html_e('Limit to posts from last N days', 'wp-aivm'); ?></label></th>
                        <td>
                            <input type="number" id="aivm-full-recency" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_recency_days]" value="<?php echo esc_attr($settings['llms_full_recency_days']); ?>" min="0" class="small-text" <?php disabled(!$settings['enable_llms_full']); ?>>
                            <p class="description"><?php esc_html_e('0 = no date filter applied.', 'wp-aivm'); ?></p>
                            <?php if (!$settings['enable_llms_full']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_recency_days]" value="<?php echo esc_attr($settings['llms_full_recency_days']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-b">
                        <th scope="row"><?php esc_html_e('Include Featured Image Alt Text', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_include_alt_text]" value="1" <?php checked($settings['llms_full_include_alt_text']); ?> <?php disabled(!$settings['enable_llms_full']); ?>>
                                <?php esc_html_e('Append featured image alt text to each entry', 'wp-aivm'); ?>
                            </label>
                            <?php if (!$settings['enable_llms_full']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[llms_full_include_alt_text]" value="<?php echo esc_attr($settings['llms_full_include_alt_text']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                </table>

                <!-- Section C: HTML Head Comment Settings -->
                <h2><?php esc_html_e('HTML Head Comment', 'wp-aivm'); ?></h2>
                <p><?php esc_html_e('Inject a comment block into the HTML <head> on all public pages. AI agents that parse HTML will see this immediately.', 'wp-aivm'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Head Comment', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_head_comment]" value="1" <?php checked($settings['enable_head_comment']); ?> class="aivm-master-toggle" data-section="aivm-section-c">
                                <?php esc_html_e('Inject comment into <head>', 'wp-aivm'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="aivm-section-c">
                        <th scope="row"><label for="aivm-comment-body"><?php esc_html_e('Comment Body', 'wp-aivm'); ?></label></th>
                        <td>
                            <textarea id="aivm-comment-body" name="<?php echo esc_attr(self::OPTION_KEY); ?>[head_comment_body]" rows="10" class="large-text code" <?php disabled(!$settings['enable_head_comment']); ?>><?php echo esc_textarea($settings['head_comment_body']); ?></textarea>
                            <?php if (!$settings['enable_head_comment']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[head_comment_body]" value="<?php echo esc_attr($settings['head_comment_body']); ?>"><?php endif; ?>
                            <p class="description">
                                <strong><?php esc_html_e('Available tokens:', 'wp-aivm'); ?></strong>
                                <code>{site_name}</code>, <code>{home_url}</code>, <code>{llms_url}</code>, <code>{llms_full_url}</code>, <code>{markdown_param}</code><br>
                                <?php esc_html_e('Replaced with live values at render time. Do not add HTML comment delimiters — the plugin wraps the output automatically.', 'wp-aivm'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Section D+E: Markdown Endpoint & Alternate Signals -->
                <h2><?php esc_html_e('Markdown Endpoint & Alternate Format Signals', 'wp-aivm'); ?></h2>
                <p><?php esc_html_e('Control how the plugin advertises Markdown versions of your content. The endpoint parameter is appended to URLs in llms.txt and llms-full.txt. The alternate signals inject a <link> tag and HTTP header on individual posts and pages.', 'wp-aivm'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Advertise Markdown Endpoint', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_markdown_endpoint]" value="1" <?php checked($settings['enable_markdown_endpoint']); ?> class="aivm-master-toggle" data-section="aivm-section-de">
                                <?php esc_html_e('Append Markdown parameter to URLs in llms.txt files', 'wp-aivm'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr class="aivm-section-de">
                        <th scope="row"><label for="aivm-param-key"><?php esc_html_e('Markdown Parameter Key', 'wp-aivm'); ?></label></th>
                        <td>
                            <input type="text" id="aivm-param-key" name="<?php echo esc_attr(self::OPTION_KEY); ?>[markdown_param_key]" value="<?php echo esc_attr($settings['markdown_param_key']); ?>" class="regular-text" <?php disabled(!$settings['enable_markdown_endpoint']); ?>>
                            <?php if (!$settings['enable_markdown_endpoint']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[markdown_param_key]" value="<?php echo esc_attr($settings['markdown_param_key']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="aivm-section-de">
                        <th scope="row"><label for="aivm-param-value"><?php esc_html_e('Markdown Parameter Value', 'wp-aivm'); ?></label></th>
                        <td>
                            <input type="text" id="aivm-param-value" name="<?php echo esc_attr(self::OPTION_KEY); ?>[markdown_param_value]" value="<?php echo esc_attr($settings['markdown_param_value']); ?>" class="regular-text" <?php disabled(!$settings['enable_markdown_endpoint']); ?>>
                            <?php if (!$settings['enable_markdown_endpoint']) : ?><input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[markdown_param_value]" value="<?php echo esc_attr($settings['markdown_param_value']); ?>"><?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Alternate Format Signals', 'wp-aivm'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_alternate_signals]" value="1" <?php checked($settings['enable_alternate_signals']); ?>>
                                <?php esc_html_e('Inject <link rel="alternate"> tag and HTTP Link header on singular pages', 'wp-aivm'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <div class="aivm-guidance-box">
                    <p><strong><?php esc_html_e('Markdown endpoint quality guidance', 'wp-aivm'); ?></strong></p>
                    <p><?php esc_html_e('For best AI compatibility, your Markdown endpoint should: remove navigation, ads, and boilerplate; use a clear heading hierarchy (H1–H3); preserve semantic structure; avoid inline scripts or styles; and return consistent structure across all pages. The signals this plugin emits are only as useful as the quality of the endpoint they point to.', 'wp-aivm'); ?></p>
                </div>

                <?php submit_button(__('Save Settings', 'wp-aivm')); ?>
            </form>

            <!-- Cache Flush -->
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="wp_aivm_flush_cache">
                <?php wp_nonce_field(self::FLUSH_NONCE); ?>
                <?php submit_button(__('Flush Cache', 'wp-aivm'), 'secondary'); ?>
            </form>

            <!-- Section F: Auto Markdown Generation -->
            <h2><?php esc_html_e('Auto Markdown Generation', 'wp-aivm'); ?></h2>
            <p><?php esc_html_e('When enabled, the plugin intercepts requests with the configured Markdown parameter and serves its own Markdown conversion of the post content — no theme support required. Works on all singular posts and pages.', 'wp-aivm'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Auto MD', 'wp-aivm'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_auto_md]" value="1" <?php checked($settings['enable_auto_md']); ?>>
                            <?php esc_html_e('Generate and serve Markdown directly from the plugin', 'wp-aivm'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Tip: set the Markdown Parameter Value above to "md" to use clean ?format=md URLs when Auto MD is enabled.', 'wp-aivm'); ?></p>
                    </td>
                </tr>
            </table>

            <!-- Robots.txt guidance -->
            <div class="aivm-guidance-box">
                <p><strong><?php esc_html_e('robots.txt', 'wp-aivm'); ?></strong></p>
                <p><?php
                printf(
                    esc_html__('You may wish to reference llms.txt in your robots.txt file. See %s for details.', 'wp-aivm'),
                    '<a href="https://llmstxt.org" target="_blank" rel="noopener">llmstxt.org</a>'
                );
                ?></p>
            </div>
        </div>
        <?php
    }
}
