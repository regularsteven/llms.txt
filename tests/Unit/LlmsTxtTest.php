<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AIVM_Llms_Txt;

class LlmsTxtTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function default_settings(): array {
        return [
            'enable_llms_txt'             => true,
            'site_title_override'         => '',
            'site_description'            => 'A test site description',
            'body_text'                   => '',
            'notes_section'               => '',
            'post_types'                  => ['post', 'page'],
            'show_preferred_format'       => true,
            'show_last_updated'           => true,
            'enable_markdown_endpoint'    => true,
            'markdown_param_key'          => 'format',
            'markdown_param_value'        => 'markdown',
            'llms_txt_recency_days'       => 0,
        ];
    }

    private function stub_common_functions(): void {
        Functions\stubs([
            'get_bloginfo'   => function (string $show) {
                return match ($show) {
                    'name' => 'Test Site',
                    default => '',
                };
            },
            'home_url'       => function (string $path = '/') {
                return 'https://example.com' . $path;
            },
            'get_the_title'  => 'Test Post Title',
            'get_permalink'  => 'https://example.com/test-post/',
            'esc_url_raw'    => function (string $url) { return $url; },
            'get_post_field' => '',
            'wp_strip_all_tags' => function (string $text) {
                return strip_tags($text);
            },
            'add_query_arg'  => function (string $key, string $value, string $url) {
                $separator = str_contains($url, '?') ? '&' : '?';
                return $url . $separator . $key . '=' . $value;
            },
        ]);
    }

    public function test_generate_starts_with_site_title_heading(): void {
        $this->stub_common_functions();

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($this->default_settings(), []);

        $lines = explode("\n", $output);
        $this->assertSame('# Test Site', $lines[0]);
    }

    public function test_generate_uses_title_override_when_set(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['site_title_override'] = 'Custom Title';

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, []);

        $lines = explode("\n", $output);
        $this->assertSame('# Custom Title', $lines[0]);
    }

    public function test_generate_includes_site_description_as_blockquote(): void {
        $this->stub_common_functions();

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($this->default_settings(), []);

        $this->assertStringContainsString('> A test site description', $output);
    }

    public function test_generate_includes_body_text(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['body_text'] = 'This is body text.';

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, []);

        $this->assertStringContainsString('This is body text.', $output);
    }

    public function test_generate_includes_notes_section(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['notes_section'] = 'Some notes here.';

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, []);

        $this->assertStringContainsString('## Notes', $output);
        $this->assertStringContainsString('Some notes here.', $output);
    }

    public function test_generate_omits_notes_section_when_empty(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['notes_section'] = '';

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, []);

        $this->assertStringNotContainsString('## Notes', $output);
    }

    public function test_generate_includes_preferred_format_section(): void {
        $this->stub_common_functions();

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($this->default_settings(), []);

        $this->assertStringContainsString('## Preferred Content Format', $output);
        $this->assertStringContainsString('?format=markdown', $output);
    }

    public function test_generate_omits_preferred_format_when_disabled(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['show_preferred_format'] = false;

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, []);

        $this->assertStringNotContainsString('## Preferred Content Format', $output);
    }

    public function test_generate_includes_last_updated_timestamp(): void {
        $this->stub_common_functions();

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($this->default_settings(), []);

        $this->assertMatchesRegularExpression('/Last updated: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $output);
    }

    public function test_generate_omits_timestamp_when_disabled(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['show_last_updated'] = false;

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, []);

        $this->assertStringNotContainsString('Last updated:', $output);
    }

    public function test_generate_lists_posts_with_markdown_url(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'  => function ($id) { return 'My Post'; },
            'get_permalink'  => function ($id) { return 'https://example.com/my-post/'; },
            'esc_url_raw'    => function (string $url) { return $url; },
            'get_post_field' => function ($field, $id) { return 'An excerpt.'; },
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'post'],
        ];

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($this->default_settings(), $posts);

        $this->assertStringContainsString('- [My Post](https://example.com/my-post/?format=markdown): An excerpt.', $output);
    }

    public function test_generate_lists_posts_without_markdown_param_when_disabled(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'  => function ($id) { return 'My Post'; },
            'get_permalink'  => function ($id) { return 'https://example.com/my-post/'; },
            'esc_url_raw'    => function (string $url) { return $url; },
            'get_post_field' => function ($field, $id) { return ''; },
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'post'],
        ];

        $settings = $this->default_settings();
        $settings['enable_markdown_endpoint'] = false;
        $settings['show_preferred_format'] = false;

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, $posts);

        $this->assertStringContainsString('- [My Post](https://example.com/my-post/)', $output);
        $this->assertStringNotContainsString('?format=markdown', $output);
    }

    public function test_generate_separates_pages_and_posts_into_sections(): void {
        $titles = [1 => 'About', 2 => 'Hello World'];
        $urls = [1 => 'https://example.com/about/', 2 => 'https://example.com/hello-world/'];

        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'  => function ($id) use ($titles) { return $titles[$id] ?? 'Untitled'; },
            'get_permalink'  => function ($id) use ($urls) { return $urls[$id] ?? 'https://example.com/'; },
            'esc_url_raw'    => function (string $url) { return $url; },
            'get_post_field' => function ($field, $id) { return ''; },
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'page'],
            (object) ['ID' => 2, 'post_type' => 'post'],
        ];

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($this->default_settings(), $posts);

        $this->assertStringContainsString('## Pages', $output);
        $this->assertStringContainsString('## Posts', $output);

        // Pages section should come before Posts section
        $pagesPos = strpos($output, '## Pages');
        $postsPos = strpos($output, '## Posts');
        $this->assertLessThan($postsPos, $pagesPos);
    }

    public function test_generate_strips_html_tags_from_output(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['body_text'] = 'Text with <strong>HTML</strong> tags';

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, []);

        $this->assertStringNotContainsString('<strong>', $output);
        $this->assertStringContainsString('Text with HTML tags', $output);
    }

    public function test_generate_decodes_html_entities_in_titles(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'add_query_arg'     => function ($key, $val, $url) {
                return $url . '?' . $key . '=' . $val;
            },
            'get_the_title'     => function ($id) { return 'Venue &#038; Parties'; },
            'get_permalink'     => function ($id) { return 'https://example.com/venue/'; },
            'esc_url_raw'       => function (string $url) { return $url; },
            'get_post_field'    => function ($field, $id) { return ''; },
        ]);

        $posts = [(object) ['ID' => 1, 'post_type' => 'page']];

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($this->default_settings(), $posts);

        $this->assertStringContainsString('[Venue & Parties]', $output);
        $this->assertStringNotContainsString('&#038;', $output);
    }

    public function test_generate_excludes_urls_matching_wildcard_pattern(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'add_query_arg'     => function ($key, $val, $url) {
                return $url . '?' . $key . '=' . $val;
            },
            'get_the_title'     => function ($id) {
                return $id === 1 ? 'Account Page' : 'Normal Page';
            },
            'get_permalink'     => function ($id) {
                return $id === 1
                    ? 'https://example.com/my-account/orders/'
                    : 'https://example.com/about/';
            },
            'esc_url_raw'       => function (string $url) { return $url; },
            'get_post_field'    => function ($field, $id) { return ''; },
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'page'],
            (object) ['ID' => 2, 'post_type' => 'page'],
        ];

        $settings = $this->default_settings();
        $settings['excluded_urls'] = '*/my-account/*';

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, $posts);

        $this->assertStringNotContainsString('Account Page', $output);
        $this->assertStringContainsString('Normal Page', $output);
    }

    public function test_generate_excludes_multiple_url_patterns(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'add_query_arg'     => function ($key, $val, $url) {
                return $url . '?' . $key . '=' . $val;
            },
            'get_the_title'     => function ($id) {
                return match ($id) {
                    1 => 'Account Page',
                    2 => 'Checkout Page',
                    default => 'Normal Page',
                };
            },
            'get_permalink'     => function ($id) {
                return match ($id) {
                    1 => 'https://example.com/my-account/orders/',
                    2 => 'https://example.com/checkout/',
                    default => 'https://example.com/about/',
                };
            },
            'esc_url_raw'       => function (string $url) { return $url; },
            'get_post_field'    => function ($field, $id) { return ''; },
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'page'],
            (object) ['ID' => 2, 'post_type' => 'page'],
            (object) ['ID' => 3, 'post_type' => 'page'],
        ];

        $settings = $this->default_settings();
        $settings['excluded_urls'] = "*/my-account/*\n*/checkout/*";

        $generator = new AIVM_Llms_Txt();
        $output = $generator->generate($settings, $posts);

        $this->assertStringNotContainsString('Account Page', $output);
        $this->assertStringNotContainsString('Checkout Page', $output);
        $this->assertStringContainsString('Normal Page', $output);
    }
}
