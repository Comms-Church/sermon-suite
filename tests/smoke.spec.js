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
