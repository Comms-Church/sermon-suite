// Release smoke suite — green, or no release.
// Runs against WordPress Playground with the plugin active and
// tests/blueprint.json seed data (1 series, 2 sermons, a /sermons/ page).
const { test, expect } = require('@playwright/test');

// PHP problems surface as text in the page body, not HTTP errors.
const PHP_ERRORS = /Fatal error|Parse error|Warning: |Notice: |Deprecated: /;

async function expectClean(page) {
  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(PHP_ERRORS);
}

test.describe('REST API', () => {
  for (const route of ['series', 'sermons', 'topics', 'speakers', 'campuses', 'categories']) {
    test(`/wp-json/sermon-suite/v1/${route} responds with JSON`, async ({ request }) => {
      const res = await request.get(`/wp-json/sermon-suite/v1/${route}`);
      expect(res.status()).toBe(200);
      expect(Array.isArray(await res.json())).toBe(true);
    });
  }

  test('seeded sermons come back with meta', async ({ request }) => {
    const sermons = await (await request.get('/wp-json/sermon-suite/v1/sermons')).json();
    expect(sermons.length).toBeGreaterThanOrEqual(2);
    const titles = JSON.stringify(sermons);
    expect(titles).toContain('Grace That Holds');
  });
});

test.describe('Public pages', () => {
  test('front page renders without PHP errors', async ({ page }) => {
    await page.goto('/');
    await expectClean(page);
  });

  test('shortcode page renders archive, hero, and series grid', async ({ page }) => {
    await page.goto('/?pagename=sermons');
    await expectClean(page);
    const body = await page.locator('body').innerText();
    // Seeded content must actually appear — this catches broken queries,
    // not just fatals. The hero shows the latest sermon; the archive and
    // grid show the series ([ss_sermon_archive] is a series archive).
    expect(body).toContain('Built on the Rock');
    expect(body).toContain('Test Series: Foundations');
    // Raw shortcode text in output means a shortcode failed to register.
    expect(body).not.toContain('[ss_');
  });

  test('series detail page lists its sermons', async ({ page, request }) => {
    const series = await (await request.get('/wp-json/sermon-suite/v1/series')).json();
    await page.goto(series[0].permalink || series[0].link || `/?p=${series[0].id}`);
    await expectClean(page);
    const body = await page.locator('body').innerText();
    expect(body).toContain('Grace That Holds');
    expect(body).toContain('Built on the Rock');
  });

  test('single sermon page renders', async ({ page, request }) => {
    const sermons = await (await request.get('/wp-json/sermon-suite/v1/sermons')).json();
    const url = sermons[0].permalink || sermons[0].link || `/?p=${sermons[0].id}`;
    await page.goto(url);
    await expectClean(page);
  });
});

test.describe('Fixed theme headers', () => {
  // Bricks' transparent sticky header is fixed over the top of every page.
  // Over our templates it covered the back link and swallowed its clicks.
  test('the sermon page starts below a fixed header laid over it', async ({ page, request }) => {
    const sermons = await (await request.get('/wp-json/sermon-suite/v1/sermons')).json();
    await page.goto(sermons[0].permalink || sermons[0].link || `/?p=${sermons[0].id}`);
    const offset = () => page.evaluate(() =>
      document.documentElement.style.getPropertyValue('--ss-header-offset'));
    expect(await offset(), 'no fixed header, so no offset').toBe('');

    await page.evaluate(() => {
      // Reach 60px into our page, wherever the test theme's own header leaves it.
      const reach = document.querySelector('.ss-sermon-page').getBoundingClientRect().top + window.scrollY + 60;
      const h = document.createElement('div');
      h.style.cssText = `position:fixed;top:0;left:0;right:0;height:${reach}px;z-index:999`;
      document.body.prepend(h);
      window.dispatchEvent(new Event('resize'));
    });
    await expect.poll(offset).not.toBe('');

    const back = page.locator('.ss-back-link');
    const onTop = await back.evaluate((el) => {
      const r = el.getBoundingClientRect();
      const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
      return el === hit || el.contains(hit);
    });
    expect(onTop, 'the header still covers the back link').toBe(true);
  });
});

