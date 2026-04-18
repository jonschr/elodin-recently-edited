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
	elodin_recently_edited_require_active_license_for_ajax();

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
	elodin_recently_edited_require_active_license_for_ajax();

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
 * Determine whether the current user can edit Gravity Forms forms.
 *
 * @since 1.3.0
 *
 * @return bool Whether the current user can edit forms.
 */
function elodin_recently_edited_can_edit_gravity_forms() {
	if ( ! class_exists( 'GFAPI' ) && ! class_exists( 'GFFormsModel' ) ) {
		return false;
	}

	if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'current_user_can_any' ) ) {
		return GFCommon::current_user_can_any( array( 'gravityforms_edit_forms' ) );
	}

	return current_user_can( 'gravityforms_edit_forms' );
}

/**
 * Get a Gravity Forms form by ID.
 *
 * @since 1.3.0
 *
 * @param int $form_id Form ID.
 * @return array|false Form data, or false when unavailable.
 */
function elodin_recently_edited_get_gravity_form( $form_id ) {
	if ( class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'get_form' ) ) {
		$form = GFAPI::get_form( $form_id );
		if ( is_array( $form ) ) {
			return $form;
		}
	}

	if ( class_exists( 'GFFormsModel' ) && method_exists( 'GFFormsModel', 'get_form' ) ) {
		$form = GFFormsModel::get_form( $form_id, true );
		if ( $form ) {
			return (array) $form;
		}
	}

	return false;
}

/**
 * Update Gravity Forms status via AJAX.
 *
 * @since 1.3.0
 */
function elodin_recently_edited_update_gravity_form_status() {
	elodin_recently_edited_require_active_license_for_ajax();

	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_status' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
	$status  = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';

	if ( ! $form_id || ! elodin_recently_edited_can_edit_gravity_forms() ) {
		wp_send_json_error( array( 'message' => 'Invalid form.' ), 403 );
	}

	if ( ! elodin_recently_edited_get_gravity_form( $form_id ) ) {
		wp_send_json_error( array( 'message' => 'Form not found.' ), 404 );
	}

	$allowed_statuses = array( 'active', 'inactive' );
	if ( ! in_array( $status, $allowed_statuses, true ) ) {
		wp_send_json_error( array( 'message' => 'Invalid status.' ), 400 );
	}

	$is_active = 'active' === $status ? 1 : 0;
	if ( class_exists( 'GFFormsModel' ) && method_exists( 'GFFormsModel', 'update_form_active' ) ) {
		GFFormsModel::update_form_active( $form_id, $is_active );
	} elseif ( class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'update_form_property' ) ) {
		$result = GFAPI::update_form_property( $form_id, 'is_active', $is_active );
		if ( is_wp_error( $result ) || false === $result ) {
			wp_send_json_error( array( 'message' => 'Failed to update status.' ), 500 );
		}
	} else {
		wp_send_json_error( array( 'message' => 'Gravity Forms API unavailable.' ), 500 );
	}

	wp_send_json_success(
		array(
			'status' => $status,
		)
	);
}

/**
 * Update Gravity Forms title via AJAX.
 *
 * @since 1.3.0
 *
 * @param int    $form_id Form ID.
 * @param string $title New form title.
 */
function elodin_recently_edited_update_gravity_form_title( $form_id, $title ) {
	if ( ! $form_id || ! elodin_recently_edited_can_edit_gravity_forms() ) {
		wp_send_json_error( array( 'message' => 'Invalid form.' ), 403 );
	}

	if ( ! elodin_recently_edited_get_gravity_form( $form_id ) ) {
		wp_send_json_error( array( 'message' => 'Form not found.' ), 404 );
	}

	if ( '' === trim( $title ) ) {
		wp_send_json_error( array( 'message' => 'Title is required.' ), 400 );
	}

	if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'update_form_property' ) ) {
		wp_send_json_error( array( 'message' => 'Gravity Forms API unavailable.' ), 500 );
	}

	$result = GFAPI::update_form_property( $form_id, 'title', $title );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
	}
	if ( false === $result ) {
		wp_send_json_error( array( 'message' => 'Failed to update title.' ), 500 );
	}

	$form = elodin_recently_edited_get_gravity_form( $form_id );
	if ( ! is_array( $form ) ) {
		$form = array(
			'id'    => $form_id,
			'title' => $title,
		);
	}

	$updated_title = isset( $form['title'] ) ? (string) $form['title'] : $title;
	$search_text   = trim( wp_strip_all_tags( $updated_title ) . ' ' . $form_id );
	$display_title = function_exists( 'elodin_recently_edited_get_gravity_form_display_title' )
		? elodin_recently_edited_get_gravity_form_display_title( array( 'title' => $updated_title ) )
		: $updated_title;

	wp_send_json_success(
		array(
			'title'        => $updated_title,
			'displayTitle' => $display_title,
			'searchText'   => $search_text,
		)
	);
}

