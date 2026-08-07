---
name: rawmark-authoring
description: Use whenever writing or editing HTML, CSS, or JS for a Rawmark Code Page, Snippet, Post Template, or Post Loop — a WordPress plugin that renders standalone documents from raw hand-authored panes. Load this before writing a line of Rawmark content; it prevents the most common wrong guesses (full HTML documents, invented placeholder syntax, unnecessary JS for dynamic content).
---

# Rawmark Authoring Conventions

Rawmark renders a Code Page as a **standalone HTML document** — no theme
header, footer, stylesheet, or block-library CSS. You write HTML, CSS, and
JS into three separate panes; Rawmark wraps them into the final document.
Everything below is verified against the plugin's actual source, not a
roadmap or a wishlist.

## The three panes — hard rules

- **HTML pane:** body content only. Never write `<!DOCTYPE>`, `<html>`,
  `<head>`, or `<body>` — Rawmark supplies those. Start with one root
  element (`<main>` is the usual right answer).
- **CSS pane:** plain CSS, no `<style>` tag. No preprocessor, no build
  step — write exactly what should ship.
- **JS pane:** plain JavaScript, no `<script>` tag. Runs as a classic
  script at the end of `<body>` (DOM already parsed — no
  `DOMContentLoaded` needed). Not a module: `import` doesn't work, use a
  `<script src>` tag in the HTML pane for a library. Wrap your code in an
  IIFE:

  ```js
  (function () {
    "use strict";
    // your code
  })();
  ```

## Static vs. dynamic — the one distinction that matters

**Default to static.** Write exact HTML/CSS/JS; exactly that renders,
nothing substituted. The JS pane is entirely optional — leave it empty
unless there's real client-side behavior needed: toggling a nav, animating
on scroll, validating a form, fetching after load.

**Never reach for JS to show WordPress content.** Pulling in a post's
title, a list of recent posts, a shared header — all of that is server-
side, resolved before the page ever reaches a browser, via the markers
below. If a task sounds like "show the latest posts" or "insert this
post's title," it is a marker, not a `fetch()` call.

## Markers — always an HTML comment, always single-quoted attributes

The comment wrapper is deliberate: it can never collide with a template
syntax like `{{ }}` that might legitimately appear in pasted content.

**Insert a Snippet** (self-closing):
```html
<!-- rawmark:snippet id='42' -->
```
Live, not copied — the marker resolves to the Snippet's *current* content
on every render. There is no `{{placeholder}}` token system on Snippets;
don't invent one.

**Post data, inside a Post Template or a Post Loop body** (self-closing,
7 tags only — do not invent an 8th):
```html
<!-- rawmark:post_title -->
<!-- rawmark:post_content -->
<!-- rawmark:post_excerpt -->
<!-- rawmark:post_date -->
<!-- rawmark:featured_image -->
<!-- rawmark:permalink -->
<!-- rawmark:author_name -->
```

**Post Loop** (paired — the only paired marker in the system):
```html
<!-- rawmark:post_loop category='news' tag='featured' count='5' -->
  <h2><!-- rawmark:post_title --></h2>
  <p><!-- rawmark:post_excerpt --></p>
  <a href="<!-- rawmark:permalink -->">Read more</a>
<!-- /rawmark:post_loop -->
```
- `category` / `tag`: post slugs, both optional (omit for "any").
- `count`: optional, defaults to 5, clamped to 50 server-side no matter
  what's written.
- **Always write the closing `<!-- /rawmark:post_loop -->`.** An unclosed
  loop doesn't fail safe — the template renders once, literally, as plain
  content. This is the one place a mistake is silently visible instead of
  silently absent.
- Loops don't nest. Don't place a `post_loop` inside another one.
- Only valid in a flagged Page/Post's own top-level source — not inside a
  Snippet that gets inserted elsewhere, not in a header/footer Snippet.

## Shortcodes

