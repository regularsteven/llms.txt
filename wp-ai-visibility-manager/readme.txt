=== WP AI Visibility Manager ===
Contributors: stevenwright
Tags: ai, llms, llms.txt, markdown, seo
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Helps AI systems discover your content via llms.txt, head comments, link tags, and HTTP headers.

== Description ==

WP AI Visibility Manager helps site owners communicate clearly and efficiently with AI crawlers, agents, and language models through five coordinated mechanisms:

* A dynamically generated `llms.txt` file
* An optional `llms-full.txt` file with expanded content
* An HTML comment block injected into `<head>`
* A `<link rel="alternate">` tag for Markdown content
* An HTTP `Link` response header

Each mechanism can be enabled or disabled independently.

== Installation ==

1. Upload the `wp-ai-visibility-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu in WordPress.
3. Go to Tools > AI Visibility to configure settings.

== Changelog ==

= 0.1.0 =
* Initial scaffolding release.
