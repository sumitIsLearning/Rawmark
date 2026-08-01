# Rawmark

Build a page by writing raw HTML, CSS, and JS in a split-pane editor with a live preview. Publishes a clean standalone document with no theme wrapper.

- **Requires at least:** WordPress 6.4
- **Requires PHP:** 8.0
- **Version:** 0.1.0
- **License:** GPLv2 or later

## Description

Rawmark adds a three-pane code editor (HTML / CSS / JS, powered by CodeMirror) to WordPress pages, with a live preview pane. Published output renders as a clean standalone document — no theme header/footer wrapper — via a linked-snippets system (`rawmark_snippet` post type) supporting header/footer placement and per-page composition.

No runtime PHP dependencies — Composer is dev-only tooling (PHPUnit).

## Development

```bash
npm install
npm run build     # esbuild bundle
npm run watch      # esbuild watch mode
npm test           # vitest
```

```bash
composer install
vendor/bin/phpunit  # PHPUnit + wp-phpunit, requires a reachable WP test database
```

`assets/dist/` is committed (not gitignored) — the plugin's real-world install path is zipping the raw repo folder, which never ran `npm run build` itself. Any change to `assets/src/` needs a matching `npm run build` in the same commit.

## License

GPL-2.0-or-later
