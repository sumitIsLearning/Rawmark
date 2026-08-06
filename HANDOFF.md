# Handoff

## Just landed on main

Sixteen pieces merged and pushed to `origin/main` (now at `ae7fbad`, tagged
`v0.1.0`). First nine unchanged from the last handoff; 10-16 are new since
then. **Piece 16 is implemented and 199/199 PHP tests are green, but is not
yet committed** — see "Not yet committed" below.

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
   picker and header/footer dropdowns *inside* the three-pane editor.
6. **Post Template (shared single-post layout)** — a Snippet can be
   designated the site's Post Template (`rawmark_post_template_id` option,
   set/unset from the Snippets screen). Every *unflagged* Post then renders
   through it instead of the theme, with 7 `<!-- rawmark:post_xxx -->`
   merge tags substituted for that post's real data. An individually-flagged
   Post always wins over the template.
7. **Two real bugs found via live browser testing, both fixed:** a Snippet
   editing blank in the three-pane editor (wrong REST route called), and
   the Header/Footer/Insert Snippet `<select>` elements sharing broken CSS
   with the topbar buttons.
8. **Full-plugin security audit** — no unauthenticated write path, no SQL
   injection surface, every route capability- and nonce-checked. One real
   finding, fixed: `SnippetsController` required only `read_post` to copy a
   Page into a Snippet, now requires `edit_post`.
9. **`_planning/IMPLEMENTATION-STATUS.md`** — human-readable feature-status
   doc, local-only like the rest of `_planning/`.
10. **Header Template & Footer Template (site-wide defaults)** — same
    pattern as Post Template: a Snippet designated the site's Header
    Template and/or Footer Template from the Snippets screen. A flagged
    Code Page with no per-page header/footer of its own falls back to
    these; the per-page picker always wins when set. New generic
    `Rawmark\Storage\TemplateOption` holds the shared get/set/clear/is_set
    logic; `PostTemplate` was refactored to delegate to it (behavior
    unchanged, its test suite untouched). Scoped to real Code Pages only,
    never to Post-Template-rendered posts. Branch
    `worktree-header-footer-template`, 7 commits, merged and deleted.
11. **Create Snippet button** — `rawmark_snippet` had no "Add New" screen
    at all before this; the only creation path was "Save as Snippet" from
    an existing Page. A small name-input form now sits above the Snippets
    table, POSTs to a new `SnippetActions::ACTION_CREATE` handler (same
    admin_post/nonce shape as every other action on that screen), creates
    a blank published Snippet, and redirects straight into the editor.
    Branch `worktree-create-snippet-button`, 2 commits, merged and deleted.
12. **Editor: single Save button for a Snippet, no status pill** — the
    topbar used to show the Page-oriented "Save draft"/"Publish" pair and
    a Draft/Published pill for a Snippet too, even though a Snippet has no
    publish-state concept and both buttons already did the identical
    thing under the hood. Now gated on `objectType`: a Page keeps both
    buttons and the pill, a Snippet gets one plain "Save" button and no
    pill. Presentation-only change in `assets/src/editor/index.jsx`.
    Branch `worktree-snippet-editor-single-save`, 1 commit, merged and
    deleted.
