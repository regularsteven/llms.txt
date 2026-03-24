# Plugin Development Brief: WP AI Visibility Manager

**Document Type:** Product & Technical Specification  
**Version:** 1.2  
**Status:** Ready for Development  
**Prepared by:** Product Owner / Full Stack Lead  
**Changelog:**
- v1.1 — Rewrite routing fully specified; caching hooks expanded; query performance mandated; URL parameter handling corrected; comment injection hardened; transient size cap added; physical file conflict check expanded; sanitisation strategy corrected; content ordering defined; canonical URL normalisation added; HTTP caching headers added; admin field disable behaviour clarified; token replacement implementation defined.
- v1.2 — Feature E (`<link rel="alternate">` injection) added; Feature F (HTTP `Link` response header) added; `llms.txt` freshness timestamp added; Preferred Content Format section added to file output; content priority filtering added (recency window option); admin page reframed with outcome-focused language, summary box, and reality-check notice; Markdown endpoint quality guidance added; file structure updated; acceptance criteria updated; phasing updated; out-of-scope list updated.

---

## 1. Overview

### 1.1 Purpose

WP AI Visibility Manager is a WordPress plugin that helps site owners communicate clearly and efficiently with AI crawlers, agents, and language models. It does this through five coordinated mechanisms: a dynamically generated `llms.txt` file, an optional `llms-full.txt` file, an HTML comment block injected into `<head>`, a `<link rel="alternate">` tag for Markdown content, and an HTTP `Link` response header. Each mechanism serves a distinct purpose and can be enabled or disabled independently.

The strategic intent of v1.2 is to move beyond passive signalling — providing optional hints that crawlers may or may not notice — toward active discoverability using standard web mechanisms that parsers and agents are already built to understand.

### 1.2 Target Users

The primary audience is site owners and developers who are aware that AI crawlers are consuming their content and want to direct those agents toward the most efficient retrieval path. This includes content-heavy sites, documentation portals, blogs, and any WordPress installation that has implemented a Markdown output endpoint.

### 1.3 Plugin Identity

- **Plugin Name:** WP AI Visibility Manager  
- **Suggested Slug:** `wp-ai-visibility-manager`  
- **Menu Location:** Tools > AI Visibility  
- **Text Domain:** `wp-aivm`  
- **Minimum WordPress Version:** 6.2  
- **Minimum PHP Version:** 8.0  
- **Licence:** GPL v2 or later

---

## 2. Goals and Non-Goals

### 2.1 Goals

The plugin must generate a spec-compliant `llms.txt` file served dynamically via WordPress rewrite rules — no physical file is written to disk. It must optionally generate a parallel `llms-full.txt` containing expanded per-post content. It must inject a configurable HTML comment block into `wp_head`. On singular posts and pages, it must inject a `<link rel="alternate" type="text/markdown">` tag into `<head>` and emit a matching HTTP `Link` response header. All features must be independently togglable. The settings interface must be accessible, outcome-focused, and require no technical knowledge to operate. The plugin must not introduce frontend asset loading of any kind on the public-facing side.

### 2.2 Non-Goals

The plugin will not attempt to block or rate-limit AI crawlers. It will not integrate with third-party SEO plugins in v1.x. It will not write physical files to the filesystem. It will not provide analytics or bot detection in v1.x. It will not require any external API or service dependency.

---

## 3. Feature Specification

### 3.1 Feature A — `llms.txt` Generation

**Description:** Serves a dynamically generated `llms.txt` file at `/llms.txt`, compliant with the llmstxt.org specification.

**Behaviour:**

The file is generated on request and cached using WordPress transients (see Section 5.3). It is served with a `Content-Type: text/plain; charset=utf-8` header and a `200` status. If the feature is disabled, the URL must return a `404` and exit immediately.

**File Structure (spec-compliant):**

```
# {Site Title}

> {User-defined site description / introduction}

{Optional user-defined body text block}

## Preferred Content Format

Markdown versions of all pages are recommended for efficient processing.
Append {markdown_param} to any URL to receive clean Markdown output.

## Pages

- [{Page Title}]({URL}): {Optional excerpt or meta description}

## Posts

- [{Post Title}]({URL}?format=markdown): {Optional excerpt}

## Notes

{Optional user-defined notes block}

Last updated: {ISO 8601 timestamp}
```

