// Regenerates marketing/docs screenshots from the live (Playground) app.
// Run with: npm run screenshots  →  writes to screenshots/*.png
const { test } = require('@playwright/test');

test.use({ viewport: { width: 1440, height: 900 } });

const shots = [
  ['/?pagename=sermons', 'sermon-library.png'],
  ['/wp-admin/admin.php?page=sermon-suite', 'admin-dashboard.png'],
  ['/wp-admin/admin.php?page=ss-add-sermon', 'add-sermon.png'],
  ['/wp-admin/admin.php?page=sermon-suite-shortcodes', 'shortcode-generator.png'],
  ['/wp-admin/admin.php?page=sermon-suite-settings', 'settings.png'],
];

for (const [path, file] of shots) {
  test(`capture ${file}`, async ({ page }) => {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `screenshots/${file}`, fullPage: true });
  });
}

// The sermon page's collapsible blocks (notes / discussion guide / transcript)
// start closed, so expand them before capturing.
test('capture discussion-guide.png', async ({ page, request }) => {
  const sermons = await (await request.get('/wp-json/sermon-suite/v1/sermons')).json();
  const withGuide = sermons.find((s) => s.title?.rendered === 'Grace That Holds' || s.title === 'Grace That Holds') || sermons[0];
  await page.goto(withGuide.permalink || withGuide.link || `/?p=${withGuide.id}`);

  const toggle = page.locator('.ss-notes-toggle', { hasText: 'Discussion Guide' });
  await toggle.click();
  const body = toggle.locator('xpath=following-sibling::div[1]');
  await body.waitFor({ state: 'visible' });

  await body.screenshot({ path: 'screenshots/discussion-guide.png' });
});
