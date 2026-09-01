// Regenerates marketing/docs screenshots.  Run with: npm run screenshots
//
// Front-end shots come from the real church site (gccws.net) so marketing
// shows genuine sermons and branding rather than Playground's placeholder
// theme and test fixtures.  Admin shots stay on Playground — we don't log
// into a production dashboard, and the test site shows a clean, empty state.
const { test } = require('@playwright/test');

const LIVE = 'https://gccws.net';

test.use({ viewport: { width: 1440, height: 900 } });

// Lazy-loaded images render as empty blocks in a fullPage capture unless they
// have been scrolled into view AND given time to finish decoding. The sermon
// archive is a long grid of series artwork, so both steps matter.
async function settle(page) {
  await page.waitForLoadState('networkidle');

  // Force every lazy image to load now. gccws.net runs Smush lazy-load, which
  // parks a blank SVG in src, keeps the real URL in data-src, and reveals the
  // image with an opacity class once it scrolls into view — so a long archive
  // captures with empty cards near the bottom unless we resolve them upfront.
  await page.evaluate(() => {
    document.querySelectorAll('img').forEach((img) => {
      const real = img.getAttribute('data-src') || img.getAttribute('data-lazy-src');
      const srcset = img.getAttribute('data-srcset') || img.getAttribute('data-lazy-srcset');
      if (real) img.setAttribute('src', real);
      if (srcset) img.setAttribute('srcset', srcset);
      img.loading = 'eager';
      img.classList.remove('lazyload');
      img.classList.add('lazyloaded'); // reveal class the loader would add
      img.style.opacity = '1';
    });
  });

  // Scroll the whole page as well, for anything driven by a JS observer.
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let y = 0;
      const step = () => {
        window.scrollBy(0, 500);
        y += 500;
        if (y < document.body.scrollHeight) return setTimeout(step, 120);
        window.scrollTo(0, 0);
        setTimeout(resolve, 300);
      };
      step();
    });
  });

  // Then wait for every image to actually finish loading.
  await page.evaluate(async () => {
    await Promise.all(
      Array.from(document.images).map((img) =>
        img.complete && img.naturalWidth > 0
          ? Promise.resolve()
          : new Promise((res) => {
              img.addEventListener('load', res, { once: true });
              img.addEventListener('error', res, { once: true });
              setTimeout(res, 8000); // never hang on a broken asset
            })
      )
    );
  });

  // Sticky/fixed headers get painted wherever the viewport happened to be when
  // a fullPage capture stitches, leaving the nav bar floating mid-page. Drop
  // them into normal flow so they render once, at the top.
  await page.evaluate(() => {
    document.querySelectorAll('body *').forEach((el) => {
      const pos = getComputedStyle(el).position;
      if (pos === 'fixed' || pos === 'sticky') el.style.position = 'static';
    });
    window.scrollTo(0, 0);
  });

  await page.waitForLoadState('networkidle');
}

const liveShots = [
  [`${LIVE}/sermons/`, 'sermon-library.png'],
  [`${LIVE}/sermons/do-what-you-can-with-what-you-have/`, 'sermon-detail.png'],
];

for (const [url, file] of liveShots) {
  test(`capture ${file} (live site)`, async ({ page }) => {
    test.setTimeout(120_000); // long page, remote host, many images
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
