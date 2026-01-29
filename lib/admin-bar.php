<?php
/**
 * Admin bar functionality for Recently Edited Quick Links
 */

function elodin_recently_edited_admin_bar( $wp_admin_bar ) {
	$user_id = get_current_user_id();
	$pinned_ids = get_user_meta( $user_id, 'elodin_recently_edited_pins', true );
	if ( ! is_array( $pinned_ids ) ) {
		$pinned_ids = array();
	}

	$pinned_posts = array();
	if ( ! empty( $pinned_ids ) ) {
		$pinned_posts = get_posts( array(
			'post_type'      => 'any',
			'post__in'       => $pinned_ids,
			'orderby'        => 'post__in',
			'post_status'    => 'any',
			'posts_per_page' => 20,
		) );
	}

	// Get more posts than needed to account for filtering
	$args = array(
		'post_type'           => 'any',
		'post_type__not_in'   => array( 'attachment' ),
		'post_status'         => 'any',
		'posts_per_page'      => 20, // Get more to account for filtering
		'orderby'             => 'modified',
		'order'               => 'DESC',
	);

	$recent_posts = get_posts( $args );

	if ( empty( $recent_posts ) && empty( $pinned_posts ) ) {
		return;
	}

	// Add main menu item
	$wp_admin_bar->add_menu( array(
		'id'    => 'recently-edited',
		'title' => 'Recently Edited',
		'href'  => '#',
	) );

	// Add submenu items
	$count = 0;
	$all_posts = array_merge( $pinned_posts, $recent_posts );
	$seen_ids = array();

	foreach ( $all_posts as $post ) {
		// Skip attachments (media items)
		if ( $post->post_type === 'attachment' ) {
			continue;
		}

		if ( isset( $seen_ids[ $post->ID ] ) ) {
			continue;
		}
		$seen_ids[ $post->ID ] = true;

		// Check if user can edit this post
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			continue;
		}

		$edit_url = get_edit_post_link( $post->ID );
		if ( ! $edit_url ) {
			continue;
		}

		$view_url = elodin_recently_edited_get_view_link( $post );

		// Limit to 10 items
		if ( $count >= 10 ) {
			break;
		}
		$count++;

		$title = $post->post_title;
		if ( empty( $title ) ) {
			$title = '(no title)';
		} else {
			// Truncate to 40 characters
			if ( strlen( $title ) > 40 ) {
				$title = substr( $title, 0, 40 ) . '...';
			}
		}
		$title = esc_html( $title );

		$post_type_obj = get_post_type_object( $post->post_type );
		$type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;

		$status_labels = array(
			'draft'   => 'Draft',
			'pending' => 'Pending',
			'private' => 'Private',
			'publish' => 'Published',
		);
		$status_label = isset( $status_labels[ $post->post_status ] ) ? $status_labels[ $post->post_status ] : ucfirst( $post->post_status );

		$is_pinned = in_array( $post->ID, $pinned_ids, true );
		$pin_class = $is_pinned ? 'elodin-recently-edited-pin is-pinned' : 'elodin-recently-edited-pin';
		$pin_icon = $is_pinned ? '★' : '☆';

		$status_options = '';
		foreach ( $status_labels as $value => $label ) {
			$selected = $post->post_status === $value ? ' selected' : '';
			$status_options .= '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
		}

		$row = '<span class="elodin-recently-edited-row">'
			. '<span class="elodin-recently-edited-title">'
			. '<span class="elodin-recently-edited-type">' . esc_html( $type_label ) . '</span>'
			. '<span class="elodin-recently-edited-status">' . esc_html( $status_label ) . '</span>'
			. '<span class="elodin-recently-edited-title-link" data-url="' . esc_url( $edit_url ) . '">' . $title . '</span>'
			. '</span>'
			. '<span class="elodin-recently-edited-actions">'
			. '<span class="elodin-recently-edited-action elodin-recently-edited-view" data-url="' . esc_url( $view_url ) . '">View</span>'
			. '<span class="elodin-recently-edited-edit" data-url="' . esc_url( $edit_url ) . '">Edit</span>'
			. '<select class="elodin-recently-edited-status-select" data-post-id="' . intval( $post->ID ) . '">' . $status_options . '</select>'
			. '<span class="' . esc_attr( $pin_class ) . '" data-post-id="' . intval( $post->ID ) . '" title="Pin">' . esc_html( $pin_icon ) . '</span>'
			. '</span>'
			. '</span>';

		$wp_admin_bar->add_menu( array(
			'id'     => 'recently-edited-' . $post->ID,
			'parent' => 'recently-edited',
			'title'  => $row,
			'href'   => $edit_url,
		) );
	}
}

function elodin_recently_edited_get_view_link( $post ) {
	$view_link = get_permalink( $post );
	if ( $post->post_status !== 'publish' ) {
		$view_link = get_preview_post_link( $post );
	}

	if ( ! $view_link ) {
		return get_edit_post_link( $post->ID );
	}

	return $view_link;
}