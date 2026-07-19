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
