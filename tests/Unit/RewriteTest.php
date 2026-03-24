<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use PHPUnit\Framework\TestCase;
use AIVM_Rewrite;

class RewriteTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_hooks(): void {
        $rewrite = new AIVM_Rewrite();
        $rewrite->register();

        $this->assertNotFalse(has_action('init', 'AIVM_Rewrite->register_rewrite_rules()'));
        $this->assertNotFalse(has_filter('query_vars', 'AIVM_Rewrite->register_query_vars()'));
        $this->assertNotFalse(has_action('template_redirect', 'AIVM_Rewrite->handle_request()'));
    }

    public function test_register_query_vars_adds_both_vars(): void {
        $rewrite = new AIVM_Rewrite();
        $vars = $rewrite->register_query_vars(['existing_var']);

        $this->assertContains('aivm_llms', $vars);
        $this->assertContains('aivm_llms_full', $vars);
        $this->assertContains('existing_var', $vars);
    }

    public function test_register_rewrite_rules_calls_add_rewrite_rule(): void {
        Functions\expect('add_rewrite_rule')
            ->once()
            ->with('^llms\.txt$', 'index.php?aivm_llms=1', 'top');

        Functions\expect('add_rewrite_rule')
            ->once()
            ->with('^llms-full\.txt$', 'index.php?aivm_llms_full=1', 'top');

        $rewrite = new AIVM_Rewrite();
        $rewrite->register_rewrite_rules();

        $this->assertTrue(true); // Expectations verified by Brain Monkey tearDown.
    }

    public function test_activate_flushes_rewrite_rules(): void {
        Functions\expect('add_rewrite_rule')->twice();
        Functions\expect('flush_rewrite_rules')->once();

        AIVM_Rewrite::activate();

        $this->assertTrue(true);
    }

    public function test_deactivate_flushes_rewrite_rules(): void {
        Functions\expect('flush_rewrite_rules')->once();

        AIVM_Rewrite::deactivate();

        $this->assertTrue(true);
    }
}
