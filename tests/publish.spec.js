// POST /sermon-suite/v1/sermons/publish — the single-sermon endpoint used by
// external automation. Covers the two behaviours that are easy to regress:
// calling it twice must update in place rather than duplicate, and a partial
// payload must not wipe fields the church edited in the admin afterwards.
const { test, expect } = require('@playwright/test');

async function nonceFor(page) {
  await page.goto('/wp-admin/admin.php?page=sermon-suite-import');
  const nonce = await page.evaluate(() => window.sermonSuiteAdmin?.restNonce);
  expect(nonce).toBeTruthy();
  return nonce;
}

const publish = (page, nonce, body) =>
  page.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: body,
  });

const fetchSermon = async (page, id) => {
  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  return all.find((s) => s.id === id);
};

test('publishes a sermon, then updates it in place without duplicating', async ({ page }) => {
  test.setTimeout(120_000);
  const nonce = await nonceFor(page);
  const runId = Date.now();
  const title = `Publish Probe ${runId}`;

  // ── create ────────────────────────────────────────────────────────────────
  const created = await publish(page, nonce, {
    sermon_title: title,
    series_name: `Publish Probe Series ${runId}`,
    speaker_name: 'Automation Speaker',
    // Deliberately back-dated: Playground's DB is shared across test projects,
    // and a recent date would hijack the [ss_latest_hero] slot the smoke suite
    // asserts on.
    date: '2019-02-03',
    scripture_reference: 'Romans 8:28',
    campus: 'Main Campus',
    youtube_url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    short_description: 'Short blurb.',
    long_description: '<p>Long body from the automation.</p>',
    social_teaser: 'Teaser copy.',
    discussion_guide_url: 'https://docs.google.com/document/d/abc123/edit',
  });
  expect(created.ok(), `create failed: ${created.status()}`).toBe(true);
  const createdBody = await created.json();
  expect(createdBody.status).toBe('created');
  expect(createdBody.post_id).toBeGreaterThan(0);
  expect(createdBody.edit_link).toContain('page=ss-edit-sermon');

  const postId = createdBody.post_id;
  let sermon = await fetchSermon(page, postId);
  expect(sermon, 'created sermon not visible via REST').toBeTruthy();
  expect(sermon.speakers).toContain('Automation Speaker');
  expect(sermon.campuses).toContain('Main Campus');
  expect(sermon.scripture_ref).toBe('Romans 8:28');
  expect(sermon.scripture_url).toContain('biblegateway.com');
  expect(sermon.youtube_id).toBe('dQw4w9WgXcQ'); // stored as the bare id
  expect(sermon.series_title).toBe(`Publish Probe Series ${runId}`);
  expect(sermon.resources.some((r) => r.label === 'Discussion Guide')).toBe(true);

  // ── same payload again: update in place, no duplicate ────────────────────
  const again = await publish(page, nonce, {
    sermon_title: title,
    date: '2019-02-03',
    long_description: '<p>Long body from the automation.</p>',
    discussion_guide_url: 'https://docs.google.com/document/d/abc123/edit',
  });
  const againBody = await again.json();
  expect(againBody.status).toBe('updated');
  expect(againBody.post_id).toBe(postId);

  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  expect(all.filter((s) => s.title === title).length, 'duplicate sermon created').toBe(1);

  sermon = await fetchSermon(page, postId);
  expect(sermon.resources.filter((r) => r.label === 'Discussion Guide').length,
    'resource stacked a duplicate').toBe(1);

  // ── partial payload must not clobber untouched fields ────────────────────
  const partial = await publish(page, nonce, {
    sermon_title: title,
    date: '2019-02-03',
    social_teaser: 'Updated teaser only.',
  });
  expect((await partial.json()).status).toBe('updated');

  sermon = await fetchSermon(page, postId);
  expect(sermon.speakers, 'speaker wiped by partial payload').toContain('Automation Speaker');
  expect(sermon.scripture_ref, 'scripture wiped by partial payload').toBe('Romans 8:28');
  expect(sermon.youtube_id, 'youtube wiped by partial payload').toBe('dQw4w9WgXcQ');
  expect(sermon.series_title, 'series wiped by partial payload').toBe(`Publish Probe Series ${runId}`);
  expect(sermon.resources.length, 'resources wiped by partial payload').toBeGreaterThan(0);
});

test('rejects a payload with no sermon_title', async ({ page }) => {
  const nonce = await nonceFor(page);
  const res = await publish(page, nonce, { series_name: 'No Title Series' });
  expect(res.status()).toBe(400);
});
