<?php
/**
 * AJAX functionality for Recently Edited Quick Links
 *
 * @package ElodinRecentlyEdited
 */

/**
 * Toggle pin status for a post.
 *
 * @since 0.1
 */
function elodin_recently_edited_toggle_pin() {
	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_pin' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	// Sanitize and validate post ID
	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 403 );
	}

	$user_id = get_current_user_id();

	// Get and validate pinned posts
	$pinned = get_user_meta( $user_id, 'elodin_recently_edited_pins', true );
	if ( ! is_array( $pinned ) ) {
		$pinned = array();
	}

	// Sanitize pinned array
	$pinned = array_map( 'intval', $pinned );
	$pinned = array_filter( $pinned );

	// Toggle pin status
	if ( in_array( $post_id, $pinned, true ) ) {
		$pinned = array_values( array_diff( $pinned, array( $post_id ) ) );
		$state  = 'unpinned';
	} else {
		array_unshift( $pinned, $post_id );
		$pinned = array_slice( array_unique( $pinned ), 0, 20 ); // Limit to 20 pins
		$state  = 'pinned';
	}

	// Save updated pins
	update_user_meta( $user_id, 'elodin_recently_edited_pins', $pinned );
	wp_send_json_success( array( 'state' => $state ) );
}

/**
 * Update post status via AJAX.
 *
 * @since 0.1
 */
function elodin_recently_edited_update_status() {
	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_status' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	// Sanitize and validate inputs
	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$status  = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 403 );
	}

	// Validate status against allowed values
	$allowed_statuses = array( 'draft', 'pending', 'private', 'publish', 'delete' );
	if ( ! in_array( $status, $allowed_statuses, true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid status.' ), 400 );
	}

	// Handle delete operation
	if ( $status === 'delete' ) {
		if ( ! current_user_can( 'delete_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Cannot delete.' ), 403 );
		}
		$result = wp_delete_post( $post_id );
		if ( ! $result ) {
			wp_send_json_error( array( 'message' => 'Failed to delete.' ), 500 );
		}
	} else {
		// Check permissions for status changes
		if ( $status === 'publish' && ! current_user_can( 'publish_posts', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Cannot publish.' ), 403 );
		}
		if ( $status === 'private' && ! current_user_can( 'publish_posts', $post_id ) ) {
			wp_send_json_error( array( 'message' => 'Cannot make private.' ), 403 );
		}
		// draft and pending should be allowed if can edit

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => 'Failed to update status.' ), 500 );
		}
	}

	wp_send_json_success();
}

/**
 * Update post type via AJAX.
 *
 * @since 0.1
 */
function elodin_recently_edited_update_post_type() {
	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_post_type' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	// Sanitize and validate inputs
	$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : '';

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 403 );
	}

	// Get available post types and validate
	$post_types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
	if ( ! isset( $post_types[ $post_type ] ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post type.' ), 400 );
	}

	$pt_obj = $post_types[ $post_type ];
	if ( ! current_user_can( $pt_obj->cap->create_posts ) ) {
		wp_send_json_error( array( 'message' => 'Cannot create posts of this type.' ), 403 );
	}

	// Update post type
	$result = wp_update_post(
		array(
			'ID'        => $post_id,
			'post_type' => $post_type,
		)
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => 'Failed to update post type.' ), 500 );
	}

	wp_send_json_success();
}