// The publish endpoint is gated on the publish_via_sermon_api capability so
// automation can run as a locked-down user. These tests prove the split is
// real: the bot role can publish, but is still refused by the admin-only CSV
// import routes.
const { test, expect } = require('@playwright/test');

// A context of its own — deliberately NOT the shared admin storage state.
async function loginAsBot(browser, baseURL) {
  const context = await browser.newContext({ baseURL });
  const page = await context.newPage();
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'sermonbot');
  await page.fill('#user_pass', 'botpassword');
  await page.click('#wp-submit');

  // The plugin localises a wp_rest nonce onto the public front end, which is
  // the only place a user with just `read` can pick one up.
  await page.goto('/');
  const nonce = await page.evaluate(() => window.sermonSuite?.nonce);
  expect(nonce, 'no wp_rest nonce for the bot user — is it logged in?').toBeTruthy();
  return { context, page, nonce };
}

test('sermon_api_bot can publish but cannot reach the import routes', async ({ browser, baseURL }) => {
  test.setTimeout(120_000);
  const { context, page, nonce } = await loginAsBot(browser, baseURL);

  // Back-dated so it never displaces the [ss_latest_hero] the smoke suite checks.
  const publish = await page.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { sermon_title: `Bot Published ${Date.now()}`, date: '2019-03-04', speaker_name: 'Bot Speaker' },
  });
  expect(publish.status(), 'bot role should be allowed to publish').toBe(200);
  expect((await publish.json()).status).toBe('created');

  // The import routes are still manage_options, which the bot does not have.
  for (const route of ['import/upload', 'import/batch', 'import/ping']) {
    const method = route === 'import/ping' ? 'get' : 'post';
    const res = await page.request[method](`/wp-json/sermon-suite/v1/${route}`, {
      headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'text/plain' },
      ...(method === 'post' ? { data: 'topic,1,Nope' } : {}),
    });
    expect(res.status(), `${route} should stay admin-only`).toBe(403);
  }

  // ...and the role really is minimal: it can drive our endpoint but has no
  // general post-creation rights through core's own REST API.
  const coreCreate = await page.request.post('/wp-json/wp/v2/ss_sermon', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { title: 'Bot should not be able to create this directly', status: 'publish' },
  });
  expect([401, 403], 'bot should not have core post-creation rights')
    .toContain(coreCreate.status());

  const listUsers = await page.request.get('/wp-json/wp/v2/users?context=edit', {
    headers: { 'X-WP-Nonce': nonce },
  });
  expect([401, 403], 'bot should not be able to enumerate users')
    .toContain(listUsers.status());

  await context.close();
});

test('an anonymous request cannot publish', async ({ browser, baseURL }) => {
  const context = await browser.newContext({ baseURL });
  const res = await context.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
    headers: { 'Content-Type': 'application/json' },
    data: { sermon_title: 'Anonymous should never land' },
  });
  expect(res.status()).toBe(401);
  await context.close();
});
