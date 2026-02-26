# Changelog

## 1.2.2 - 2026-02-26
- Removed the forced admin-bar behavior in editors by dropping the `show_admin_bar` override and editor fullscreen/distraction-free toggling.
- Added an explicit admin-bar visibility guard before building Recently Edited / Related dropdown menus.
- Changed FSE template defaults to include only singular templates (`page*`, `single*`, `singular*`) in menu lists.
- Excluded non-singular templates from menu lists by default (archives, `404`, `index`, `search`, and similar layout templates).

## 1.2.1 - 2026-02-13
- Added `name` attributes to admin-bar form fields (`search`, `status`, and `post type`) to resolve accessibility/autofill warnings for unnamed form controls.
