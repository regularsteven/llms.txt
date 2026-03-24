# agents.md - Release Management & Versioning Protocol

## Versioning

This project uses Semantic Versioning (semver): MAJOR.MINOR.PATCH

- **PATCH** (1.0.x): Bug fixes, typo corrections, minor CSS tweaks. No new features, no API changes.
- **MINOR** (1.x.0): New features that are backward-compatible. New admin settings, new output sections.
- **MAJOR** (x.0.0): Breaking changes. Settings format changes, removed features, PHP version requirement bumps.

## Version Bump Procedure

1. Ensure all tests pass: `npm test`
2. Determine version type (patch/minor/major)
3. Run: `npm run version:{type}`
   - This updates `package.json`, `wp-ai-visibility-manager.php` header, and `readme.txt`
4. Commit the version bump
5. Build the zip: `npm run build:zip`
6. The zip appears in `dist/wp-ai-visibility-manager-{version}.zip`

## Release Workflow

1. Complete feature work on `feature/<name>` branch
2. Merge to `dev` branch
3. Run full test suite on `dev`
4. Merge `dev` to `test`
5. Manual testing on `test` branch (install zip in WordPress, verify features)
6. If tests pass: merge `test` to `main`
7. Bump version on `main`
8. Build final zip from `main`
9. Tag the release: `git tag v{X.Y.Z}`

## Pre-Release Checklist

- [ ] All PHPUnit tests pass (`npm test`)
- [ ] Plugin activates without errors in WordPress (`WP_DEBUG=true`)
- [ ] All enabled features produce correct output
- [ ] Disabling features returns 404 / hides output
- [ ] Settings save and persist correctly
- [ ] Cache flush works
- [ ] No PHP warnings or notices
- [ ] No frontend JS/CSS loaded on public pages
- [ ] Version numbers match in package.json, plugin header, and readme.txt

## Git Branch Strategy

```
feature/<name>  →  dev  →  test  →  main
```

- `feature/<name>`: Individual feature development (created from `dev`)
- `dev`: Integration branch — all features merge here first
- `test`: QA branch — only stable dev merges go here
- `main`: Production releases only — tagged versions

## ⚠️ Critical Workflow Reminder for Agents

**NEVER commit directly to `main`, `test`, or `dev`.** The required flow is:

```
git checkout dev
git checkout -b feature/<name>
# ... do work, commit on feature branch ...
git checkout dev && git merge feature/<name> --no-ff
npm test   # must pass before proceeding
git checkout test && git merge dev --no-ff
git checkout main && git merge test --no-ff
npm run version:minor   # or patch/major
git add ... && git commit -m "Bump version to X.Y.Z"
git tag vX.Y.Z
```

A previous agent session (v1.1.0 feature work) committed directly to `main` and had to be rescued via stash → feature branch after the fact. Do not repeat this.

## Current Release

| Field | Value |
|---|---|
| Version | 1.1.0 |
| Status | Complete |
| Release | URL exclusion, protected posts, entity decoding, Auto MD |

## Release History

| Version | Date | Type | Description |
|---|---|---|---|
| 1.1.0 | 2026-03-24 | Minor | URL exclusion (wildcards), protected post filtering, HTML entity decoding, plugin-generated Auto MD, HTML→Markdown converter |
| 1.0.1 | 2026-03-24 | Patch | Fix: head comment injection priority changed from 999 to 1 for immediate visibility |
| 1.0.0 | 2026-03-24 | Major | Full v1.2 spec: all 6 features (A-F), admin UI, caching, 57 tests |
| 0.1.0 | 2026-03-24 | Initial | Project scaffolding and tooling setup |
