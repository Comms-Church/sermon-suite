# Sermon Suite — GitHub Hosting & Self-Update Guide

This guide gets your plugin onto GitHub with automatic updates, so every
site running Sermon Suite sees new versions in **Plugins → Updates** the
same way they'd see any WordPress.org plugin update.

---

## How it works (the short version)

1. Your plugin code lives in the GitHub repo `Comms-Church/sermon-suite`.
2. When you push a **version tag** (like `v1.3.0`), a GitHub Action
   automatically builds a clean `sermon-suite.zip` and attaches it to a
   GitHub Release.
3. The updater built into the plugin checks the GitHub Releases API once a
   day. When it sees a release newer than the installed version, WordPress
   offers the update — one click, just like any other plugin.

No manifest file to hand-edit, no manual zipping. You bump a version, push
a tag, done.

---

## One-time setup

### 1. Create the repository

You've already created the `Comms-Church` organization. Now:

- Go to the org → **New repository**
- Name: `sermon-suite` (exactly — it must match the plugin folder)
- Visibility: **Public**
- Do **not** add a README, .gitignore, or license (they're already in the code)
- Click **Create repository**

### 2. Push the plugin code

On your computer, unzip the `sermon-suite.zip` from this session somewhere,
open a terminal in that `sermon-suite` folder, and run:

```bash
git init
git branch -M main
git add .
git commit -m "Initial release — Sermon Suite 1.3.0"
git remote add origin https://github.com/Comms-Church/sermon-suite.git
git push -u origin main
```

If git asks you to authenticate, use a GitHub personal access token as the
password (GitHub no longer accepts account passwords over HTTPS). You can
create one at **GitHub → Settings → Developer settings → Personal access
tokens → Tokens (classic)** with the `repo` scope.

### 3. Cut your first release

This is what triggers the auto-build:

```bash
git tag v1.3.0
git push origin v1.3.0
```

That's it. Within a minute or two:

- The **Actions** tab will show a "Build Release" run.
- When it finishes, the **Releases** section (right side of the repo page)
  will have a `v1.3.0` release with `sermon-suite.zip` attached.

You can watch it under the repo's **Actions** tab. Green check = done.

---

## Releasing a new version (every time after)

Three steps:

1. **Bump the version in two places** in `sermon-suite.php`:
   ```php
    * Version:     1.4.0          ← line ~6 (plugin header)
   define( 'SERMON_SUITE_VERSION', '1.4.0' );   ← line ~14
   ```
   Both must match the tag, or the build will fail with a clear error
   (this is a safety check so you never ship mismatched versions).

2. **Commit and push** your changes:
   ```bash
   git add .
   git commit -m "Version 1.4.0 — describe what changed"
   git push
   ```

3. **Tag and push the tag:**
   ```bash
   git tag v1.4.0
   git push origin v1.4.0
   ```

The Action builds the zip, attaches it to a new release, and auto-generates
release notes from your commit messages. Every installed site picks up the
update within a day (or instantly if they click **Check for updates** on the
Plugins screen).

> **Tip:** Write meaningful commit messages — they become the changelog
> shown in WordPress's "View details" popup and the GitHub release notes.

---

## Installing on a site for the first time

Since this isn't on WordPress.org, the first install is a manual upload
(updates after that are automatic):

1. Download `sermon-suite.zip` from the latest GitHub release.
2. WordPress admin → **Plugins → Add New → Upload Plugin**.
3. Choose the zip, install, activate.
4. **Settings → Permalinks → Save** once (registers the URL structure).

From then on, that site gets updates automatically.

---

## How a site owner sees updates

- **Dashboard → Updates** and the **Plugins** screen show "new version
  available" with a working **update now** link — identical to any
  WordPress.org plugin.
- The plugin's row on the Plugins screen also has a **Check for updates**
  link that clears the cache and checks immediately, for the impatient.
- **View details** shows the changelog pulled from your release notes.

---

## Changing the repo location later

If you ever move or rename the repo, edit one line in
`includes/updater.php`:

```php
const GITHUB_REPO = 'Comms-Church/sermon-suite';
```

Then ship that as a normal update. Every site that already has the current
version will pick up the new location on their next update.

---

## Troubleshooting

**The Action failed with a version mismatch error.**
The tag (`v1.4.0`) didn't match the `Version:` line in `sermon-suite.php`.
Fix the header, commit, delete the bad tag, and re-tag:
```bash
git tag -d v1.4.0
git push origin :refs/tags/v1.4.0
# fix version in sermon-suite.php, commit, push, then:
git tag v1.4.0
git push origin v1.4.0
```

**A site isn't seeing the update.**
WordPress caches update checks for up to 12 hours. Click **Check for
updates** on the Plugins screen, or visit **Dashboard → Updates** and hit
**Check again**.

**The update downloads but installs to a folder like `sermon-suite-1`.**
This means the zip's internal folder name is wrong. The included GitHub
Action builds the zip with the correct `sermon-suite/` top-level folder, so
this only happens if you manually zip the wrong way. Always let the Action
build it, or zip so the structure is `sermon-suite/sermon-suite.php`.

**GitHub API rate limits.**
Unauthenticated API calls (what the updater uses) are limited to 60/hour
per server IP. Since the check is cached for a day, this is a non-issue for
normal use even on servers hosting many sites.
