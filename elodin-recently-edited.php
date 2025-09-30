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

// Add recently edited posts to admin bar
add_action( 'admin_bar_menu', 'elodin_recently_edited_admin_bar', 999 );

function elodin_recently_edited_admin_bar( $wp_admin_bar ) {
	// Get more posts than needed to account for filtering
	$args = array(
		'post_type'           => 'any',
		'post_type__not_in'   => array( 'attachment' ),
		'post_status'         => 'any',
		'posts_per_page'      => 20, // Get more to account for filtering
		'orderby'             => 'modified',
		'order'               => 'DESC',
	);

	$recent_posts = get_posts( $args );

	if ( empty( $recent_posts ) ) {
		return;
	}

	// Add main menu item
	$wp_admin_bar->add_menu( array(
		'id'    => 'recently-edited',
		'title' => 'Recently Edited',
		'href'  => '#',
	) );

	// Add submenu items
	$count = 0;
	foreach ( $recent_posts as $post ) {
		// Skip attachments (media items)
		if ( $post->post_type === 'attachment' ) {
			continue;
		}

		// Check if user can edit this post
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			continue;
		}

		$edit_url = get_edit_post_link( $post->ID );
		if ( ! $edit_url ) {
			continue;
		}

		// Limit to 10 items
		if ( $count >= 10 ) {
			break;
		}
		$count++;

		$title = $post->post_title;
		if ( empty( $title ) ) {
			$title = '(no title)';
		} else {
			// Truncate to 40 characters
			if ( strlen( $title ) > 40 ) {
				$title = substr( $title, 0, 40 ) . '...';
			}
		}
		$title = esc_html( $title );

		$post_type_obj = get_post_type_object( $post->post_type );
		$type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;

		$wp_admin_bar->add_menu( array(
			'id'     => 'recently-edited-' . $post->ID,
			'parent' => 'recently-edited',
			'title'  => $type_label . ' <span style="color: white;">' . $title . '</span>',
			'href'   => $edit_url,
		) );
	}
}

// Load Plugin Update Checker.
require ELODIN_RECENTLY_EDITED_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
$update_checker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/jonschr/elodin-recently-edited',
	__FILE__,
	'elodin-recently-edited'
);

// Optional: Set the branch that contains the stable release.
$update_checker->setBranch( 'master' );