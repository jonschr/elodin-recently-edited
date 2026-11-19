# Recently Edited Quick Links

WordPress plugin that adds recently edited content to the admin bar, with pinning,
review states, content status controls, and metadata inspection.

Free and open source under GPL-2.0-or-later. No purchase, license key, or
activation is required. See [LICENSE](LICENSE). The bundled update checker
retains its own license.

## Development workspace

This folder is the development checkout of
<https://github.com/jonschr/elodin-recently-edited>, on `master` tracking
`origin/master`. Setup started from `38387ee` (version 1.8.3).

The dedicated Cove test site is <https://recently-edited-main-dev.localhost:8453>.
Its WordPress root is
`/Users/jonschroeder/Cove/Sites/recently-edited-main-dev.localhost/public`.
The site's `wp-content/plugins/elodin-recently-edited` symlinks to this folder,
and the plugin is active. Edits here run directly on that site; no build or copy
step is required. Other Cove sites retain their own plugin copies.

For a fresh administrator login link, run:

```sh
cove login recently-edited-main-dev
```

The site was created with WordPress 7.1.5. PHP CLI during setup was 8.5.8.
Existing WP-CLI and bundled update-checker code emits PHP deprecation notices;
plugin activation and the updater smoke test still pass.

## Code map

- `elodin-recently-edited.php`: plugin bootstrap, request gating, and hooks.
- `lib/admin-bar.php`: menu rendering and content queries.
- `lib/ajax.php`: editing actions and endpoints.
- `lib/cache.php`: menu cache invalidation.
- `lib/settings.php`: settings and review-state configuration.
- `lib/assets.php`, `assets/`: asset loading, JavaScript, and CSS.
- `vendor/plugin-update-checker/`, `update.json`: bundled updater and release metadata.
- `tests/updater-smoke.php`: updater bootstrap check under simulated update AJAX.
- `tests/open-source-smoke.php`: operation without a license and access-control checks.
- `CHANGES.md`: existing change history.

## Checks

Run these from this folder. The smoke test requires the active plugin on the
dedicated Cove site:

```sh
php -l elodin-recently-edited.php
for file in lib/*.php tests/*.php; do php -l "$file" || break; done
wp --path="$(cove path recently-edited-main-dev)" \
  --require=tests/updater-smoke.php eval-file tests/updater-smoke.php
wp --path="$(cove path recently-edited-main-dev)" eval-file tests/open-source-smoke.php
```

Run the open-source smoke test on a disposable development site. It temporarily
changes the saved license option and creates a draft and subscriber, then restores
the option and deletes its fixtures. Existing license data on upgraded sites is
ignored; no licensing-server requests or automatic remote deactivations are made.

For UI changes, also exercise the affected admin-bar or settings flow in the
test site while logged in. The smoke test does not cover those interactions.
There is no project-level Composer or npm build configuration.
