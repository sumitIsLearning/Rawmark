# Handoff

## Just landed on main

`worktree-linked-snippets-backend` merged to main (fast-forward, `754b198`).
Full backend for linked snippets: `rawmark_snippet` post type, `_rawmark_linked`
flag, placement finder, render-time composer (marker expansion + header/footer
wrap), REST CRUD, link/unlink/delete + Snippets admin screen, per-page
header/footer picker, editor screen accepting snippets.

Verified with a real `vendor/bin/phpunit.bat` run via Local's Site Shell:
**95/95 tests green, zero known failures** (the one long-standing failure,
`Test_Renderer::test_escaping_is_case_insensitive`, was root-caused and fixed
this session too — see Environment Gotchas below).

Design/plan docs for that work: `docs/superpowers/specs/2026-07-31-linked-snippets-design.md`,
`docs/superpowers/plans/2026-07-31-linked-snippets-backend.md`.

The worktree at `.claude/worktrees/linked-snippets-backend` (branch
`worktree-linked-snippets-backend`) is still present and locked, fully
merged — safe to remove when someone runs
`superpowers:finishing-a-development-branch`. Not done yet, wasn't asked for.

## In progress: Rawmark for Posts (single post detail page)

Mid-brainstorm when this handoff was written. Not yet a written spec, not
planned, not implemented. Pick up with `superpowers:brainstorming` (or just
continue the conversation) — the scoping questions below are already
answered, don't re-ask them.

**Decided:**
- Extend Rawmark's raw-render model (flag a post → bypass the theme → render
  through the Rawmark template) to the `post` post type, same as it already
  works for `page`.
- **Single post only.** The blog index/archive listing (`home.php`/`index.php`
  equivalent) stays themed, untouched. Explicitly out of scope — a list of
  excerpts/links has nothing that needs hand-coded HTML/CSS/JS per item.
- Not extending Snippets or the header/footer picker to Posts. Not asked for.

**Recommended approach (proposed, not yet confirmed in a written spec):**
generalize the existing Page mechanism rather than duplicating it into a
parallel `PostFlag`/`PostModeToggle` track. Same feature on a second post
type shouldn't double the surface.

Files that currently hardcode `'page'` and would need to accept `post` too:

| File | What changes |
|---|---|
| `src/Storage/PageFlag.php` | Guard checks `'page' !== get_post_type()` — broaden to an eligible-types list (`['page', 'post']`). Possibly rename the class if "Page" no longer fits; not decided. |
| `src/Frontend/Router.php` | `is_singular( 'page' )` → `is_singular( ['page', 'post'] )` |
| `src/Admin/PageModeToggle.php` | Same post-type guard broadening |
| `src/Admin/EditorScreen.php` | `is_editable()` already checks `PageFlag::is_enabled() OR Snippet::SLUG` — no change needed once `PageFlag` itself is broadened |
| `src/Admin/EditorLock.php` | Same post-type guard broadening |
| `src/Admin/HeaderFooterMetaBox.php` | Uses `PageFlag::is_enabled()` as its gate — no change needed once `PageFlag` is broadened (though header/footer-on-posts wasn't asked for; may want to keep this metabox Page-only regardless) |
| `src/Admin/PageListIntegration.php` | Bigger change: WP's Posts list table uses **different hook names** than the Pages list — `post_row_actions` (not `page_row_actions`), `manage_posts_columns` (not `manage_pages_columns`), `manage_posts_custom_column` (not `manage_pages_custom_column`). Needs a second set of hook registrations, same logic. |

Not touched: `src/Migration/Migrator.php` (unrelated `'page'` usage, migration
is a one-time upgrade path from the old custom post type) and
`src/Storage/SnippetUsage.php` (its `'page'` is the snippet-placement scanner
— out of scope since Snippets aren't being extended to Posts).

**Next step when resuming:** confirm the design above with the user (it was
presented but not yet explicitly approved before this handoff was written),
write it to `docs/superpowers/specs/<date>-rawmark-for-posts-design.md`, then
`superpowers:writing-plans` for the implementation plan, same flow as the
snippets work.

## Environment gotchas (don't re-discover these)

Running `vendor/bin/phpunit` for this plugin from **outside Local's own
PHP-FPM runtime** (e.g. a plain Windows cmd/bash shell, including Local's own
"Site Shell") hits two real bugs, both fixed this session in untracked,
machine-local config files — **not in git**, so a fresh clone or a different
machine will hit them again:

1. **`'C:\Users\Sumit' is not recognized...`** — a space in the Windows
   username (`Sumit C`) breaks any unquoted `system()`/`exec()` call inside
   PHP's stack. wp-phpunit's own bootstrap does exactly this
   (`system( WP_PHP_BINARY . ' ' . ... )`, unquoted, in
   `vendor/wp-phpunit/wp-phpunit/includes/bootstrap.php`). Quoting the value
   ourselves proved unreliable through the bash → cmd → PHP call chain.
   **Fix:** use the Windows 8.3 short path (no space, ever) for
   `WP_PHP_BINARY` instead: `C:\Users\SUMITC~1\...\php.bat`. Get a short path
   via PowerShell: `(New-Object -ComObject Scripting.FileSystemObject).GetFolder("<path>").ShortPath`.
   **Trap:** wp-phpunit's bootstrap looks for `wp-tests-config.php` next to
   *itself first* (`vendor/wp-phpunit/wp-phpunit/wp-tests-config.php`),
   before ever considering a worktree-root copy — if both exist, the vendor
   one wins silently. Fix both copies, or you'll edit the one that's ignored.
2. **`Error establishing a database connection`** — `DB_HOST => 'localhost'`
   (what the real site's `wp-config.php` uses, and what works for the
   browser-served site via Local's php-fpm) does not resolve from a plain
   CLI `php.exe` process here — likely a `mysqli.default_socket` mismatch
   between php-fpm's ini and the CLI ini. **Fix:** use the actual TCP port
   Local assigned this site (`AppData/Roaming/Local/sites.json` →
   `services.mysql.ports.MYSQL`, `10005` for this site) —
   `DB_HOST => '127.0.0.1:10005'`.

Also fixed as a real (not env) bug: `WP_REST_Request::set_body_params()`
returns `void` in WP core, so `(new WP_REST_Request(...))->set_body_params(...)`
silently evaluates to `null`. `tests/php/SnippetsRestTest.php` had this in 4
places (copied verbatim from the plan doc) — split into two statements
instead of chaining.
