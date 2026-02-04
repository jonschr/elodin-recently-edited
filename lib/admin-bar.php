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
 * Determine the current post type for related queries.
 *
 * Falls back to "page" if the current context does not provide a post type.
 *
 * @since 0.1
 *
 * @return string Current post type slug.
 */
function elodin_recently_edited_get_current_post_type() {
	$default_post_type = 'page';
	$post_type         = '';

	if ( is_admin() ) {
		if ( isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
			if ( $post_id ) {
				$post_type = get_post_type( $post_id );
			}
		}

		if ( empty( $post_type ) && isset( $_GET['post_type'] ) ) {
			$post_type = sanitize_key( $_GET['post_type'] );
		}

		if ( empty( $post_type ) && ! empty( $GLOBALS['typenow'] ) ) {
			$post_type = $GLOBALS['typenow'];
		}

		if ( empty( $post_type ) && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! empty( $screen->post_type ) ) {
				$post_type = $screen->post_type;
			}
		}
	} else {
		if ( is_singular() ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Post ) {
				$post_type = $queried->post_type;
			}
		}

		if ( empty( $post_type ) && is_post_type_archive() ) {
			$archive_post_type = get_query_var( 'post_type' );
			if ( is_array( $archive_post_type ) ) {
				$archive_post_type = reset( $archive_post_type );
			}
			if ( is_string( $archive_post_type ) ) {
				$post_type = $archive_post_type;
			}
		}

		if ( empty( $post_type ) && is_home() ) {
			$post_type = 'post';
		}
	}

	if ( empty( $post_type ) ) {
		$post_type = $default_post_type;
	}

	$post_type = sanitize_key( $post_type );

	if ( $post_type === 'attachment' ) {
		$post_type = $default_post_type;
	}

	if ( ! get_post_type_object( $post_type ) ) {
		$post_type = $default_post_type;
	}

	return $post_type;
}

/**
 * Get a fallback admin URL for a post type.
 *
 * @since 0.1
 *
 * @param string $post_type Post type slug.
 * @return string Admin URL for the post type list.
 */
function elodin_recently_edited_get_post_type_admin_url( $post_type ) {
	$admin_url = admin_url( 'edit.php?post_type=' . $post_type );
	if ( $post_type === 'post' ) {
		$admin_url = admin_url( 'edit.php' );
	}

	return $admin_url;
}

/**
 * Add a menu with posts to the WordPress admin bar.
 *
 * @since 0.1
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 * @param string       $menu_id Menu id.
 * @param string       $menu_title Menu title.
 * @param array        $pinned_posts Pinned posts list.
 * @param array        $recent_posts Recent posts list.
 * @param array        $pinned_ids Pinned post IDs.
 * @param int|null     $position Menu position.
 * @param array        $extra_items Extra submenu items to show before posts.
 * @param string|null  $main_href_override Override main menu href.
 * @return void
 */
