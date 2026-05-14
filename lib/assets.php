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
	if ( function_exists( 'elodin_recently_edited_should_load_admin_bar' ) ) {
		if ( ! elodin_recently_edited_should_load_admin_bar() ) {
			return;
		}
	} elseif ( ! is_admin_bar_showing() || ! elodin_recently_edited_runtime_enabled() ) {
		return;
	}

	// Enqueue jQuery dependency
	wp_enqueue_script( 'jquery' );

	// Create nonces for AJAX security
	$nonce_pin       = wp_create_nonce( 'elodin_recently_edited_pin' );
	$nonce_status    = wp_create_nonce( 'elodin_recently_edited_status' );
	$nonce_post_type = wp_create_nonce( 'elodin_recently_edited_post_type' );
	$nonce_title     = wp_create_nonce( 'elodin_recently_edited_title' );
	$nonce_slug      = wp_create_nonce( 'elodin_recently_edited_slug' );
	$nonce_cache     = wp_create_nonce( 'elodin_recently_edited_cache' );
	$current_post_id = function_exists( 'elodin_recently_edited_get_current_post_id' ) ? elodin_recently_edited_get_current_post_id() : 0;
	$current_post    = $current_post_id ? get_post( $current_post_id ) : null;
	$current_edit_url = '';
	$current_view_url = '';
	if ( $current_post instanceof WP_Post ) {
		$current_edit_url = function_exists( 'elodin_recently_edited_get_edit_link' )
			? elodin_recently_edited_get_edit_link( $current_post )
			: get_edit_post_link( $current_post_id );
		$current_view_url = function_exists( 'elodin_recently_edited_get_view_link' )
			? elodin_recently_edited_get_view_link( $current_post )
			: get_permalink( $current_post_id );
	}

	// Localize script with AJAX URL and nonces
	wp_localize_script(
		'jquery',
		'ElodinRecentlyEdited',
		array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'noncePin'      => $nonce_pin,
			'nonceStatus'   => $nonce_status,
			'noncePostType' => $nonce_post_type,
			'nonceTitle'    => $nonce_title,
			'nonceSlug'     => $nonce_slug,
			'nonceCache'    => $nonce_cache,
			'menuRestUrl'   => esc_url_raw( rest_url( 'elodin-recently-edited/v1/menu' ) ),
			'restNonce'     => wp_create_nonce( 'wp_rest' ),
			'currentPostType' => function_exists( 'elodin_recently_edited_get_current_post_type' ) ? elodin_recently_edited_get_current_post_type() : '',
			'currentPostId'   => $current_post_id,
			'currentEditUrl'  => $current_edit_url ? esc_url_raw( $current_edit_url ) : '',
			'currentViewUrl'  => $current_view_url ? esc_url_raw( $current_view_url ) : '',
			'isAdmin'         => is_admin(),
			'cacheKey'        => 'elodin_recently_edited_menu_' . md5( home_url() ),
			'cacheSchema'     => function_exists( 'elodin_recently_edited_get_client_menu_cache_version' ) ? elodin_recently_edited_get_client_menu_cache_version() : 1,
		)
	);

	$js_path  = plugin_dir_path( __FILE__ ) . '../assets/js/admin-bar.js';
	$css_path = plugin_dir_path( __FILE__ ) . '../assets/css/admin-bar.css';
	$js_ver   = file_exists( $js_path ) ? filemtime( $js_path ) : ELODIN_RECENTLY_EDITED_VERSION;
	$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : ELODIN_RECENTLY_EDITED_VERSION;

	// Enqueue main JavaScript file
	wp_enqueue_script(
		'elodin-recently-edited-js',
		plugin_dir_url( __FILE__ ) . '../assets/js/admin-bar.js',
		array( 'jquery' ),
		$js_ver,
		true
	);

	// Enqueue main CSS file
	wp_enqueue_style(
		'elodin-recently-edited-css',
		plugin_dir_url( __FILE__ ) . '../assets/css/admin-bar.css',
		array(),
		$css_ver
	);
}
