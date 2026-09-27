// Sermon Suite on a real Divi install. Run with: DIVI_DIR=… npm run test:divi
// Each check targets a known way Divi breaks plugins.
const { test, expect } = require('@playwright/test');

const PHP_ERRORS = /Fatal error|Parse error|Warning: |Notice: |Deprecated: /;

// Collect JS errors for a page — Divi's own scripts (jQuery deferral, fitVids)
// are a common source of breakage for plugins that rely on jQuery.
function watchErrors(page) {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  return errors;
}

async function sermon(request, title) {
  const all = await (await request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  const s = all.find((x) => x.title === title);
  expect(s, `seeded sermon "${title}" missing`).toBeTruthy();
  return s;
}

test('Divi is actually the active theme', async ({ page }) => {
  await page.goto('/divi-sermons/');
  expect(await page.content()).toContain('/themes/Divi/');
  await expect(page.locator('.et_pb_section').first()).toBeVisible();
});

test('shortcodes render inside Divi Builder Text and Code modules', async ({ page }) => {
  const errors = watchErrors(page);
  await page.goto('/divi-sermons/', { waitUntil: 'networkidle' });
  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(PHP_ERRORS);
  expect(body, 'a shortcode was printed raw instead of rendering').not.toContain('[ss_');
  await expect(page.locator('.et_pb_text .gcc-hero-wrap')).toBeVisible();
  await expect(page.locator('.et_pb_code .gcc-archive-wrap')).toBeVisible();
  expect(errors, `JS errors: ${errors.join(' | ')}`).toEqual([]);
});

test('the sermon template fits Divi: layout, video, typography, no errors', async ({ page, request }) => {
  const errors = watchErrors(page);
  const s = await sermon(request, 'Grace That Holds');
  await page.goto(s.permalink, { waitUntil: 'networkidle' });
  expect(await page.locator('body').innerText()).not.toMatch(PHP_ERRORS);

  // Divi runs fitVids over embedded video; the plugin already sizes its own
  // player, so a second wrapper would distort it. Expect a clean 16:9.
  const box = await page.locator('.ss-video-wrap').boundingBox();
  expect(Math.abs(box.width / box.height - 16 / 9)).toBeLessThan(0.02);

  // Divi sets headings to line-height:1em, which crushes wrapped titles.
  const lh = await page.locator('.ss-sermon-title').evaluate((el) =>
    parseFloat(getComputedStyle(el).lineHeight) / parseFloat(getComputedStyle(el).fontSize));
  expect(lh).toBeGreaterThan(1.05);
  expect(errors, `JS errors: ${errors.join(' | ')}`).toEqual([]);
});

test('discussion questions keep their numbers under Divi\'s list reset', async ({ page, request }) => {
  const s = await sermon(request, 'Grace That Holds');
  await page.goto(s.permalink);
  const block = page.locator('.ss-notes-block', { hasText: 'Discussion Guide' });
  await block.locator('.ss-notes-toggle').click();
  const body = block.locator('.ss-notes-body');
  await body.waitFor({ state: 'visible' });
  const styles = await body.evaluate((el) => ({
    ol: getComputedStyle(el.querySelector('ol')).listStyleType,
    ul: getComputedStyle(el.querySelector('ul')).listStyleType,
  }));
  expect(styles).toEqual({ ol: 'decimal', ul: 'disc' });
});

test('the Spotify player renders at full width and its own height', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=sermon-suite-import');
  const nonce = await page.evaluate(() => window.sermonSuiteAdmin?.restNonce);
  const res = await page.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { sermon_title: `Divi Spotify ${Date.now()}`, date: '2019-07-07',
            youtube_url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            spotify_url: 'https://open.spotify.com/episode/098gM1uPWKADc7BfRwMxTO' },
  });
  const { post_id } = await res.json();
  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  await page.goto(all.find((x) => x.id === post_id).permalink);

  const frame = page.locator('.ss-spotify-wrap iframe');
  await expect(frame).toHaveCount(1);
  const f = await frame.boundingBox();
  const v = await page.locator('.ss-video-wrap').boundingBox();
  expect(Math.round(f.height)).toBe(152);
  expect(Math.abs(f.width - v.width)).toBeLessThan(2);
  expect(await page.locator('.ss-spotify-wrap .fluid-width-video-wrapper').count()).toBe(0);
});

test('the series page renders inside Divi without errors', async ({ page, request }) => {
  const errors = watchErrors(page);
  const s = await sermon(request, 'Grace That Holds');
  await page.goto(`/?p=${s.series_id}`, { waitUntil: 'networkidle' });
  expect(await page.locator('body').innerText()).not.toMatch(PHP_ERRORS);
  await expect(page.locator('.gcc-sermon-card').first()).toBeVisible();
  expect(errors, `JS errors: ${errors.join(' | ')}`).toEqual([]);
});

test('the plugin admin screens work with Divi active', async ({ page }) => {
  // Divi loads its own scripts into wp-admin; the plugin's custom editors are
  // jQuery-driven, so check they render and run without errors alongside it.
  for (const slug of ['sermon-suite', 'ss-add-sermon', 'ss-add-series', 'sermon-suite-settings', 'sermon-suite-shortcodes']) {
    const errors = watchErrors(page);
    await page.goto(`/wp-admin/admin.php?page=${slug}`, { waitUntil: 'networkidle' });
    await expect(page.locator('#wpwrap')).toBeVisible();
    expect(await page.locator('body').innerText(), `${slug}: PHP error`).not.toMatch(PHP_ERRORS);
    expect(errors, `${slug}: JS errors: ${errors.join(' | ')}`).toEqual([]);
  }
});

test('a sermon can be created and saved through the editor with Divi active', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=ss-add-sermon');
  const title = `Divi Editor Save ${Date.now()}`;
  await page.locator('#gcc-title').fill(title);
  await page.locator('#gcc-save-btn').click();
  await expect.poll(async () => {
    const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
    return all.some((s) => s.title === title);
  }, { timeout: 15_000 }).toBe(true);
});
