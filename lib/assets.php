<?php
/**
 * Assets functionality for Recently Edited Quick Links
 *
 * @package ElodinRecentlyEdited
 */

/**
 * Enqueue assets for the Recently Edited Quick Links plugin.
 *
 * Loads JavaScript and CSS files, and localizes AJAX nonces for security.
 * Only enqueues assets if the admin bar is showing.
 *
 * @since 0.1
 * @return void
 */
function elodin_recently_edited_enqueue_assets() {
	// Only load assets if admin bar is visible
	if ( ! is_admin_bar_showing() ) {
		return;
	}

	// Enqueue jQuery dependency
	wp_enqueue_script( 'jquery' );

	// Create nonces for AJAX security
	$nonce_pin      = wp_create_nonce( 'elodin_recently_edited_pin' );
	$nonce_status   = wp_create_nonce( 'elodin_recently_edited_status' );
	$nonce_post_type = wp_create_nonce( 'elodin_recently_edited_post_type' );

	// Localize script with AJAX URL and nonces
	wp_localize_script(
		'jquery',
		'ElodinRecentlyEdited',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'noncePin'     => $nonce_pin,
			'nonceStatus'  => $nonce_status,
			'noncePostType' => $nonce_post_type,
		)
	);

	// Enqueue main JavaScript file
	wp_enqueue_script(
		'elodin-recently-edited-js',
		plugin_dir_url( __FILE__ ) . '../assets/js/admin-bar.js',
		array( 'jquery' ),
		ELODIN_RECENTLY_EDITED_VERSION,
		true
	);

	// Enqueue main CSS file
	wp_enqueue_style(
		'elodin-recently-edited-css',
		plugin_dir_url( __FILE__ ) . '../assets/css/admin-bar.css',
		array(),
		ELODIN_RECENTLY_EDITED_VERSION
	);
}