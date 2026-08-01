# Handoff

## Just landed on main

Six pieces merged and pushed to `origin/main` (now at `c62a4ca`):

1. **Linked snippets backend** — `rawmark_snippet` post type, `_rawmark_linked`
   flag, placement finder, render-time composer (marker expansion +
   header/footer wrap), REST CRUD, link/unlink/delete + Snippets admin
   screen, per-page header/footer picker, editor screen accepting snippets.
2. **Rawmark on Posts (single post detail page)** — the flag/router/toggle/
   editor-lock/list-integration mechanism that used to be Page-only now
   accepts a Post too, via `PageFlag::ELIGIBLE_TYPES = ['page', 'post']`.
3. **"Save as Snippet" editor button** — calls the REST endpoint from (1),
   which had no UI calling it before.
4. **Editor UI chores** — "Edit with Rawmark" panel in the Gutenberg
   Document sidebar (`src/Admin/GutenbergPanel.php`, its own small esbuild
   entry point), a "View" button (real permalink, new tab), an "Insert
   Media" button (`wp.media()`, inserts at cursor as `<img>`/`<video>` in
   the HTML pane or a bare URL elsewhere).
5. **Snippet listing + header/footer over REST** — `GET /rawmark/v1/snippets`
   (every snippet + its linked state), `PUT /rawmark/v1/pages/{id}` now also
   accepts `headerSnippetId`/`footerSnippetId`. Backs an "Insert Snippet"
   picker and header/footer dropdowns *inside* the three-pane editor — they
   used to only exist on the classic wp-admin Page edit screen, which the
   normal "Edit with Rawmark" navigation path never actually visits.
6. **Post Template (shared single-post layout)** — a Snippet can be
   designated the site's Post Template (`rawmark_post_template_id` option,
   set/unset from the Snippets screen). Every *unflagged* Post then renders
   through it instead of the theme, with `<!-- rawmark:post_title -->` /
   `post_content` / `post_excerpt` / `post_date` / `featured_image` /
   `permalink` / `author_name` markers substituted for that post's real
   data (`post_content` runs through WordPress's real `the_content` filter
   chain — blocks/shortcodes/embeds all work). An individually-flagged Post
   still renders its own source and always wins over the template. A
   template pointing at a deleted/wrong-type Snippet fails safe straight
   back to the theme — `PostTemplate::is_set()` is the only gate anything
   needs, no guard duplicated anywhere else.

Design/plan docs (all local-only — see "docs/ is gitignored" below):
- `docs/superpowers/specs/2026-07-31-linked-snippets-design.md`
- `docs/superpowers/plans/2026-07-31-linked-snippets-backend.md`
- `docs/superpowers/specs/2026-08-01-rawmark-for-posts-design.md`
- `docs/superpowers/plans/2026-08-01-rawmark-for-posts.md`
- `docs/superpowers/plans/2026-08-01-save-as-snippet-ui.md`
- `docs/superpowers/specs/2026-08-01-post-template-design.md`
- `docs/superpowers/plans/2026-08-01-post-template.md`
(Pieces 4-5 above were small enough they were implemented directly from
conversation, no separate spec/plan doc — same bar item 1's original plan
used for "is this worth a doc" throughout this project.)

Verified with real `vendor/bin/phpunit` runs on the actual merged `main`
tree (not just `php -l`, not just on a feature branch): **131/131 PHP tests
green**, plus the JS suite (`npm test`) unaffected. Zero known failures
anywhere in the suite as of this handoff.

Every worktree and feature branch used to build the above
(`worktree-linked-snippets-backend`, `worktree-rawmark-posts-and-snippet-ui`,
`rawmark-editor-chores`, `post-template`) has been merged, verified on
`main`, and deleted — nothing left to clean up.

## Still needs a human: manual UI verification

Two things were built and unit-tested but **never actually clicked through
in a browser** — no browser automation tool was available this session:

**"Save as Snippet" button:**
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

**Post Template**, once you have at least one real blog post:
1. Rawmark → Snippets → build a small layout with `<!-- rawmark:post_title -->`
   and `<!-- rawmark:post_content -->` in it, click "Set as Post Template".
2. Visit a normal, never-flagged Post on the front end. Confirm it renders
   through the layout with the real title/content substituted in, not the
   theme.
3. Visit the Snippets screen again — confirm the ★ badge and "Unset
   Template" link show on the right row.
4. Individually flag a *different* Post with the existing per-post toggle,
   give it its own content. Confirm that one still renders its own content,
   not the template — the flag must win.
5. Click "Unset Template". Confirm the Post from step 2 goes back to the
   theme.

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
3. **The editor bundle is gitignored** (`assets/dist/`) — a merge or pull
   that changes `assets/src/editor/*.jsx`/`*.js` does **not** rebuild
   `assets/dist/editor.js` for you. Run `npm run build` after every pull
   that touches `assets/src/`, or the browser silently keeps serving a
   stale bundle. This bit once already this session (Save as Snippet button
   invisible after a merge until `npm run build` ran in the right
   directory).

Also fixed as a real (not env) bug: `WP_REST_Request::set_body_params()`
returns `void` in WP core, so `(new WP_REST_Request(...))->set_body_params(...)`
silently evaluates to `null`. Watch for this pattern anywhere a REST request
is built and dispatched in one chained expression — split into two
statements instead.