/**
 * Update post or Gravity Forms title via AJAX.
 *
 * @since 1.3.0
 */
function elodin_recently_edited_update_title() {
	elodin_recently_edited_require_active_license_for_ajax();

	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_title' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	// Sanitize and validate inputs
	$resource_type = isset( $_POST['resource_type'] ) ? sanitize_key( $_POST['resource_type'] ) : 'post';
	$resource_id   = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
	$post_id       = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

	if ( ! $resource_id && $post_id ) {
		$resource_id = $post_id;
	}

	if ( 'gravity_form' === $resource_type ) {
		elodin_recently_edited_update_gravity_form_title( $resource_id, $title );
	}

	$post_id = $resource_id;

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 403 );
	}

	$result = wp_update_post(
		array(
			'ID'         => $post_id,
			'post_title' => $title,
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => 'Failed to update title.' ), 500 );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_send_json_error( array( 'message' => 'Post not found.' ), 404 );
	}

	$search_text = wp_strip_all_tags( $post->post_title );
	if ( '' === trim( $search_text ) ) {
		$search_text = __( '(no title)', 'elodin-recently-edited' );
	}
	$search_text = function_exists( 'elodin_recently_edited_get_post_search_text' )
		? elodin_recently_edited_get_post_search_text( $post )
		: trim( $search_text ) . ' ' . $post->ID;

	$display_title = function_exists( 'elodin_recently_edited_get_display_title' )
		? elodin_recently_edited_get_display_title( $post )
		: $post->post_title;

	wp_send_json_success(
		array(
			'title'        => $post->post_title,
			'displayTitle' => $display_title,
			'searchText'   => $search_text,
		)
	);
}

/**
 * Update post slug via AJAX.
 *
 * @since 1.3.0
 */
function elodin_recently_edited_update_slug() {
	elodin_recently_edited_require_active_license_for_ajax();

	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_slug' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$slug    = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 403 );
	}

	if ( '' === trim( $slug ) ) {
		wp_send_json_error( array( 'message' => 'Slug is required.' ), 400 );
	}

	$result = wp_update_post(
		array(
			'ID'        => $post_id,
			'post_name' => $slug,
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => 'Failed to update slug.' ), 500 );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		wp_send_json_error( array( 'message' => 'Post not found.' ), 404 );
	}

	$display_slug = function_exists( 'elodin_recently_edited_get_display_slug' )
		? elodin_recently_edited_get_display_slug( $post )
		: $post->post_name;
	$search_text  = function_exists( 'elodin_recently_edited_get_post_search_text' )
		? elodin_recently_edited_get_post_search_text( $post )
		: trim( wp_strip_all_tags( $post->post_title ) . ' ' . $post->post_name . ' ' . $post->ID );
	$edit_url     = function_exists( 'elodin_recently_edited_get_edit_link' )
		? elodin_recently_edited_get_edit_link( $post )
		: get_edit_post_link( $post->ID );
	$title_url    = function_exists( 'elodin_recently_edited_get_post_title_link' )
		? elodin_recently_edited_get_post_title_link( $post, $edit_url )
		: get_permalink( $post->ID );
	$copy_url     = get_permalink( $post->ID );

	wp_send_json_success(
		array(
			'slug'        => $post->post_name,
			'displaySlug' => $display_slug,
			'searchText'  => $search_text,
			'titleUrl'    => $title_url,
			'copyUrl'     => $copy_url,
		)
	);
}

/**
 * Update post type via AJAX.
 *
 * @since 0.1
 */
function elodin_recently_edited_update_post_type() {
	elodin_recently_edited_require_active_license_for_ajax();

	// Verify nonce for security
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'elodin_recently_edited_post_type' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	}

	// Sanitize and validate inputs
	$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post.' ), 403 );
	}

	// Match the admin-bar post type switcher so visible types can also be selected.
	$post_types = function_exists( 'elodin_recently_edited_get_switchable_post_types' )
		? elodin_recently_edited_get_switchable_post_types()
		: get_post_types( array( 'show_ui' => true ), 'objects' );
	if ( ! isset( $post_types[ $post_type ] ) ) {
		wp_send_json_error( array( 'message' => 'Invalid post type.' ), 400 );
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
