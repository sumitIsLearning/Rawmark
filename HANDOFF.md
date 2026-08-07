# Handoff

## Just landed on main

Eighteen pieces merged and pushed to `origin/main` (now at `2bca8a2`,
tagged `v0.3.0`). First nine unchanged from the last handoff; 10-18 are
new since then. **Item 18
(Gutenberg block rendering, for SureCart Cart/Checkout) is implemented,
unit-tested, and now committed/tagged/released, but still NOT verified
live in a real browser** - see "In progress: still needs a live check"
below, read that section first if picking this up.

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
17. **Fix: plugin deletion fataled on every attempt since v0.1.0.**
    `uninstall.php` requires every class dependency by hand (the
    autoloader never loads in that context) and was missing
    `src/Storage/TemplateOption.php` — a dependency `PostTemplate::clear()`
    picked up when the Header/Footer Template refactor (item 10) landed,
    but this file was never updated for it. Every real deletion from
    wp-admin fataled mid-script (`Class "Rawmark\Storage\TemplateOption"
    not found`), which WordPress surfaces as "Deletion failed: There has
    been a critical error on this website." Root cause confirmed against
    a real production error log, not just reasoning about the code. One
    added `require_once`, fixed. **Tagged and released as `v0.2.0`** along
    with item 16 — see "Distribution" below.
18. **Gutenberg block rendering (opt-in) — implemented, committed, shipped
    as `v0.3.0`, still NOT verified live.** SureCart's Cart and Checkout
    blocks have no shortcode alternative (unlike Shop/Product) -
    Rawmark's render path only ever called `do_shortcode()`, never
    `do_blocks()`, so pasted Cart/Checkout block markup would render as
    inert HTML comments. Full detail and exact status in "In progress:
    still needs a live check" immediately below - read that section
    before touching this, not just this summary.

## In progress: still needs a live check - Gutenberg block rendering

Committed and tagged as `v0.3.0` (`src/Frontend/Router.php`,
`src/Storage/Source.php`, `templates/code-page.php`,
`tests/php/RendererTest.php`, `tests/php/StorageTest.php` all touched).
Implemented against a plan the user had already independently
investigated and handed over as confirmed findings (see that session's
conversation for the full reasoning trail - not saved as a file in this
repo, checked: `rawmark-surecart-integration.md` does not exist here, it
was a pointer to reasoning that lived elsewhere, not a real file to
read). Shipped in this state deliberately, so it can be downloaded and
tested against the real installed SureCart site rather than blocked
waiting on a browser-automation tool that isn't available in this
environment - see "Still needed - the actual live check, not done"
further down.

**What's actually done, mechanically verified:**
1. `Source.php` - new `enable_blocks` boolean setting, same
   `normalize_settings()` shape/pattern as `external_assets`, defaults
   `false`.
2. `templates/code-page.php` - `do_blocks()` now runs on the composed
   HTML before `do_shortcode()`, gated on `settings['enable_blocks']`.
   Called directly (not via `apply_filters('the_content', ...)`), so
   `do_blocks()`'s own `doing_filter('the_content')` check stays false
   and its wpautop-removal side effect never fires - confirmed by reading
   `wp-includes/blocks.php:2527` directly in this WP core install
   (7.0.2), not assumed.
3. `Router.php` - `dequeue_theme_assets()` normally strips
   `wp-block-library`/`wp-block-library-theme`/`global-styles`/
   `core-block-supports` from every Code Page unconditionally. Now skips
   stripping those four specific handles when the current page (or its
   Post Template) has `enable_blocks` on - new private
   `current_page_enables_blocks()` re-derives the same `source_id` logic
   `code-page.php` uses. Nothing else this function strips changed - the
   separate theme-asset-URI-matching loop further down is untouched and
   still runs unconditionally regardless of this flag (confirmed to the
   user directly when asked: this carve-out does not let the active
   theme's own stylesheet load, only WP-core/theme.json-derived block
   CSS).
4. `StorageTest.php` / `RendererTest.php` - new tests, **204/204 green**
   including these. One real snag surfaced and fixed *in the test code*,
   not the feature: `wp_deregister_style()` (pre-existing in Router,
   untouched by this change) has a process-lifetime side effect once any
   test exercises the default "strip" path, the handle is gone from the
   global registry for the rest of the PHPUnit run - later tests can't
   naively check "is it still registered" against real WP-core state.
   Fixed by having the "kept when enabled" test re-register the handle
   itself under test control rather than trusting whatever the shared
   process left behind. Worth remembering for any future test in this
   suite that touches global `$wp_styles`/`$wp_scripts` state.