The "Preferred Content Format" section and the "Last updated" timestamp are each independently togglable in admin settings. Both are enabled by default.

**Admin Controls:** See Section 4.3.

---

### 3.2 Feature B — `llms-full.txt` Generation

**Description:** Serves a more comprehensive file at `/llms-full.txt` that includes expanded content per post or page.

**Behaviour:**

Structurally identical to `llms.txt` in its header section, but each listed URL is followed by an indented content block containing the post excerpt (or a truncated version of the post content if no excerpt exists). Content length per entry is configurable (default: 500 characters). Total generated content is capped at 500,000 characters before caching (see Section 5.3). The generation function must accept `$limit` and `$offset` parameters internally to future-proof pagination, even though v1.2 only ever calls it with offset `0`.

**Admin Controls:** See Section 4.4.

---

### 3.3 Feature C — HTML `<head>` Comment Block

**Description:** Injects a configurable HTML comment block into `<head>` via `wp_head` at priority `999`.

**Behaviour:**

Injected on all public pages when enabled. Absent from all wp-admin pages. Tokens resolved at render time (see Section 5.5). Comment body processed through the safety pipeline defined in Section 5.6.

**Default Output:**

```html
<!--
════════════════════════════════════════════
  AI AGENT NOTICE — {site_name}
════════════════════════════════════════════
  Clean Markdown available: append ?format=markdown to any URL
  Site index:  {llms_url}
  Full index:  {llms_full_url}
  This site supports token-efficient Markdown retrieval.
  Maintained by WP AI Visibility Manager.
════════════════════════════════════════════
-->
```

**Admin Controls:** See Section 4.5.

---

### 3.4 Feature D — Markdown Endpoint Advertisement

A configuration toggle within Features A and B. When enabled, all URLs in both files have the Markdown parameter appended using `add_query_arg()`. The parameter key and value are individually configurable. Defaults: key `format`, value `markdown`. See Section 5.4 for implementation requirements.

---

### 3.5 Feature E — `<link rel="alternate">` Injection (NEW)

**Description:** Injects a `<link rel="alternate" type="text/markdown">` tag into `<head>` on all singular posts and pages. This is a standard web mechanism for advertising alternate content representations and is recognised by crawlers and parsers that understand content negotiation.

**Behaviour:**

The tag is injected via `wp_head` at priority `999`, on singular views only (i.e. `is_singular()` must return true). It must not appear on archive pages, the home page, or wp-admin pages. The URL is constructed using `add_query_arg()` (see Section 5.4). The feature is conditional on the "Advertise Markdown Endpoint" toggle being enabled.

**Output:**

```html
<link rel="alternate" type="text/markdown" href="https://example.com/post-slug?format=markdown">
```

**Admin Controls:** See Section 4.6. This feature shares its enable toggle with Feature F — they are presented as a single "Alternate Format Signals" section in admin, since they serve the same purpose via different layers.

---

### 3.6 Feature F — HTTP `Link` Response Header (NEW)

**Description:** Emits an HTTP `Link` header on singular post and page responses, advertising the Markdown alternate. This targets machine clients that inspect response headers directly without parsing HTML.

**Behaviour:**

Hooked into `template_redirect`. Fires on singular views only. Must not fire on admin requests, feeds, or non-singular archives. URL constructed using `add_query_arg()`.

**Output:**

```
Link: <https://example.com/post-slug?format=markdown>; rel="alternate"; type="text/markdown"
```

**Implementation:**

```php
add_action('template_redirect', function(): void {
    if (!is_singular()) return;

    $settings = get_option('wp_aivm_settings', []);
    if (empty($settings['enable_alternate_signals'])) return;
    if (empty($settings['enable_markdown_endpoint'])) return;

    $param_key   = $settings['markdown_param_key']   ?? 'format';
    $param_value = $settings['markdown_param_value'] ?? 'markdown';

    $url = add_query_arg($param_key, $param_value, esc_url_raw(get_permalink()));

    header('Link: <' . $url . '>; rel="alternate"; type="text/markdown"', false);
});
```

The `false` second argument to `header()` is required to allow multiple `Link` headers without overwriting any set by other plugins.

**Admin Controls:** Shared toggle with Feature E (see Section 4.6).

---

