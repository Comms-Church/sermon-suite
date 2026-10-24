# Apple Design Audit — Sermon Suite

Audit of this plugin against the `apple-design` skill (`.claude/skills/apple-design/SKILL.md`), 2026-10-24. Section references (e.g. §16 *Agency*) point to headings in that skill. The audit of all five Comms.Church plugins is in the shared doc: https://claude.ai/artifact/GGvMskv9nX4bc82HuR4wmS

## Overview

The five plugins have 61 design issues between them: 4 Critical, 13 High, 20 Medium and 24 Low. The worst ones lose people's work or leave them stuck, not visual polish: an editor that hangs on a failed save, sermon edits lost without warning, a confirmation that never fires, and future event dates deleted with no warning.

Each finding gives **What** is wrong, **Why** it matters (citing the section and heading of the vendored `apple-design` skill, e.g. §16 *Agency*), and a **Fix** in the plugin's own stack: plain CSS, jQuery or plain JS, and WordPress PHP. Severity is ranked by harm to the person using it:

- **Critical:** data loss, or a broken layout most visitors will see
- **High:** blocks a task, excludes keyboard or screen-reader users, or shows a wrong state
- **Medium:** friction, unclear feedback, or contrast below WCAG minimums
- **Low:** polish and consistency

The skill is mostly about gesture-driven motion (springs, flicks, momentum). These plugins have no draggable UI, so most citations come from §1 Response, §7 Spatial consistency, §12 Materials, §14 Reduced motion & accessibility, §15 Typography and §16 Design foundations.

### Worst issues across all plugins

| Rank | Plugin | Finding | Severity | Fixed in this pass |
| --- | --- | --- | --- | --- |
| 1 | Recurring Events | Changing a pattern permanently deletes future dates without warning | Critical | In part (warning added) |
| 2 | Sermon Suite | A failed save leaves the editor stuck on "Saving…" | Critical | Yes |
| 3 | Sermon Suite | No warning before leaving with unsaved edits | Critical | Yes |
| 4 | PCO TEC | Events without a featured image show an empty gray box | Critical | Yes |
| 5 | PCO TEC | "Force Full Re-sync" never asks for confirmation | High | Yes |
| 6 | PCO | Add-to-Calendar menu opens upward and can't be used from the keyboard | High | Yes |
| 7 | Sermon Suite, PCO TEC | No visible keyboard focus | High | Yes |
| 8 | PCO | Generator preview doesn't match the live page | High | Yes |
| 9 | Groups | Filtering changes the page silently | High | Yes |
| 10 | Groups, PCO, PCO TEC | Cards lift on hover but aren't links | High / Medium / Low | Yes in Groups and PCO; TEC still open |

A pattern across the suite: Groups and PCO already handle reduced motion, focus rings and container sizing well, while Sermon Suite and PCO TEC don't. A shared base stylesheet for focus, press and reduced-motion styles would stop these drifting apart again.

## Sermon Suite

Sermon Suite has 17 findings. The worst two are in the admin editor: a failed save leaves the button stuck on "Saving…", and there is no warning before leaving with unsaved edits. Files: `public/css/sermons.css`, `public/js/sermons.js`, `templates/single-ss_sermon.php`, `includes/shortcodes.php`, `admin/custom-editor.php`, `public/css/admin.css`.

### 1. A failed save traps the editor (Critical)

**What:** `#gcc-save-btn` posts with `$.post(..., success)` and has no failure handler. On a timeout, a 500 or a nonce expiry, the button stays disabled on "Saving…" for good. When an error does come back, its toast fades after 3 s, the same as a success, and the toast has no live region, so screen readers never announce it.

**Why:** §16 Design foundations, *Feedback comes in four kinds: status, completion, warning, error*. Errors must be reported, not dropped. §16 *Wayfinding*: "Never trap the user."

**Fix** (`admin/custom-editor.php`, both editors):

