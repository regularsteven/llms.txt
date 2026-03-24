<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AIVM_Alternate;

class AlternateTest extends TestCase {

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
            'enable_alternate_signals' => true,
            'enable_markdown_endpoint' => true,
            'markdown_param_key'       => 'format',
            'markdown_param_value'     => 'markdown',
        ];
    }

    private function stub_common_functions(): void {
        Functions\stubs([
            'get_permalink' => 'https://example.com/test-post/',
            'esc_url_raw'   => function (string $url) { return $url; },
            'esc_url'       => function (string $url) { return $url; },
            'add_query_arg' => function (string $key, string $value, string $url) {
                $sep = str_contains($url, '?') ? '&' : '?';
                return $url . $sep . $key . '=' . $value;
            },
        ]);
    }

    public function test_build_markdown_url_constructs_correct_url(): void {
        $this->stub_common_functions();

        $alternate = new AIVM_Alternate();
        $url = $alternate->build_markdown_url($this->default_settings());

        $this->assertSame('https://example.com/test-post/?format=markdown', $url);
    }

    public function test_build_markdown_url_uses_custom_param(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['markdown_param_key'] = 'output';
        $settings['markdown_param_value'] = 'md';

        $alternate = new AIVM_Alternate();
        $url = $alternate->build_markdown_url($settings);

        $this->assertSame('https://example.com/test-post/?output=md', $url);
    }

    public function test_render_link_tag_produces_correct_html(): void {
        $this->stub_common_functions();

        $alternate = new AIVM_Alternate();
        $output = $alternate->render_link_tag($this->default_settings());

        $expected = '<link rel="alternate" type="text/markdown" href="https://example.com/test-post/?format=markdown">' . "\n";
        $this->assertSame($expected, $output);
    }

    public function test_render_link_tag_returns_empty_when_alternate_disabled(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['enable_alternate_signals'] = false;

        $alternate = new AIVM_Alternate();
        $output = $alternate->render_link_tag($settings);

        $this->assertSame('', $output);
    }

    public function test_render_link_tag_returns_empty_when_markdown_endpoint_disabled(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['enable_markdown_endpoint'] = false;

        $alternate = new AIVM_Alternate();
        $output = $alternate->render_link_tag($settings);

        $this->assertSame('', $output);
    }

    public function test_build_link_header_value_produces_correct_format(): void {
        $this->stub_common_functions();

        $alternate = new AIVM_Alternate();
        $header = $alternate->build_link_header_value($this->default_settings());

        $expected = 'Link: <https://example.com/test-post/?format=markdown>; rel="alternate"; type="text/markdown"';
        $this->assertSame($expected, $header);
    }

    public function test_build_link_header_returns_empty_when_disabled(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['enable_alternate_signals'] = false;

        $alternate = new AIVM_Alternate();
        $header = $alternate->build_link_header_value($settings);

        $this->assertSame('', $header);
    }

    public function test_register_adds_hooks(): void {
        $alternate = new AIVM_Alternate();
        $alternate->register();

        $this->assertNotFalse(has_action('wp_head', 'AIVM_Alternate->inject_link_tag()'));
        $this->assertNotFalse(has_action('template_redirect', 'AIVM_Alternate->send_link_header()'));
    }
}