**What's NOT done - this is the actual gap, not a formality:**

No browser-automation tool is available in this environment (checked via
`ToolSearch` this session - only Figma design tools came back, no
Playwright/Chrome DevTools MCP). The task's acceptance test was explicit
and mandatory: flag a real Page, turn `enable_blocks` on, paste **real**
SureCart Cart block markup exported from the actual installed SureCart
site (not hand-written block comments), and in a real browser confirm
structural layout, add-to-cart, quantity update, item removal, and a full
Checkout flow. None of that happened. The session was interrupted
mid-attempt to pull real Cart block markup out of the live site's
database (looking for the MySQL port via `sites.json`, the same technique
the "Environment gotchas" section below already documents for the test
DB) to stage the test page for a human to click through - that pull never
completed.

**Steps 1-3 done this session (2026-08-07), step 4 still needs a human:**

1. **Real markup pulled from the live install**, not hand-written - no
   `wp` CLI on `PATH`, so pulled via a direct `mysqli` connection using
   `127.0.0.1:10005` (the `sites.json` port, same fix as the test-DB
   gotcha below) against the real `local` database. Two real findings
   from that query, worth knowing before testing:
   - **There is no standalone SureCart "Cart" page.** SureCart's cart is
     a slide-out drawer (`surecart/slide-out-cart` block), stored as
     `wp_template_part` post ID 139, injected globally rather than routed
     to its own URL. Pages titled "Cart"/"Checkout" (IDs 97/98,
     `woocommerce_cart_page_id`/`woocommerce_checkout_page_id`) are
     leftover **WooCommerce** pages using `[woocommerce_cart]` - a
     different plugin, not SureCart, and not what this feature targets.
   - The real SureCart checkout page is ID 135
     (`surecart_checkout_page_id` option, slug `checkout-2`), content is
     exactly `<!-- wp:surecart/checkout-form {"id":134} -->
     <!-- /wp:surecart/checkout-form -->`.
2. **Throwaway Page 140 created and staged**, flagged, `enable_blocks`
   set true via `Source::save()` directly (bootstrapped WP from the CLI:
   `DB_HOST` predefined as `127.0.0.1:10005` before `wp-load.php` so
   wp-config's `localhost` define is ignored, same trick
   `wp-tests-config.php` already uses; needed `-d memory_limit=1024M`,
   the CLI SAPI's default 128M isn't enough to bootstrap WP with
   WooCommerce + SureCart + Rank Math all active - this fataled silently
   into `debug.log`, not stdout, until raised). URL:
   `http://wordpress-plugin-lab.local/rawmark-surecart-block-test-throwaway/`.
   HTML pane holds both the real checkout-form markup and the real
   slide-out-cart markup back to back.
3. Real markup pasted into that page - done as part of step 2.

**Still needed - the actual live check, not done:**
4. In an actual browser: open Page 140, confirm the checkout form renders
   and functions. The slide-out cart is a drawer, not inline content -
   confirm whatever normally triggers it (add an item from a real product
   page first) still opens it correctly with this markup pasted directly
   rather than coming from its usual template-part injection point;
   confirm quantity update, item removal, and total recalculation; then
   confirm the checkout-form block completes a real order.
5. Only after step 4 passes live: promote item 18 out of "in progress" in
   this file's "Just landed on main" list for real (it has NOT been
   promoted yet - v0.3.0 below ships the code un-verified-live so it can
   be tested by downloading and installing fresh, not because the live
   check happened).

No browser-automation tool has been available in any session so far
(checked again this session via `ToolSearch` - still only `WebFetch` and
a Figma page-capture tool, no Playwright/Chrome DevTools MCP), so step 4
still needs a human. If a future session picks this up cold: the code is
believed correct (matches an already-investigated plan, unit tests pass,
one specific WP core behavior was verified by reading the actual source
rather than assumed, and the test page now holds real exported markup
rather than hand-written block comments), but "should work based on the
mechanism" and "confirmed working in a real browser against the real
installed SureCart site" are still different claims, and only the second
one means this is actually done.

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

## Next ecom platform: SureCart, not just WooCommerce

