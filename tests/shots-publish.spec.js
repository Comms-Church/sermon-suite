// The publish endpoint's Sermon Shots discussion-guide import (added in 2.4.1).
// Sermon Shots is stubbed by a test-only mu-plugin, so no key or network.
const { test, expect } = require('@playwright/test');

async function restNonce(page) {
  await page.goto('/wp-admin/admin.php?page=sermon-suite-import');
  const n = await page.evaluate(() => window.sermonSuiteAdmin?.restNonce);
  expect(n).toBeTruthy();
  return n;
}
const publish = (page, nonce, body) =>
  page.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }, data: body,
  });

test('an imported guide renders as formatted content, not raw Markdown', async ({ page }) => {
  test.setTimeout(120_000);
  const nonce = await restNonce(page);
  const res = await publish(page, nonce, {
    sermon_title: `Shots Guide ${Date.now()}`, date: '2019-06-02', sermonshots_video_id: '424242',
  });
  const body = await res.json();
  expect(res.status()).toBe(200);
  expect(body.warning, 'import should have succeeded').toBeUndefined();

  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  const s = all.find((x) => x.id === body.post_id);
  await page.goto(s.permalink);
  const block = page.locator('.ss-notes-block', { hasText: 'Discussion Guide' });
  await block.locator('.ss-notes-toggle').click();
  const guide = block.locator('.ss-notes-body');

  // Rendered like a guide imported by hand in the editor — not literal syntax.
  expect(await guide.innerText()).not.toContain('##');
  expect(await guide.innerText()).not.toContain('**');
  await expect(guide.locator('h3', { hasText: 'Talk It Over' })).toBeVisible();
  await expect(guide.locator('ol > li')).toHaveCount(2);
  await expect(guide.locator('strong', { hasText: 'two' })).toBeVisible();

  // And the link back to the Sermon Shots video is recorded, as the editor does.
  await page.goto(`/wp-admin/admin.php?page=ss-edit-sermon&post_id=${body.post_id}`);
  await expect(page.locator('#gcc-shots-video-id')).toHaveValue('424242');
});

test('a Sermon Shots failure still publishes the sermon, with a warning', async ({ page }) => {
  const nonce = await restNonce(page);
  const res = await publish(page, nonce, {
    sermon_title: `Shots Fail ${Date.now()}`, date: '2019-06-03', sermonshots_video_id: '500500',
  });
  expect(res.status()).toBe(200);
  const body = await res.json();
  expect(body.status).toBe('created');
  expect(body.warning).toContain('Sermon Shots');
});

test('guides already stored by 2.4.1–3.0.0 repair themselves on render', async ({ page }) => {
  test.setTimeout(120_000);
  // Exactly what the publish endpoint stored before the fix: Sermon Shots'
  // Markdown, wrapped in <p>/<br> by wpautop.
  const legacy = '<p>## Talk It Over</p>\n<p>1. Stub question one?<br />\n2. Stub question **two**?</p>';

  await page.goto('/wp-admin/admin.php?page=ss-add-sermon');
  const nonce = (await page.content()).match(/action:'ss_save_sermon',\s*nonce:'([a-f0-9]+)'/)[1];
  const res = await page.request.post('/wp-admin/admin-ajax.php', {
    form: { action: 'ss_save_sermon', nonce, post_id: '0', title: `Legacy Wrapped ${Date.now()}`,
            status: 'publish', sermon_date: '2019-06-04', discussion_guide: legacy },
  });
  const { data } = await res.json();

  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  await page.goto(all.find((x) => x.id === data.post_id).permalink);
  const block = page.locator('.ss-notes-block', { hasText: 'Discussion Guide' });
  await block.locator('.ss-notes-toggle').click();
  const guide = block.locator('.ss-notes-body');
  expect(await guide.innerText()).not.toContain('##');
  await expect(guide.locator('h3', { hasText: 'Talk It Over' })).toBeVisible();
  await expect(guide.locator('ol > li')).toHaveCount(2);
});