WordPress `[shortcode]` syntax expands anywhere in a flagged Page/Post's
rendered HTML — its own source, a Snippet, a header/footer — via
`do_shortcode()`. Not the full `the_content` filter chain, so `wpautop()`
never touches your markup. This is what lets a plugin's own shortcode
(WooCommerce's `[woocommerce_cart]`, for example) render and stay
interactive inside otherwise hand-authored HTML.

- The live preview renders server-side through the real pipeline, so
  shortcodes and blocks (with Render blocks on) do expand in it. What it
  can't run is block JavaScript: the iframe is sandboxed without
  `allow-same-origin`, so ES modules and their REST calls fail. Judge
  layout and styles in the preview; judge interactions (add to cart,
  variant pills, quantity stepper) on the live URL.
- A shortcode runs whatever PHP its plugin registered for it — same
  trust boundary as the rest of a pane, see Trust model below.

## WooCommerce

Rawmark supplies markup/CSS on top of WooCommerce. It never reads or
writes WooCommerce's data model — no products, orders, or settings.

- **Cart / Checkout / My Account:** already just a shortcode as page
  content. Flag the page, keep `[woocommerce_cart]` /
  `[woocommerce_checkout]` / `[woocommerce_my_account]` in the HTML pane,
  wrap in whatever markup the design needs. `wp_head()`/`wp_footer()`
  fire by default, so WooCommerce's AJAX cart/checkout JS keeps working.
- **Shop / Single Product:** WooCommerce's own template loader
  (`archive-product.php` / `single-product.php`) ignores page content on
  these no matter what — don't flag WooCommerce's default Shop page and
  expect it to change anything. Build a fresh Page instead: flag it, use
  `[products limit="12" columns="4"]` for a grid or
  `[product_page id="107"]` for a single product's buy box.

## SureCart (and other block-only shortcodes)

`do_shortcode()` alone doesn't cover everything — some plugins only
register a Gutenberg block, no shortcode fallback. SureCart's Cart and
Checkout are like this (`surecart/checkout-form`, `surecart/slide-out-cart`
and friends). For a flagged page with an `enable_blocks` setting turned on
(off by default), Rawmark also runs `do_blocks()` on the composed HTML
before `do_shortcode()`, so pasted block-comment markup
(`<!-- wp:surecart/checkout-form {"id":134} --><!-- /wp:surecart/checkout-form -->`)
renders and functions instead of sitting inert as HTML comments.

- Toggled by the **Render blocks** checkbox in the editor topbar (Pages
  and Snippets both). For a Post Template, it's the *Snippet's* checkbox
  that governs the rendered product/post, since that's where the source
  comes from.
- Only turn it on for a page that actually pastes block-comment markup
  needing it. Leave it off otherwise — `do_blocks()` is extra rendering
  work every page doesn't need.
- Block markup must be **copied from the real block editor's output**
  (or exported from the live install), not hand-written. The JSON in a
  block comment's attributes is exact and easy to get wrong by guessing.

### SureCart product pages, at scale

SureCart products are their own post type (`sc_product`), not the built-in
`post`. Post Template's list of eligible types is a site-wide whitelist
(`Rawmark → Settings`, default: `post`, plus `sc_product` automatically
once SureCart is active). Check `sc_product`, write one Post Template
Snippet, and every product renders through it — present and future, no
per-product page, no hardcoded UUID.

**Wrap the product markup in a real `surecart/product-page` block.** This
is the part that is easy to get wrong:

```html
<!-- wp:surecart/product-page -->
<main class="shop__product">
  <p class="shop__eyebrow">Shop</p>
  <!-- wp:surecart/product-title /-->
  <!-- wp:surecart/product-selected-price-amount /-->
  <!-- wp:surecart/product-quantity /-->
  <!-- wp:surecart/product-buy-buttons /-->
</main>
<!-- /wp:surecart/product-page -->
```

Your own HTML and classes inside the wrapper pass straight through
untouched — this is still hand-authored markup, not a page builder. Turn
**Render blocks** on in the editor topbar for the Snippet (that is the
`enable_blocks` setting; without it the block comments stay inert).

**Why the wrapper is mandatory.** `surecart/product-page` is what supplies
the product context, calls `wp_interactivity_state()`, emits the
`<form data-wp-interactive>` element, and enqueues SureCart's
`@surecart/product-page` script module. Its child blocks declare it in
`"ancestor"` and produce nothing useful without it.

**Shortcodes are not a substitute here**, despite reading like one:

| Shortcode | Standalone? |
|---|---|
| `[sc_product_title]`, `[sc_product_description]` | Works — reads the PHP global |
| `[sc_product_price]`, `[sc_product_media]`, `[sc_product_quantity]`, `[sc_product_cart_button]` | **Renders empty / dead** — needs the ancestor |

That split is the trap: the page looks built because the title shows up,
while the price is blank and add-to-cart does nothing. If you find
yourself writing those four shortcodes standalone, use the block wrapper
instead. `[sc_product_list]` and `[sc_product_page id='…']` are
self-contained and fine anywhere.

**Self-closing syntax is exact.** The parser only sees a void block when
the slash touches the closer: `<!-- wp:surecart/product-media /-->`.
`/ -->` or `media/ -->` (space anywhere around the slash) parses as plain
text — the block silently never renders, and a half-mangled comment can
leak onto the page as visible text. If a block "doesn't render," diff its
comment character for character against a known-good one first.

**Container blocks need their children.** `surecart/product-buy-buttons`
is only a wrapper div; the buttons are nested `surecart/product-buy-button`
blocks. `<!-- wp:surecart/product-buy-buttons /-->` alone renders an empty
container (reads as "buy button missing"). Same for
`product-collection-tags` / `product-collection-tag`,
`product-variant-pills` / `product-variant-pill`, and
`product-price-chooser` / `product-price-choice-template`:

```html
<!-- wp:surecart/product-buy-buttons -->
<div class="wp-block-surecart-product-buy-buttons wp-block-buttons is-layout-flex">
  <!-- wp:surecart/product-buy-button {"add_to_cart":true,"text":"Add To Cart"} /-->
  <!-- wp:surecart/product-buy-button {"text":"Buy Now","className":"is-style-outline"} /-->
</div>
<!-- /wp:surecart/product-buy-buttons -->
```

**Preview as: match the blocks.** When previewing a product template,
pick an `sc_product` in the picker, not a same-named WooCommerce product
(the Woo one renders every SureCart block empty). The optgroup label is
the tell.

**Blocks that wait for data self-hide.** Variant pills, price chooser,
ad-hoc amount, trial/fee lines, price interval: empty unless the product
has that data. Reviews show zeros until the first review; Related
Products stays empty while the store has one product. Empty means no
data, not broken.

**Contain the media slider.** Cap the outer `.sc-image-media`, never the
inner `figure.wp-block-surecart-product-media`, and use
`minmax(0, 1fr)` for the grid tracks it sits in (bare `1fr` stretches to
the slider's intrinsic width and pushes the image off screen):

```css
.product__container { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.product .sc-image-media { max-width: 520px; margin: 0 auto; }
.product .sc-image-media img { max-height: 560px; object-fit: contain; }
```

- **There is one Post Template Snippet, shared across every type checked
  in the whitelist** — not one per type. Checking both `post` and
  `sc_product` points blog posts and products at the exact same Snippet.
  Either write that one Snippet so it's sane for both (rare), or only
  check one type at a time.
- An individually flagged post or product still wins over the template,
  same rule as everywhere else in Rawmark.

## The fail-safe rule

Everywhere else, a broken or missing reference resolves to **nothing** —
never a fatal error. A Snippet marker pointing at a deleted Snippet, an
unset Header/Footer Template, an unset Post Template, a `post_loop`
`category` that matches zero posts: all render empty, on purpose. Don't
add defensive fallback content for these cases unless asked — empty is
the correct, expected behavior, not a bug to guard against.

## Naming & formatting

- Class names: kebab-case, `block__element--modifier` (BEM-ish).
- Give each page's root a short prefix (`lp__`, `pricing__`).
- **Never use the `rawmark-` prefix** for your own classes — reserved for
  classes Rawmark itself adds.
- Two-space indent, in all three panes.
- Attributes lowercase, double-quoted.
- A comment banner above each major section — in a single-file page with
  no folder structure, comments are the only navigation available.

## Accessibility baseline (Rawmark enforces none of this — you own it)

- Exactly one `<h1>`, heading levels descend without skipping.
- Landmarks: `<main>`, plus `<nav>`/`<header>`/`<footer>` where relevant.
- Every image has `alt` (`alt=""` for decorative, never omitted).
- Every input has a real `<label>`, not a placeholder standing in.
- Visible focus states — never `outline: none` with nothing replacing it.
- Text contrast at least 4.5:1.
- Interactive elements are `<button>`/`<a>`, never a `<div>` with a click handler.

## Trust model

Editing panes requires the `rawmark_edit_code` capability
(Administrator-only by default). **Pane output is not sanitized** — same
trust level as a theme's PHP file, not a comment field. A pane can also
reference any registered shortcode, which runs whatever PHP that
shortcode's plugin defined. Write accordingly; don't add sanitization the
plugin itself deliberately omits, and don't assume a lower-trust caller
could reach these panes.

## Size limits

Soft warning at 256 KB combined; hard rejection above 1 MB per pane or
2 MB combined. Keep generated/minified blobs out of a single pane if it
can be avoided — long unbroken lines are the one thing that makes the
editor feel slow.

## What does not exist — don't write code assuming it does

- No placeholder/token substitution on Snippets.
- No shared/global site-wide stylesheet feature.
- No MCP or AI-agent abilities registered by the plugin itself.
- Snippets cannot be renamed from the editor once created (title field is
  read-only there).
- SEO title/description and the `wp_head`/`wp_footer` toggles exist in the
  data model but have no editor UI and are not writable over REST — every
  page uses its real post/page title, no meta description, and both hooks
  always fire. (`enable_blocks` used to be in this list; it now has the
  **Render blocks** checkbox.)
