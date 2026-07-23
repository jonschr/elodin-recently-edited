<?php
/**
 * Cache invalidation for Recently Edited Quick Links.
 *
 * This file is intentionally loaded for every WordPress request. Post updates
 * commonly arrive through the REST API or third-party AJAX actions, so those
 * requests only mark the menu as dirty. An administrator page load promotes
 * that marker to a new cache generation and the browser rebuilds it lazily.
 *
 * @package ElodinRecentlyEdited
 */

/**
 * Get the browser-side menu cache version.
 *
 * @since 1.4.2
 *
 * @return int Cache version.
 */
function elodin_recently_edited_get_client_menu_cache_version() {
	$version = (int) get_option( 'elodin_recently_edited_menu_cache_version', 1 );

	return max( 1, $version );
}

/**
 * Bump the browser-side menu cache version.
 *
 * @since 1.4.2
 *
 * @return int Updated cache version.
 */
function elodin_recently_edited_bump_client_menu_cache_version() {
	$version = elodin_recently_edited_get_client_menu_cache_version() + 1;
	update_option( 'elodin_recently_edited_menu_cache_version', $version, false );

	return $version;
}

/**
 * Determine whether content has changed since the last cache generation.
 *
 * @since 1.8.1
 *
 * @return bool Whether the menu cache is dirty.
 */
function elodin_recently_edited_is_menu_cache_dirty() {
	return false !== get_option( 'elodin_recently_edited_menu_cache_dirty', false );
}

/**
 * Mark the menu cache dirty without rebuilding or changing its generation.
 *
 * The option is written only for the first content change in a dirty period.
 * This keeps bulk imports from repeatedly updating the generation option or
 * scheduling one cron rebuild per request.
 *
 * @since 1.8.1
 *
 * @return void
 */
function elodin_recently_edited_mark_menu_cache_dirty() {
	static $marked = false;

	if ( $marked || elodin_recently_edited_is_menu_cache_dirty() ) {
		return;
	}
	$marked = true;

	// Autoload this tiny marker so subsequent import requests find it without
	// issuing a dedicated option query.
	add_option( 'elodin_recently_edited_menu_cache_dirty', time(), '', 'yes' );
}

/**
 * Clear cached rendered menu fragments.
 *
 * The generation is part of the rendered-cache key, so bumping it also
 * invalidates caches from requests where the full admin-bar runtime was not
 * loaded and the exact transient keys cannot be calculated.
 *
 * @since 1.4.2
 *
 * @return void
 */
function elodin_recently_edited_clear_menu_cache() {
	static $cleared = false;

	// A save can fire save, status, and plugin-specific callbacks in one request.
	if ( $cleared ) {
		return;
	}
	$cleared = true;

	delete_option( 'elodin_recently_edited_menu_cache_dirty' );

	if ( function_exists( 'elodin_recently_edited_get_global_menu_cache_key' ) ) {
		delete_transient( elodin_recently_edited_get_global_menu_cache_key( true ) );
		delete_transient( elodin_recently_edited_get_global_menu_cache_key( false ) );
	}

	elodin_recently_edited_bump_client_menu_cache_version();
}

/**
 * Mark the menu cache dirty after content changes.
 *
 * @since 1.8.1
 *
 * @param int $post_id Changed post ID.
 * @return void
 */
function elodin_recently_edited_mark_menu_cache_dirty_on_content_change( $post_id = 0 ) {
	$post_id = absint( $post_id );

	// The parent post save provides the meaningful change for revisions.
	if ( $post_id && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ) {
		return;
	}

	elodin_recently_edited_mark_menu_cache_dirty();
}

/**
 * Promote a pending content change to a new cache generation on page load.
 *
 * Requiring an administrator and an ordinary GET/HEAD page request prevents
 * imports, cron jobs, REST requests, AJAX actions, and form submissions from
 * doing the expensive menu work. The newly generated page tells the browser
 * that its local index is stale; the existing lazy REST request then rebuilds
 * the rendered cache after the page has loaded.
 *
 * @since 1.8.1
 *
 * @return void
 */
function elodin_recently_edited_maybe_invalidate_dirty_menu_cache() {
	if ( ! elodin_recently_edited_is_menu_cache_dirty() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
	) {
		return;
	}

	$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( ! in_array( $request_method, array( 'GET', 'HEAD' ), true ) ) {
		return;
	}

	elodin_recently_edited_clear_menu_cache();
}

// Keep only the lightweight dirty-marker hooks active during external saves.
add_action( 'save_post', 'elodin_recently_edited_mark_menu_cache_dirty_on_content_change', 100 );
add_action( 'deleted_post', 'elodin_recently_edited_mark_menu_cache_dirty_on_content_change', 100 );
add_action( 'trashed_post', 'elodin_recently_edited_mark_menu_cache_dirty_on_content_change', 100 );
add_action( 'untrashed_post', 'elodin_recently_edited_mark_menu_cache_dirty_on_content_change', 100 );

// Cover both wp-admin screens and front-end pages viewed by an administrator.
add_action( 'admin_init', 'elodin_recently_edited_maybe_invalidate_dirty_menu_cache', 1 );
add_action( 'wp', 'elodin_recently_edited_maybe_invalidate_dirty_menu_cache', 1 );
