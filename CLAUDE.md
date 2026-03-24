# CLAUDE.md - Claude Code Instructions for WP AI Visibility Manager

## Project Overview
WordPress plugin for AI crawler discoverability. Standalone dev environment (not inside WordPress).

## Key Paths
- Plugin source: `wp-ai-visibility-manager/` (this is what gets zipped for distribution)
- Tests: `tests/Unit/`
- Build scripts: `scripts/`
- Spec: `docs/llms-txt-plugin-brief-v1.2.md`

## Commands
- `npm test` — run PHPUnit tests via Composer
- `npm run build:zip` — create distributable zip in `dist/`
- `npm run version:patch|minor|major` — bump version and sync to PHP header + readme.txt
- `composer test` — run PHPUnit directly

## Git Workflow — MANDATORY, NO EXCEPTIONS
- `main` — production releases only
- `test` — tested features ready for release
- `dev` — integration branch
- `feature/<name>` — feature branches (created from `dev`)
- Flow: `feature/<name>` → `dev` → `test` → `main`
- **NEVER commit directly to `main`, `test`, or `dev`**
- Always start with: `git checkout dev && git checkout -b feature/<name>`
- Merge order: feature → dev (run tests) → test → main → bump version → tag
- See `agents.md` for the full step-by-step release protocol

## Architecture
- All plugin code in `wp-ai-visibility-manager/` directory
- One class per feature file in `includes/`
- Single settings option: `wp_aivm_settings` (serialized array)
- No frontend JS/CSS — admin only
- Tests use PHPUnit + Brain Monkey (mocks WP functions without WP installation)

## Conventions
- Class prefix: `AIVM_`
- Hook prefix: `wp_aivm_`
- Text domain: `wp-aivm`
- PHP 8.0+ syntax (typed properties, union types, named args allowed)
- All WP function calls must use standard API (no direct DB queries)
- URL construction must use `add_query_arg()`, never string concatenation
- Sanitization: `wp_kses_post()` on save, `wp_strip_all_tags()` on .txt output

## Testing
- TDD approach: write tests before implementation
- Tests in `tests/Unit/` with Brain Monkey for WP function mocking
- Each feature class has a corresponding test file
- Run tests before every merge

## Version Management
- Canonical version in `package.json`
- `npm run version:*` syncs to plugin PHP header and readme.txt
- See `agents.md` for release protocol
