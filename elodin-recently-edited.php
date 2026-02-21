<?php
/**
 * Recently Edited Quick Links - Main Plugin File
 *
 * @package ElodinRecentlyEdited
 * @version 1.2.1
 * @author Jon Schroeder
 * @license GPL-2.0+
 */

/*
	Plugin Name: Recently Edited Quick Links
	Plugin URI: https://elod.in
	Description: Adds a quick access menu to the WordPress admin bar showing recently edited posts with status management and pinning functionality.
	Version: 1.2.1
	Author: Jon Schroeder
	Author URI: https://elod.in
	License: GPL-2.0+
	License URI: https://www.gnu.org/licenses/gpl-2.0.html
	Text Domain: elodin-recently-edited
	Requires at least: 5.0
	Tested up to: 6.4
	Requires PHP: 7.2
*/

/* Prevent direct access to the plugin */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Define plugin constants
define( 'ELODIN_RECENTLY_EDITED_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELODIN_RECENTLY_EDITED_URL', plugin_dir_url( __FILE__ ) );
define( 'ELODIN_RECENTLY_EDITED_VERSION', '1.2.1' );
define( 'ELODIN_RECENTLY_EDITED_BASENAME', plugin_basename( __FILE__ ) );

// Include library files with proper path validation
$library_files = array(
	'admin-bar.php',
	'ajax.php',
	'assets.php',
);

foreach ( $library_files as $file ) {
	$file_path = ELODIN_RECENTLY_EDITED_DIR . 'lib/' . $file;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}

// Hook into WordPress
add_action( 'admin_bar_menu', 'elodin_recently_edited_admin_bar', 999 );
add_action( 'admin_enqueue_scripts', 'elodin_recently_edited_enqueue_assets' );
add_action( 'wp_enqueue_scripts', 'elodin_recently_edited_enqueue_assets' );
add_action( 'enqueue_block_editor_assets', 'elodin_recently_edited_block_editor_assets' );
add_filter( 'show_admin_bar', 'elodin_recently_edited_force_admin_bar', 9999 );

// AJAX handlers with proper action naming
add_action( 'wp_ajax_elodin_recently_edited_toggle_pin', 'elodin_recently_edited_toggle_pin' );
add_action( 'wp_ajax_elodin_recently_edited_update_status', 'elodin_recently_edited_update_status' );
add_action( 'wp_ajax_elodin_recently_edited_update_post_type', 'elodin_recently_edited_update_post_type' );

/**
 * Force the admin bar to show for administrative users.
 *
 * @since 1.0.1
 *
 * @param bool $show Whether to show the admin bar.
 * @return bool
 */
function elodin_recently_edited_force_admin_bar( $show ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return $show;
}

/**
 * Ensure the admin bar is visible in the block editor for admins.
 *
 * Disables fullscreen/distraction-free modes when active.
 *
 * @since 1.0.1
 *
 * @return void
 */
function elodin_recently_edited_block_editor_assets() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$inline_script = <<<JS
(function () {
	if (!window.wp || !wp.data || !wp.data.select || !wp.data.dispatch) {
		return;
	}
	var stores = [];
	['core/edit-post', 'core/edit-site'].forEach(function (storeName) {
		var select = wp.data.select(storeName);
		var dispatch = wp.data.dispatch(storeName);
		stores.push({
			name: storeName,
			select: select,
			dispatch: dispatch,
			hasFeatures:
				select &&
				dispatch &&
				typeof select.isFeatureActive === 'function' &&
				typeof dispatch.toggleFeature === 'function',
		});
	});

	function disableFullscreen() {
		if (document.body) {
			document.body.classList.remove('is-fullscreen-mode');
			document.body.classList.remove('is-distraction-free');
		}
		stores.forEach(function (store) {
			if (!store.hasFeatures) {
				return;
			}
			if (store.select.isFeatureActive('fullscreenMode')) {
				store.dispatch.toggleFeature('fullscreenMode');
			}
		});
		var preferencesDispatch =
			wp.data.dispatch && wp.data.dispatch('core/preferences');
		if (preferencesDispatch && typeof preferencesDispatch.set === 'function') {
			preferencesDispatch.set('core', 'fullscreenMode', false);
			preferencesDispatch.set('core', 'distractionFree', false);
		}
	}

	disableFullscreen();

	var lastStates = {};
	stores.forEach(function (store) {
		if (store.hasFeatures) {
			lastStates[store.name] = store.select.isFeatureActive('fullscreenMode');
		}
	});
	wp.data.subscribe(function () {
		var shouldDisable = false;
		stores.forEach(function (store) {
			if (!store.hasFeatures) {
				return;
			}
			var currentState = store.select.isFeatureActive('fullscreenMode');
			if (currentState !== lastStates[store.name]) {
				lastStates[store.name] = currentState;
				shouldDisable = true;
			}
		});
		if (
			shouldDisable ||
			(document.body &&
				document.body.classList.contains('is-fullscreen-mode'))
		) {
			disableFullscreen();
		}
	});
})();
JS;

	wp_add_inline_script( 'wp-edit-post', $inline_script );
}

// Load Plugin Update Checker with error handling
$update_checker_file = ELODIN_RECENTLY_EDITED_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $update_checker_file ) ) {
	require $update_checker_file;

	if ( class_exists( 'Puc_v4_Factory' ) ) {
		$update_checker = Puc_v4_Factory::buildUpdateChecker(
			'https://github.com/jonschr/elodin-recently-edited',
			__FILE__,
			'elodin-recently-edited'
		);

		// Set the branch that contains the stable release
		if ( method_exists( $update_checker, 'setBranch' ) ) {
			$update_checker->setBranch( 'master' );
		}
	}
}
