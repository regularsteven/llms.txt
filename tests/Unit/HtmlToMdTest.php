<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use PHPUnit\Framework\TestCase;
use AIVM_Html_To_Md;

class HtmlToMdTest extends TestCase {

    private AIVM_Html_To_Md $converter;

    protected function setUp(): void {
        parent::setUp();
        $this->converter = new AIVM_Html_To_Md();
    }

    public function test_empty_input_returns_empty_string(): void {
        $this->assertSame('', $this->converter->convert(''));
        $this->assertSame('', $this->converter->convert('   '));
    }

    public function test_headings_are_converted(): void {
        $output = $this->converter->convert('<h1>Main Title</h1>');
        $this->assertStringContainsString('# Main Title', $output);

        $output = $this->converter->convert('<h2>Sub Heading</h2>');
        $this->assertStringContainsString('## Sub Heading', $output);

        $output = $this->converter->convert('<h3>Third</h3>');
        $this->assertStringContainsString('### Third', $output);
    }

    public function test_all_heading_levels_are_converted(): void {
        $html = '<h1>H1</h1><h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5><h6>H6</h6>';
        $output = $this->converter->convert($html);

        $this->assertStringContainsString('# H1', $output);
        $this->assertStringContainsString('## H2', $output);
        $this->assertStringContainsString('### H3', $output);
        $this->assertStringContainsString('#### H4', $output);
        $this->assertStringContainsString('##### H5', $output);
        $this->assertStringContainsString('###### H6', $output);
    }

    public function test_paragraph_is_rendered_as_plain_text(): void {
        $output = $this->converter->convert('<p>Hello world.</p>');
        $this->assertStringContainsString('Hello world.', $output);
        $this->assertStringNotContainsString('<p>', $output);
    }

    public function test_link_is_converted_to_markdown(): void {
        $output = $this->converter->convert('<a href="https://example.com">Click here</a>');
        $this->assertStringContainsString('[Click here](https://example.com)', $output);
    }

    public function test_link_with_empty_href_renders_text_only(): void {
        $output = $this->converter->convert('<a href="">Just text</a>');
        $this->assertStringContainsString('Just text', $output);
        $this->assertStringNotContainsString('](', $output);
    }

    public function test_image_is_converted_to_markdown(): void {
        $output = $this->converter->convert('<img src="photo.jpg" alt="A photo">');
        $this->assertStringContainsString('![A photo](photo.jpg)', $output);
    }

    public function test_image_without_src_is_omitted(): void {
        $output = $this->converter->convert('<img src="" alt="nothing">');
        $this->assertStringNotContainsString('![', $output);
    }

    public function test_strong_is_converted_to_bold(): void {
        $output = $this->converter->convert('<strong>Important</strong>');
        $this->assertStringContainsString('**Important**', $output);
    }

    public function test_em_is_converted_to_italic(): void {
        $output = $this->converter->convert('<em>Emphasis</em>');
        $this->assertStringContainsString('*Emphasis*', $output);
    }

    public function test_inline_code_is_backtick_wrapped(): void {
        $output = $this->converter->convert('<code>$var</code>');
        $this->assertStringContainsString('`$var`', $output);
    }

    public function test_pre_code_block_is_fenced(): void {
        $output = $this->converter->convert('<pre><code>echo "hello";</code></pre>');
        $this->assertStringContainsString('```', $output);
        $this->assertStringContainsString('echo "hello";', $output);
    }

    public function test_unordered_list_items_use_dash(): void {
        $output = $this->converter->convert('<ul><li>One</li><li>Two</li></ul>');
        $this->assertStringContainsString('- One', $output);
        $this->assertStringContainsString('- Two', $output);
    }

    public function test_ordered_list_items_are_numbered(): void {
        $output = $this->converter->convert('<ol><li>First</li><li>Second</li></ol>');
        $this->assertStringContainsString('1. First', $output);
        $this->assertStringContainsString('2. Second', $output);
    }

    public function test_blockquote_uses_gt_prefix(): void {
        $output = $this->converter->convert('<blockquote><p>A quote.</p></blockquote>');
        $this->assertStringContainsString('> ', $output);
        $this->assertStringContainsString('A quote.', $output);
    }

    public function test_script_tags_are_stripped(): void {
        $output = $this->converter->convert('<p>Text</p><script>alert("x")</script>');
        $this->assertStringNotContainsString('alert', $output);
        $this->assertStringContainsString('Text', $output);
    }

    public function test_style_tags_are_stripped(): void {
        $output = $this->converter->convert('<style>.foo{color:red}</style><p>Visible</p>');
        $this->assertStringNotContainsString('.foo', $output);
        $this->assertStringContainsString('Visible', $output);
    }

    public function test_nav_and_footer_are_stripped(): void {
        $output = $this->converter->convert('<nav><a href="/">Home</a></nav><p>Content</p><footer>Footer</footer>');
        $this->assertStringNotContainsString('Home', $output);
        $this->assertStringNotContainsString('Footer', $output);
        $this->assertStringContainsString('Content', $output);
    }

    public function test_div_is_unwrapped(): void {
        $output = $this->converter->convert('<div><p>Inside div</p></div>');
        $this->assertStringContainsString('Inside div', $output);
        $this->assertStringNotContainsString('<div>', $output);
    }

    public function test_html_tags_are_not_in_output(): void {
        $html   = '<h2>Title</h2><p>Paragraph with a <a href="https://x.com">link</a>.</p>';
        $output = $this->converter->convert($html);

        $this->assertStringNotContainsString('<h2>', $output);
        $this->assertStringNotContainsString('<p>', $output);
        $this->assertStringNotContainsString('<a ', $output);
    }

    public function test_multiple_newlines_are_collapsed(): void {
        $html   = '<p>One</p><p>Two</p><p>Three</p>';
        $output = $this->converter->convert($html);

        // Should not have more than 2 consecutive newlines.
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $output);
    }

    public function test_horizontal_rule_becomes_dashes(): void {
        $output = $this->converter->convert('<hr>');
        $this->assertStringContainsString('---', $output);
    }
}
