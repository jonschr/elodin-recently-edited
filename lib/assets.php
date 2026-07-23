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
	$nonce_review    = wp_create_nonce( 'elodin_recently_edited_review' );
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
			'nonceReview'   => $nonce_review,
			'menuRestUrl'   => esc_url_raw( rest_url( 'elodin-recently-edited/v1/menu' ) ),
			'mediaRestUrl'  => esc_url_raw( rest_url( 'elodin-recently-edited/v1/media' ) ),
			'metaRestUrl'   => esc_url_raw( rest_url( 'elodin-recently-edited/v1/posts/' ) ),
			'restNonce'     => wp_create_nonce( 'wp_rest' ),
			'currentPostType' => function_exists( 'elodin_recently_edited_get_current_post_type' ) ? elodin_recently_edited_get_current_post_type() : '',
			'currentPostId'   => $current_post_id,
			'currentUserId'   => get_current_user_id(),
			'currentEditUrl'  => $current_edit_url ? esc_url_raw( $current_edit_url ) : '',
			'currentViewUrl'  => $current_view_url ? esc_url_raw( $current_view_url ) : '',
			'reviewStates'    => function_exists( 'elodin_recently_edited_get_review_states_config' ) ? array_values( elodin_recently_edited_get_review_states_config( true ) ) : array(),
			'reviewPostTypes' => function_exists( 'elodin_recently_edited_get_review_post_types' ) ? elodin_recently_edited_get_review_post_types() : array(),
			'reviewPostStates' => function_exists( 'elodin_recently_edited_get_user_review_states' ) ? elodin_recently_edited_get_user_review_states( get_current_user_id() ) : array(),
			'isAdmin'         => is_admin(),
			'cacheKey'        => 'elodin_recently_edited_menu_' . md5( home_url() ) . '_' . ELODIN_RECENTLY_EDITED_VERSION,
			'cacheSchema'     => function_exists( 'elodin_recently_edited_get_client_menu_cache_version' ) ? elodin_recently_edited_get_client_menu_cache_version() : 1,
			'cacheFormat'     => 3,
			'strings'         => array(
				'copied'             => __( 'Copied', 'elodin-recently-edited' ),
				'copiedFilename'     => __( 'Copied filename', 'elodin-recently-edited' ),
				'copiedUrl'          => __( 'Copied URL', 'elodin-recently-edited' ),
				'loadingMedia'       => __( 'Loading media...', 'elodin-recently-edited' ),
				'unableToLoadMedia'  => __( 'Unable to load media.', 'elodin-recently-edited' ),
				'noMediaMatches'     => __( 'No media matches found.', 'elodin-recently-edited' ),
				'loadingMeta'        => __( 'Loading post meta...', 'elodin-recently-edited' ),
				'unableToLoadMeta'   => __( 'Unable to load post meta.', 'elodin-recently-edited' ),
				'noMetaMatches'      => __( 'No meta keys match this search.', 'elodin-recently-edited' ),
				'copiedMetaKey'      => __( 'Copied meta key', 'elodin-recently-edited' ),
				'copiedMetaValue'    => __( 'Copied meta value', 'elodin-recently-edited' ),
				'reviewSaved'        => __( 'Review status saved.', 'elodin-recently-edited' ),
				'reviewSaveFailed'   => __( 'Unable to save review status.', 'elodin-recently-edited' ),
				'moveToTrashConfirm' => __( 'Move "%s" to the Trash?', 'elodin-recently-edited' ),
				'starred'            => __( 'Starred', 'elodin-recently-edited' ),
				'recentlyEdited'     => __( 'Recently edited', 'elodin-recently-edited' ),
			),
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
