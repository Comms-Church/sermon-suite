// Logs into wp-admin once (Playground default credentials) and saves the
// session for the other projects.
const { test: setup, expect } = require('@playwright/test');

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/wp-login.php');

  const login = page.locator('#user_login');
  const onLoginForm = await login.isVisible().catch(() => false);

  if (!onLoginForm) {
    // Playground's boot-time auto-login already carried over.
    await page.goto('/wp-admin/');
  } else {
    // Wait for the form to settle before typing. Filling while wp-login.php is
    // still loading has raced here — WordPress moves focus on load, and the
    // password text has ended up in the username box, failing the login.
    await login.waitFor({ state: 'visible' });
    await login.fill('admin');
    await page.locator('#user_pass').fill('password');

    // Cheap guard: if the race happened anyway, retype rather than submitting
    // credentials we know are wrong.
    if ((await login.inputValue()) !== 'admin') {
      await login.fill('admin');
      await page.locator('#user_pass').fill('password');
    }
    await expect(login).toHaveValue('admin');

    await Promise.all([
      page.waitForURL(/wp-admin/, { timeout: 30_000 }),
      page.locator('#wp-submit').click(),
    ]);
  }

  await expect(page.locator('#wpwrap')).toBeVisible();
  await page.context().storageState({ path: 'test-results/.auth/admin.json' });
});
