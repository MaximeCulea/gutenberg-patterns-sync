# Gutenberg Patterns Sync (Export/Import)

Dev‑oriented plugin to sync patterns from local with other environments (staging, production) by exporting and importing block patterns (wp_block) as JSON via WP‑CLI.
By default, stores these JSON files in the current theme's patterns/blocks-sync directory. It checks existing patterns to handle create/updates instead of hard‑deleting each time, which helps keep references in the DB and code stable.
Also, for each pattern, it keeps synced (or not) status, block categories.

## Requirements
- WordPress 6.0+
- PHP 7.4+
- WP-CLI available in the environment

## Installation

This package is a regular WordPress plugin. You can install and activate it like any other plugin. You can install by Composer, it is designed to be installed as a MU plugin (developer‑oriented).

### As a regular plugin (manual)
- Copy this folder into `wp-content/plugins/gutenberg-patterns-sync` on your site.
- In WP Admin → Plugins, activate “Gutenberg Patterns Sync”.

### Via Composer (as MU‑plugin)
This repository is set with Composer type `wordpress-muplugin`, so when installed via Composer it should be installed under `wp-content/mu-plugins/`.

#### From Packagist

Do composer require maximeculea/gutenberg-patterns-sync.

#### From Github

- Add into your composer json { "type": "vcs", "url": "https://github.com/MaximeCulea/gutenberg-patterns-sync" }
- Include "maximeculea/gutenberg-patterns-sync": "dev-master" in your composer file as require
- Before use, launch composer update

## Commands

- `wp patterns export` — Export all published patterns (`wp_block` posts) to JSON files.
- `wp patterns import` — Import patterns (`wp_block` posts) from JSON files and optionally delete missing ones.

### Export
```
wp patterns export [--dir=<path>] [--no-pretty] [--prefix=<slug-prefix>]
```
Options:
- `--dir=<path>`: Destination directory. Default is current theme: `<theme>/patterns/blocks-sync`.
- `--no-pretty`: Disable pretty-printed JSON (pretty-print is enabled by default).
- `--prefix=<slug-prefix>`: Prefix for the `slug` field inside JSON (e.g. `mytheme`). Defaults to the active theme textdomain.

Output files are named `<slug>.json` and contain:
```
{
  "title": "...",
  "slug": "<prefix>/<slug>",
  "content": "...",
  "categories": ["..."],
  "syncStatus": "synced" | "unsynced"
}
```

### Import
```
wp patterns import [--dir=<path>] [--dry-run] [--no-verbose]
```
Options:
- `--dir=<path>`: Source directory. Default is current theme: `<theme>/patterns/blocks-sync`.
- `--dry-run`: Show actions without applying changes.
- `--no-verbose`: Reduce log noise.

Behavior:
- Creates or updates by slug.
- Assigns `wp_pattern_category` terms (creates them if missing).
- Preserves sync status (`wp_pattern_sync_status` post meta).
- Deletes existing `wp_block` posts not present in the source directory (unless `--dry-run` is used).

## License
© Maxime Culea. Gutenberg Patterns Sync is licensed under GPL-3.0+.