## 4. Admin Interface Specification

### 4.1 Menu Location

The plugin registers a submenu item under the native **Tools** menu. The submenu label is **AI Visibility**. The page title is **WP AI Visibility Manager**.

### 4.2 Page Layout and Tone

The settings page uses standard WordPress Settings API patterns (`<h2>` section headings, `<table class="form-table">` layouts). Each section begins with a plain-English explanation of what the feature does, framed in terms of outcomes rather than technical mechanisms.

**Page header summary box** — a styled `<div>` immediately below the `<h1>` page title must display the following fixed text (not user-editable):

> **What this plugin does**  
> WP AI Visibility Manager helps AI systems discover your content, access clean Markdown versions, and reduce processing cost and ambiguity. It does this using a combination of standard web signals and advisory files — giving AI agents multiple ways to find the most efficient path to your content.

**Reality-check notice** — immediately below the summary box, display a fixed notice (styled as a standard WP info notice, not user-editable):

> **Important:** AI crawlers are not required to follow these signals. This plugin improves discoverability and efficiency but does not guarantee crawler behaviour.

Each section's enable toggle, when disabled, must render child fields with the `disabled` HTML attribute. Disabled fields must simultaneously output a corresponding hidden input so their values are preserved on form submission. Visual muting via CSS opacity is acceptable in addition to this.

All settings are saved via the WordPress Settings API using a single options key `wp_aivm_settings` (serialised array). Nonce verification and `current_user_can('manage_options')` are required on all save operations.

### 4.3 Section A — llms.txt Settings

| Field | Type | Default | Notes |
|---|---|---|---|
| Enable llms.txt | Checkbox | Enabled | Master toggle |
| Site Title Override | Text input | WP site title | Overrides `# Heading` |
| Site Description | Textarea | WP tagline | The `>` blockquote intro |
| Body Text | Textarea | Empty | Freeform Markdown block |
| Notes Section | Textarea | Empty | Appended under `## Notes` |
| Post Types to Include | Checkbox group | Pages, Posts | All public CPTs listed dynamically |
| Show "Preferred Content Format" section | Checkbox | Enabled | Emits the format hint block |
| Show "Last updated" timestamp | Checkbox | Enabled | ISO 8601 timestamp at end of file |
| Limit to posts from last N days | Number input | 0 (disabled) | 0 = no date filter applied |

### 4.4 Section B — llms-full.txt Settings

| Field | Type | Default | Notes |
|---|---|---|---|
| Enable llms-full.txt | Checkbox | Disabled | Master toggle |
| Inherit Post Types from A | Checkbox | Enabled | Mirrors Section A selection |
| Post Types (if not inherited) | Checkbox group | Pages, Posts | Shown only if above unchecked |
| Content Truncation Limit | Number input | 500 | Characters per entry |
| Maximum Posts | Number input | 200 | Hard cap on total entries |
| Limit to posts from last N days | Number input | 0 (disabled) | 0 = no date filter |
| Include Featured Image Alt Text | Checkbox | Enabled | Appends alt text if present |

### 4.5 Section C — HTML Head Comment Settings

| Field | Type | Default | Notes |
|---|---|---|---|
| Enable Head Comment | Checkbox | Enabled | Master toggle |
| Comment Body | Textarea | Default template | Token-aware; see Section 5.5 |
| Reset to Default | Button | — | Repopulates textarea |

Below the textarea, display the following static token reference:

> **Available tokens:** `{site_name}`, `{home_url}`, `{llms_url}`, `{llms_full_url}`  
> Replaced with live values at render time. Do not add HTML comment delimiters — the plugin wraps the output automatically.

### 4.6 Section D+E — Markdown Endpoint & Alternate Signals

| Field | Type | Default | Notes |
|---|---|---|---|
| Advertise Markdown Endpoint | Checkbox | Enabled | Controls URL param appending in A, B, E, F |
| Markdown Parameter Key | Text input | `format` | The query arg key |
| Markdown Parameter Value | Text input | `markdown` | The query arg value |
| Enable Alternate Format Signals | Checkbox | Enabled | Master toggle for Features E and F |

Below this section, display the following static guidance block (not user-editable):

