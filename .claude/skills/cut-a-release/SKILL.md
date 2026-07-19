---
name: cut-a-release
description: Full release runbook for Sermon Suite — bump the version, commit, push, tag, and verify the GitHub Action builds and publishes the release zip. Use when the user says "cut a release", "ship it", "release", "publish a new version", or names a version to release.
---

# Cut a Sermon Suite Release

Run every step below in order. Stop and report if any step fails — never tag a broken state.

Repo facts:
- The version lives in **two places** in `sermon-suite.php`: the `Version:` plugin header (~line 6) and the `SERMON_SUITE_VERSION` constant (~line 17). They must match each other and the tag, or the GitHub Action fails the build on purpose.
- Pushing a tag `vX.Y.Z` triggers `.github/workflows/release.yml`, which builds `sermon-suite.zip` with the correct `sermon-suite/` top-level folder and attaches it to a GitHub Release with auto-generated notes from the commit messages.
- The plugin's built-in updater (`includes/updater.php`) checks GitHub Releases, so every installed site sees the update in **Plugins → Updates** within a day. Nothing else to deploy.
- Release notes = commit messages since the last tag. Write the release commit message in plain, user-facing language — church admins read it in the WordPress "View details" popup.
- The zip build in `release.yml` strips dev tooling (`node_modules`, `tests/`, `package*.json`, `playwright.config.js`, `screenshots/`, `test-results/`). If you add a new top-level dev file or folder, add a matching `rm` line there or it ships to every church.
- Git pushes authenticate through the GitHub CLI (`~/.local/bin/gh`, logged in as AddisonRoberts). If a push ever fails with an auth error, stop and tell the user to re-run `gh auth login` (or `gh auth refresh -s workflow` if the error mentions workflow scope). Never try to work around auth yourself.

## 1. Preflight

```bash
git status
git fetch origin
git log origin/main..HEAD --oneline   # unpushed commits
git log $(git describe --tags --abbrev=0)..HEAD --oneline  # commits since last release
```

- Must be on `main`. If behind `origin/main`, pull (rebase) first.
- Uncommitted changes are fine — they become part of the release commit. List them so the user knows what's shipping.
- If there is nothing new since the last tag and no uncommitted changes, say so and stop.

## 2. Run the smoke suite — green, or no release

```bash
npm test
```

This boots a real WordPress in Node (WordPress Playground, no Docker/PHP needed) with the plugin active and seed data from `tests/blueprint.json`, then runs Playwright smoke tests (`tests/smoke.spec.js`): every REST endpoint, the public shortcode page, a series detail page, a single sermon page, and every wp-admin screen — asserting no PHP fatals/warnings/notices anywhere.

- First run downloads WordPress (~1 min); later runs take ~30s.
- If `node_modules` is missing, run `npm install` first (if npm hits an EACCES cache error, add `--cache <scratchpad>/npm-cache` — the user's `~/.npm` has root-owned files).
- **Any failure = stop.** Report which test failed and what it saw. Do not tag.
- A PHP fatal shows up as page text, not an HTTP error — the suite greps for `Fatal error|Parse error|Warning:|Notice:` in every page body.

## 3. Pick the version

- If the user named a version, use it.
- Otherwise read the current version from `sermon-suite.php` and bump based on what's shipping: bug fixes only → patch (2.1.0 → 2.1.1); new features/settings → minor (2.1.0 → 2.2.0); breaking or major rework → major. State the chosen version and why; only ask if genuinely ambiguous.

## 4. Bump the version in BOTH places

Edit `sermon-suite.php`:
- ` * Version:     X.Y.Z` (plugin header)
- `define( 'SERMON_SUITE_VERSION', 'X.Y.Z' );`

Then verify they match:

```bash
grep -n "Version:\|SERMON_SUITE_VERSION" sermon-suite.php
```

## 5. Commit and push

```bash
git add -A
git commit -m "vX.Y.Z — <plain-language summary of what changed for users>"
git push origin main
```

## 6. Tag and push the tag (this triggers the release build)

```bash
git tag vX.Y.Z
git push origin vX.Y.Z
```

## 7. Verify the release actually published

Poll the public GitHub API (no auth needed) until the release exists with the zip attached — usually under 2 minutes. Poll every ~20s, give up after ~5 minutes:

```bash
curl -s https://api.github.com/repos/Comms-Church/sermon-suite/releases/tags/vX.Y.Z | grep -E '"name"|"browser_download_url"'
```

Success = the release exists and has a `sermon-suite.zip` asset. Also check the workflow run conclusion:

```bash
curl -s "https://api.github.com/repos/Comms-Church/sermon-suite/actions/runs?per_page=1" | grep -E '"status"|"conclusion"|"html_url"' | head -6
```

## 8. Regenerate screenshots

```bash
npm run screenshots
```

Writes fresh full-page captures of the live app (sermon library page, admin dashboard, add-sermon editor, shortcode generator, settings) to `screenshots/` — gitignored, regenerable, for marketing/docs use. Send the user any screenshots that changed meaningfully.

## 9. Refresh the comms.church marketing content

If this release added or changed user-facing features, offer to update the Sermon Suite section of comms.church: invoke the `bricks-comms-church` skill to regenerate the relevant page/section JSON with the new version number and feature claims, and hand the user the importable Bricks JSON (plus the fresh screenshots). This part stays human-in-the-loop — the user imports it in Bricks Builder. For a fixes-only patch release, skip this and say so.

## 10. Report

Tell the user: the version shipped, the release URL (`https://github.com/Comms-Church/sermon-suite/releases/tag/vX.Y.Z`), and that installed sites will pick it up automatically (or instantly via the "Check for updates" link on their Plugins screen).

## If the build fails

Most common cause: tag ↔ header version mismatch. Recovery:

```bash
git tag -d vX.Y.Z
git push origin :refs/tags/vX.Y.Z
# fix sermon-suite.php, commit, push, then re-tag:
git tag vX.Y.Z
git push origin vX.Y.Z
```

Report the Actions run URL (`https://github.com/Comms-Church/sermon-suite/actions`) so the user can see the log.
