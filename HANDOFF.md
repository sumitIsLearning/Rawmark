# Handoff

## Just landed on main

Three pieces merged and pushed to `origin/main` (now at `2916345`):

1. **Linked snippets backend** — `rawmark_snippet` post type, `_rawmark_linked`
   flag, placement finder, render-time composer (marker expansion +
   header/footer wrap), REST CRUD, link/unlink/delete + Snippets admin
   screen, per-page header/footer picker, editor screen accepting snippets.
2. **Rawmark on Posts (single post detail page)** — the flag/router/toggle/
   editor-lock/list-integration mechanism that used to be Page-only now
   accepts a Post too, via `PageFlag::ELIGIBLE_TYPES = ['page', 'post']`.
   Blog index/archive stays themed — out of scope, unchanged. Snippets and
   the header/footer picker were **not** extended to Posts — not asked for.
3. **"Save as Snippet" editor button** — the REST endpoint from (1) had no
   UI calling it. Added a topbar button (Page/flagged-Post editor only, not
   shown when editing a Snippet itself) that prompts for a name and calls
   `POST /rawmark/v1/snippets`.

Design/plan docs (all local-only — see "docs/ is gitignored" below):
- `docs/superpowers/specs/2026-07-31-linked-snippets-design.md`
- `docs/superpowers/plans/2026-07-31-linked-snippets-backend.md`
- `docs/superpowers/specs/2026-08-01-rawmark-for-posts-design.md`
- `docs/superpowers/plans/2026-08-01-rawmark-for-posts.md`
- `docs/superpowers/plans/2026-08-01-save-as-snippet-ui.md`

Verified with real `vendor/bin/phpunit` runs (not just `php -l`): **101/101
PHP tests green**, plus the JS suite (`npm test`, `tests/js/`) unaffected.
Zero known failures anywhere in the suite as of this handoff.

The worktree that did this work (`.claude/worktrees/linked-snippets-backend`,
branches `worktree-linked-snippets-backend` and
`worktree-rawmark-posts-and-snippet-ui`) has been removed and both branches
deleted — fully merged, nothing left to clean up.

## Still needs a human: manual UI verification

The "Save as Snippet" button (piece 3 above) was built and unit-tested at
the API-wrapper level, but **the actual click-through in a browser has not
been verified** — no browser automation tool was available this session.
Before trusting it in production, check:

1. Open a flagged Page in the Rawmark editor. Confirm "Save as Snippet"
   appears in the topbar and "Save draft"/"Publish" still work.
2. Click it, type a name, confirm. Confirm the green confirmation message
   appears with a working "View" link to Rawmark → Snippets, and the new
   snippet is actually there.
3. Open a Snippet in the same editor. Confirm "Save as Snippet" does **not**
   appear.
4. Type an unsaved change into a Page, click "Save as Snippet" without
   saving first. Confirm the "unsaved changes" browser dialog appears, and
   Cancel aborts cleanly (no snippet created).

Full detail: `docs/superpowers/plans/2026-08-01-save-as-snippet-ui.md`, Task 3 Step 7.

## `docs/` is gitignored — this project's convention, not an accident

Every design spec and implementation plan referenced above lives only on
this machine. `.gitignore` line 2 is bare `docs`. This was true before this
session too (the very first linked-snippets plan was never committed
either) — treat it as deliberate and keep writing plans there, not as
something to "fix" by force-adding.

## Environment gotchas (don't re-discover these)

Running `vendor/bin/phpunit` for this plugin from **outside Local's own
PHP-FPM runtime** (any plain Windows shell, including Local's own "Site
Shell") hits real bugs. Fixed this session in **untracked, machine-local
files present in the main plugin directory now** (`php.bat`,
`wp-tests-config.php`, and `vendor/wp-phpunit/wp-phpunit/wp-tests-config.php`)
— none of this is in git, so a fresh clone or a different machine hits it
again from scratch:

1. **`'C:\Users\Sumit' is not recognized...`** — the space in the Windows
   username (`Sumit C`) breaks any unquoted `system()`/`exec()` call
   anywhere in the PHP stack. wp-phpunit's own bootstrap does exactly this
   (`system( WP_PHP_BINARY . ' ' . ... )`, unquoted). Quoting the value
   ourselves proved unreliable through bash → cmd → PHP call chains.
   **Fix:** use the Windows 8.3 short path (no space, ever) for
   `WP_PHP_BINARY`, e.g. `C:\Users\SUMITC~1\...\php.bat`. Get one via
   PowerShell: `(New-Object -ComObject Scripting.FileSystemObject).GetFolder("<path>").ShortPath`.
   **Composer-bin traps, both real:**
   - `vendor/bin/phpunit.bat` sometimes calls bare `php` (fresh/default
     Composer install) and sometimes calls a project-customized
     `..\..\php.bat` wrapper — check which one you actually have before
     assuming `vendor\bin\phpunit.bat` alone will work. If it calls bare
     `php`, run `.\php.bat vendor\bin\phpunit` directly instead (note the
     `.\` — `cmd /c "php.bat ..."` without it fails to find a same-directory
     `.bat` that isn't on `PATH`).
   - wp-phpunit's own `bootstrap.php` looks for `wp-tests-config.php` next
     to *itself first* — `vendor/wp-phpunit/wp-phpunit/wp-tests-config.php`
     — before ever considering a project-root copy. If both exist, the
     vendor one silently wins. **Keep both copies in sync**, or you'll edit
     the one that's ignored and wonder why nothing changed.
2. **`Error establishing a database connection`** — `DB_HOST => 'localhost'`
   (what the real site's `wp-config.php` uses, and what works for the
   browser-served site via Local's php-fpm) does not resolve from a plain
   CLI `php.exe` process here — likely a `mysqli.default_socket` mismatch
   between php-fpm's ini and the CLI ini. **Fix:** use the actual TCP port
   Local assigned this site (`AppData/Roaming/Local/sites.json` →
   `services.mysql.ports.MYSQL` — `10005` for this site) —
   `DB_HOST => '127.0.0.1:10005'`.

Also fixed as a real (not env) bug: `WP_REST_Request::set_body_params()`
returns `void` in WP core, so `(new WP_REST_Request(...))->set_body_params(...)`
silently evaluates to `null`. Watch for this pattern anywhere a REST request
is built and dispatched in one chained expression — split into two
statements instead.
