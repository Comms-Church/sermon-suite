// Spotify podcast sync — one site-wide feed of every sermon, sorted into
// series afterwards from the All Sermons list.
//
// The real Spotify API is never called: a test-only mu-plugin (installed by
// the blueprint) answers the ss_spotify_episodes filter with two fixed
// episodes, so this exercises the plugin's own logic end to end.
const { test, expect } = require('@playwright/test');

const SHOW = 'TESTSHOW00000000000000';
const TITLES = ['Stub Episode One', 'Stub Episode Two'];

async function settingsNonce(page) {
  await page.goto('/wp-admin/admin.php?page=sermon-suite-settings');
  const m = (await page.content()).match(/action:\s*'ss_spotify_sync_feed',\s*nonce:\s*'([a-f0-9]+)'/);
  expect(m, 'Spotify podcast sync controls missing from Settings').toBeTruthy();
  return m[1];
}

const syncFeed = (page, nonce, show) =>
  page.request.post('/wp-admin/admin-ajax.php', { form: { action: 'ss_spotify_sync_feed', nonce, show } });

async function restNonce(page) {
  await page.goto('/wp-admin/admin.php?page=sermon-suite-import');
  return page.evaluate(() => window.sermonSuiteAdmin?.restNonce);
}

// Titles visible in the All Sermons list for a given series filter.
async function listTitles(page, filter) {
  await page.goto(`/wp-admin/edit.php?post_type=ss_sermon&post_status=all&ss_series_filter=${filter}`);
  return page.locator('#the-list .row-title').allInnerTexts();
}

test('Sync Now imports every episode as a draft with no series, then dedupes', async ({ page }) => {
  test.setTimeout(180_000);
  const nonce = await settingsNonce(page);

  const first = await (await syncFeed(page, nonce, `https://open.spotify.com/show/${SHOW}`)).json();
  expect(first.success, JSON.stringify(first)).toBe(true);
  expect(first.data.created).toBe(2);
  expect(first.data.errors).toBe(0);

  // They land unassigned — the "No series" filter is where you find them.
  const unassigned = await listTitles(page, 'none');
  for (const t of TITLES) expect(unassigned, `${t} should be unassigned`).toContain(t);

  // Sync Now saved the show link to Settings.
  await page.goto('/wp-admin/admin.php?page=sermon-suite-settings');
  await expect(page.locator('#ss-spotify-show')).toHaveValue(`https://open.spotify.com/show/${SHOW}`);

  // Re-running adds nothing and duplicates nothing.
  const again = await (await syncFeed(page, nonce, `https://open.spotify.com/show/${SHOW}`)).json();
  expect(again.data.created).toBe(0);
  expect(again.data.skipped).toBe(2);
  const all = await listTitles(page, 'none');
  for (const t of TITLES) expect(all.filter((x) => x === t).length, `${t} duplicated`).toBe(1);
});

