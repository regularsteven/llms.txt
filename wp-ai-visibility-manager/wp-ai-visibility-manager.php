<?php
/**
 * Plugin Name:       WP AI Visibility Manager
 * Plugin URI:        https://github.com/stevenwright/wp-ai-visibility-manager
 * Description:       Helps AI systems discover your content via llms.txt, head comments, link tags, and HTTP headers.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Steven Wright
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-aivm
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIVM_VERSION', '1.0.0');
define('AIVM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIVM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include class files.
require_once AIVM_PLUGIN_DIR . 'includes/class-aivm-rewrite.php';
require_once AIVM_PLUGIN_DIR . 'includes/class-aivm-llms-txt.php';
require_once AIVM_PLUGIN_DIR . 'includes/class-aivm-llms-full.php';
require_once AIVM_PLUGIN_DIR . 'includes/class-aivm-head-comment.php';
require_once AIVM_PLUGIN_DIR . 'includes/class-aivm-alternate.php';
require_once AIVM_PLUGIN_DIR . 'includes/class-aivm-admin.php';

/**
 * Initialize the plugin.
 */
function aivm_init(): void {
    $rewrite      = new AIVM_Rewrite();
    $llms_txt     = new AIVM_Llms_Txt();
    $llms_full    = new AIVM_Llms_Full();
    $head_comment = new AIVM_Head_Comment();
    $alternate    = new AIVM_Alternate();
    $admin        = new AIVM_Admin();

    $rewrite->register();
    $llms_txt->register();
    $llms_full->register();
    $head_comment->register();
    $alternate->register();
    $admin->register();
}
add_action('plugins_loaded', 'aivm_init');

// Activation / deactivation hooks.
register_activation_hook(__FILE__, function (): void {
    AIVM_Rewrite::activate();
});

register_deactivation_hook(__FILE__, function (): void {
    AIVM_Rewrite::deactivate();
});
