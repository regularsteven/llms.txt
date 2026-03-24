# WP AI Visibility Manager

WordPress plugin that helps AI systems discover your content via `llms.txt`, head comments, `<link rel="alternate">` tags, and HTTP `Link` headers.

## Features

- **llms.txt** — Dynamically generated file at `/llms.txt` (spec: [llmstxt.org](https://llmstxt.org))
- **llms-full.txt** — Expanded version with per-post content
- **Head Comment** — HTML comment block in `<head>` for AI agents
- **Link Alternate** — `<link rel="alternate" type="text/markdown">` on singular pages
- **HTTP Link Header** — `Link` response header on singular pages
- **Markdown Endpoint** — Advertises `?format=markdown` parameter across all signals

## Development Setup

```bash
# Install PHP dev dependencies
composer install

# Install Node dependencies (none currently, but enables npm scripts)
npm install

# Run tests
npm test

# Build distributable zip
npm run build:zip

# Version management
npm run version:patch   # Bug fixes
npm run version:minor   # New features
npm run version:major   # Breaking changes
```

## Git Workflow

```
feature/<name>  →  dev  →  test  →  main
```

See [agents.md](agents.md) for the full release protocol.

## Installing the Plugin

1. Run `npm run build:zip`
2. Go to your WordPress admin: Plugins > Add New > Upload Plugin
3. Upload the zip from `dist/wp-ai-visibility-manager-{version}.zip`
4. Activate and configure at Tools > AI Visibility

## Spec

Full product brief: [docs/llms-txt-plugin-brief-v1.2.md](docs/llms-txt-plugin-brief-v1.2.md)

## License

GPL v2 or later