test('Bulk Edit files sermons into a series, numbers them by date, and can publish them', async ({ page }) => {
  test.setTimeout(180_000);
  // A series to sort into (created by publishing a throwaway sermon into it).
  const nonce = await restNonce(page);
  const seriesName = `Bulk Series ${Date.now()}`;
  const seed = await (await page.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { sermon_title: `${seriesName} seed`, series_name: seriesName, date: '2019-01-01' },
  })).json();
  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  const seriesId = all.find((s) => s.id === seed.post_id).series_id;

  // Make sure the stub episodes exist (the previous test imports them).
  await syncFeed(page, await settingsNonce(page), `https://open.spotify.com/show/${SHOW}`);

  // All Sermons → No series → tick both → Bulk actions: Edit.
  await page.goto('/wp-admin/edit.php?post_type=ss_sermon&post_status=all&ss_series_filter=none');
  for (const t of TITLES) {
    await page.locator('#the-list tr', { has: page.locator('.row-title', { hasText: t }) })
      .locator('input[type=checkbox]').check();
  }
  await page.locator('#bulk-action-selector-top').selectOption('edit');
  await page.locator('#doaction').click();

  // The Series field sits in the Bulk Edit panel, alongside core's Status.
  const panel = page.locator('#bulk-edit');
  await expect(panel.locator('select[name="ss_bulk_series"]')).toBeVisible();
  await panel.locator('select[name="ss_bulk_series"]').selectOption(String(seriesId));
  await panel.locator('select[name="_status"]').selectOption('publish');
  await panel.locator('#bulk_edit').click();
  await page.waitForLoadState('networkidle');

  // Now listed under the series and gone from "No series".
  const inSeries = await listTitles(page, String(seriesId));
  for (const t of TITLES) expect(inSeries).toContain(t);
  const stillLoose = await listTitles(page, 'none');
  for (const t of TITLES) expect(stillLoose).not.toContain(t);

  // Published, attached, and numbered after the seed sermon in date order.
  const pub = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  const one = pub.find((s) => s.title === 'Stub Episode One');
  const two = pub.find((s) => s.title === 'Stub Episode Two');
  expect(one?.series_id).toBe(seriesId);
  expect(two?.series_id).toBe(seriesId);
  expect(one.series_order).toBeLessThan(two.series_order);

  // The series page shows them with the artwork stored at import — URLs that
  // exist only in the stubbed API response, so nothing was fetched to render.
  await page.goto(`/?p=${seriesId}`);
  const html = await page.content();
  expect(html).toContain('https://i.scdn.co/image/stub-art-300-one');
  expect(html).not.toContain('stub-art-640-one');
});

test('"Remove from series" in Bulk Edit puts a sermon back under No series', async ({ page }) => {
  test.setTimeout(120_000);
  await page.goto('/wp-admin/edit.php?post_type=ss_sermon&post_status=all');
  const row = page.locator('#the-list tr', { has: page.locator('.row-title', { hasText: 'Stub Episode Two' }) });
  await row.locator('input[type=checkbox]').check();
  await page.locator('#bulk-action-selector-top').selectOption('edit');
  await page.locator('#doaction').click();
  await page.locator('#bulk-edit select[name="ss_bulk_series"]').selectOption('none');
  await page.locator('#bulk-edit #bulk_edit').click();
  await page.waitForLoadState('networkidle');

  expect(await listTitles(page, 'none')).toContain('Stub Episode Two');
});

test('a single episode link is refused where the show is required', async ({ page }) => {
  const nonce = await settingsNonce(page);
  const body = await (await syncFeed(page, nonce, 'https://open.spotify.com/episode/098gM1uPWKADc7BfRwMxTO')).json();
  expect(body.success).toBe(false);
  expect(String(body.data)).toContain('show link');
});

test('the daily check can be switched on and off from Settings', async ({ page }) => {
  // Each step runs in a fresh tab. In the headless test browser, a tab stops
  // rendering frames after any form POST — WordPress core's own General
  // Settings save does the same — so Playwright can never click in it again.
  // A new tab is unaffected. It's a quirk of the test browser, not the plugin.
  const fresh = async () => {
    const p = await page.context().newPage();
    await p.goto('/wp-admin/admin.php?page=sermon-suite-settings');
    return p;
  };

  let p = await fresh();
  await p.locator('#ss-spotify-show').fill(`https://open.spotify.com/show/${SHOW}`);
  await p.locator('input[name="spotify_auto_sync"]').check();
  await p.locator('#submit').click();
  await expect(p.locator('#ss-spotify-schedule')).toContainText('Next automatic check');
  await p.close();

  p = await fresh();
  await expect(p.locator('input[name="spotify_auto_sync"]')).toBeChecked();
  await p.locator('input[name="spotify_auto_sync"]').uncheck();
  await p.locator('#submit').click();
  await expect(p.locator('#ss-spotify-schedule')).not.toContainText('Next automatic check');
  await p.close();
});