The user is now building the actual store with **SureCart** (installed
locally, v4.6.3), not WooCommerce alone. Investigated by reading the real
installed plugin source, not just its docs (one doc claim turned out
wrong against the real code - see below). Findings for the next session:

- **Shop/product-list page is a real, editable WP Page by default**
  (`Shop — Sample Page`), not a forced archive template the way
  WooCommerce's Shop page is. `[sc_product_list]` is a genuine
  `add_shortcode()` registration - no redirect-style workaround needed
  for this part at all.
- **Every product-detail piece is a real shortcode too** -
  `sc_product_title`, `sc_product_price`, `sc_product_media`,
  `sc_product_cart_button`, `sc_product_variant_choices`,
  `sc_product_description`, plus `sc_product_page` (renders a whole
  product page by ID). Confirmed in
  `wp-content/plugins/surecart/app/src/WordPress/Shortcodes/ShortcodesServiceProvider.php`
  - real `add_shortcode()` calls, rendered internally via `do_blocks()`,
  not dependent on `the_content` or theme context. Rawmark's
  `do_shortcode()`-only render pass (`templates/code-page.php:60`) handles
  these fine, same mechanism that already makes WooCommerce shortcodes work.
- **Doc inaccuracy caught by reading the real code:** SureCart's own docs
  say the `id` attribute breaks in v3+, use `product_id` instead. The
  actual installed code
  (`ShortcodesService.php::registerBlockShortcodeByName()`, ~line 91)
  accepts both and prefers `id` if both are given. Don't trust that doc
  page.
- **Individual product URLs still go through SureCart's own template
  engine** - same class of problem as WooCommerce's `single-product.php`,
  not yet verified against SureCart's actual routing code the way the
  shortcode claims above were. The existing "Single Product mapping" gap
  noted for WooCommerce (per-product Rawmark pages, not one global
  setting) would need the same treatment for SureCart, scoped to SureCart
  product IDs - not built, not scoped yet.
- **Unverified, flagged not confirmed broken:** SureCart's shortcode
  callback calls `wp_enqueue_global_styles()`; Rawmark's
  `Router::dequeue_theme_assets()` strips the `global-styles` handle from
  every Code Page by design. Likely harmless - SureCart's product widgets
  are Stencil web components that mostly self-style - but needs an actual
  visual check once a SureCart shortcode is live inside a Rawmark page,
  not just static-code reasoning.

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
6. **WooCommerce Shop redirect** — never clicked through live. WooCommerce
   *is* installed in this environment (`wp-content/plugins/woocommerce`),
   unlike when this was first built (PHPUnit tests still stub `is_shop()`
   and `WooCommerce`, since the test DB has no reason to activate it).
   Confirm `Rawmark → Settings` is invisible with WooCommerce deactivated;
   confirm the section appears and the page picker lists real Pages once
   active; pick a Page, save, visit the default `/shop/` URL, confirm a
   real 301 to that Page's actual permalink; rename the target Page's slug
   afterward and confirm the redirect follows it (the whole point of
   storing a Page ID instead of a URL).
7. **Plugin deletion** — confirmed fixed against the real error log that
   caused item 17 above, but never re-attempted live through wp-admin
   after the fix. Deactivate, click Delete, confirm it actually succeeds
   this time with no "critical error" notice.

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

## Distribution: v0.3.0 is on GitHub Releases now

`github.com/sumitIsLearning/Rawmark/releases/tag/v0.3.0` - zip and
`SKILL.md` both attached as release assets (same two files
`RAWMARK-GUIDE.html`'s two `.dl-btn` links point at). Shipped specifically
so item 18 (Gutenberg block rendering) could be downloaded and tested
against the real installed SureCart site, since no browser-automation
tool is available in this environment to verify it live directly - see
item 18 above.

Process, same as v0.1.0/v0.2.0's: bump `Version:` in `rawmark.php` and
`RAWMARK_VERSION` in the same file, commit, `git tag -a vX.Y.Z`, push the
tag, `git archive --format=zip --prefix=rawmark/ -o rawmark-vX.Y.Z.zip
vX.Y.Z`, `gh release create vX.Y.Z <zip> SKILL.md --title vX.Y.Z --notes
"..."`, then swap both download URLs in `RAWMARK-GUIDE.html`'s two
`.dl-btn` `href`s (not committed - it's untracked by design, see below).

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
