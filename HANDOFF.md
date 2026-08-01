# Handoff

## Just landed on main

Nine pieces merged and pushed to `origin/main` (now at `6336e37`). First six unchanged from the last handoff; 7-9 are new since then:

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
7. **Two real bugs found via live browser testing, both fixed:**
   - Editing a Snippet in the three-pane editor showed blank HTML/CSS/JS
     panes. The editor always called `GET`/`PUT /rawmark/v1/pages/{id}`,
     even for a Snippet — `PagesController` 404s on a Snippet ID (it's not
     `PageFlag`-eligible), so the load silently failed. Now branches on
     `objectType` to the Snippet REST routes instead. A Snippet's title is
     read-only in the editor now too — there's still no rename endpoint,
     so it would otherwise look editable and silently do nothing on save.
   - The Header/Footer/Insert Snippet `<select>` elements looked visually
     broken next to the pill-shaped topbar buttons — a full second copy of
     every style value instead of sharing the button's own rule. Fixed by
     grouping the selectors in CSS so both pull from one source of truth.
8. **Full-plugin security audit**, static analysis, all of `src/`,
   `templates/`, and `assets/src/`. Result: no unauthenticated write path,
   no SQL injection surface (`$wpdb` is never used), no dynamic code
   execution, every REST route and `admin-post.php` handler capability-
   and nonce-checked. One real finding, fixed same session: creating a
   Snippet from a Page only required *read* access to that page, not
   *edit* access — harmless today since `rawmark_edit_code` defaults to
   Administrator-only, but would become exploitable the moment a future
   release grants that capability to a lower role. `SnippetsController`
   now requires `edit_post`. See `_planning/IMPLEMENTATION-STATUS.md`
   section 6 for the plain-language summary, or ask for the full report
   (it was produced in-conversation, not saved as its own file).
9. **`_planning/IMPLEMENTATION-STATUS.md`** — a new, human-readable
   feature-status document: what's built, organized by feature, plus an
   explicit reconciliation against `MVP-Feature-List.md` where this
   session's actual implementation diverged from that original plan (most
   notably: Snippets ended up *live/linked*, not the static copy-on-insert
   the MVP doc originally called for). **Currently local-only, like the
   rest of `_planning/`** (see below) — flagged to the user as a decision
   point (human-facing doc vs. AI-process doc) but not yet resolved either
   way.

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
tree (not just `php -l`, not just on a feature branch): **132/132 PHP tests
green**, plus the JS suite (`npm test`) unaffected. Zero known failures
anywhere in the suite as of this handoff.

Every worktree and feature branch used to build pieces 1-6
(`worktree-linked-snippets-backend`, `worktree-rawmark-posts-and-snippet-ui`,
`rawmark-editor-chores`, `post-template`) has been merged, verified on
`main`, and deleted. Pieces 7-9 were small enough to commit directly to
`main`, no branch — nothing left to clean up either way.

## Still needs a human: manual UI verification

**Post Template — verified working**, live, by the user, this session:
title/date/author/content all resolved correctly on a real post rendered
through a real template. The 5-step checklist that used to live here is
done; no longer pending.

**"Save as Snippet" button — still not clicked through in a real browser.**
No browser automation tool was available this session, and it hasn't come
up in manual testing yet either:
1. Open a flagged Page in the Rawmark editor. Confirm "Save as Snippet"
   appears in the topbar and "Save draft"/"Publish" still work.
2. Click it, type a name, confirm. Confirm the green confirmation message
   appears with a working "View" link to Rawmark → Snippets, and the new
   snippet is actually there.
3. Open a Snippet in the same editor. Confirm "Save as Snippet" does **not**
   appear, and — new since the last handoff — confirm the panes actually
   show that snippet's real HTML/CSS/JS (this was broken and is now fixed,
   see piece 7 above; worth double-checking live).
4. Type an unsaved change into a Page, click "Save as Snippet" without
   saving first. Confirm the "unsaved changes" browser dialog appears, and
   Cancel aborts cleanly (no snippet created).

Full detail: `docs/superpowers/plans/2026-08-01-save-as-snippet-ui.md`, Task 3 Step 7.

## `docs/` and `_planning/` are both gitignored — this project's convention, not an accident

Every design spec and implementation plan referenced above lives only on
this machine. `.gitignore` lines 1-2 are `_planning/` and bare `docs`. This
was true before this session too (the very first linked-snippets plan was
never committed either) — treat it as deliberate and keep writing plans
there, not as something to "fix" by force-adding. The one open question:
whether `_planning/IMPLEMENTATION-STATUS.md` (piece 9 above) should be an
exception, since — unlike everything else in `_planning/`/`docs/` — it's
written for a human reader, not an AI session. Not yet decided.

## Pending, outside this repo: a future snippet-sharing docs app

Not part of Rawmark itself. The user wants to eventually build a separate
Next.js app — a free public documentation/snippet-sharing site sharing
common Rawmark snippets (e.g. the Post Template layout) with good design.
Not scoped, not started, no repo yet. A pointer to this lives in the
user's global `~/.claude/CLAUDE.md` (so any future session, in any
project, discovers it) and in this project's own Claude memory
(`future_goal_snippet_docs_app.md`) — both say to come back here
(`HANDOFF.md`) and to `_planning/IMPLEMENTATION-STATUS.md` for context
before scoping it. Nothing to do here unless that work actually starts.

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