test.describe('Sermon cards', () => {
  test('a card links straight to its sermon\'s discussion guide', async ({ page, request }) => {
    const series = await (await request.get('/wp-json/sermon-suite/v1/series')).json();
    const seed = series.find((s) => (s.title?.rendered || s.title) === 'Test Series: Foundations');
    await page.goto(seed.permalink || seed.link || `/?p=${seed.id}`);

    const card = page.locator('.gcc-sermon-card', { has: page.locator('.gcc-card-title', { hasText: 'Grace That Holds' }) });
    const chip = card.locator('.gcc-resource-study');
    await expect(chip).toHaveText('Discussion Guide');
    expect(await chip.getAttribute('href')).toMatch(/#discussion-guide$/);

    await chip.click();
    await expect(page.locator('#discussion-guide .ss-notes-toggle')).toHaveAttribute('aria-expanded', 'true');
  });
});

test.describe('Messages in this series', () => {
  // The list scrolls inside its card, so in a long series the current message
  // used to sit out of sight below the first few. Builds a 12-message series
  // (back-dated so it never takes the [ss_latest_hero] slot), numbers it with
  // Bulk Edit the way a church would, then opens message 10.
  test('a long series opens scrolled to the current message, without moving the page', async ({ page }) => {
    test.setTimeout(180_000);
    await page.goto('/wp-admin/admin.php?page=sermon-suite-import');
    const nonce = await page.evaluate(() => window.sermonSuiteAdmin?.restNonce);
    expect(nonce).toBeTruthy();

    const runId = Date.now();
    const seriesName = `Long Series ${runId}`;
    const titles = Array.from({ length: 12 }, (_, i) => `Long Series ${runId} Message ${i + 1}`);
    const ids = [];
    for (const [i, title] of titles.entries()) {
      const res = await page.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
        headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
        // Only the first names the series; it creates it. The rest join via Bulk Edit.
        data: { sermon_title: title, date: `2018-01-${String(i + 1).padStart(2, '0')}`,
                ...(i === 0 ? { series_name: seriesName } : {}) },
      });
      expect(res.ok(), `publish ${title} failed: ${res.status()}`).toBe(true);
      ids.push((await res.json()).post_id);
    }
    const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
    const seriesId = all.find((s) => s.id === ids[0]).series_id;
    expect(seriesId).toBeGreaterThan(0);

    // Search for this run's titles: other suites leave enough unfiled sermons
    // to push ours onto page 2 of "No series".
    await page.goto(`/wp-admin/edit.php?post_type=ss_sermon&post_status=all&ss_series_filter=none&s=${encodeURIComponent(seriesName)}`);
    for (const title of titles.slice(1)) {
      // Exact match: "Message 2" is also a substring of "Message 12".
      await page.locator('#the-list tr', { has: page.locator('.row-title', { hasText: new RegExp(`^${title}$`) }) })
        .locator('input[type=checkbox]').check();
    }
    await page.locator('#bulk-action-selector-top').selectOption('edit');
    await page.locator('#doaction').click();
    await page.locator('#bulk-edit select[name="ss_bulk_series"]').selectOption(String(seriesId));
    await page.locator('#bulk-edit #bulk_edit').click();
    await page.waitForLoadState('networkidle');

    const listState = () => page.evaluate(() => {
      const list = document.querySelector('.ss-series-list');
      const cur = list.querySelector('.is-current').getBoundingClientRect();
      const box = list.getBoundingClientRect();
      return { scrollTop: list.scrollTop, scrollable: list.scrollHeight > list.clientHeight,
               inView: cur.top >= box.top && cur.bottom <= box.bottom, pageY: window.scrollY };
    });

    await page.goto(`/?p=${ids[9]}`);
    await expectClean(page);
    await expect(page.locator('.ss-series-pos')).toHaveText('10/12');
    let s = await listState();
    expect(s.scrollable, 'a 12-message list should scroll inside its card').toBe(true);
    expect(s.inView, 'message 10 should be visible in the list').toBe(true);
    expect(s.pageY, 'the page itself should not scroll').toBe(0);

    // The first message needs no scrolling at all.
    await page.goto(`/?p=${ids[0]}`);
    s = await listState();
    expect(s.scrollTop).toBe(0);
    expect(s.inView).toBe(true);
  });
});

