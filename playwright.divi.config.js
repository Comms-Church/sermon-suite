// Opt-in compatibility suite: runs Sermon Suite on a real Divi install.
//
//   npm run test:divi          (Divi unzipped into .divi/, git-ignored)
//   DIVI_DIR=/path/to/Divi npm run test:divi
//
// Divi is a licensed theme, so it is never committed here. Boots its own
// WordPress Playground on port 9500, seeded like the main suite plus a page
// built with the Divi Builder, so it can run alongside `npm test`.
const fs = require('fs');
const os = require('os');
const path = require('path');
const { defineConfig } = require('@playwright/test');

// Defaults to .divi/Divi — a git-ignored folder in this repo (unzip Divi.zip
// into .divi/). DIVI_DIR overrides it.
const DIVI_DIR = path.resolve(process.env.DIVI_DIR || '.divi/Divi');
if (!fs.existsSync(path.join(DIVI_DIR, 'style.css'))) {
  throw new Error(
    `No Divi theme at ${DIVI_DIR}. Unzip Divi.zip into .divi/ (git-ignored), ` +
    'or set DIVI_DIR to an unzipped Divi folder.'
  );
}

const PORT = 9500;
const V = (fs.readFileSync(path.join(DIVI_DIR, 'style.css'), 'utf8').match(/^Version:\s*(.+)$/m) || [])[1] || '4';

// A page built with the Divi Builder: the plugin's shortcodes inside Divi's
// Text and Code modules, which is how Divi sites actually embed them.
const diviPage =
  `[et_pb_section fb_built="1" _builder_version="${V}"][et_pb_row _builder_version="${V}"]` +
  `[et_pb_column type="4_4" _builder_version="${V}"]` +
  `[et_pb_text _builder_version="${V}"]<h2>Latest message</h2>[ss_latest_hero][/et_pb_text]` +
  `[et_pb_code _builder_version="${V}"][ss_sermon_archive layout="list"][/et_pb_code]` +
  `[et_pb_text _builder_version="${V}"][ss_series_grid columns="3"][/et_pb_text]` +
  `[/et_pb_column][/et_pb_row][/et_pb_section]`;

const blueprint = JSON.parse(fs.readFileSync('tests/blueprint.json', 'utf8'));
blueprint.steps.push({
  step: 'runPHP',
  code:
    "<?php require_once '/wordpress/wp-load.php';" +
    // Clearing theme_switched skips Divi's onboarding redirect, which would
    // otherwise hijack Playground's plugin-activation request.
    " switch_theme('Divi'); delete_option('theme_switched');" +
    " if ( ! get_page_by_path('divi-sermons') ) {" +
    "   $id = wp_insert_post([ 'post_type'=>'page','post_status'=>'publish','post_title'=>'Divi Sermons'," +
    "     'post_name'=>'divi-sermons','post_content'=>" + JSON.stringify(diviPage) + " ]);" +
    "   update_post_meta($id,'_et_pb_use_builder','on'); update_post_meta($id,'_et_pb_page_layout','et_full_width_page'); }" +
    " update_option('permalink_structure','/%postname%/'); flush_rewrite_rules();",
});
// Written outside test-results/, which Playwright empties at the start of
// every run — after this config has already been evaluated.
const BLUEPRINT = path.join(os.tmpdir(), 'sermon-suite-blueprint-divi.json');
fs.writeFileSync(BLUEPRINT, JSON.stringify(blueprint));

module.exports = defineConfig({
  timeout: 90_000,
  retries: 1,
  workers: 1,
  reporter: [['list']],
  use: { baseURL: `http://127.0.0.1:${PORT}`, screenshot: 'only-on-failure' },
  projects: [
    { name: 'setup', testDir: './tests', testMatch: /auth\.setup\.js/ },
    {
      name: 'divi',
      testDir: './tests/divi',
      dependencies: ['setup'],
      use: { storageState: 'test-results/.auth/admin.json' },
    },
  ],
  webServer: {
    command:
      `npx wp-playground-cli server --auto-mount --login --port ${PORT}` +
      ` --mount "${DIVI_DIR}:/wordpress/wp-content/themes/Divi"` +
      ` --blueprint "${BLUEPRINT}" --blueprint-may-read-adjacent-files`,
    url: `http://127.0.0.1:${PORT}/wp-json/`,
    timeout: 240_000,
    reuseExistingServer: false,
  },
});