```js
$.post(ajaxurl, payload)
  .done(function(res){
    if (res.success) { /* existing success branch */ }
    else { toast('Error: ' + res.data, 'error'); }
  })
  .fail(function(){
    toast('Couldn’t save — check your connection and try again. Your changes are still on this page.', 'error');
  })
  .always(function(){ btn.prop('disabled', false).text('Save Sermon'); });

function toast(msg, type){
  var $t = $('#gcc-toast');
  clearTimeout(toast.timer);
  $t.stop(true, true).text(msg)
    .removeClass('gcc-feedback-success gcc-feedback-error')
    .addClass('gcc-feedback-' + type).show();
  // Errors stay until dismissed; only success auto-hides.
  if (type !== 'error') toast.timer = setTimeout(function(){ $t.fadeOut(); }, 3000);
}
```

In the markup, add `role="status" aria-live="polite"` to `#gcc-toast`, and dismiss on click.

### 2. Edits are lost without warning (Critical)

**What:** The plugin sends every sermon and series edit to its own editor instead of the WordPress editor. That drops WordPress's built-in "Leave site? Changes you made may not be saved" warning. Closing the tab or clicking a menu link throws away a half-written sermon with no warning.

**Why:** §16 *Agency*: keep people in control and "back it with forgiveness — easy undo for slips."

**Fix:**

```js
var dirty = false;
$('.gcc-custom-editor').on('input change', ':input', function(){ dirty = true; });
$(window).on('beforeunload', function(e){ if (dirty) { e.preventDefault(); return ''; } });
// in the save .done() success branch, before any redirect:
dirty = false;
```

### 3. Keyboard users can't see where they are (High)

**What:** `sermons.css` has no `:focus` or `:focus-visible` rule at all. Every card, chip, row and nav card depends on the theme's outline, and many themes remove it. The play overlay and row arrow only appear on `:hover`, so keyboard and touch users never see them. In the admin, `.gcc-input:focus` sets `outline: none` and replaces it with a 10%-opacity ring (`rgba(26,58,92,0.1)`), which is effectively invisible.

**Why:** §16 *Flexibility*: design "for the full range of abilities." §1 *Response*: feedback must arrive the moment input does, and focus is a keyboard user's version of that feedback.

**Fix** (`public/css/sermons.css`):

```css
.ss-message-list-wrap :is(a, button):focus-visible,
.gcc-archive-wrap :is(a, button):focus-visible,
.ss-sermon-page :is(a, button):focus-visible,
:is(.gcc-sermon-card, .gcc-series-card, .ss-topic-tile, .ss-tax-series-row, .gcc-tag, .ss-topic-chip):focus-visible {
  outline: 2px solid var(--gcc-accent);
  outline-offset: 2px;
}
.gcc-card-thumb-wrap:focus-visible .gcc-play-btn,
.gcc-sermon-row-item:focus-visible .gcc-sermon-row-play { opacity: 1; }
```

In the admin, keep the border change and make the ring visible: `box-shadow: 0 0 0 3px rgba(37,99,235,.35)`.

### 4. Motion ignores reduced-motion settings (High)

**What:** Cards lift `translateY(-3px)`, series art zooms `scale(1.03)` over 0.3 s, topic tiles nudge, row arrows slide, and chevrons rotate. Nothing checks `prefers-reduced-motion`, `prefers-contrast` or `prefers-reduced-transparency`.

**Why:** §14 *Reduced motion & accessibility*: "Respond to three independent signals and bake them into your components." Replace movement with opacity and color changes.

**Fix:**

```css
@media (prefers-reduced-motion: reduce) {
  .gcc-sermon-card, .gcc-series-card, .ss-topic-tile,
  .gcc-series-card-img img, .gcc-sermon-row-arrow, .ss-notes-chevron, .gcc-notes-arrow {
    transition-property: opacity, color, background-color, border-color !important;
  }
  .gcc-sermon-card:hover, .gcc-series-card:hover, .ss-topic-tile:hover,
  .gcc-series-card:hover .gcc-series-card-img img,
  .gcc-sermon-row-item:hover .gcc-sermon-row-arrow { transform: none; }
}
@media (prefers-contrast: more) {
  .gcc-sermon-card, .gcc-series-card { box-shadow: none; border: 1px solid var(--gcc-text); }
  .gcc-hero-overlay, .gcc-series-hero-overlay { background: rgba(0,0,0,.85); }
}
```

