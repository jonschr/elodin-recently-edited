<?php
/**
 * Assets functionality for Recently Edited Quick Links
 */

function elodin_recently_edited_enqueue_assets() {
	if ( ! is_admin_bar_showing() ) {
		return;
	}

	wp_enqueue_script( 'jquery' );

	$nonce = wp_create_nonce( 'elodin_recently_edited_pin' );
	wp_localize_script(
		'jquery',
		'ElodinRecentlyEdited',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => $nonce,
		)
	);

	wp_enqueue_script(
		'elodin-recently-edited-js',
		plugin_dir_url( __FILE__ ) . '../assets/js/admin-bar.js',
		array( 'jquery' ),
		ELODIN_RECENTLY_EDITED_VERSION,
		true
	);

	wp_enqueue_style(
		'elodin-recently-edited-css',
		plugin_dir_url( __FILE__ ) . '../assets/css/admin-bar.css',
		array(),
		ELODIN_RECENTLY_EDITED_VERSION
	);
}