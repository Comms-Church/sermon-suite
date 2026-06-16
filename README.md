# Sermon Suite

**By Comms.Church**

A modern WordPress sermon library plugin. Organize sermons by series, embed YouTube videos, filter by topic, link scripture to Bible Gateway, and manage downloadable resources — all from a purpose-built admin interface.

---

## Features

- Custom sermon and series post types with a clean, purpose-built editor
- YouTube video embedding with thumbnail preview
- Bible book/chapter/verse picker with auto-generated Bible Gateway links
- Topic filtering, speaker tagging, scripture indexing
- Downloadable resources (PDFs, devotionals, notes, links) per sermon
- Series-first archive with grid and list layouts
- Latest Message hero banner — auto-updates every Sunday
- Related sermons (by series and topic) on single sermon pages
- Collapsible sermon notes/outline section
- YouTube playlist sync — paste a playlist URL, create sermon drafts automatically
- Series Engine CSV importer with dry-run and batch processing
- Full REST API at `/wp-json/sermon-suite/v1/`
- Gutenberg blocks for all display types
- Shortcode support for Bricks, Elementor, Divi, and any page builder
- Brand color customization in Settings

---

## Installation

1. Upload the `sermon-suite/` folder to `/wp-content/plugins/`
2. Activate in **Plugins → Installed Plugins**
3. Go to **Settings → Permalinks** and click Save
4. Go to **Sermons → Settings** to configure your sermons page and brand colors

---

## Gutenberg Blocks

Search **"Sermon Suite"** in the block inserter. Five blocks available:

| Block | Description |
|---|---|
| Sermon Archive | Series grid or list with topic filter |
| Latest Message Hero | Auto-updating cinematic banner |
| Series Grid | Standalone series card grid |
| Sermon Player | Single sermon embed |
| Related Sermons | More from series + topic |

---

## Shortcodes

| Shortcode | Description |
|---|---|
| `[ss_sermon_archive]` | Full archive (grid or list layout) |
| `[ss_latest_hero]` | Latest message hero banner |
| `[ss_series_grid columns="3"]` | Series card grid |
| `[ss_sermon_player id="X"]` | Single sermon embed |
| `[ss_related_sermons id="X"]` | Related sermons |

Use in any page builder via a Shortcode element, or in Gutenberg via the Shortcode block.

---

## REST API

Base URL: `https://yoursite.com/wp-json/sermon-suite/v1/`

| Endpoint | Description |
|---|---|
| `GET /series` | All series — params: `per_page`, `page`, `topic`, `featured` |
| `GET /series/{id}` | Single series with all sermons |
| `GET /sermons` | All sermons — params: `per_page`, `page`, `series_id`, `topic`, `speaker`, `search`, `year` |
| `GET /sermons/{id}` | Single sermon with full data |
| `GET /topics` | All topics with counts |
| `GET /speakers` | All speakers |

---

## Importing from Series Engine

1. In Series Engine: **Tools → Export**
2. In Sermon Suite: **Sermons → Import CSV**
3. Upload the CSV, run Dry Run to preview, then import for real

---

## Brand Colors

**Sermons → Settings → Brand Colors** — seven color pickers with a live preview card. Changes apply instantly to the front end via CSS variables.

---

## YouTube Playlist Sync

On any series edit screen, paste a YouTube playlist URL and click **Sync Now**. New videos become sermon drafts automatically. Existing sermons and any edits you've made are never overwritten. Requires a free YouTube Data API v3 key (set in Settings).

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Any theme or page builder

---

## By Comms.Church

Built for churches of all sizes. [comms.church](https://comms.church)
