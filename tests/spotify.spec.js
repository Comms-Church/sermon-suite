// Spotify episode support — the audio counterpart to the YouTube embed.
// Self-contained: each test publishes the sermon it needs, so nothing depends
// on seed data that a persisted Playground database may predate.
const { test, expect } = require('@playwright/test');

const EPISODE = '098gM1uPWKADc7BfRwMxTO';
const SHOW = '0qjz3dLaJvYNT4jyjjAVaa';

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

const sermonById = async (page, id) => {
  const all = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  return all.find((s) => s.id === id);
};

test('a Spotify episode renders a player and is exposed over REST', async ({ page }) => {
  test.setTimeout(120_000);
  const nonce = await nonceFor(page);

  // Back-dated so it never displaces the [ss_latest_hero] the smoke suite checks.
  const res = await publish(page, nonce, {
    sermon_title: `Spotify Player ${Date.now()}`,
    date: '2019-04-02',
    youtube_url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    spotify_url: `https://open.spotify.com/episode/${EPISODE}?si=abc123`,
  });
  expect(res.status()).toBe(200);
  const { post_id } = await res.json();

  // REST parity with the YouTube fields.
  const sermon = await sermonById(page, post_id);
  expect(sermon.spotify_id).toBe(EPISODE);
  expect(sermon.spotify_type).toBe('episode');
  expect(sermon.spotify_embed).toBe(`https://open.spotify.com/embed/episode/${EPISODE}`);
  expect(sermon.spotify_url).toBe(`https://open.spotify.com/episode/${EPISODE}`);

  // A real player on the page, sitting alongside the video rather than
  // replacing it.
  await page.goto(sermon.permalink || sermon.link || `/?p=${post_id}`);
  await expect(page.locator(`iframe[src*="open.spotify.com/embed/episode/${EPISODE}"]`)).toHaveCount(1);
  await expect(page.locator('iframe[src*="youtube.com/embed"]')).toHaveCount(1);

  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(/Fatal error|Parse error|Warning: |Notice: /);
});

test('a sermon with no Spotify link renders no player and empty REST fields', async ({ page }) => {
  const nonce = await nonceFor(page);
  const res = await publish(page, nonce, {
    sermon_title: `Spotify Absent ${Date.now()}`,
    date: '2019-04-02',
  });
  const { post_id } = await res.json();

  const sermon = await sermonById(page, post_id);
  expect(sermon.spotify_id).toBe('');
  expect(sermon.spotify_embed).toBe('');

  await page.goto(sermon.permalink || sermon.link || `/?p=${post_id}`);
  await expect(page.locator('iframe[src*="open.spotify.com"]')).toHaveCount(0);
});

test('every link shape an editor might paste is parsed, and junk is refused', async ({ page }) => {
  test.setTimeout(120_000);
  const nonce = await nonceFor(page);

  const shapes = [
    [`https://open.spotify.com/episode/${EPISODE}?si=xyz`, 'episode', EPISODE, 'share link with query'],
    [`https://open.spotify.com/intl-de/episode/${EPISODE}`, 'episode', EPISODE, 'locale-prefixed link'],
    [`spotify:episode:${EPISODE}`, 'episode', EPISODE, 'app URI'],
    [EPISODE, 'episode', EPISODE, 'bare id'],
    [`https://open.spotify.com/show/${SHOW}`, 'show', SHOW, 'show link'],
  ];

  for (const [value, type, id, label] of shapes) {
    const res = await publish(page, nonce, {
      sermon_title: `Spotify ${label} ${Date.now()}`,
      date: '2019-04-02',
      spotify_url: value,
    });
    expect(res.status(), `${label} should be accepted`).toBe(200);
    const created = await sermonById(page, (await res.json()).post_id);
    expect(created.spotify_id, `${label} did not parse`).toBe(id);
    expect(created.spotify_type, `${label} wrong type`).toBe(type);
  }

  // Something that isn't Spotify is refused rather than silently stored,
  // so a bad link in an automation surfaces instead of producing a sermon
  // with a missing player.
  const bad = await publish(page, nonce, {
    sermon_title: `Spotify junk ${Date.now()}`,
    date: '2019-04-02',
    spotify_url: 'https://example.com/not-spotify',
  });
  expect(bad.status()).toBe(400);
});
