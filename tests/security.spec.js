// Regression tests for the 3.0.1 security fixes. Each one reproduces an
// attack that worked in 3.0.0 and asserts it is now refused.
const { test, expect } = require('@playwright/test');

// A Contributor has edit_posts — enough to open the plugin's editors — but may
// not publish, edit pages, or touch other people's content.
async function loginAsVolunteer(browser, baseURL) {
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await page.goto('/wp-login.php');
  const login = page.locator('#user_login');
  await login.waitFor({ state: 'visible' });
  await login.fill('volunteer');
  await page.locator('#user_pass').fill('volunteerpw');
  await expect(login).toHaveValue('volunteer');
  await Promise.all([page.waitForURL(/wp-admin/), page.locator('#wp-submit').click()]);
  return { context, page };
}

async function editorNonce(page) {
  await page.goto('/wp-admin/admin.php?page=ss-add-sermon');
  const m = (await page.content()).match(/action:'ss_save_sermon',\s*nonce:'([a-f0-9]+)'/)
         || (await page.content()).match(/nonce:'([a-f0-9]+)'/);
  expect(m, 'no editor nonce for the Contributor').toBeTruthy();
  return m[1];
}

const saveSermon = (page, fields) =>
  page.request.post('/wp-admin/admin-ajax.php', { form: { action: 'ss_save_sermon', ...fields } });

test('a Contributor cannot convert and overwrite a page through the sermon editor', async ({ browser, baseURL, page: admin }) => {
  test.setTimeout(120_000);
  const pages = await (await admin.request.get('/wp-json/wp/v2/pages?slug=sermons')).json();
  const target = pages[0];
  expect(target, 'seeded Sermons page missing').toBeTruthy();

  const { context, page } = await loginAsVolunteer(browser, baseURL);
  const nonce = await editorNonce(page);
  const res = await saveSermon(page, {
    nonce, post_id: String(target.id), title: 'DEFACED BY A CONTRIBUTOR',
    content: '<p>overwritten</p>', status: 'publish',
  });
  const body = await res.json();
  expect(body.success, 'the attack should be refused').toBe(false);

  // And the page is untouched: still a page, still titled Sermons.
  const after = await (await admin.request.get(`/wp-json/wp/v2/pages/${target.id}`)).json();
  expect(after.id).toBe(target.id);
  expect(after.title.rendered).toBe('Sermons');
  await context.close();
});

test('a Contributor’s new sermon goes to review instead of publishing', async ({ browser, baseURL, page: admin }) => {
  test.setTimeout(120_000);
  const { context, page } = await loginAsVolunteer(browser, baseURL);
  const nonce = await editorNonce(page);
  const title = `Contributor Draft ${Date.now()}`;
  const res = await saveSermon(page, { nonce, post_id: '0', title, status: 'publish' });
  const body = await res.json();
  expect(body.success, JSON.stringify(body)).toBe(true);
  await context.close();

  // Not publicly listed…
  const pub = await (await admin.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  expect(pub.some((s) => s.title === title), 'Contributor managed to publish').toBe(false);

  // …but saved, pending review.
  await admin.goto('/wp-admin/admin.php?page=sermon-suite-import');
  const rest = await admin.evaluate(() => window.sermonSuiteAdmin?.restNonce);
  const pending = await (await admin.request.get(
    `/wp-json/wp/v2/ss_sermon/${body.data.post_id}?context=edit`, { headers: { 'X-WP-Nonce': rest } }
  )).json();
  expect(pending.status).toBe('pending');
});

test('the sermon editor refuses to save onto a non-sermon post id', async ({ page }) => {
  // Even an administrator must not be able to turn a page into a sermon —
  // the fix is a type check, not only a permission check.
  const pages = await (await page.request.get('/wp-json/wp/v2/pages?slug=sermons')).json();
  const nonce = await editorNonce(page);
  const res = await saveSermon(page, { nonce, post_id: String(pages[0].id), title: 'Should not land', status: 'draft' });
  expect((await res.json()).success).toBe(false);
});

test('the public sermons endpoint caps page size', async ({ page }) => {
  const res = await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100000');
  expect(res.status()).toBe(200);
  expect((await res.json()).length).toBeLessThanOrEqual(100);

  const one = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=1')).json();
  expect(one.length).toBe(1);
});
