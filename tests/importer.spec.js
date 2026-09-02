// Regression test for the Series Engine importer's join-table handling.
//
// Series Engine exports join rows as [type, row_id, message_id, other_id] —
// the row's OWN id sits in column 1. Keying the lookup maps by that column
// (rather than the message id) silently attaches nothing: no error, no
// warning, just messages that import with zero topics, files, or speakers.
// The fixture's join rows use row_ids that match no message id, so that bug
// fails this test instead of passing quietly.
const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');

const FIXTURE = fs.readFileSync(path.join(__dirname, 'fixtures/series-engine-sample.csv'), 'utf8');

test('Series Engine import attaches topics, resources, speakers, and scripture', async ({ page }) => {
  test.setTimeout(120_000);

  // The import routes require manage_options + a REST nonce, which the plugin
  // localises onto its own admin pages.
  await page.goto('/wp-admin/admin.php?page=sermon-suite-import');
  const nonce = await page.evaluate(() => window.sermonSuiteAdmin?.restNonce);
  expect(nonce, 'sermonSuiteAdmin.restNonce missing on the import page').toBeTruthy();

  const headers = { 'X-WP-Nonce': nonce, 'Content-Type': 'text/plain' };

  // Playground's database persists between runs, so give each run its own
  // titles. Importing fresh content every time means the import path is
  // genuinely exercised — skipping an already-imported message would let this
  // test pass against data a previous (correct) run created.
  const runId = Date.now();
  const title = `Imported Message ${runId}`;
  const csv = FIXTURE
    .replace('Imported Message One', title)
    .replace('Import Test Series', `Import Test Series ${runId}`);

  const upload = await page.request.post(
    '/wp-json/sermon-suite/v1/import/upload',
    { headers, data: csv }
  );
  expect(upload.ok(), `upload failed: ${upload.status()}`).toBe(true);
  const { job_id } = await upload.json();
  expect(job_id).toBeTruthy();

  // Drive the batches to completion.
  for (let i = 0; i < 20; i++) {
    const res = await page.request.post('/wp-json/sermon-suite/v1/import/batch', {
      headers: { 'X-WP-Nonce': nonce },
      data: { job_id },
    });
    expect(res.ok(), `batch failed: ${res.status()}`).toBe(true);
    const body = await res.json();
    expect(body.counters?.errors ?? 0).toBe(0);
    if (body.done) break;
  }

  const sermons = await (await page.request.get('/wp-json/sermon-suite/v1/sermons?per_page=100')).json();
  const imported = sermons.find((s) => (s.title?.rendered || s.title) === title);
  expect(imported, 'imported sermon not found').toBeTruthy();

  // The four join tables, each of which the old column indices dropped.
  expect(imported.topics, 'mtm join dropped').toEqual(
    expect.arrayContaining(['Import Topic Alpha', 'Import Topic Beta'])
  );
  expect(imported.resources?.length, 'mfm join dropped').toBeGreaterThan(0);
  expect(imported.resources[0].url).toBe('https://example.org/import-study-guide.pdf');
  // The fixture leaves the message's own speaker field blank, so this can only
  // have come through the msp join — no fallback to mask a regression.
  expect(imported.speakers, 'msp join dropped').toContain('Import Speaker');
  // Likewise the scripture column is blank, so this proves the scm join, whose
  // column order is flipped relative to the others.
  expect(imported.scripture_ref, 'scm join dropped').toBe('Romans 8:28');
});
