<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AIVM_Auto_Md;

class AutoMdTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_generate_produces_markdown_heading_from_title(): void {
        Functions\stubs([
            'get_the_title'   => function ($id) { return 'My Post Title'; },
            'get_post_field'  => function ($field, $id) { return '<p>Some content.</p>'; },
            'apply_filters'   => function ($tag, $value) { return $value; },
        ]);

        $auto_md = new AIVM_Auto_Md();
        $output  = $auto_md->generate(1);

        $this->assertStringStartsWith('# My Post Title', $output);
    }

    public function test_generate_decodes_html_entities_in_title(): void {
        Functions\stubs([
            'get_the_title'   => function ($id) { return 'Venue &#038; Parties'; },
            'get_post_field'  => function ($field, $id) { return ''; },
            'apply_filters'   => function ($tag, $value) { return $value; },
        ]);

        $auto_md = new AIVM_Auto_Md();
        $output  = $auto_md->generate(1);

        $this->assertStringContainsString('# Venue & Parties', $output);
        $this->assertStringNotContainsString('&#038;', $output);
    }

    public function test_generate_converts_html_body_to_markdown(): void {
        Functions\stubs([
            'get_the_title'   => function ($id) { return 'Test Page'; },
            'get_post_field'  => function ($field, $id) {
                return '<h2>Section</h2><p>A paragraph.</p>';
            },
            'apply_filters'   => function ($tag, $value) { return $value; },
        ]);

        $auto_md = new AIVM_Auto_Md();
        $output  = $auto_md->generate(1);

        $this->assertStringContainsString('## Section', $output);
        $this->assertStringContainsString('A paragraph.', $output);
        $this->assertStringNotContainsString('<h2>', $output);
        $this->assertStringNotContainsString('<p>', $output);
    }

    public function test_generate_handles_empty_post_content(): void {
        Functions\stubs([
            'get_the_title'   => function ($id) { return 'Empty Post'; },
            'get_post_field'  => function ($field, $id) { return ''; },
            'apply_filters'   => function ($tag, $value) { return $value; },
        ]);

        $auto_md = new AIVM_Auto_Md();
        $output  = $auto_md->generate(1);

        $this->assertSame('# Empty Post', $output);
    }

    public function test_serve_uses_transient_cache(): void {
        Functions\stubs([
            'get_transient' => function ($key) { return '# Cached Content'; },
            'status_header' => null,
        ]);
        Functions\expect('set_transient')->never();

        $auto_md = new AIVM_Auto_Md();

        ob_start();
        $auto_md->serve(1);
        $output = ob_get_clean();

        $this->assertSame('# Cached Content', $output);
    }

    public function test_serve_generates_and_caches_when_no_cache(): void {
        Functions\stubs([
            'get_transient'  => false,
            'get_the_title'  => function ($id) { return 'Fresh Post'; },
            'get_post_field' => function ($field, $id) { return ''; },
            'apply_filters'  => function ($tag, $value) { return $value; },
            'status_header'  => null,
        ]);
        Functions\expect('set_transient')
            ->once()
            ->with('wp_aivm_md_5', \Mockery::any(), \Mockery::any());

        $auto_md = new AIVM_Auto_Md();

        ob_start();
        $auto_md->serve(5);
        $output = ob_get_clean();

        $this->assertStringContainsString('# Fresh Post', $output);
    }

    public function test_invalidate_post_cache_deletes_transient(): void {
        Functions\expect('delete_transient')
            ->once()
            ->with('wp_aivm_md_42');

        $auto_md = new AIVM_Auto_Md();
        $auto_md->invalidate_post_cache(42);

        $this->assertTrue(true);
    }
}