test.describe('Rich text rendering (notes / guide / transcript)', () => {
  // Sermon Shots returns Markdown; older guides are HTML. Both must render.
  // Opens the sermon page, expands the named collapsible block (they start
  // closed), and returns the body locator.
  async function openBlock(page, request, title, blockLabel) {
    const sermons = await (await request.get('/wp-json/sermon-suite/v1/sermons')).json();
    const match = sermons.find((s) => (s.title?.rendered || s.title) === title);
    expect(match, `seeded sermon "${title}" not found`).toBeTruthy();
    await page.goto(match.permalink || match.link || `/?p=${match.id}`);

    const block = page.locator('.ss-notes-block', { hasText: blockLabel });
    await block.locator('.ss-notes-toggle').click();
    const body = block.locator('.ss-notes-body');
    await body.waitFor({ state: 'visible' });
    return body;
  }

  test('Markdown guide renders as HTML, not raw syntax', async ({ page, request }) => {
    const guide = await openBlock(page, request, 'Grace That Holds', 'Discussion Guide');

    const text = await guide.innerText();
    // The bug this guards: raw Markdown leaking to visitors.
    expect(text).not.toContain('##');
    expect(text).not.toContain('**');
    expect(text).not.toMatch(/\]\(https?:/); // raw [label](url)
    expect(text).not.toMatch(/^\s*>\s/m); // raw blockquote marker

    // And the structure it should have produced instead.
    await expect(guide.locator('h3', { hasText: 'Opening Question' })).toBeVisible();
    await expect(guide.locator('ol > li')).toHaveCount(3);
    await expect(guide.locator('ul > li')).toHaveCount(3);
    await expect(guide.locator('blockquote')).toHaveCount(1);
    await expect(guide.locator('strong', { hasText: 'John 1:14-17' })).toBeVisible();
    await expect(guide.locator('em', { hasText: 'receive' })).toBeVisible();
    await expect(guide.locator('a[href="https://comms.church"]')).toBeVisible();
  });

  test('legacy HTML guide still renders unchanged', async ({ page, request }) => {
    const guide = await openBlock(page, request, 'Built on the Rock', 'Discussion Guide');

    await expect(guide.locator('h3', { hasText: 'Legacy HTML Guide' })).toBeVisible();
    await expect(guide.locator('strong', { hasText: 'HTML' })).toBeVisible();
    await expect(guide.locator('ul > li')).toHaveCount(1);
    // Not double-escaped into visible tags.
    expect(await guide.innerText()).not.toContain('<strong>');
  });

  test('list markers survive a theme CSS reset like Divi\'s', async ({ page, request }) => {
    const guide = await openBlock(page, request, 'Grace That Holds', 'Discussion Guide');
    // Divi ships exactly this reset and only restores markers inside its own
    // content areas, which the sermon template sits outside of.
    await page.addStyleTag({ content: 'ol,ul{list-style:none}' });
    const styles = await guide.evaluate((el) => ({
      ol: getComputedStyle(el.querySelector('ol')).listStyleType,
      ul: getComputedStyle(el.querySelector('ul')).listStyleType,
    }));
    expect(styles.ol, 'discussion questions lost their numbers').toBe('decimal');
    expect(styles.ul, 'bulleted lists lost their bullets').toBe('disc');
  });

  test('Markdown sermon notes render as HTML', async ({ page, request }) => {
    const notes = await openBlock(page, request, 'Grace That Holds', 'Sermon Notes');

    expect(await notes.innerText()).not.toContain('##');
    await expect(notes.locator('h3, h4')).not.toHaveCount(0);
    await expect(notes.locator('blockquote')).toHaveCount(1);
  });
});

test.describe('Admin pages', () => {
  const adminPages = [
    ['sermon-suite', 'dashboard'],
    ['ss-add-sermon', 'add sermon editor'],
    ['ss-add-series', 'add series editor'],
    ['sermon-suite-shortcodes', 'shortcode generator'],
    ['sermon-suite-import', 'CSV importer'],
    ['sermon-suite-settings', 'settings'],
    ['sermon-suite-api-docs', 'API docs'],
  ];

  for (const [slug, label] of adminPages) {
    test(`${label} (admin.php?page=${slug}) renders without PHP errors`, async ({ page }) => {
      await page.goto(`/wp-admin/admin.php?page=${slug}`);
      // Playground --login auto-authenticates as admin; if we ended up on a
      // login form, auth is broken and every admin assertion is meaningless.
      await expect(page.locator('#wpwrap')).toBeVisible();
      await expectClean(page);
    });
  }

  test('sermon list table shows seeded sermons', async ({ page }) => {
    await page.goto('/wp-admin/edit.php?post_type=ss_sermon');
    await expect(page.locator('#wpwrap')).toBeVisible();
    const body = await page.locator('body').innerText();
    expect(body).toContain('Grace That Holds');
    expect(body).toContain('Built on the Rock');
  });
});
