<?php
/**
 * Cache invalidation for Recently Edited Quick Links.
 *
 * This file is intentionally loaded for every WordPress request. Post updates
 * commonly arrive through the REST API or third-party AJAX actions, while the
 * heavier admin-bar runtime is skipped for those requests.
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
 * Schedule an immediate background rebuild of rendered menu fragments.
 *
 * @since 1.4.2
 *
 * @return void
 */
function elodin_recently_edited_schedule_menu_cache_rebuild() {
	static $queued = false;

	if ( $queued ) {
		return;
	}
	$queued = true;

	$GLOBALS['elodin_recently_edited_cache_rebuild_user_id'] = get_current_user_id();
	add_action( 'shutdown', 'elodin_recently_edited_dispatch_menu_cache_rebuild', 1 );
}

/**
 * Dispatch the queued rebuild after the update request has finished saving.
 *
 * @since 1.8.0
 *
 * @return void
 */
function elodin_recently_edited_dispatch_menu_cache_rebuild() {
	$user_id = isset( $GLOBALS['elodin_recently_edited_cache_rebuild_user_id'] )
		? absint( $GLOBALS['elodin_recently_edited_cache_rebuild_user_id'] )
		: 0;
	$args = array( $user_id );
	if ( wp_next_scheduled( 'elodin_recently_edited_rebuild_menu_cache', $args ) ) {
		return;
	}

	wp_schedule_single_event(
		time(),
		'elodin_recently_edited_rebuild_menu_cache',
		$args
	);

	if ( function_exists( 'spawn_cron' ) ) {
		spawn_cron( time() );
	}
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

	if ( function_exists( 'elodin_recently_edited_get_global_menu_cache_key' ) ) {
		delete_transient( elodin_recently_edited_get_global_menu_cache_key( true ) );
		delete_transient( elodin_recently_edited_get_global_menu_cache_key( false ) );
	}

	elodin_recently_edited_bump_client_menu_cache_version();
	elodin_recently_edited_schedule_menu_cache_rebuild();
}

/**
 * Clear the menu cache after a post, page, or custom post type changes.
 *
 * @since 1.4.2
 *
 * @param int $post_id Changed post ID.
 * @return void
 */
function elodin_recently_edited_clear_menu_cache_on_content_change( $post_id = 0 ) {
	$post_id = absint( $post_id );

	// The parent post save provides the meaningful invalidation for revisions.
	if ( $post_id && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ) {
		return;
	}

	elodin_recently_edited_clear_menu_cache();
}

// Keep these lightweight hooks active during core REST and third-party AJAX saves.
add_action( 'save_post', 'elodin_recently_edited_clear_menu_cache_on_content_change', 100 );
add_action( 'deleted_post', 'elodin_recently_edited_clear_menu_cache_on_content_change', 100 );
add_action( 'trashed_post', 'elodin_recently_edited_clear_menu_cache_on_content_change', 100 );
add_action( 'untrashed_post', 'elodin_recently_edited_clear_menu_cache_on_content_change', 100 );
add_action( 'elodin_recently_edited_rebuild_menu_cache', 'elodin_recently_edited_rebuild_menu_cache' );
