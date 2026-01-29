<?php
/*
	Plugin Name: Recently Edited Quick Links
	Plugin URI: https://elod.in
	Description: Just another plugin
	Version: 0.1
	Author: Jon Schroeder
	Author URI: https://elod.in

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.
*/


/* Prevent direct access to the plugin */
if ( !defined( 'ABSPATH' ) ) {
	die( "Sorry, you are not allowed to access this page directly." );
}

// Plugin directory
define( 'ELODIN_RECENTLY_EDITED_DIR', dirname( __FILE__ ) );

// Define the version of the plugin
define ( 'ELODIN_RECENTLY_EDITED_VERSION', '0.1' );

// Include library files
require_once ELODIN_RECENTLY_EDITED_DIR . '/lib/admin-bar.php';
require_once ELODIN_RECENTLY_EDITED_DIR . '/lib/ajax.php';
require_once ELODIN_RECENTLY_EDITED_DIR . '/lib/assets.php';

// Add recently edited posts to admin bar
add_action( 'admin_bar_menu', 'elodin_recently_edited_admin_bar', 999 );
add_action( 'admin_enqueue_scripts', 'elodin_recently_edited_enqueue_assets' );
add_action( 'wp_enqueue_scripts', 'elodin_recently_edited_enqueue_assets' );
add_action( 'wp_ajax_elodin_recently_edited_toggle_pin', 'elodin_recently_edited_toggle_pin' );
add_action( 'wp_ajax_elodin_recently_edited_update_status', 'elodin_recently_edited_update_status' );
add_action( 'wp_ajax_elodin_recently_edited_update_post_type', 'elodin_recently_edited_update_post_type' );

// Load Plugin Update Checker.
require ELODIN_RECENTLY_EDITED_DIR . '/vendor/plugin-update-checker/plugin-update-checker.php';
$update_checker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/jonschr/elodin-recently-edited',
	__FILE__,
	'elodin-recently-edited'
);

// Optional: Set the branch that contains the stable release.
$update_checker->setBranch( 'master' );