> **Markdown endpoint quality guidance**  
> For best AI compatibility, your Markdown endpoint should: remove navigation, ads, and boilerplate; use a clear heading hierarchy (H1–H3); preserve semantic structure; avoid inline scripts or styles; and return consistent structure across all pages. The signals this plugin emits are only as useful as the quality of the endpoint they point to.

### 4.7 Save & Cache Controls

A standard **Save Settings** button submits the form. On save, both transient caches are flushed immediately and a success or error admin notice is shown. A separate **Flush Cache** button posts to an `admin-post.php` action with its own nonce and calls `delete_transient()` on both cache keys without altering settings.

---

## 5. Technical Architecture

### 5.1 File Structure

```
wp-ai-visibility-manager/
├── wp-ai-visibility-manager.php      # Plugin bootstrap, headers, init hook
├── includes/
│   ├── class-aivm-admin.php          # Admin page, Settings API, notices
│   ├── class-aivm-llms-txt.php       # llms.txt generation logic
│   ├── class-aivm-llms-full.php      # llms-full.txt generation logic
│   ├── class-aivm-head-comment.php   # wp_head comment injection
│   ├── class-aivm-alternate.php      # <link rel="alternate"> + HTTP Link header
│   └── class-aivm-rewrite.php        # Rewrite rules, query vars, routing
├── assets/
│   └── admin.css                     # Admin-only styles
└── readme.txt                        # WordPress.org readme format
```

`class-aivm-alternate.php` encapsulates both Feature E and Feature F, since they share a toggle, a URL generation method, and a singular-only condition.

### 5.2 Rewrite Routing — Full Implementation

All three pieces below must be present. Missing any one of them will cause 404s.

**Step 1 — Register query vars:**

```php
add_filter('query_vars', function(array $vars): array {
    $vars[] = 'aivm_llms';
    $vars[] = 'aivm_llms_full';
    return $vars;
});
```

**Step 2 — Register rewrite rules (hooked to `init`, priority 10):**

```php
add_rewrite_rule('^llms\.txt$',      'index.php?aivm_llms=1',      'top');
add_rewrite_rule('^llms-full\.txt$', 'index.php?aivm_llms_full=1', 'top');
```

**Step 3 — Handle the request:**

```php
add_action('template_redirect', function(): void {
    $settings = get_option('wp_aivm_settings', []);

    if (get_query_var('aivm_llms')) {
        if (empty($settings['enable_llms_txt'])) { status_header(404); exit; }
        // serve llms.txt content
        exit;
    }

    if (get_query_var('aivm_llms_full')) {
        if (empty($settings['enable_llms_full'])) { status_header(404); exit; }
        // serve llms-full.txt content
        exit;
    }
});
```

`flush_rewrite_rules()` must be called on both `register_activation_hook` and `register_deactivation_hook`.

### 5.3 Caching Strategy

Transient keys: `wp_aivm_llms_txt_cache` and `wp_aivm_llms_full_txt_cache`. TTL: 12 hours. The following hooks must all trigger `delete_transient()` on both keys:

```
save_post
delete_post
trash_post
untrash_post
transition_post_status
clean_post_cache
switch_theme
update_option_wp_aivm_settings
```

For `llms-full.txt`, if generated content exceeds 500,000 characters before caching, truncate the entry list and append:

```
## Notice

Output truncated. Reduce maximum post count or per-entry character limit to include all entries.
```

A separate option `wp_aivm_llms_last_modified` stores a Unix timestamp updated on every cache invalidation, used for the `Last-Modified` HTTP header and the in-file timestamp.

### 5.4 URL and Query Parameter Handling

All permalink retrieval must use:

```php
$url = esc_url_raw(get_permalink($post_id));
```

Markdown parameter appending must always use `add_query_arg()`:

```php
$url = add_query_arg($param_key, $param_value, $url);
```

String concatenation of `?format=markdown` is explicitly prohibited. It will produce malformed URLs on any post whose permalink already contains a query string.

### 5.5 Token Replacement

Tokens are resolved at render time, not at save time, using `str_replace`:

```php
$tokens = [
    '{site_name}'     => get_bloginfo('name'),
    '{home_url}'      => home_url('/'),
    '{llms_url}'      => home_url('/llms.txt'),
    '{llms_full_url}' => home_url('/llms-full.txt'),
    '{markdown_param}' => '?' . $param_key . '=' . $param_value,
];

$output = str_replace(array_keys($tokens), array_values($tokens), $template);
```

