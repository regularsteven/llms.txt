<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AIVM_Llms_Full;

class LlmsFullTest extends TestCase {

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
            'enable_llms_full'             => true,
            'site_title_override'          => '',
            'site_description'             => 'A test site',
            'body_text'                    => '',
            'notes_section'                => '',
            'post_types'                   => ['post', 'page'],
            'llms_full_inherit_post_types' => true,
            'llms_full_post_types'         => ['post', 'page'],
            'llms_full_truncation'         => 500,
            'llms_full_max_posts'          => 200,
            'llms_full_recency_days'       => 0,
            'llms_full_include_alt_text'   => true,
            'show_preferred_format'        => true,
            'show_last_updated'            => true,
            'enable_markdown_endpoint'     => true,
            'markdown_param_key'           => 'format',
            'markdown_param_value'         => 'markdown',
        ];
    }

    private function stub_common_functions(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'esc_url_raw'       => function (string $url) { return $url; },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'          => function ($id) { return "Post $id"; },
            'get_permalink'          => function ($id) { return "https://example.com/post-$id/"; },
            'get_post_field'         => function ($field, $id) {
                if ($field === 'post_excerpt') return "Excerpt for post $id.";
                if ($field === 'post_content') return str_repeat("Content for post $id. ", 100);
                return '';
            },
            'get_post_thumbnail_id'  => 0,
            'wp_get_attachment_image_url' => false,
            'get_post_meta'          => '',
        ]);
    }

    public function test_generate_starts_with_site_title(): void {
        $this->stub_common_functions();

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($this->default_settings(), [], 200, 0);

        $lines = explode("\n", $output);
        $this->assertSame('# Test Site', $lines[0]);
    }

    public function test_generate_includes_content_block_per_entry(): void {
        $this->stub_common_functions();

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'post'],
        ];

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($this->default_settings(), $posts, 200, 0);

        $this->assertStringContainsString('- [Post 1]', $output);
        $this->assertStringContainsString('Excerpt for post 1.', $output);
    }

    public function test_generate_truncates_content_at_limit(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'esc_url_raw'       => function (string $url) { return $url; },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'          => 'Long Post',
            'get_permalink'          => 'https://example.com/long-post/',
            'get_post_field'         => function ($field, $id) {
                if ($field === 'post_excerpt') return '';
                if ($field === 'post_content') return str_repeat('A', 1000);
                return '';
            },
            'get_post_thumbnail_id'  => 0,
        ]);

        $settings = $this->default_settings();
        $settings['llms_full_truncation'] = 50;

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'post'],
        ];

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($settings, $posts, 200, 0);

        // The content block should be truncated to 50 chars + ellipsis.
        $this->assertStringContainsString(str_repeat('A', 50) . '...', $output);
        $this->assertStringNotContainsString(str_repeat('A', 51), $output);
    }

    public function test_generate_falls_back_to_content_when_no_excerpt(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'esc_url_raw'       => function (string $url) { return $url; },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'          => 'No Excerpt Post',
            'get_permalink'          => 'https://example.com/no-excerpt/',
            'get_post_field'         => function ($field, $id) {
                if ($field === 'post_excerpt') return '';
                if ($field === 'post_content') return 'Fallback content here.';
                return '';
            },
            'get_post_thumbnail_id'  => 0,
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'post'],
        ];

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($this->default_settings(), $posts, 200, 0);

        $this->assertStringContainsString('Fallback content here.', $output);
    }

    public function test_generate_caps_total_output_at_500k_chars(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'esc_url_raw'       => function (string $url) { return $url; },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'          => function ($id) { return "Post $id"; },
            'get_permalink'          => function ($id) { return "https://example.com/post-$id/"; },
            'get_post_field'         => function ($field, $id) {
                if ($field === 'post_excerpt') return str_repeat('X', 5000);
                return '';
            },
            'get_post_thumbnail_id'  => 0,
        ]);

        // Create enough posts to exceed 500k chars.
        $posts = [];
        for ($i = 1; $i <= 200; $i++) {
            $posts[] = (object) ['ID' => $i, 'post_type' => 'post'];
        }

        $settings = $this->default_settings();
        $settings['llms_full_truncation'] = 5000;

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($settings, $posts, 200, 0);

        $this->assertLessThanOrEqual(510000, strlen($output)); // Some overhead for headers/structure.
        $this->assertStringContainsString('## Notice', $output);
        $this->assertStringContainsString('Output truncated', $output);
    }

    public function test_generate_respects_limit_and_offset(): void {
        $this->stub_common_functions();

        $posts = [];
        for ($i = 1; $i <= 10; $i++) {
            $posts[] = (object) ['ID' => $i, 'post_type' => 'post'];
        }

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($this->default_settings(), $posts, 3, 0);

        // Should only include 3 posts.
        $this->assertStringContainsString('Post 1', $output);
        $this->assertStringContainsString('Post 3', $output);
        $this->assertStringNotContainsString('Post 4', $output);
    }

    public function test_generate_includes_alt_text_when_enabled(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'esc_url_raw'       => function (string $url) { return $url; },
            'add_query_arg'     => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
            'get_the_title'          => 'Alt Text Post',
            'get_permalink'          => 'https://example.com/alt-post/',
            'get_post_field'         => function ($field, $id) {
                if ($field === 'post_excerpt') return 'An excerpt.';
                return '';
            },
            'get_post_thumbnail_id'  => 42,
            'get_post_meta'          => function ($id, $key, $single) {
                if ($key === '_wp_attachment_image_alt') return 'A descriptive alt text';
                return '';
            },
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'post'],
        ];

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($this->default_settings(), $posts, 200, 0);

        $this->assertStringContainsString('Featured image: A descriptive alt text', $output);
    }

    public function test_generate_inherits_post_types_from_section_a(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['llms_full_inherit_post_types'] = true;
        $settings['post_types'] = ['page'];

        $generator = new AIVM_Llms_Full();
        $effective_types = $generator->get_post_types($settings);

        $this->assertSame(['page'], $effective_types);
    }

    public function test_generate_uses_own_post_types_when_not_inherited(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['llms_full_inherit_post_types'] = false;
        $settings['llms_full_post_types'] = ['post'];

        $generator = new AIVM_Llms_Full();
        $effective_types = $generator->get_post_types($settings);

        $this->assertSame(['post'], $effective_types);
    }

    public function test_generate_decodes_html_entities_in_titles(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'esc_url_raw'       => function (string $url) { return $url; },
            'add_query_arg'     => function ($key, $val, $url) {
                return $url . '?' . $key . '=' . $val;
            },
            'get_the_title'          => function ($id) { return 'Venue &#038; Parties'; },
            'get_permalink'          => function ($id) { return 'https://example.com/venue/'; },
            'get_post_field'         => function ($field, $id) { return ''; },
            'get_post_thumbnail_id'  => 0,
            'get_post_meta'          => '',
        ]);

        $posts = [(object) ['ID' => 1, 'post_type' => 'page']];

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($this->default_settings(), $posts, 200, 0);

        $this->assertStringContainsString('[Venue & Parties]', $output);
        $this->assertStringNotContainsString('&#038;', $output);
    }

    public function test_generate_excludes_urls_matching_wildcard_pattern(): void {
        Functions\stubs([
            'get_bloginfo'      => 'Test Site',
            'home_url'          => 'https://example.com/',
            'wp_strip_all_tags' => function (string $text) { return strip_tags($text); },
            'esc_url_raw'       => function (string $url) { return $url; },
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
            'get_post_field'         => function ($field, $id) { return ''; },
            'get_post_thumbnail_id'  => 0,
            'get_post_meta'          => '',
        ]);

        $posts = [
            (object) ['ID' => 1, 'post_type' => 'page'],
            (object) ['ID' => 2, 'post_type' => 'page'],
        ];

        $settings = $this->default_settings();
        $settings['excluded_urls'] = '*/my-account/*';

        $generator = new AIVM_Llms_Full();
        $output = $generator->generate($settings, $posts, 200, 0);

        $this->assertStringNotContainsString('Account Page', $output);
        $this->assertStringContainsString('Normal Page', $output);
    }
}
