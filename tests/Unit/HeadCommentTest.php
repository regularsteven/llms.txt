<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AIVM_Head_Comment;

class HeadCommentTest extends TestCase {

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
            'enable_head_comment'      => true,
            'head_comment_body'        => $this->default_template(),
            'enable_markdown_endpoint' => true,
            'markdown_param_key'       => 'format',
            'markdown_param_value'     => 'markdown',
        ];
    }

    private function default_template(): string {
        return <<<'TPL'
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
    }

    private function stub_common_functions(): void {
        Functions\stubs([
            'get_bloginfo' => function (string $show) {
                return match ($show) {
                    'name' => 'Test Site',
                    default => '',
                };
            },
            'home_url' => function (string $path = '/') {
                return 'https://example.com' . $path;
            },
        ]);
    }

    public function test_render_replaces_site_name_token(): void {
        $this->stub_common_functions();

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($this->default_settings());

        $this->assertStringContainsString('Test Site', $output);
        $this->assertStringNotContainsString('{site_name}', $output);
    }

    public function test_render_replaces_llms_url_token(): void {
        $this->stub_common_functions();

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($this->default_settings());

        $this->assertStringContainsString('https://example.com/llms.txt', $output);
        $this->assertStringNotContainsString('{llms_url}', $output);
    }

    public function test_render_replaces_llms_full_url_token(): void {
        $this->stub_common_functions();

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($this->default_settings());

        $this->assertStringContainsString('https://example.com/llms-full.txt', $output);
        $this->assertStringNotContainsString('{llms_full_url}', $output);
    }

    public function test_render_replaces_markdown_param_token(): void {
        $this->stub_common_functions();

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($this->default_settings());

        $this->assertStringContainsString('?format=markdown', $output);
        $this->assertStringNotContainsString('{markdown_param}', $output);
    }

    public function test_render_strips_comment_close_sequences(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['head_comment_body'] = 'Malicious --> injection attempt';

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($settings);

        // Extract the body between the comment delimiters.
        $body = str_replace(["<!--\n", "\n-->\n"], '', $output);
        $this->assertStringNotContainsString('-->', $body);
        $this->assertStringContainsString('Malicious  injection attempt', $body);
    }

    public function test_render_wraps_in_html_comment_delimiters(): void {
        $this->stub_common_functions();

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($this->default_settings());

        $this->assertStringStartsWith('<!--', $output);
        $this->assertStringEndsWith("-->\n", $output);
    }

    public function test_render_returns_empty_string_when_body_is_empty(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['head_comment_body'] = '';

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($settings);

        $this->assertSame('', $output);
    }

    public function test_render_returns_empty_string_when_body_is_only_whitespace(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['head_comment_body'] = "   \n\t  \n  ";

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($settings);

        $this->assertSame('', $output);
    }

    public function test_render_normalises_line_endings(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['head_comment_body'] = "Line one\r\nLine two\rLine three";

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($settings);

        $this->assertStringNotContainsString("\r", $output);
        $this->assertStringContainsString("Line one\nLine two\nLine three", $output);
    }

    public function test_render_returns_empty_when_disabled(): void {
        $this->stub_common_functions();

        $settings = $this->default_settings();
        $settings['enable_head_comment'] = false;

        $comment = new AIVM_Head_Comment();
        $output = $comment->render($settings);

        $this->assertSame('', $output);
    }
}