Render-time resolution ensures tokens reflect the current site state even after a domain migration.

### 5.6 HTML Comment Injection — Safety Pipeline

Process the comment body through the following steps in order before output:

1. Resolve tokens (Section 5.5)
2. Normalise line endings to `\n`
3. Strip all occurrences of `-->` to prevent comment injection
4. Trim leading and trailing whitespace
5. Suppress output entirely if the result is empty — do not emit `<!-- -->`

Hook at priority `999`:

```php
add_action('wp_head', [$this, 'inject_comment'], 999);
```

### 5.7 `<link rel="alternate">` Injection

Hooked to `wp_head` at priority `999`. Only fires when `is_singular()` is true. URL constructed via `add_query_arg()`.

```php
add_action('wp_head', function(): void {
    $settings = get_option('wp_aivm_settings', []);
    if (empty($settings['enable_alternate_signals'])) return;
    if (empty($settings['enable_markdown_endpoint'])) return;
    if (!is_singular()) return;

    $param_key   = $settings['markdown_param_key']   ?? 'format';
    $param_value = $settings['markdown_param_value'] ?? 'markdown';
    $url = add_query_arg($param_key, $param_value, esc_url_raw(get_permalink()));

    echo '<link rel="alternate" type="text/markdown" href="' . esc_url($url) . '">' . "\n";
}, 999);
```

### 5.8 HTTP `Link` Header

Hooked to `template_redirect`. Singular views only. The `false` parameter prevents overwriting headers set by other plugins.

```php
add_action('template_redirect', function(): void {
    if (!is_singular()) return;

    $settings = get_option('wp_aivm_settings', []);
    if (empty($settings['enable_alternate_signals'])) return;
    if (empty($settings['enable_markdown_endpoint'])) return;

    $param_key   = $settings['markdown_param_key']   ?? 'format';
    $param_value = $settings['markdown_param_value'] ?? 'markdown';
    $url = add_query_arg($param_key, $param_value, esc_url_raw(get_permalink()));

    header('Link: <' . $url . '>; rel="alternate"; type="text/markdown"', false);
});
```

### 5.9 Query Performance

Both generators must fetch IDs first, then retrieve only required fields. Full post objects must not be loaded.

```php
$args = [
    'post_type'      => $post_types,
    'post_status'    => 'publish',
    'posts_per_page' => $limit,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
];

// Apply recency filter if configured
if (!empty($days) && $days > 0) {
    $args['date_query'] = [[
        'after'     => $days . ' days ago',
        'inclusive' => true,
    ]];
}

$query = new WP_Query($args);

foreach ($query->posts as $post_id) {
    $title   = get_the_title($post_id);
    $url     = esc_url_raw(get_permalink($post_id));
    $excerpt = get_post_field('post_excerpt', $post_id);
}
```

Content ordering must be `date DESC` consistently across all environments.

### 5.10 HTTP Response Headers for File Endpoints

```php
$last_modified = get_option('wp_aivm_llms_last_modified', time());
header('Content-Type: text/plain; charset=utf-8');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
header('ETag: "' . md5($content) . '"');
header('Cache-Control: public, max-age=43200');
```

### 5.11 Sanitisation Strategy

| Context | Function |
|---|---|
| Admin textarea on save | `wp_kses_post()` — preserves Markdown formatting |
| Output to `.txt` files | `wp_strip_all_tags()` — strips all HTML before writing to response |
| Output into HTML comment | Strip `-->` per Section 5.6; do not apply `esc_html()` |
| Admin UI textarea values | `esc_textarea()` |
| Admin UI text input values | `esc_attr()` |

`sanitize_textarea_field()` must not be used for Markdown content — it collapses whitespace and strips formatting.

### 5.12 Permissions

All admin page rendering and settings saves must be gated with `current_user_can('manage_options')`. All form submissions must verify a nonce created with `wp_create_nonce('wp_aivm_settings')`. The cache flush `admin-post.php` action requires its own nonce.

---

## 6. Edge Cases and Constraints

**Physical file conflict:** Apache and Nginx will serve a static file at the document root before WordPress rewrite rules can intercept the request. On the settings page, check for the existence of both paths and display a persistent admin error notice if found:

