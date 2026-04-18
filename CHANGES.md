# Changelog

## 1.4.0 - 2026-04-18
- Added Lemon Squeezy licensing with a dedicated WordPress admin page for activating, refreshing, and deactivating site licenses.
- Added a `Licensing` shortcut on the Plugins screen for direct access to the licensing page.
- Added unlicensed admin notices and disabled the plugin's runtime functionality until a valid license is active, while leaving update checks untouched.
- Added periodic background license revalidation for administrators and stored license metadata such as instance, customer, product, and expiry details.
- Added product/store/variant constraint hooks and constants so release builds can verify a license belongs to the intended Lemon Squeezy product.

## 1.3.1 - 2026-04-10
- Combined the former Recently Edited and Related admin-bar dropdowns into a single starred Recently Edited menu.
- Added content-type pills inside the menu, including an All view and per-type lists that switch on click.
- Defaulted the active content-type view to the current screen's post type, while search switches back to All to maximize matches.
- Moved the scrolling region to the post list so search, content-type pills, and column headings stay visible.
- Added compact column headings, scrollbar-aware alignment, a subtle header divider/shadow, and a slimmer custom scrollbar.
- Highlighted the current post anywhere it appears in the menu.
- Aligned content-type pills with the same post types available in the post type switcher.
- Improved menu close timing so accidental pointer movement away from the dropdown is less disruptive.
- Added inline title editing from the title column while keeping direct title text as the view/preview link.
- Added a wider dropdown layout with an editable slug column after the title; direct slug clicks copy the full URL.
- Added Escape handling so cancelling an inline title edit does not close the full dropdown.
- Added Gravity Forms as a supported content type when Gravity Forms is active.
- Added Gravity Forms preview links that open in a new tab, edit links to the form editor, notification settings links, inline form title editing, Active/Inactive status editing, and shortcode copying from the form ID.
- Routed Edit links for Elementor-built posts to the Elementor editor instead of the regular WordPress editor, and open those Elementor links in a new tab.
- Normalized front-end and admin menu queries so public editable content types, including Modern Tribe Events, appear consistently across contexts.

## 1.2.2 - 2026-02-26
- Removed the forced admin-bar behavior in editors by dropping the `show_admin_bar` override and editor fullscreen/distraction-free toggling.
- Added an explicit admin-bar visibility guard before building Recently Edited / Related dropdown menus.
- Changed FSE template defaults to include only singular templates (`page*`, `single*`, `singular*`) in menu lists.
- Excluded non-singular templates from menu lists by default (archives, `404`, `index`, `search`, and similar layout templates).

## 1.2.1 - 2026-02-13
- Added `name` attributes to admin-bar form fields (`search`, `status`, and `post type`) to resolve accessibility/autofill warnings for unnamed form controls.
