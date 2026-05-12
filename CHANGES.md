# Changelog

## 1.4.2 - 2026-05-12
- Reworked the admin-bar menu to use an explicit “Build Initial Index” flow with browser-side caching, avoiding repeated index builds across page loads.
- Added a REST-powered menu index that is cached globally on the server and hydrated from local browser storage when available.
- Prevented unrelated REST and AJAX requests from loading the plugin runtime, admin-bar code, assets, AJAX handlers, or update checker.
- Reduced menu payload size by rendering one canonical row set and filtering it client-side by content type.
- Preserved up to 500 items per content type while keeping the All view based on the combined per-type index.
- Improved search responsiveness with an in-memory row index and direct DOM visibility updates.
- Added small-site automatic index preloading only when no browser cache exists and the site has fewer than 100 editable posts.
- Cleared browser-side menu cache after pin, title, slug, status, form status, and post type changes so refreshed menus reflect edits.
- Cleaned up the unloaded dropdown state with a focused index-building panel and explicit styling.

## 1.4.1 - 2026-04-18
- Refined the licensing flow with a hidden admin page, in-place Ajax activation/refresh/deactivation, and a cleaner single-message success state.
- Simplified the licensing page by removing extra product/status metadata, clearing the saved license key on deactivation, and suppressing unrelated admin notices on that screen.
- Updated licensing copy to use generic license-key language in the plugin UI.
- Expanded current-item highlighting so the full row reads as active, not just the title.
- Increased the per-group Recently Edited item cap to 500 and centralized that limit behind a filterable helper.
- Restored uniform `8px` padding on the content-type pill band.

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
