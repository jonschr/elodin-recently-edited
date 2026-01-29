<?php
/**
 * AJAX functionality for Recently Edited Quick Links
 */

function elodin_recently_edited_toggle_pin() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_pin' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 403 );
	}

	$user_id = get_current_user_id();
	$pinned = get_user_meta( $user_id, 'elodin_recently_edited_pins', true );
	if ( ! is_array( $pinned ) ) {
		$pinned = array();
	}

	if ( in_array( $post_id, $pinned, true ) ) {
		$pinned = array_values( array_diff( $pinned, array( $post_id ) ) );
		$state = 'unpinned';
	} else {
		array_unshift( $pinned, $post_id );
		$pinned = array_slice( array_unique( $pinned ), 0, 20 );
		$state = 'pinned';
	}

	update_user_meta( $user_id, 'elodin_recently_edited_pins', $pinned );
	wp_send_json_success( array( 'state' => $state ) );
}