13. **Post Loop marker** — a new paired marker,
    `<!-- rawmark:post_loop category='' tag='' count='' --> ... <!-- /rawmark:post_loop -->`,
    the first paired marker in the plugin (every other one is self-closing).
    Repeats its own inner HTML once per matching WP post, filtered by
    category/tag slug, `count` defaulting to 5 and clamped server-side to
    a max of 50. Reuses the existing 7 `PostDataTags` tags per post inside
    the loop body rather than inventing new syntax. New
    `Rawmark\Frontend\PostLoopTags::resolve()`, wired into `code-page.php`
    to run *before* the single-post `PostDataTags` pass (loop must
    consume its own tags first, or the single-post pass would stomp them
    with the page's own post data). Only resolves in a flagged Page/Post's
    own top-level source, same restriction `PostDataTags` already has.
    Also added: a cheap lint check in the editor
    (`assets/src/editor/index.jsx`, extends the existing bracket-balance
    `countLintIssues`) that flags a mismatched open/close `post_loop`
    count, since an unclosed loop is the one failure mode that doesn't
    fail safe — it renders the template once, literally, as page content.
    Branch `worktree-post-loop`, 3 commits, merged and deleted.
14. **`RAWMARK-GUIDE.html` + `SKILL.md`** — new user-facing docs at the
    repo root, **deliberately not committed** (user asked they stay
    local). `RAWMARK-GUIDE.html` is a styled doc-style reference (static
    vs. dynamic pages, all four dynamic mechanisms, marker syntax,
    naming/a11y conventions, the fail-safe rule, trust model, size
    limits) — also published as a Claude Artifact for easy viewing.
    `SKILL.md` is a condensed, Claude-skill-formatted version meant to be
    dropped into an AI assistant's skills directory so it writes valid
    Rawmark content instead of guessing. Both were run through the
    `humanizer` skill to strip AI-writing tells (em dashes especially —
    the doc had ~25 of them, now zero).

    **Found while writing these: `_planning/AUTHORING-CONVENTIONS.md` is
    stale.** It describes Snippets as static copies with a `{{variable}}`
    placeholder-substitution system. Neither is true of what's actually
    built — Snippets are live/linked (see item 9's MVP-doc reconciliation
    note, still applies), and no placeholder system exists anywhere in
    `src/`. The new docs were written from verified current source, not
    from that file. Worth a decision at some point: update or retire
    `AUTHORING-CONVENTIONS.md` so it stops being a trap for whoever reads
    it next (including a future AI session).

    **Also found:** `Source.php`'s `settings` shape includes `seo_title`,
    `seo_description`, `use_wp_head`, `use_wp_footer`, and
    `external_assets` — all read and normalized server-side, but *nothing
    in any REST controller or the editor UI ever writes them*. Confirmed
    by grep across `PagesController`/`SnippetsController`/`index.jsx`.
    Every page today effectively uses its real title, no meta description,
    and both WordPress hooks always firing — the fields exist in the data
    model with no way to change them from anywhere. Documented honestly
    in the new guide's "Not built yet" section rather than described as
    working.
15. **Tagged `v0.1.0`, first GitHub Release, zip distribution.** Tagged
    current `main` and pushed the tag, built `rawmark-v0.1.0.zip` via
    `git archive` (only tracked files go in — `vendor/`, `node_modules/`,
    `.claude/`, `_planning/`, `docs/` all excluded automatically, no
    manual filtering needed), created the release via `gh release create`
    with the zip attached
    (`sumitIsLearning/Rawmark` releases/tag/v0.1.0). The real download URL
    is now wired into `RAWMARK-GUIDE.html`'s "Download .zip" button,
    replacing the placeholder that shipped with item 14.
16. **WooCommerce Shop redirect setting.** WooCommerce's default Shop
    archive (`is_shop()`) renders through WooCommerce's own template
    loader regardless of what's in a Page's content — a flagged Rawmark
    Page can never replace it directly, only give a site owner somewhere
    else to point traffic. New `Rawmark → Settings` submenu
    (`src/Admin/SettingsScreen.php`, slug `rawmark-settings`, first
    screen the plugin has that isn't Snippets or the editor), gated
    entirely on `class_exists( 'WooCommerce' )` — the section, and the
    admin_post save handler, only exist for sites with WooCommerce active.
    A `wp_dropdown_pages()` picker stores the chosen Page's ID
    (`rawmark_shop_redirect_page_id` option, via new
    `Rawmark\Storage\ShopRedirect`, same get/set/clear/is_configured shape
    as `TemplateOption` but validating `post_type === 'page'` instead of
    Snippet) rather than a URL string, so a later slug change can't break
    it. New `Rawmark\Frontend\ShopArchiveRedirect` hooks
    `template_redirect` (the plugin's first use of that hook — `Router`
    only ever hooked `template_include`) and 301s to
    `get_permalink()` of the configured page when `is_shop()` is true,
    guarded by `function_exists( 'is_shop' )` so it's a no-op with
    WooCommerce inactive. Scope: Shop archive only. Single Product pages
    have the same underlying problem but need a per-product mapping, not
    one global setting — explicitly out of scope for this pass, left for
    a future feature. No existing "Settings" tab actually existed before
    this — the task assumed one; investigation found only the Snippets
    screen, so this is a new submenu, not a new section on an old one.

Design/plan docs (all local-only — see "docs/ is gitignored" below):
- `docs/superpowers/specs/2026-07-31-linked-snippets-design.md`
- `docs/superpowers/plans/2026-07-31-linked-snippets-backend.md`
- `docs/superpowers/specs/2026-08-01-rawmark-for-posts-design.md`
- `docs/superpowers/plans/2026-08-01-rawmark-for-posts.md`
- `docs/superpowers/plans/2026-08-01-save-as-snippet-ui.md`
- `docs/superpowers/specs/2026-08-01-post-template-design.md`
- `docs/superpowers/plans/2026-08-01-post-template.md`
- `docs/superpowers/specs/2026-08-04-header-footer-template-design.md`
- `docs/superpowers/plans/2026-08-04-header-footer-template.md`
- `docs/superpowers/specs/2026-08-04-create-snippet-button-design.md`
- `docs/superpowers/plans/2026-08-04-create-snippet-button.md`
- `docs/superpowers/specs/2026-08-04-snippet-editor-single-save-design.md`
- `docs/superpowers/plans/2026-08-04-snippet-editor-single-save.md`
- `docs/superpowers/specs/2026-08-04-post-loop-design.md`
- `docs/superpowers/plans/2026-08-04-post-loop.md`
(Pieces 4-5 above were small enough they were implemented directly from
conversation, no separate spec/plan doc — same bar item 1's original plan
used for "is this worth a doc" throughout this project.)

Verified with real `vendor/bin/phpunit` runs on the actual merged `main`
tree, every time, after every merge: **180/180 PHP tests green**, plus
**5/5 JS tests** (`npm test`, `vitest`) green. Zero known failures
anywhere in the suite as of this handoff.

Every worktree and feature branch used to build pieces 1-6 and 10-13
(`worktree-linked-snippets-backend`, `worktree-rawmark-posts-and-snippet-ui`,
`rawmark-editor-chores`, `post-template`, `worktree-header-footer-template`,
`worktree-create-snippet-button`, `worktree-snippet-editor-single-save`,
`worktree-post-loop`) has been merged, verified on `main`, and deleted.
Pieces 7-9 and 14-15 were small enough (or not code at all) to work
directly against `main`, no branch — nothing left to clean up either way.

## Not yet committed

Piece 16 (WooCommerce Shop redirect setting) exists only in the working
tree as of this handoff: `src/Storage/ShopRedirect.php`,
`src/Frontend/ShopArchiveRedirect.php`, `src/Admin/SettingsScreen.php`,
`tests/php/ShopRedirectTest.php`, `tests/php/ShopArchiveRedirectTest.php`,
`tests/php/SettingsScreenTest.php` are all untracked; `src/Plugin.php` has
an uncommitted diff wiring the two new services in. No branch, no commit —
next session should commit (and branch first, per this project's usual
pattern) before doing anything else with these files.

## Still needs a human: manual UI verification

No browser automation tool has been available in any session so far —
everything below is unverified in an actual browser, PHPUnit/vitest green
is not the same claim.

**Post Template — verified working**, live, by the user, in an earlier
session: title/date/author/content all resolved correctly on a real post
rendered through a real template. Still true, nothing since has touched
this path.

**Still pending, never clicked through live:**
1. **"Save as Snippet" button** (unchanged from every prior handoff — see
   `docs/superpowers/plans/2026-08-01-save-as-snippet-ui.md`, Task 3 Step 7
   for the full 4-step checklist).
2. **Header Template / Footer Template** — set one from the Snippets
   screen, confirm the badge shows, confirm a flagged Page with no
   per-page header/footer picks it up; confirm a per-page override still
   wins when both are set.
3. **Create Snippet button** — type a name, click Create, confirm it
   lands in the editor with three empty panes and the typed title.
4. **Snippet editor single Save button** — open a Snippet, confirm no
   status pill and exactly one "Save" button; open a Page, confirm both
   buttons and the pill are unchanged.
5. **Post Loop** — build a `post_loop` block with a real `category`/`tag`
   filter, confirm the right posts render in real DOM output; type an
   unclosed `post_loop` marker, confirm the editor's lint indicator flags
   it.
6. **WooCommerce Shop redirect** — never clicked through live, and no
   WooCommerce install exists in this dev environment at all (confirmed:
   not in `composer.json`, no `WooCommerce` class anywhere in the repo).
   On a real WooCommerce site: confirm `Rawmark → Settings` is invisible
   with WooCommerce deactivated; confirm the section appears and the page
   picker lists real Pages once active; pick a Page, save, visit the
   default `/shop/` URL, confirm a real 301 to that Page's actual
   permalink; rename the target Page's slug afterward and confirm the
   redirect follows it (the whole point of storing a Page ID instead of a
   URL).

## `docs/` and `_planning/` are both gitignored — this project's convention, not an accident

Every design spec and implementation plan referenced above lives only on
this machine. `.gitignore` lines 1-2 are `_planning/` and bare `docs`.
Treat it as deliberate and keep writing plans there, not as something to
"fix" by force-adding. `RAWMARK-GUIDE.html` and `SKILL.md` (item 14) are
a different case: they live at the repo root, outside both ignored
directories, and are untracked by explicit user choice ("don't commit
it"), not by `.gitignore`. If that changes, they're ready to `git add`
as-is. The open question about `_planning/IMPLEMENTATION-STATUS.md`
(piece 9) is still unresolved, and `_planning/AUTHORING-CONVENTIONS.md`'s
staleness (item 14) is a second, newer open question in the same
directory.

## Pending, outside this repo: a future snippet-sharing docs app

Not part of Rawmark itself. The user wants to eventually build a separate
Next.js app — a free public documentation/snippet-sharing site sharing
common Rawmark snippets (e.g. the Post Template layout) with good design.
Not scoped, not started, no repo yet. A pointer to this lives in the
user's global `~/.claude/CLAUDE.md` and in this project's own Claude
memory (`future_goal_snippet_docs_app.md`) — both say to come back here
and to `_planning/IMPLEMENTATION-STATUS.md` for context before scoping
it. Nothing to do here unless that work actually starts.

## Distribution: v0.1.0 is on GitHub Releases now

Item 15 above set up the actual release mechanism:
`github.com/sumitIsLearning/Rawmark/releases/tag/v0.1.0`, zip asset
attached. Next version: bump `Version:` in `rawmark.php` and
`RAWMARK_VERSION` in the same file, `git tag -a vX.Y.Z`, push the tag,
`git archive --format=zip --prefix=rawmark/ -o rawmark-vX.Y.Z.zip vX.Y.Z`,
`gh release create vX.Y.Z <zip> --title vX.Y.Z --notes "..."`, then swap
the download URL in `RAWMARK-GUIDE.html`'s `.dl-btn` `href`.

## Environment gotchas (don't re-discover these)

Running `vendor/bin/phpunit` for this plugin from **outside Local's own
PHP-FPM runtime** (any plain Windows shell, including Local's own "Site
Shell") hits real bugs. Fixed in **untracked, machine-local files present
in the main plugin directory** (`php.bat`, `wp-tests-config.php`, and
`vendor/wp-phpunit/wp-phpunit/wp-tests-config.php`) — none of this is in
git, so a fresh clone or a different machine hits it again from scratch:

1. **`'C:\Users\Sumit' is not recognized...`** — the space in the Windows
   username (`Sumit C`) breaks any unquoted `system()`/`exec()` call
   anywhere in the PHP stack. **Fix:** use the Windows 8.3 short path (no
   space, ever) for `WP_PHP_BINARY`, e.g. `C:\Users\SUMITC~1\...\php.bat`.
   Get one via PowerShell:
   `(New-Object -ComObject Scripting.FileSystemObject).GetFolder("<path>").ShortPath`.
   **Composer-bin traps, both real:**
   - `vendor/bin/phpunit.bat` sometimes calls bare `php` and sometimes
     calls a project-customized `..\..\php.bat` wrapper — check which one
     you actually have. If it calls bare `php`, run
     `.\php.bat vendor\bin\phpunit` directly instead (note the `.\`).
   - wp-phpunit's own `bootstrap.php` looks for `wp-tests-config.php` next
     to *itself first* — before ever considering a project-root copy.
     **Keep both copies in sync.**
2. **`Error establishing a database connection`** — `DB_HOST => 'localhost'`
   does not resolve from a plain CLI `php.exe` process here. **Fix:** use
   the actual TCP port Local assigned this site
   (`AppData/Roaming/Local/sites.json` → `services.mysql.ports.MYSQL` —
   `10005` for this site) — `DB_HOST => '127.0.0.1:10005'`.
3. **`assets/dist/` is committed to git, not gitignored** (as of `16d02f9`)
   because the plugin's only real-world install method has been zipping
   the raw repo folder, which never included the built bundle otherwise.
   `assets/dist/` on disk does not auto-update on `git pull` or branch
   switch — a source change to `assets/src/editor/*.jsx`/`*.js` needs
   `npm run build` run and its output committed in the *same* commit, same
   discipline as a lockfile.
4. **New worktrees have no `vendor/`, no `php.bat`, no `wp-tests-config.php`,
   and Composer is not on `PATH` inside the Bash tool** — `composer` isn't
   found even though it clearly built the main checkout's `vendor/` at
   some point. Every worktree this session copied these four items
   straight from the main checkout instead of reinstalling:
   `cp php.bat`, `cp wp-tests-config.php`, `cp -r vendor`,
   `cp composer.lock`. `npm install` works fine directly in a fresh
   worktree, no equivalent workaround needed there.
5. **`vendor\bin\phpunit tests/php/SomeTest.php` (a bare file path) fails
   with "Class SomeTest could not be found"**, even though the file is
   real and passes when discovered normally. `phpunit.xml`'s testsuite
   uses `<directory suffix="Test.php">` discovery, and every test class
   in this project is named `Test_Something` (underscore prefix), not
   `SomethingTest` — passing a file path directly makes PHPUnit assume
   the class name matches the filename instead, which is wrong here.
   **Fix:** always run `vendor/bin/phpunit --filter Test_Class_Name`, and
   let the testsuite directory discovery find the file.
6. **A `--filter` value containing a regex pipe (`Test_A|Test_B`) silently
   breaks when `php.bat` is invoked from Git Bash** — the pipe gets
   interpreted somewhere in the bash → cmd → `.bat` chain instead of
   reaching PHPUnit as a literal regex character, and the second half of
   the alternation gets executed as its own command
   (`'Test_Footer_Template' is not recognized...`). **Fix:** run each
   filter separately instead of combining them with `|`.

Also fixed as a real (not env) bug: `WP_REST_Request::set_body_params()`
returns `void` in WP core, so `(new WP_REST_Request(...))->set_body_params(...)`
silently evaluates to `null`. Watch for this pattern anywhere a REST request
is built and dispatched in one chained expression — split into two
statements instead.