### 5. Text size ignores the reader's browser setting (High)

**What:** All front-end type is `calc(var(--gcc-fs) * n)`, and `--gcc-fs` is printed as a fixed pixel value (15–21 px) in `sermon-suite.php`. A reader who has raised their browser's default font size gets no larger sermon text. The code comment says this is deliberate, to stop themes that set `html { font-size: 62.5% }` from shrinking text.

**Why:** §15 *Typography*: "Respect the user's text-size setting (Dynamic Type). Scale layout *with* the text — spacing in `rem`/`em`, not fixed px."

**Fix:** keep the pixel value as a floor and let `rem` scale above it. This still defeats the 62.5% trick.

```php
$base_rem = $base_px / 16;
echo "  --gcc-fs: max({$base_px}px, {$base_rem}rem);\n";
```

### 6. Filter chips highlight the wrong topic after load (High)

**What:** The PHP already renders the selected chip with `.active`. Then `sermons.js` re-applies `.active` by substring match: `href.indexOf('topic=' + activeTopic)`. With `?topic=faith`, the *Faithfulness* chip lights up too, so two chips look selected. The change also happens after first paint, so the highlight visibly jumps.

**Why:** §16 *Familiarity*: "things that look the same must behave the same." Two highlighted chips claim two active filters when there's only one. §1 *Response*: the state should be right on first paint, with no late change.

**Fix:** delete the "Filter tag active state" block from `public/js/sermons.js`. The server-rendered class is already correct.

### 7. Hover looks the same as selected, and nothing reacts on press (Medium)

**What:** `.gcc-tag:hover` and `.gcc-tag.active` share one rule, so hovering a chip makes it look selected. No interactive element in either stylesheet has an `:active` state, so a tap gets no feedback until the next page loads.

**Why:** §1 *Response*: "Respond on pointer-down, not on release." §16 *Familiarity*: a selected chip and a hovered one must look different.

**Fix:**

```css
.gcc-tag:hover { border-color: var(--gcc-accent); color: var(--gcc-accent); }
.gcc-tag.active { background: var(--gcc-accent); border-color: var(--gcc-accent); color: var(--gcc-button-text); }
:is(.gcc-tag, .ss-topic-chip, .gcc-watch-btn, .gcc-hero-watch-btn, .gcc-hero-resource-btn,
    .gcc-resource-item, .ss-view-series-btn, .ss-pagination .page-numbers):active {
  transform: scale(0.97);
  transition: transform 100ms ease-out;
}
```

### 8. Two overlapping style systems that contradict each other (Medium)

**What:** The older `gcc-*` components and the newer `ss-*` ones both style the same parts (notes toggle, back link, series nav, resources) with different sizes. Some rules are defined twice with different values: `.gcc-related-sermons`, `.gcc-related-heading` and `.gcc-resource-item`. `.gcc-topic-badge-light` uses `composes:`, a CSS Modules keyword that browsers ignore, so it renders with no padding, size or radius. Corner radii come in nine values: 3, 4, 5, 6, 8, 10, 12, 20 and 24 px.

**Why:** §16 *Craft*: "Nothing is random — every spacing, timing, and alignment value is a deliberate choice you can defend." §16 *Familiarity*: be consistent.

**Fix:** add a radius scale to `:root` (`--gcc-radius-xs: 4px; --gcc-radius-sm: 6px; --gcc-radius: 10px; --gcc-radius-pill: 999px`) and map every literal onto it. Merge the duplicate blocks, keeping the later one. Replace `composes:` with a real selector list:

```css
.gcc-topic-badge, .gcc-topic-badge-light { /* shared badge rules */ }
.gcc-topic-badge-light { background: rgba(255,255,255,.2); color: #fff; }
```

### 9. Headings have no tracking and the type scale is inconsistent (Medium)

