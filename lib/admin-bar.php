<?php
/**
 * Admin bar functionality for Recently Edited Quick Links
 */

/**
 * Get the view link for a post.
 *
 * @since 0.1
 *
 * @param WP_Post|null $post Post object.
 * @return string Permalink URL or '#' if post is invalid.
 */
function elodin_recently_edited_get_view_link( $post ) {
	if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
		return '#';
	}
	return get_permalink( $post->ID );
}

/**
 * Check if a post can be viewed on the frontend.
 *
 * @since 0.1
 *
 * @param WP_Post $post Post object.
 * @return bool True if post can be viewed on frontend, false otherwise.
 */
/**
 * Add recently edited posts menu to the WordPress admin bar.
 *
 * @since 0.1
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 */
function elodin_recently_edited_admin_bar( $wp_admin_bar ) {
	// Validate admin bar object
	if ( ! is_a( $wp_admin_bar, 'WP_Admin_Bar' ) ) {
		return;
	}

	$user_id = get_current_user_id();

	// Ensure we have a valid user
	if ( ! $user_id ) {
		return;
	}

	// Get pinned posts with proper validation
	$pinned_ids = get_user_meta( $user_id, 'elodin_recently_edited_pins', true );
	if ( ! is_array( $pinned_ids ) ) {
		$pinned_ids = array();
	}

	// Sanitize pinned IDs
	$pinned_ids = array_map( 'intval', $pinned_ids );
	$pinned_ids = array_filter( $pinned_ids );

	$pinned_posts = array();
	if ( ! empty( $pinned_ids ) ) {
		$pinned_posts = get_posts(
			array(
				'post_type'      => 'any',
				'post__in'       => $pinned_ids,
				'orderby'        => 'post__in',
				'post_status'    => 'any',
				'posts_per_page' => 20,
				'no_found_rows'  => true, // Performance optimization
			)
		);
	}

	// Get recent posts with security considerations
	$args = array(
		'post_type'           => 'any',
		'post_type__not_in'   => array( 'attachment' ), // Exclude media attachments
		'post_status'         => 'any',
		'posts_per_page'      => 20, // Get more to account for filtering
		'orderby'             => 'modified',
		'order'               => 'DESC',
		'no_found_rows'       => true, // Performance optimization
	);

	$recent_posts = get_posts( $args );

	if ( empty( $recent_posts ) && empty( $pinned_posts ) ) {
		return;
	}

	// Find the most recent post the user can edit
	$most_recent_edit_url = '#';
	$most_recent_post_id  = 0;
	$all_posts_for_link   = array_merge( $pinned_posts, $recent_posts );

	foreach ( $all_posts_for_link as $post ) {
		// Skip attachments and validate post object
		if ( ! is_a( $post, 'WP_Post' ) || $post->post_type === 'attachment' ) {
			continue;
		}

		// Check edit permissions
		if ( current_user_can( 'edit_post', $post->ID ) ) {
			$edit_url = get_edit_post_link( $post->ID );
			if ( $edit_url ) {
				$most_recent_edit_url = $edit_url;
				$most_recent_post_id  = $post->ID;
				break;
			}
		}
	}

	// If we're on the edit page of the most recent post, link to frontend instead
	$main_href = $most_recent_edit_url;
	if ( is_admin() && isset( $_GET['post'] ) && isset( $_GET['action'] ) && $_GET['action'] === 'edit' && intval( $_GET['post'] ) === $most_recent_post_id ) {
		$main_href = elodin_recently_edited_get_view_link( get_post( $most_recent_post_id ) );
	}

	// Add main menu item with proper escaping
	$wp_admin_bar->add_menu(
		array(
			'id'     => 'recently-edited',
			'title'  => esc_html__( 'Recently Edited', 'elodin-recently-edited' ),
			'href'   => esc_url( $main_href ),
			'parent' => 'top-secondary',
		)
	);

	// Add submenu items
	$count = 0;
	$all_posts = array_merge( $pinned_posts, $recent_posts );
	$seen_ids = array();

	foreach ( $all_posts as $post ) {
		// Validate post object and skip attachments
		if ( ! is_a( $post, 'WP_Post' ) || $post->post_type === 'attachment' ) {
			continue;
		}

		// Prevent duplicates
		if ( isset( $seen_ids[ $post->ID ] ) ) {
			continue;
		}
		$seen_ids[ $post->ID ] = true;

		// Check edit permissions
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			continue;
		}

		$edit_url = get_edit_post_link( $post->ID );
		if ( ! $edit_url ) {
			continue;
		}

		$view_url = elodin_recently_edited_get_view_link( $post );

		// Limit to 50 items
		if ( $count >= 50 ) {
			break;
		}
		$count++;

		// Process post title with proper sanitization
		$title = $post->post_title;
		if ( empty( $title ) ) {
			$title = esc_html__( '(no title)', 'elodin-recently-edited' );
		} else {
			// Truncate to 40 characters for UI consistency
			if ( strlen( $title ) > 40 ) {
				$title = substr( $title, 0, 40 ) . '...';
			}
		}
		$title = esc_html( $title );

		$is_pinned = in_array( $post->ID, $pinned_ids, true );
		$pin_class = $is_pinned ? 'elodin-recently-edited-pin is-pinned' : 'elodin-recently-edited-pin';
		$pin_icon = $is_pinned ? '★' : '☆';

		// Build status options with security
		$status_options = '';
		$status_labels  = array(
			'draft'   => esc_html__( 'Draft', 'elodin-recently-edited' ),
			'pending' => esc_html__( 'Pending', 'elodin-recently-edited' ),
			'private' => esc_html__( 'Private', 'elodin-recently-edited' ),
			'publish' => esc_html__( 'Published', 'elodin-recently-edited' ),
			'delete'  => esc_html__( 'Delete', 'elodin-recently-edited' ),
		);
		foreach ( $status_labels as $value => $label ) {
			$selected = $post->post_status === $value ? ' selected' : '';
			$class = $value === 'delete' ? ' class="delete-option"' : '';
			$status_options .= '<option value="' . esc_attr( $value ) . '"' . $selected . $class . '>' . esc_html( $label ) . '</option>';
		}

		// Build post type options
		$post_type_options = '';
		$post_types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
		foreach ( $post_types as $pt_slug => $pt_obj ) {
			// Check if user can create posts of this type
			if ( ! current_user_can( $pt_obj->cap->create_posts ) ) {
				continue;
			}
			$selected = $post->post_type === $pt_slug ? ' selected' : '';
			$post_type_options .= '<option value="' . esc_attr( $pt_slug ) . '"' . $selected . '>' . esc_html( $pt_obj->labels->singular_name ) . '</option>';
		}

		// Determine the URL for the title link
		// Always link to frontend view for consistency
		$title_url = $view_url;

		// Add class for non-published posts
		$row_class = $post->post_status === 'publish' ? 'elodin-recently-edited-row' : 'elodin-recently-edited-row elodin-recently-edited-row--not-published';

		// Build menu item HTML with proper escaping
		$row = '<span class="' . esc_attr( $row_class ) . '">'
			. '<span class="elodin-recently-edited-title">'
			. '<span class="elodin-recently-edited-action elodin-recently-edited-title-link" data-url="' . esc_url( $title_url ) . '">' . $title . '</span>'
			. '</span>'
			. '<span class="elodin-recently-edited-actions">'
			. '<span class="elodin-recently-edited-action elodin-recently-edited-edit" data-url="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'elodin-recently-edited' ) . '</span>'
			. '<select class="elodin-recently-edited-status-select" data-post-id="' . intval( $post->ID ) . '" data-original="' . esc_attr( $post->post_status ) . '">' . $status_options . '</select>'
			. '<select class="elodin-recently-edited-post-type-select" data-post-id="' . intval( $post->ID ) . '" data-original="' . esc_attr( $post->post_type ) . '">' . $post_type_options . '</select>'
			. '<span class="' . esc_attr( $pin_class ) . '" data-post-id="' . intval( $post->ID ) . '" title="' . esc_attr__( 'Pin', 'elodin-recently-edited' ) . '">' . esc_html( $pin_icon ) . '</span>'
			. '</span>'
			. '</span>';

		$wp_admin_bar->add_menu( array(
			'id'     => 'recently-edited-' . $post->ID,
			'parent' => 'recently-edited',
			'title'  => $row,
			'href'   => '#',
		) );
	}
}

