# Working agreements

## Checkout and Git freshness

- This folder is the development repository. The dedicated Cove site's plugin
  symlink resolves here; existing installations on other sites are separate.
- Before editing code, resolve the actual checkout (including symlink targets
  and nested repositories), inspect branch and working tree, and run
  `git fetch --all --prune`. Repeat when switching checkouts, returning after a
  substantial pause, and before publishing; not on every small edit.
- Compare HEAD with its upstream. If no upstream exists, inspect remotes and
  the same-name remote branch; do not assume another branch is the base.
  Report failed fetches or unavailable comparisons.
- If remote commits are missing locally, pause edits to that repository and ask
  how to proceed. Explain that this user-requested freshness rule requires the
  pause. Include checkout, branch, ahead/behind counts, incoming commits and
  affected files, possible conflicts with local changes, and a recommendation.
  Do not pull, merge, rebase, reset, stash, or overwrite local work without
  authorization.
- With no incoming commits, proceed while preserving local changes. Reuse an
  explicit decision for the same fetched state; new incoming commits need a new
  decision. Fetch only updates remote-tracking references, not working files.
  These rules apply to every test-site checkout being edited.

## Development and verification

- See README.md for the Cove test site, source map, and check commands.
- Edit this checkout rather than other sites' plugin copies. Use the dedicated
  `recently-edited-main-dev` Cove site for runtime checks.
- Use WordPress APIs or WP-CLI for site data changes; never raw SQL by default.
- Run PHP syntax checks for changed PHP and the existing updater smoke test
  when changing bootstrap or update behavior. Verify affected UI flows for UI
  changes; the updater test does not exercise them.
- Keep release metadata and plugin versions in sync when making a release.
  Do not bump versions, commit, push, or publish as part of workspace setup.