**What:** The 34–41 px weight-800 titles (`.ss-sermon-title`, `.gcc-hero-title`, `.gcc-series-hero-content h1`, `.ss-tax-title`) keep default letter-spacing, so they read loose. In grid cards, the sermon title is 1.4× (24 px) inside a 210 px column, larger than the series card title at 1.25×. The card description is 1.08×, larger than the single-sermon description at 1.0×.

**Why:** §15 *Typography*: "Large display text wants *negative* tracking". "Build hierarchy from weight + size + leading as a set."

**Fix:**

```css
.ss-sermon-title, .gcc-sermon-title, .gcc-hero-title, .ss-tax-title,
.gcc-series-hero-content h1 { letter-spacing: -0.02em; line-height: 1.1; }
.gcc-card-title { font-size: calc(var(--gcc-fs, 17px) * 1.1); letter-spacing: -0.01em; }
.gcc-card-desc  { font-size: calc(var(--gcc-fs, 17px) * 0.95); }
```

### 10. Hero text over photos loses contrast (Medium)

**What:** On the Latest Message and series heroes, the text sits on a gradient over a photo, and the meta, series and description lines are muted with `opacity: 0.8–0.85`. Over a light photo, the top of the gradient is nearly clear and those lines become hard to read.

**Why:** §12 *Materials & depth*, Vibrancy: "don't use flat gray text — use higher-contrast, slightly heavier weight" over changing backgrounds.

**Fix:** keep the text fully opaque and let a stronger scrim do the separating:

```css
.gcc-hero-overlay { background: linear-gradient(to top, rgba(0,0,0,.85) 0%, rgba(0,0,0,.55) 55%, rgba(0,0,0,.15) 100%); }
.gcc-hero-meta, .gcc-hero-series, .gcc-hero-desc, .gcc-series-hero-meta { opacity: 1; color: rgba(255,255,255,.92); font-weight: 500; }
```

### 11. Unlabelled video players and emoji used as icons (Medium)

**What:** The YouTube iframes have no `title`, so screen readers announce "frame". The interface mixes hand-drawn SVG icons with emoji and text glyphs: 📖, ▶, →, ✕, ✅, 🔌, 📄. Screen readers read them aloud ("open book", "rightwards arrow"), and they render differently on every OS.

**Why:** §16 *Craft*: "clear iconography". §16 *Familiarity*: "things that look the same must behave the same", so one icon language.

**Fix:** add `title="<?php echo esc_attr( get_the_title() ); ?> — video"` to each iframe. Replace each emoji or glyph with the template's existing inline SVGs, marked `aria-hidden="true"`. Give `.gcc-remove-resource` the attribute `aria-label="Remove resource"`.

### 12. Transitions animate every property (Low)

**What:** `--gcc-transition: 0.2s ease` is used as `transition: var(--gcc-transition)`, which means `all`. Admin CSS uses `transition: all` 14 times. Border width, padding and color all tween, and some of that is layout work.

**Why:** §11 *Frame-level smoothness*: "Animate only compositor-friendly properties — `transform` and `opacity`."

**Fix:** name the properties: `transition: background-color .2s ease, border-color .2s ease, color .2s ease, transform .2s ease;`.

### 13. Touch targets are too small (Low)

**What:** Pagination buttons are 36 px tall, filter chips about 30 px, topic badges about 20 px, and the admin remove button 28 px.

**Why:** §10 *Gesture design details*: "Add \~10px of hysteresis/hit padding around the target." §16 *Flexibility*: "iPhone = quick touch."

**Fix:** `min-height: 44px` on `.ss-pagination .page-numbers` and `.gcc-tag`. For small badges, keep the visual size and grow the hit area with `position: relative` and `::after { content:''; position:absolute; inset:-8px; }`.

### 14. Confirm and alert dialogs are overused (Low)

**What:** A Sermon Shots import can fire up to three `confirm()` dialogs in a row (description, guide, transcript). Empty-field checks use `alert()`.

