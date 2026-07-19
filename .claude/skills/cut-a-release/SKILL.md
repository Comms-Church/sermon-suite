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

## 2. Sanity-check the plugin loads

There is no test suite, so at minimum lint every PHP file — but only if PHP is installed (`command -v php`). As of July 2026 this Mac does NOT have PHP, so by default:

- Run `git diff $(git describe --tags --abbrev=0)..HEAD --stat` and read through the changed PHP files for obvious breakage (unbalanced braces, missing quotes, half-finished edits).
- Tell the user lint was skipped because PHP isn't installed, and that `brew install php` would enable real syntax checking for future releases.

If PHP **is** available:

```bash
find . -name "*.php" -not -path "./.git/*" -exec php -l {} \; | grep -v "No syntax errors"
```

Any output = a syntax error = no release. Fix it (or report it) first. Never print or claim "all clean" unless php -l actually ran.

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

## 8. Report

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
