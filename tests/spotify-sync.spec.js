// Spotify show sync — the audio counterpart to the YouTube playlist sync.
// The real Spotify API is never called: a test-only mu-plugin (installed by
// the blueprint) answers the ss_spotify_episodes filter with fixed episodes,
// so this exercises the sync's own logic — creation, dedupe, meta, ordering.
const { test, expect } = require('@playwright/test');

const SHOW = 'TESTSHOW00000000000000';
const EPISODES = ['TESTEPISODE00000000001', 'TESTEPISODE00000000002'];

async function restNonce(page) {
  await page.goto('/wp-admin/admin.php?page=sermon-suite-import');
  const n = await page.evaluate(() => window.sermonSuiteAdmin?.restNonce);
  expect(n).toBeTruthy();
  return n;
}

// Creates a series by publishing a throwaway sermon into it, then returns its id.
async function makeSeries(page, nonce, name) {
  const res = await page.request.post('/wp-json/sermon-suite/v1/sermons/publish', {
    headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
    data: { sermon_title: `${name} seed`, series_name: name, date: '2019-01-01' },
  });
  expect(res.status()).toBe(200);
  const { post_id } = await res.json();
  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  const seed = all.find((s) => s.id === post_id);
  expect(seed.series_id).toBeGreaterThan(0);
  return seed.series_id;
}

// The sync runs over admin-ajax with its own nonce, printed into the Spotify
// card on the plugin's custom series editor — which is the screen the plugin
// redirects series edit links to, and the one users actually see.
async function syncNonce(page, seriesId) {
  await page.goto(`/wp-admin/admin.php?page=ss-edit-series&post_id=${seriesId}`);
  const html = await page.content();
  const m = html.match(/action:'ss_spotify_sync_show',\s*nonce:'([a-f0-9]+)'/);
  expect(m, 'Spotify sync card not found on the series editor').toBeTruthy();
  return m[1];
}

async function fetchDrafts(page, nonce) {
  const res = await page.request.get(
    '/wp-json/wp/v2/ss_sermon?status=draft&per_page=100&context=edit',
    { headers: { 'X-WP-Nonce': nonce } }
  );
  expect(res.status(), 'draft lookup failed — cookie auth needs the wp_rest nonce').toBe(200);
  return res.json();
}

// Core's /wp/v2 response carries no `meta` for this post type (ss_sermon does
// not declare custom-fields support), so the synced values are verified where
// a user would actually see them: the plugin's own sermon editor.
async function editorHtml(page, postId) {
  await page.goto(`/wp-admin/admin.php?page=ss-edit-sermon&post_id=${postId}`);
  return page.content();
}

const runSync = (page, nonce, seriesId, show) =>
  page.request.post('/wp-admin/admin-ajax.php', {
    form: { action: 'ss_spotify_sync_show', nonce, series_id: String(seriesId), show },
  });

test('syncing a show creates a draft sermon per episode, then dedupes on re-run', async ({ page }) => {
  test.setTimeout(180_000);
  const nonce = await restNonce(page);
  const seriesId = await makeSeries(page, nonce, `Sync Series ${Date.now()}`);
  const sNonce = await syncNonce(page, seriesId);

  // ── first run: both episodes land ────────────────────────────────────────
  const first = await runSync(page, sNonce, seriesId, `https://open.spotify.com/show/${SHOW}`);
  expect(first.status()).toBe(200);
  const firstBody = await first.json();
  expect(firstBody.success, JSON.stringify(firstBody)).toBe(true);
  expect(firstBody.data.created).toBe(2);
  expect(firstBody.data.errors).toBe(0);

  // They're drafts, so check through core's REST with admin cookies.
  const drafts = await fetchDrafts(page, nonce);
  const made = drafts.filter((d) => ['Stub Episode One', 'Stub Episode Two'].includes(d.title?.raw));
  expect(made.length, 'both episodes should exist as drafts').toBe(2);

  // Each draft opens in the editor with its Spotify link and date already
  // filled in, and attached to the series we synced into.
  for (const d of made) {
    const html = await editorHtml(page, d.id);
    const epIndex = d.title.raw === 'Stub Episode One' ? 0 : 1;
    expect(html, `${d.title.raw} has no Spotify link`)
      .toContain(`https://open.spotify.com/episode/${EPISODES[epIndex]}`);
    expect(html).toContain(epIndex === 0 ? '2019-05-01' : '2019-05-08');
    expect(html).toContain(`value="${seriesId}" selected`);
  }

  // ── second run: nothing new, nothing duplicated ──────────────────────────
  const second = await runSync(page, sNonce, seriesId, `https://open.spotify.com/show/${SHOW}`);
  const secondBody = await second.json();
  expect(secondBody.data.created, 're-sync should create nothing').toBe(0);
  expect(secondBody.data.skipped).toBe(2);

  const after = await fetchDrafts(page, nonce);
  expect(after.filter((d) => ['Stub Episode One', 'Stub Episode Two'].includes(d.title?.raw)).length,
    're-sync duplicated sermons').toBe(2);
});

test('an episode link is rejected where a show link is required', async ({ page }) => {
  test.setTimeout(120_000);
  const nonce = await restNonce(page);
  const seriesId = await makeSeries(page, nonce, `Sync Reject ${Date.now()}`);
  const sNonce = await syncNonce(page, seriesId);

  const res = await runSync(
    page, sNonce, seriesId,
    'https://open.spotify.com/episode/098gM1uPWKADc7BfRwMxTO'
  );
  const body = await res.json();
  expect(body.success).toBe(false);
  expect(String(body.data)).toContain('show link');
});
