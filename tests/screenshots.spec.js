// Regenerates marketing/docs screenshots.  Run with: npm run screenshots
//
// Front-end shots come from the real church site (gccws.net) so marketing
// shows genuine sermons and branding rather than Playground's placeholder
// theme and test fixtures.  Admin shots stay on Playground — we don't log
// into a production dashboard, and the test site shows a clean, empty state.
const { test } = require('@playwright/test');

const LIVE = 'https://gccws.net';

test.use({ viewport: { width: 1440, height: 900 } });

// Lazy-loaded images render blank in a fullPage capture unless they've been
// scrolled into view first.
async function settle(page) {
  await page.waitForLoadState('networkidle');
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let y = 0;
      const step = () => {
        window.scrollBy(0, 600);
        if ((y += 600) < document.body.scrollHeight) return setTimeout(step, 60);
        window.scrollTo(0, 0);
        setTimeout(resolve, 250);
      };
      step();
    });
  });
  await page.waitForLoadState('networkidle');
}

const liveShots = [
  [`${LIVE}/sermons/`, 'sermon-library.png'],
  [`${LIVE}/sermons/do-what-you-can-with-what-you-have/`, 'sermon-detail.png'],
];

for (const [url, file] of liveShots) {
  test(`capture ${file} (live site)`, async ({ page }) => {
    await page.goto(url);
    await settle(page);
    await page.screenshot({ path: `screenshots/${file}`, fullPage: true });
  });
}

const adminShots = [
  ['/wp-admin/admin.php?page=sermon-suite', 'admin-dashboard.png'],
  ['/wp-admin/admin.php?page=ss-add-sermon', 'add-sermon.png'],
  ['/wp-admin/admin.php?page=sermon-suite-shortcodes', 'shortcode-generator.png'],
  ['/wp-admin/admin.php?page=sermon-suite-settings', 'settings.png'],
];

for (const [path, file] of adminShots) {
  test(`capture ${file}`, async ({ page }) => {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `screenshots/${file}`, fullPage: true });
  });
}

// Captured on Playground, not the live site: this shows the Markdown-rendered
// discussion guide, and gccws.net only gets that once it updates the plugin.
// Point it at the live site after that update lands.
test('capture discussion-guide.png', async ({ page, request }) => {
  const sermons = await (await request.get('/wp-json/sermon-suite/v1/sermons')).json();
  const withGuide = sermons.find((s) => (s.title?.rendered || s.title) === 'Grace That Holds') || sermons[0];
  await page.goto(withGuide.permalink || withGuide.link || `/?p=${withGuide.id}`);

  const toggle = page.locator('.ss-notes-toggle', { hasText: 'Discussion Guide' });
  await toggle.click();
  const body = toggle.locator('xpath=following-sibling::div[1]');
  await body.waitFor({ state: 'visible' });

  await body.screenshot({ path: 'screenshots/discussion-guide.png' });
});
