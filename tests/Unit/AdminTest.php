<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AIVM_Admin;

class AdminTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_admin_hooks(): void {
        $admin = new AIVM_Admin();
        $admin->register();

        $this->assertNotFalse(has_action('admin_menu', 'AIVM_Admin->add_menu_page()'));
        $this->assertNotFalse(has_action('admin_init', 'AIVM_Admin->register_settings()'));
        $this->assertNotFalse(has_action('admin_enqueue_scripts', 'AIVM_Admin->enqueue_admin_assets()'));
        $this->assertNotFalse(has_action('admin_post_wp_aivm_flush_cache', 'AIVM_Admin->handle_flush_cache()'));
    }

    public function test_register_adds_cache_invalidation_hooks(): void {
        $admin = new AIVM_Admin();
        $admin->register();

        $this->assertNotFalse(has_action('save_post', 'AIVM_Admin->invalidate_cache()'));
        $this->assertNotFalse(has_action('delete_post', 'AIVM_Admin->invalidate_cache()'));
        $this->assertNotFalse(has_action('trash_post', 'AIVM_Admin->invalidate_cache()'));
        $this->assertNotFalse(has_action('untrash_post', 'AIVM_Admin->invalidate_cache()'));
        $this->assertNotFalse(has_action('transition_post_status', 'AIVM_Admin->invalidate_cache()'));
        $this->assertNotFalse(has_action('clean_post_cache', 'AIVM_Admin->invalidate_cache()'));
        $this->assertNotFalse(has_action('switch_theme', 'AIVM_Admin->invalidate_cache()'));
    }

    public function test_sanitize_settings_sanitizes_checkboxes(): void {
        Functions\stubs([
            'sanitize_text_field' => function ($val) { return $val; },
            'wp_kses_post'        => function ($val) { return $val; },
            'sanitize_key'        => function ($val) { return $val; },
            'absint'              => function ($val) { return abs((int) $val); },
        ]);

        $admin = new AIVM_Admin();

        $input = [
            'enable_llms_txt'    => '1',
            'enable_head_comment' => '',
        ];

        $result = $admin->sanitize_settings($input);

        $this->assertTrue($result['enable_llms_txt']);
        $this->assertFalse($result['enable_head_comment']);
    }

    public function test_sanitize_settings_sanitizes_text_fields(): void {
        Functions\stubs([
            'sanitize_text_field' => function ($val) { return trim(strip_tags($val)); },
            'wp_kses_post'        => function ($val) { return $val; },
            'sanitize_key'        => function ($val) { return $val; },
            'absint'              => function ($val) { return abs((int) $val); },
        ]);

        $admin = new AIVM_Admin();

        $input = [
            'site_title_override' => '  My Site  ',
            'markdown_param_key'  => 'format',
        ];

        $result = $admin->sanitize_settings($input);

        $this->assertSame('My Site', $result['site_title_override']);
        $this->assertSame('format', $result['markdown_param_key']);
    }

    public function test_sanitize_settings_sanitizes_numeric_fields(): void {
        Functions\stubs([
            'sanitize_text_field' => function ($val) { return $val; },
            'wp_kses_post'        => function ($val) { return $val; },
            'sanitize_key'        => function ($val) { return $val; },
            'absint'              => function ($val) { return abs((int) $val); },
        ]);

        $admin = new AIVM_Admin();

        $input = [
            'llms_txt_recency_days' => '30',
            'llms_full_truncation'  => '1000',
            'llms_full_max_posts'   => '-5',
        ];

        $result = $admin->sanitize_settings($input);

        $this->assertSame(30, $result['llms_txt_recency_days']);
        $this->assertSame(1000, $result['llms_full_truncation']);
        $this->assertSame(5, $result['llms_full_max_posts']);
    }

    public function test_sanitize_settings_sanitizes_post_types_array(): void {
        Functions\stubs([
            'sanitize_text_field' => function ($val) { return $val; },
            'wp_kses_post'        => function ($val) { return $val; },
            'sanitize_key'        => function ($val) { return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $val)); },
            'absint'              => function ($val) { return abs((int) $val); },
        ]);

        $admin = new AIVM_Admin();

        $input = [
            'post_types' => ['post', 'page', 'custom_type'],
        ];

        $result = $admin->sanitize_settings($input);

        $this->assertSame(['post', 'page', 'custom_type'], $result['post_types']);
    }

    public function test_invalidate_cache_deletes_both_transients(): void {
        Functions\expect('delete_transient')
            ->once()
            ->with('wp_aivm_llms_txt_cache');

        Functions\expect('delete_transient')
            ->once()
            ->with('wp_aivm_llms_full_txt_cache');

        Functions\expect('update_option')
            ->once()
            ->with('wp_aivm_llms_last_modified', \Mockery::type('int'));

        $admin = new AIVM_Admin();
        $admin->invalidate_cache();

        $this->assertTrue(true);
    }

    public function test_get_defaults_returns_expected_keys(): void {
        $defaults = AIVM_Admin::get_defaults();

        $this->assertArrayHasKey('enable_llms_txt', $defaults);
        $this->assertArrayHasKey('enable_llms_full', $defaults);
        $this->assertArrayHasKey('enable_head_comment', $defaults);
        $this->assertArrayHasKey('enable_markdown_endpoint', $defaults);
        $this->assertArrayHasKey('enable_alternate_signals', $defaults);
        $this->assertArrayHasKey('markdown_param_key', $defaults);
        $this->assertArrayHasKey('markdown_param_value', $defaults);

        // Check default values.
        $this->assertTrue($defaults['enable_llms_txt']);
        $this->assertFalse($defaults['enable_llms_full']);
        $this->assertTrue($defaults['enable_head_comment']);
        $this->assertSame('format', $defaults['markdown_param_key']);
        $this->assertSame('markdown', $defaults['markdown_param_value']);
    }
}