function elodin_recently_edited_add_menu( $wp_admin_bar, $menu_id, $menu_title, $pinned_posts, $recent_posts, $pinned_ids, $position = null, $extra_items = array(), $main_href_override = null, $menu_class = '' ) {
	if ( empty( $recent_posts ) && empty( $pinned_posts ) && empty( $extra_items ) ) {
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

	if ( null !== $main_href_override ) {
		$main_href = $main_href_override;
	}

	// Add main menu item with proper escaping
	$menu_args = array(
		'id'     => $menu_id,
		'title'  => $menu_title,
		'href'   => esc_url( $main_href ),
		'parent' => 'top-secondary',
	);

	if ( ! empty( $menu_class ) ) {
		$menu_args['meta'] = array( 'class' => $menu_class );
	}

	if ( null !== $position ) {
		$menu_args['position'] = $position;
	}

	$wp_admin_bar->add_menu( $menu_args );

	// Add extra submenu items before post list
	if ( ! empty( $extra_items ) ) {
		foreach ( $extra_items as $extra_item ) {
			if ( ! is_array( $extra_item ) || empty( $extra_item['id'] ) ) {
				continue;
			}

			$extra_args = array(
				'id'     => $menu_id . '-' . $extra_item['id'],
				'parent' => $menu_id,
				'title'  => $extra_item['title'],
				'href'   => isset( $extra_item['href'] ) ? $extra_item['href'] : '#',
			);

			if ( ! empty( $extra_item['meta'] ) ) {
				$extra_args['meta'] = $extra_item['meta'];
			}

			$wp_admin_bar->add_menu( $extra_args );
		}
	}

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
		// Link to frontend unless post type has no singular template or status is draft/pending
		$post_type_obj = get_post_type_object( $post->post_type );

		// Check if post type has singular templates
		// Some post types might be registered with publicly_queryable=false but still have templates
		$has_singular_template = false;
		if ( $post_type_obj ) {
			// Standard WordPress post types that should have singular templates
			$standard_public_types = array( 'post', 'page' );
			if ( in_array( $post->post_type, $standard_public_types, true ) ) {
				$has_singular_template = true;
			} elseif ( isset( $post_type_obj->publicly_queryable ) && $post_type_obj->publicly_queryable ) {
				$has_singular_template = true;
			}
		}

		$is_draft_or_pending = in_array( $post->post_status, array( 'draft', 'pending' ), true );

		$title_url = ( $has_singular_template && ! $is_draft_or_pending ) ? $view_url : $edit_url;

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

		$wp_admin_bar->add_menu(
			array(
				'id'     => $menu_id . '-' . $post->ID,
				'parent' => $menu_id,
				'title'  => $row,
				'href'   => '#',
			)
		);
	}
}

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

	elodin_recently_edited_add_menu(
		$wp_admin_bar,
		'recently-edited',
		'<span class="elodin-recently-edited-menu-star" aria-hidden="true">★</span> ' . esc_html__( 'Recently Edited', 'elodin-recently-edited' ),
		$pinned_posts,
		$recent_posts,
		$pinned_ids,
		999
	);

	$current_post_type = elodin_recently_edited_get_current_post_type();
	$post_type_items   = array();
	$post_type_links   = array();

	$post_types = get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
	foreach ( $post_types as $pt_slug => $pt_obj ) {
		if ( ! current_user_can( $pt_obj->cap->edit_posts ) ) {
			continue;
		}

		$href = elodin_recently_edited_get_post_type_admin_url( $pt_slug );

		$type_classes = 'elodin-related-pill';
		if ( $pt_slug === $current_post_type ) {
			$type_classes .= ' is-current';
		}

		$post_type_links[] = '<a class="' . esc_attr( $type_classes ) . '" href="' . esc_url( $href ) . '">' . esc_html( $pt_obj->labels->singular_name ) . '</a>';
	}

	if ( ! empty( $post_type_links ) ) {
		$post_type_items[] = array(
			'id'    => 'types',
			'title' => '<div class="elodin-related-pill-band">' . implode( '', $post_type_links ) . '</div>',
			'href'  => false,
			'meta'  => array( 'class' => 'elodin-related-pill-item' ),
		);
	}

	$related_pinned_posts = array();
	if ( ! empty( $pinned_ids ) ) {
		$related_pinned_posts = get_posts(
			array(
				'post_type'      => $current_post_type,
				'post__in'       => $pinned_ids,
				'orderby'        => 'post__in',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'no_found_rows'  => true, // Performance optimization
			)
		);
	}

	$related_recent_posts = get_posts(
		array(
			'post_type'      => $current_post_type,
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'modified'   => 'DESC',
			),
			'no_found_rows'  => true, // Performance optimization
		)
	);

	$related_unique_ids = array();
	foreach ( array_merge( $related_pinned_posts, $related_recent_posts ) as $post ) {
		if ( is_a( $post, 'WP_Post' ) ) {
			$related_unique_ids[ $post->ID ] = true;
		}
	}

	$related_menu_class = 'elodin-related-menu';
	if ( count( $related_unique_ids ) < 10 ) {
		$related_menu_class .= ' elodin-related-short';
	}

	elodin_recently_edited_add_menu(
		$wp_admin_bar,
		'related',
		esc_html__( 'Related', 'elodin-recently-edited' ),
		$related_pinned_posts,
		$related_recent_posts,
		$pinned_ids,
		998,
		$post_type_items,
		'#',
		$related_menu_class
	);
}