```php
$paths = [ABSPATH . 'llms.txt', ABSPATH . 'llms-full.txt'];
```

**`<link rel="alternate">` and non-singular pages:** The tag and header must only fire on singular views. On archive pages, the home page, and taxonomies, no alternate signal should be emitted, as there is no single canonical Markdown URL to point to.

**`Link` header and header-already-sent errors:** The `template_redirect` hook fires before any output is sent, so this should not be an issue in practice. However, if headers have already been sent (e.g. due to a misconfigured plugin), the `header()` call should be wrapped in a `headers_sent()` guard.

**Multisite:** Not supported in v1.x. Deferred.

**Markdown endpoint availability:** When the Markdown endpoint feature is enabled, a dismissible admin notice should advise the administrator to confirm their endpoint is active. The plugin does not validate or probe the endpoint.

**Large sites:** Query is capped at the configured maximum (default 200 posts). Generated content is capped at 500,000 characters. The generation function accepts `$limit` and `$offset` to future-proof pagination.

**`robots.txt` advisory:** A read-only guidance block on the settings page should note that administrators may wish to reference `llms.txt` in their `robots.txt`, and link to llmstxt.org.

---

## 7. Out of Scope for v1.x

The following are explicitly deferred. The architecture must not preclude them.

- AI bot detection, logging, or analytics dashboard
- Automatic redirection of AI user agents to the Markdown endpoint
- Per-post or per-page opt-out (post-level metabox)
- Integration with Yoast SEO, Rank Math, or other SEO plugins
- WordPress Multisite support
- WP-CLI commands
- REST API endpoint for plugin settings
- `llms-full.txt` pagination (generator is internally ready; no UI or routing required yet)
- JSON structured endpoint (`?format=json`)
- `/llms-sitemap.xml`

---

## 8. Acceptance Criteria

The plugin is considered complete when all of the following are verifiable under `WP_DEBUG = true` with zero PHP errors or warnings.

`llms.txt` returns `200` with `Content-Type: text/plain; charset=utf-8` when Feature A is enabled, and `404` when disabled. `llms-full.txt` behaves identically. URLs in both files are well-formed under all permalink structures, including those already containing query strings. The Markdown parameter is appended via `add_query_arg()`, not string concatenation. The HTML comment is present in `<head>` on all public singular and non-singular pages when Feature C is enabled, tokens are resolved correctly, and the comment is entirely absent from wp-admin pages. A `<link rel="alternate" type="text/markdown">` tag is present in `<head>` on singular posts and pages when Feature E is enabled, and absent on archives and the home page. An HTTP `Link` response header with the correct value is present on singular responses when Feature F is enabled, and absent on non-singular responses. Disabling any section retains field values after save. `Last-Modified` and `ETag` headers are present on both file responses. Physical file conflict warnings appear correctly. Cache is invalidated on all hooks listed in Section 5.3. No JavaScript or CSS is loaded on any public-facing page.

---

## 9. Suggested Phasing

**Phase 1 (MVP):** Features A and C — `llms.txt` generation and the `<head>` comment block. Establishes the rewrite routing and caching architecture.

**Phase 2:** Features E and F — `<link rel="alternate">` and HTTP `Link` header. These share a class and a toggle and can be delivered together. This is the single highest-impact addition in v1.2.

**Phase 3:** Feature B — `llms-full.txt`. Dependent on Phase 1 architecture being stable.

**Phase 4:** Feature D refinement, freshness timestamp, preferred format section, recency filter, and admin UX improvements (summary box, reality-check notice, endpoint quality guidance).

---

## 10. Reference Material

- llmstxt.org specification: https://llmstxt.org  
- WordPress Plugin Developer Handbook: https://developer.wordpress.org/plugins/  
- WordPress Settings API: https://developer.wordpress.org/apis/settings/  
- WordPress Rewrite API: https://developer.wordpress.org/reference/classes/wp_rewrite/  
- `add_query_arg()` reference: https://developer.wordpress.org/reference/functions/add_query_arg/  
- `is_singular()` reference: https://developer.wordpress.org/reference/functions/is_singular/  
- HTTP `Link` header specification: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Link  
- Existing open-source reference: https://github.com/WP-Autoplugin/llms-txt-for-wp

---

*End of Brief — v1.2*