**Why:** §16 *Agency*: confirmation only for genuinely destructive actions, "use sparingly; overusing it trains people to click through." Tactical rule: "validate inline (not on submit)."

**Fix:** collect the fields that would be overwritten and ask once ("Replace Description, Discussion Guide and Transcript?"). Show validation as an inline `.gcc-field-error` under the input with `aria-describedby`.

### 15. The notes chevron animates but the body snaps (Low)

**What:** `.ss-notes-chevron` rotates over 0.2 s while `.ss-notes-body` jumps open with `hidden`, so the motion and the content don't match.

**Why:** §17 *Process*: "Design interaction and visuals together." §7 *Spatial consistency*: content should come from the control that revealed it.

**Fix:** wrap the body in a `display:grid; grid-template-rows:0fr → 1fr` transition (200 ms) and keep `hidden` only under `prefers-reduced-motion`.

### 16. Hard-coded colors ignore the brand settings (Low)

**What:** The placeholder gradient (`#2563eb → #2d6a9f`), the `.gcc-series-view-all:hover` color `#dce8f5`, and `#ebebeb` are literals, so they stay blue whatever brand color a church picks. The admin UI hard-codes `#2563eb` and ignores the WordPress admin color scheme.

**Why:** §16 *Craft*: "colors that adapt."

**Fix:** `background: linear-gradient(135deg, var(--gcc-accent), color-mix(in srgb, var(--gcc-accent) 70%, #000));` and `color-mix(in srgb, var(--gcc-accent) 15%, white)` for hover tints. In the admin, use `var(--wp-admin-theme-color, #2563eb)`.

### 17. Each sermon card has two links to the same page (Low)

**What:** `.gcc-sermon-card` links the thumbnail and the title to the same permalink, so keyboard users tab through every card twice.

**Why:** §16 *Simplicity*: "Every element earns its place."

**Fix:** add `tabindex="-1" aria-hidden="true"` to `.gcc-card-thumb-wrap`, or make the whole card one link with the title as its accessible name.

## Fixes applied in this pass

I applied 29 of the 61 fixes in full and 5 more in part, covering every Critical and High finding, on the `claude/quirky-feynman-2ppasd` branch of each repo. Each repo also got the `apple-design` skill and a Markdown copy of its section, both under `.claude/`, which the release zip already excludes.

| Plugin | Applied in full | Applied in part | Still open |
| --- | --- | --- | --- |
| Sermon Suite | 1, 2, 3, 4, 5, 6, 7 | 8 (`composes:` bug only), 9 (heading tracking only), 11 (iframe titles, remove-button label) | 10, 12–17 |
| Groups | 1, 2, 3, 4, 7 | — | 5, 6, 8–12 |
| PCO | 1, 2, 3, 4, 7, 8 | — | 5, 6, 9–12 |
| PCO TEC | 1, 2, 3, 4, 5, 6, 7, 8 | — | 9–12 |
| Recurring Events | 4, 5, 8 | 1 (warning only), 2 (summary line only) | 3, 6, 7 |

Two changes need a second opinion before going further:

- **Recurring Events #1:** I added the warning but kept `wp_delete_post( …, true )`. Switching to `wp_trash_post()` changes what `children_of()` sees, so it needs its own change and test.
- **PCO #1:** making the menu drop down meant removing `overflow: hidden` from `.ccpco-card`. The image now rounds its own corners. Check a theme that adds a border to cards.

**Verification.** The repos' Playwright suites couldn't run here: the environment's network policy blocks `wordpress.org` and `playground.wordpress.net`, which WordPress Playground needs to boot. Instead, every edited PHP file passes `php -l` and every edited script passes `node --check`. A headless-Chromium harness loaded each plugin's real CSS and JS against markup copied from its renderer and passed 39 of 39 checks. Those covered the menu's direction, focus and keyboard behavior and that the card no longer clips it; the Groups count, reset and focus return; the TEC confirm and Syncing state; the recurrence summary text; and the Sermon Suite chip, badge and focus fixes. Run `npm test` in each repo locally before merging.
