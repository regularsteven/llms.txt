<?php

declare(strict_types=1);

namespace AIVM\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test to verify the test infrastructure works.
 */
class SmokeTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_brain_monkey_is_available(): void {
        $this->assertTrue(true, 'Brain Monkey loaded successfully.');
    }

    public function test_abspath_is_defined(): void {
        $this->assertTrue(defined('ABSPATH'));
    }

    public function test_plugin_classes_are_loadable(): void {
        $this->assertTrue(class_exists('AIVM_Rewrite'));
        $this->assertTrue(class_exists('AIVM_Llms_Txt'));
        $this->assertTrue(class_exists('AIVM_Llms_Full'));
        $this->assertTrue(class_exists('AIVM_Head_Comment'));
        $this->assertTrue(class_exists('AIVM_Alternate'));
        $this->assertTrue(class_exists('AIVM_Admin'));
    }
}
