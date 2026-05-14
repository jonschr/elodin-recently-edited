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
 * Determine whether a post has been built with Elementor.
 *
 * @since 1.3.0
 *
 * @param WP_Post|null $post Post object.
 * @return bool Whether the post uses Elementor.
 */
function elodin_recently_edited_is_elementor_post( $post ) {
	if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
		return false;
	}

	$is_elementor = 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true );

	/**
	 * Filter whether a post should use the Elementor editor link.
	 *
	 * @since 1.3.0
	 *
	 * @param bool    $is_elementor Whether the post uses Elementor.
	 * @param WP_Post $post         Post object.
	 */
	return (bool) apply_filters( 'elodin_recently_edited_is_elementor_post', $is_elementor, $post );
}

/**
 * Get the Elementor editor URL for a post.
 *
 * @since 1.3.0
 *
 * @param WP_Post|null $post Post object.
 * @return string Elementor editor URL, or an empty string when unavailable.
 */
function elodin_recently_edited_get_elementor_edit_link( $post ) {
	if ( ! elodin_recently_edited_is_elementor_post( $post ) ) {
		return '';
	}

	if (
		class_exists( '\Elementor\Plugin' )
		&& isset( \Elementor\Plugin::$instance->documents )
		&& method_exists( \Elementor\Plugin::$instance->documents, 'get' )
	) {
		$document = \Elementor\Plugin::$instance->documents->get( $post->ID );
		if ( $document && method_exists( $document, 'get_edit_url' ) ) {
			$edit_url = $document->get_edit_url();
			if ( $edit_url ) {
				return $edit_url;
			}
		}
	}

	return add_query_arg(
		array(
			'post'   => $post->ID,
			'action' => 'elementor',
		),
		admin_url( 'post.php' )
	);
}

/**
 * Get the most appropriate edit link for a post.
 *
 * Uses Elementor when the post was built with Elementor, otherwise falls back to WordPress.
 *
 * @since 1.3.0
 *
 * @param WP_Post|null $post Post object.
 * @return string Edit URL, or an empty string when unavailable.
 */
function elodin_recently_edited_get_edit_link( $post ) {
	if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
		return '';
	}

	$elementor_edit_url = elodin_recently_edited_get_elementor_edit_link( $post );
	if ( $elementor_edit_url ) {
		return $elementor_edit_url;
	}

	return get_edit_post_link( $post->ID );
}

/**
 * Get the title link for a post row.
 *
 * Draft-like posts use the editor; published public content uses the view link.
 *
 * @since 1.3.0
 *
 * @param WP_Post|null $post Post object.
 * @param string       $edit_url Optional edit URL.
 * @return string Title URL, or an empty string when unavailable.
 */
function elodin_recently_edited_get_post_title_link( $post, $edit_url = '' ) {
	if ( ! $post || ! is_a( $post, 'WP_Post' ) ) {
		return '';
	}

	if ( '' === $edit_url ) {
		$edit_url = elodin_recently_edited_get_edit_link( $post );
	}

	$post_type_obj = get_post_type_object( $post->post_type );

	$has_singular_template = false;
	if ( $post_type_obj ) {
		$standard_public_types = array( 'post', 'page' );
		if ( in_array( $post->post_type, $standard_public_types, true ) ) {
			$has_singular_template = true;
		} elseif ( isset( $post_type_obj->publicly_queryable ) && $post_type_obj->publicly_queryable ) {
			$has_singular_template = true;
		}
	}

	$is_draft_or_pending = in_array( $post->post_status, array( 'draft', 'pending' ), true );

	return ( $has_singular_template && ! $is_draft_or_pending ) ? elodin_recently_edited_get_view_link( $post ) : $edit_url;
}

/**
 * Get normalized slug for a wp_template post.
 *
 * Template post names can include a theme namespace (for example "theme//single-page").
 *
 * @since 1.2.2
 *
 * @param WP_Post $post Post object.
 * @return string Normalized template slug.
 */
function elodin_recently_edited_get_template_slug( $post ) {
	$slug = is_a( $post, 'WP_Post' ) ? (string) $post->post_name : '';
	if ( '' === $slug ) {
		return '';
	}

	if ( false !== strpos( $slug, '//' ) ) {
		$parts = explode( '//', $slug );
		$slug  = (string) end( $parts );
	}

	return sanitize_title( $slug );
}

/**
 * Determine whether a post should be shown in Recently Edited/Related menus.
 *
 * Defaults wp_template entries to off unless they are singular templates.
 *
 * @since 1.2.2
 *
 * @param WP_Post $post Post object.
 * @return bool
 */
function elodin_recently_edited_should_include_post( $post ) {
	if ( ! is_a( $post, 'WP_Post' ) ) {
		return false;
	}

	if ( 'attachment' === $post->post_type ) {
		return false;
	}

	if ( 'wp_template' !== $post->post_type ) {
		return true;
	}

	$template_slug = elodin_recently_edited_get_template_slug( $post );
	if ( '' === $template_slug ) {
		return false;
	}

	$allowed_prefixes = array( 'page', 'single', 'singular' );
	$is_singular      = false;

	foreach ( $allowed_prefixes as $prefix ) {
		if ( $template_slug === $prefix || 0 === strpos( $template_slug, $prefix . '-' ) ) {
			$is_singular = true;
			break;
		}
	}

	/**
	 * Filter whether a wp_template post should be included in menu lists.
	 *
	 * @since 1.2.2
	 *
	 * @param bool    $is_singular  Whether the template matches singular defaults.
	 * @param WP_Post $post         Post object.
	 * @param string  $template_slug Normalized template slug.
	 */
	return (bool) apply_filters( 'elodin_recently_edited_include_wp_template', $is_singular, $post, $template_slug );
}

/**
 * Filter a post list to entries that should appear in plugin menus.
 *
 * @since 1.2.2
 *
 * @param array $posts List of post objects.
 * @return array
 */
function elodin_recently_edited_filter_menu_posts( $posts ) {
	if ( ! is_array( $posts ) ) {
		return array();
	}

	$filtered = array();
	foreach ( $posts as $post ) {
		if ( elodin_recently_edited_should_include_post( $post ) ) {
			$filtered[] = $post;
		}
	}

	return $filtered;
}

/**
 * Get post statuses that menu queries should include.
 *
 * Explicit statuses avoid front-end/admin differences in how WordPress resolves "any".
 *
 * @since 1.3.0
 *
 * @return array Post status slugs.
 */
function elodin_recently_edited_get_menu_post_statuses() {
	$statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );

	/**
	 * Filter post statuses included in Recently Edited menu queries.
	 *
	 * @since 1.3.0
	 *
	 * @param array $statuses Post status slugs.
	 */
	$statuses = apply_filters( 'elodin_recently_edited_menu_post_statuses', $statuses );
	if ( ! is_array( $statuses ) ) {
		return array( 'publish', 'future', 'draft', 'pending', 'private' );
	}

	$valid_statuses = array();
	foreach ( $statuses as $status ) {
		$status = sanitize_key( $status );
		if ( $status && get_post_status_object( $status ) ) {
			$valid_statuses[] = $status;
		}
	}

	return array_values( array_unique( $valid_statuses ) );
}

/**
 * Get the maximum number of items to load and render per menu group.
 *
 * @since 1.4.0
 *
 * @return int Item limit.
 */
function elodin_recently_edited_get_menu_item_limit() {
	$limit = 500;

	/**
	 * Filter the maximum number of items loaded per Recently Edited group.
	 *
	 * @since 1.4.0
	 *
	 * @param int $limit Item limit.
	 */
	$limit = (int) apply_filters( 'elodin_recently_edited_menu_item_limit', $limit );

	return max( 1, $limit );
}

/**
 * Get total posts across switchable content types.
 *
 * @since 1.4.2
 *
 * @return int Total content count.
 */
function elodin_recently_edited_get_total_switchable_post_count() {
	$total      = 0;
	$post_types = elodin_recently_edited_get_switchable_post_types();
	$statuses   = elodin_recently_edited_get_menu_post_statuses();

	foreach ( array_keys( $post_types ) as $post_type ) {
		$counts = wp_count_posts( $post_type );
		foreach ( $statuses as $status ) {
			if ( isset( $counts->{$status} ) ) {
				$total += (int) $counts->{$status};
			}
		}
	}

	return $total;
}

/**
 * Determine whether the menu can preload for small sites.
 *
 * @since 1.4.2
 *
 * @return bool Whether to preload.
 */
function elodin_recently_edited_should_preload_menu_index() {
	$threshold = (int) apply_filters( 'elodin_recently_edited_preload_post_count_threshold', 100 );

	return elodin_recently_edited_get_total_switchable_post_count() < $threshold;
}

/**
 * Determine whether the admin-bar menu should be built for this request.
 *
 * @since 1.4.2
 *
 * @return bool Whether menu runtime should run.
 */
function elodin_recently_edited_should_load_admin_bar() {
	if ( ! is_admin_bar_showing() || ! elodin_recently_edited_runtime_enabled() ) {
		return false;
	}

	if ( empty( $GLOBALS['elodin_recently_edited_render_full_menu'] ) && ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) ) {
		return false;
	}

	/**
	 * Filter whether Recently Edited should build its admin-bar menu.
	 *
	 * @since 1.4.2
	 *
	 * @param bool $should_load Whether the menu should be built.
	 */
	return (bool) apply_filters( 'elodin_recently_edited_should_load_admin_bar', true );
}

/**
 * Get the global rendered menu cache key.
 *
 * @since 1.4.2
 *
 * @return string Cache key.
 */
function elodin_recently_edited_get_global_menu_cache_key( $is_admin_context = null ) {
	if ( null === $is_admin_context ) {
		$is_admin_context = is_admin();
	}

	return 'elodin_recently_edited_menu_' . md5(
		wp_json_encode(
			array(
				'version'   => ELODIN_RECENTLY_EDITED_VERSION,
				'schema'    => 3,
				'statuses'  => elodin_recently_edited_get_menu_post_statuses(),
				'limit'     => elodin_recently_edited_get_menu_item_limit(),
				'is_admin'  => (bool) $is_admin_context,
				'posttypes' => array_keys( elodin_recently_edited_get_switchable_post_types() ),
				'gf'        => elodin_recently_edited_is_gravity_forms_available(),
			)
		)
	);
}

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
 * Save globally rendered menu fragments.
 *
 * @since 1.4.2
 *
 * @param array  $value Cached value.
 * @return void
 */
function elodin_recently_edited_set_global_menu_cache_item( $value ) {
	$ttl = (int) apply_filters( 'elodin_recently_edited_menu_cache_ttl', 10 * MINUTE_IN_SECONDS );
	set_transient( elodin_recently_edited_get_global_menu_cache_key(), $value, $ttl );
}

/**
 * Get globally rendered menu fragments.
 *
 * @since 1.4.2
 *
 * @return array|false Cached value, or false on miss.
 */
function elodin_recently_edited_get_global_menu_cache_item() {
	$cache = get_transient( elodin_recently_edited_get_global_menu_cache_key() );

	return is_array( $cache ) ? $cache : false;
}

/**
 * Apply request-specific active/current classes to cached fragments.
 *
 * @since 1.4.2
 *
 * @param array  $cached_menu Cached fragments.
 * @param string $current_post_type Current post type.
 * @param int    $current_post_id Current post ID.
 * @param array  $pinned_ids Pinned post IDs.
 * @return array Decorated fragments.
 */
function elodin_recently_edited_decorate_cached_menu( $cached_menu, $current_post_type, $current_post_id, $pinned_ids = array() ) {
	if ( ! is_array( $cached_menu ) ) {
		return array();
	}

	$current_post_type = sanitize_key( $current_post_type );
	$current_post_id   = intval( $current_post_id );
	$pinned_ids        = array_fill_keys( array_map( 'intval', is_array( $pinned_ids ) ? $pinned_ids : array() ), true );
	$active_group      = $current_post_type ? $current_post_type : 'all';

	if ( ! empty( $cached_menu['available_groups'] ) && is_array( $cached_menu['available_groups'] ) && ! in_array( $active_group, $cached_menu['available_groups'], true ) ) {
		$active_group = 'all';
	}

	if ( ! empty( $cached_menu['post_type_links'] ) && is_array( $cached_menu['post_type_links'] ) ) {
		foreach ( $cached_menu['post_type_links'] as $index => $link ) {
			$link = str_replace( array( ' is-current', ' is-active' ), '', (string) $link );
			if ( false !== strpos( $link, 'data-related-target="' . esc_attr( $active_group ) . '"' ) ) {
				$link = preg_replace( '/class="([^"]*elodin-related-pill[^"]*)"/', 'class="$1 is-active"', $link, 1 );
			}
			if ( $current_post_type && false !== strpos( $link, 'data-related-target="' . esc_attr( $current_post_type ) . '"' ) ) {
				$link = preg_replace( '/class="([^"]*elodin-related-pill[^"]*)"/', 'class="$1 is-current"', $link, 1 );
			}
			$cached_menu['post_type_links'][ $index ] = $link;
		}
	}

	if ( ! empty( $cached_menu['post_list_rows'] ) && is_array( $cached_menu['post_list_rows'] ) ) {
		foreach ( $cached_menu['post_list_rows'] as $index => $row ) {
			$row = str_replace( array( ' is-active', ' elodin-recently-edited-row--current' ), '', (string) $row );
			$row_matches_group = 'all' === $active_group
				? true
				: false !== strpos( $row, 'data-post-type="' . esc_attr( $active_group ) . '"' ) || false !== strpos( $row, 'data-related-group="' . esc_attr( $active_group ) . '"' );
			if ( $row_matches_group ) {
				$row = preg_replace( '/class="([^"]*elodin-recently-edited-list-item[^"]*)"/', 'class="$1 is-active"', $row, 1 );
			}
			if ( $current_post_id && false !== strpos( $row, 'data-post-id="' . $current_post_id . '"' ) ) {
				$row = preg_replace( '/class="([^"]*elodin-recently-edited-row[^"]*)"/', 'class="$1 elodin-recently-edited-row--current"', $row, 1 );
			}
			$row = preg_replace_callback(
				'/<span class="([^"]*elodin-recently-edited-pin[^"]*)" data-post-id="(\d+)"([^>]*)>(.*?)<\/span>/',
				function ( $matches ) use ( $pinned_ids ) {
					$post_id   = intval( $matches[2] );
					$is_pinned = isset( $pinned_ids[ $post_id ] );
					$classes   = str_replace( ' is-pinned', '', $matches[1] );
					if ( $is_pinned ) {
						$classes .= ' is-pinned';
					}

					return '<span class="' . esc_attr( $classes ) . '" data-post-id="' . $post_id . '"' . $matches[3] . '>' . ( $is_pinned ? '★' : '☆' ) . '</span>';
				},
				$row
			);
			$cached_menu['post_list_rows'][ $index ] = $row;
		}

		$cached_menu['post_list_rows'] = elodin_recently_edited_sort_cached_rows_by_pins( $cached_menu['post_list_rows'], $pinned_ids );
	}

	return $cached_menu;
}

/**
 * Sort cached row fragments so pinned rows appear first.
 *
 * Pinned rows are sorted by modified date descending, while unpinned rows keep
 * their existing cache order.
 *
 * @since 1.5.0
 *
 * @param array $rows Cached row HTML fragments.
 * @param array $pinned_ids Pinned post IDs keyed by ID.
 * @return array Sorted row HTML fragments.
 */
function elodin_recently_edited_sort_cached_rows_by_pins( $rows, $pinned_ids ) {
	if ( empty( $rows ) || ! is_array( $rows ) || empty( $pinned_ids ) ) {
		return $rows;
	}

	$indexed_rows = array();
	foreach ( $rows as $index => $row ) {
		$post_id  = 0;
		$modified = 0;

		if ( preg_match( '/data-post-id="(\d+)"/', (string) $row, $matches ) ) {
			$post_id = intval( $matches[1] );
		}

		if ( preg_match( '/data-modified="(\d+)"/', (string) $row, $matches ) ) {
			$modified = intval( $matches[1] );
		}

		$indexed_rows[] = array(
			'html'     => $row,
			'index'    => $index,
			'modified' => $modified,
			'pinned'   => $post_id && isset( $pinned_ids[ $post_id ] ),
		);
	}

	usort(
		$indexed_rows,
		function ( $a, $b ) {
			if ( $a['pinned'] !== $b['pinned'] ) {
				return $a['pinned'] ? -1 : 1;
			}

			if ( $a['pinned'] && $a['modified'] !== $b['modified'] ) {
				return $a['modified'] > $b['modified'] ? -1 : 1;
			}

			return $a['index'] <=> $b['index'];
		}
	);

	return wp_list_pluck( $indexed_rows, 'html' );
}

/**
 * Clear cached rendered menu fragments.
 *
 * @since 1.4.2
 *
 * @return void
 */
function elodin_recently_edited_clear_menu_cache() {
	delete_transient( elodin_recently_edited_get_global_menu_cache_key( true ) );
	delete_transient( elodin_recently_edited_get_global_menu_cache_key( false ) );
	elodin_recently_edited_bump_client_menu_cache_version();
	elodin_recently_edited_schedule_menu_cache_rebuild();
}

/**
 * Schedule an immediate background rebuild of rendered menu fragments.
 *
 * @since 1.4.2
 *
 * @return void
 */
function elodin_recently_edited_schedule_menu_cache_rebuild() {
	$args = array( get_current_user_id() );
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
 * Rebuild rendered menu fragments in the background.
 *
 * @since 1.4.2
 *
 * @param int $user_id User ID whose capabilities should be used for rendering.
 * @return void
 */
function elodin_recently_edited_rebuild_menu_cache( $user_id = 0 ) {
	$user_id = intval( $user_id );
	if ( $user_id > 0 ) {
		wp_set_current_user( $user_id );
	}

	if ( ! is_user_logged_in() || ! elodin_recently_edited_runtime_enabled() ) {
		return;
	}

	if ( ! class_exists( 'WP_Admin_Bar' ) ) {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
	}

	$wp_admin_bar       = new WP_Admin_Bar();
	$previous_rendering = ! empty( $GLOBALS['elodin_recently_edited_render_full_menu'] );

	show_admin_bar( true );
	$GLOBALS['elodin_recently_edited_render_full_menu'] = true;
	elodin_recently_edited_admin_bar( $wp_admin_bar );
	$GLOBALS['elodin_recently_edited_render_full_menu'] = $previous_rendering;
}

/**
 * Clear rendered menu cache after post changes.
 *
 * @since 1.4.2
 *
 * @return void
 */
function elodin_recently_edited_clear_menu_cache_on_content_change() {
	elodin_recently_edited_clear_menu_cache();
}

/**
 * Prepare WP_Query arguments for admin-bar menu lookups.
 *
 * Menu lists should reflect editable content, not front-end archive semantics.
 *
 * @since 1.3.0
 *
 * @param array $args Query arguments.
 * @return array Prepared query arguments.
 */
function elodin_recently_edited_prepare_menu_query_args( $args ) {
	if ( ! is_array( $args ) ) {
		$args = array();
	}

	$args = wp_parse_args(
		$args,
		array(
			'no_found_rows'                 => true,
			'ignore_sticky_posts'           => true,
			'suppress_filters'             => true,
			'tribe_suppress_query_filters' => true,
			'update_post_term_cache'        => false,
		)
	);

	/**
	 * Filter query arguments used for Recently Edited menu post lookups.
	 *
	 * @since 1.3.0
	 *
	 * @param array $args Query arguments.
	 */
	return apply_filters( 'elodin_recently_edited_menu_query_args', $args );
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
	if ( ! empty( $GLOBALS['elodin_recently_edited_current_post_type'] ) ) {
		$override = sanitize_key( $GLOBALS['elodin_recently_edited_current_post_type'] );
		if ( 'gravity_forms' === $override || get_post_type_object( $override ) ) {
			return $override;
		}
	}

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

		if ( empty( $post_type ) && isset( $_GET['page'] ) && in_array( sanitize_key( $_GET['page'] ), array( 'gf_edit_forms', 'gf_new_form', 'gf_entries' ), true ) ) {
			$post_type = 'gravity_forms';
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

		if ( empty( $post_type ) && isset( $_GET['gf_page'] ) && 'preview' === sanitize_key( $_GET['gf_page'] ) ) {
			$post_type = 'gravity_forms';
		}
	}

	if ( empty( $post_type ) ) {
		$post_type = $default_post_type;
	}

	$post_type = sanitize_key( $post_type );

	if ( $post_type === 'attachment' ) {
		$post_type = $default_post_type;
	}

	if ( 'gravity_forms' === $post_type && elodin_recently_edited_is_gravity_forms_available() ) {
		return $post_type;
	}

	if ( ! get_post_type_object( $post_type ) ) {
		$post_type = $default_post_type;
	}

	return $post_type;
}

/**
 * Determine the current post ID for row highlighting.
 *
 * @since 1.3.0
 *
 * @return int Current post ID, or 0 when unavailable.
 */
function elodin_recently_edited_get_current_post_id() {
	if ( isset( $GLOBALS['elodin_recently_edited_current_post_id'] ) ) {
		return intval( $GLOBALS['elodin_recently_edited_current_post_id'] );
	}

	$post_id = 0;

	if ( is_admin() ) {
		if ( isset( $_GET['post'] ) ) {
			$post_id = intval( $_GET['post'] );
		}
	} elseif ( is_singular() ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			$post_id = intval( $queried->ID );
		}
	}

	return $post_id;
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
 * Get the toolbar markup shown at the top of the Recently Edited menu.
 *
 * @since 1.5.0
 *
 * @return string Toolbar HTML.
 */
function elodin_recently_edited_get_search_toolbar_html() {
	$toolbar_links = '';
	if ( current_user_can( 'manage_options' ) ) {
		$toolbar_links = '<a class="elodin-recently-edited-toolbar-link" href="' . esc_url( admin_url( 'options-general.php?page=elodin-recently-edited-settings' ) ) . '">' . esc_html__( 'Settings', 'elodin-recently-edited' ) . '</a>'
			. '<a class="elodin-recently-edited-toolbar-link elodin-recently-edited-cache-refresh" href="#">' . esc_html__( 'Rebuild cache', 'elodin-recently-edited' ) . '</a>';
	}

	return '<div class="elodin-recently-edited-toolbar">'
		. '<div class="elodin-recently-edited-search"><input class="elodin-recently-edited-search-input" type="search" name="elodin_recently_edited_search" placeholder="' . esc_attr__( 'Search this site\'s content...', 'elodin-recently-edited' ) . '" aria-label="' . esc_attr__( 'Search this site\'s content', 'elodin-recently-edited' ) . '" /></div>'
		. '<div class="elodin-recently-edited-toolbar-links">' . $toolbar_links . '</div>'
		. '</div>';
}

/**
 * Get post types that are supported by the related switcher.
 *
 * @since 1.5.0
 *
 * @return array<string,WP_Post_Type> Post type objects keyed by slug.
 */
function elodin_recently_edited_get_available_switchable_post_types() {
	static $available = null;

	if ( null !== $available ) {
		return $available;
	}

	$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
	$available  = array();

	foreach ( $post_types as $pt_slug => $pt_obj ) {
		if (
			function_exists( 'elodin_recently_edited_is_excluded_content_type' )
			&& elodin_recently_edited_is_excluded_content_type( $pt_slug, $pt_obj )
		) {
			continue;
		}

		$available[ $pt_slug ] = $pt_obj;
	}

	return $available;
}

/**
 * Get post types that can appear in the related switcher.
 *
 * @since 1.3.0
 *
 * @return array<string,WP_Post_Type> Post type objects keyed by slug.
 */
function elodin_recently_edited_get_switchable_post_types() {
	static $available = null;

	if ( null !== $available ) {
		return $available;
	}

	$post_types         = elodin_recently_edited_get_available_switchable_post_types();
	$enabled_post_types = function_exists( 'elodin_recently_edited_get_enabled_post_types' )
		? elodin_recently_edited_get_enabled_post_types()
		: array_keys( $post_types );
	$available          = array();

	foreach ( $post_types as $pt_slug => $pt_obj ) {
		if ( in_array( $pt_slug, $enabled_post_types, true ) ) {
			$available[ $pt_slug ] = $pt_obj;
		}
	}

	return $available;
}

/**
 * Determine whether Gravity Forms can be listed in the menu.
 *
 * @since 1.3.0
 *
 * @return bool Whether Gravity Forms forms are available for the current user.
 */
function elodin_recently_edited_is_gravity_forms_available() {
	if ( ! class_exists( 'GFFormsModel' ) && ! class_exists( 'RGFormsModel' ) ) {
		return false;
	}

	if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'current_user_can_any' ) ) {
		return GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_preview_forms' ) );
	}

	return current_user_can( 'gravityforms_edit_forms' ) || current_user_can( 'gravityforms_preview_forms' );
}

/**
 * Get Gravity Forms records for the menu.
 *
 * @since 1.3.0
 *
 * @return array<int,array<string,mixed>> Forms as menu item arrays.
 */
function elodin_recently_edited_get_gravity_forms_items() {
	if ( ! elodin_recently_edited_is_gravity_forms_available() ) {
		return array();
	}

	$forms = array();
	if ( class_exists( 'GFFormsModel' ) && method_exists( 'GFFormsModel', 'get_forms_columns' ) ) {
		$forms = GFFormsModel::get_forms_columns( null, false, 'date_updated', 'DESC', array( 'id', 'title', 'date_created', 'date_updated', 'is_active' ) );
	} elseif ( class_exists( 'RGFormsModel' ) && method_exists( 'RGFormsModel', 'get_forms' ) ) {
		$forms = RGFormsModel::get_forms( null, 'date_created', 'DESC', false );
	}

	if ( ! is_array( $forms ) ) {
		return array();
	}

	$items = array();
	foreach ( $forms as $form ) {
		$form = (array) $form;
		$id   = isset( $form['id'] ) ? intval( $form['id'] ) : 0;
		if ( ! $id ) {
			continue;
		}

		$title        = isset( $form['title'] ) ? (string) $form['title'] : '';
		$date_created = isset( $form['date_created'] ) ? (string) $form['date_created'] : '';
		$date_updated = isset( $form['date_updated'] ) ? (string) $form['date_updated'] : '';

		$items[] = array(
			'id'                => $id,
			'title'             => '' === trim( $title ) ? __( '(no title)', 'elodin-recently-edited' ) : $title,
			'is_active'         => ! empty( $form['is_active'] ),
			'status'            => ! empty( $form['is_active'] ) ? __( 'Active', 'elodin-recently-edited' ) : __( 'Inactive', 'elodin-recently-edited' ),
			'date_created'      => $date_created,
			'date_updated'      => $date_updated,
			'view_url'          => trailingslashit( site_url() ) . '?gf_page=preview&id=' . $id,
			'edit_url'          => admin_url( 'admin.php?page=gf_edit_forms&id=' . $id ),
			'notifications_url' => add_query_arg(
				array(
					'page'    => 'gf_edit_forms',
					'view'    => 'settings',
					'subview' => 'notification',
					'id'      => $id,
				),
				admin_url( 'admin.php' )
			),
			'modified_ts'       => $date_updated ? strtotime( $date_updated ) : strtotime( $date_created ),
		);
	}

	return $items;
}

/**
 * Get the title text displayed for a Gravity Forms row.
 *
 * @since 1.3.0
 *
 * @param array $form_item Form menu item.
 * @return string Display title.
 */
function elodin_recently_edited_get_gravity_form_display_title( $form_item ) {
	$title = isset( $form_item['title'] ) ? (string) $form_item['title'] : __( '(no title)', 'elodin-recently-edited' );
	if ( strlen( $title ) > 40 ) {
		$title = substr( $title, 0, 40 ) . '...';
	}

	return $title;
}

/**
 * Get the title text displayed in menu rows.
 *
 * @since 1.3.0
 *
 * @param WP_Post $post Post object.
 * @return string Display title.
 */
function elodin_recently_edited_get_display_title( $post ) {
	if ( ! is_a( $post, 'WP_Post' ) || '' === trim( $post->post_title ) ) {
		return __( '(no title)', 'elodin-recently-edited' );
	}

	$title = $post->post_title;
	if ( strlen( $title ) > 40 ) {
		$title = substr( $title, 0, 40 ) . '...';
	}

	return $title;
}

/**
 * Get the slug text displayed in menu rows.
 *
 * @since 1.3.0
 *
 * @param WP_Post $post Post object.
 * @return string Display slug.
 */
function elodin_recently_edited_get_display_slug( $post ) {
	if ( ! is_a( $post, 'WP_Post' ) || '' === trim( $post->post_name ) ) {
		return __( '(no slug)', 'elodin-recently-edited' );
	}

	$slug = $post->post_name;
	if ( strlen( $slug ) > 34 ) {
		$slug = substr( $slug, 0, 34 ) . '...';
	}

	return $slug;
}

/**
 * Get searchable row text for a post.
 *
 * @since 1.3.0
 *
 * @param WP_Post $post Post object.
 * @return string Search text.
 */
function elodin_recently_edited_get_post_search_text( $post ) {
	if ( ! is_a( $post, 'WP_Post' ) ) {
		return '';
	}

	$title = wp_strip_all_tags( $post->post_title );
	if ( '' === trim( $title ) ) {
		$title = __( '(no title)', 'elodin-recently-edited' );
	}

	return trim( $title . ' ' . $post->post_name . ' ' . $post->ID );
}

/**
 * Build the row HTML for a post menu item.
 *
 * @since 1.3.0
 *
 * @param WP_Post $post Post object.
 * @param array   $pinned_ids Pinned post IDs.
 * @param string  $group Related group slug.
 * @param int     $current_post_id Current post ID.
 * @return string Row HTML, or an empty string when the row should be skipped.
 */
function elodin_recently_edited_get_post_row( $post, $pinned_ids, $group = 'all', $current_post_id = 0 ) {
	static $post_type_options = null;

	if ( ! elodin_recently_edited_should_include_post( $post ) ) {
		return '';
	}

	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return '';
	}

	$edit_url = elodin_recently_edited_get_edit_link( $post );
	if ( ! $edit_url ) {
		return '';
	}

	$search_text = elodin_recently_edited_get_post_search_text( $post );

	$title = esc_html( elodin_recently_edited_get_display_title( $post ) );
	$slug  = esc_html( elodin_recently_edited_get_display_slug( $post ) );

	$is_pinned = in_array( $post->ID, $pinned_ids, true );
	$pin_class = $is_pinned ? 'elodin-recently-edited-pin is-pinned' : 'elodin-recently-edited-pin';
	$pin_icon  = $is_pinned ? '★' : '☆';

	$status_options = '';
	$status_labels  = array(
		'draft'   => esc_html__( 'Draft', 'elodin-recently-edited' ),
		'pending' => esc_html__( 'Pending', 'elodin-recently-edited' ),
		'private' => esc_html__( 'Private', 'elodin-recently-edited' ),
		'publish' => esc_html__( 'Published', 'elodin-recently-edited' ),
		'delete'  => esc_html__( 'Delete', 'elodin-recently-edited' ),
	);
	foreach ( $status_labels as $value => $label ) {
		$selected        = $post->post_status === $value ? ' selected' : '';
		$class           = $value === 'delete' ? ' class="delete-option"' : '';
		$status_options .= '<option value="' . esc_attr( $value ) . '"' . $selected . $class . '>' . esc_html( $label ) . '</option>';
	}

	if ( null === $post_type_options ) {
		$post_type_options = array();
		$post_types        = elodin_recently_edited_get_switchable_post_types();
		foreach ( $post_types as $pt_slug => $pt_obj ) {
			$post_type_options[ $pt_slug ] = '<option value="' . esc_attr( $pt_slug ) . '"%s>' . esc_html( $pt_obj->labels->singular_name ) . '</option>';
		}
	}

	$post_type_options_html = '';
	foreach ( $post_type_options as $pt_slug => $option_template ) {
		$post_type_options_html .= sprintf( $option_template, $post->post_type === $pt_slug ? ' selected' : '' );
	}

	$title_url = elodin_recently_edited_get_post_title_link( $post, $edit_url );
	$edit_new_tab_attr = elodin_recently_edited_is_elementor_post( $post ) ? ' data-new-tab="true"' : '';
	$title_new_tab_attr = ( $title_url === $edit_url && elodin_recently_edited_is_elementor_post( $post ) ) ? ' data-new-tab="true"' : '';

	$date_format   = 'n/j/y';
	$published_raw = get_post_time( 'U', false, $post );
	$modified_raw  = get_post_modified_time( 'U', false, $post );
	$published     = $published_raw ? date_i18n( $date_format, $published_raw ) : '';
	$modified      = $modified_raw ? date_i18n( $date_format, $modified_raw ) : '';
	$author_name   = '';
	$editor_name   = '';

	$author = get_userdata( $post->post_author );
	if ( $author ) {
		$author_name = $author->display_name;
	}

	$last_editor_id = get_post_meta( $post->ID, '_edit_last', true );
	if ( $last_editor_id ) {
		$last_editor = get_userdata( intval( $last_editor_id ) );
		if ( $last_editor ) {
			$editor_name = $last_editor->display_name;
		}
	}

	$published_title = __( 'Published', 'elodin-recently-edited' );
	if ( $author_name ) {
		$published_title .= ': ' . $author_name;
	}
	$modified_title = __( 'Last edited', 'elodin-recently-edited' );
	if ( $editor_name ) {
		$modified_title .= ': ' . $editor_name;
	}
	$copy_url = get_permalink( $post->ID );

	$row_class = $post->post_status === 'publish' ? 'elodin-recently-edited-row' : 'elodin-recently-edited-row elodin-recently-edited-row--not-published';
	if ( intval( $post->ID ) === intval( $current_post_id ) ) {
		$row_class .= ' elodin-recently-edited-row--current';
	}

	return '<span class="' . esc_attr( $row_class ) . '" data-related-group="' . esc_attr( $group ) . '" data-post-type="' . esc_attr( $post->post_type ) . '" data-modified="' . intval( $modified_raw ) . '" data-search-text="' . esc_attr( $search_text ) . '">'
		. '<span class="' . esc_attr( $pin_class ) . '" data-post-id="' . intval( $post->ID ) . '" title="' . esc_attr__( 'Pin', 'elodin-recently-edited' ) . '">' . esc_html( $pin_icon ) . '</span>'
		. '<span class="elodin-recently-edited-title">'
		. '<span class="elodin-recently-edited-action elodin-recently-edited-title-link" data-url="' . esc_url( $title_url ) . '"' . $title_new_tab_attr . ' data-resource-type="post" data-resource-id="' . intval( $post->ID ) . '" data-post-id="' . intval( $post->ID ) . '" data-full-title="' . esc_attr( $post->post_title ) . '">' . $title . '</span>'
		. '</span>'
		. '<span class="elodin-recently-edited-slug">'
		. '<span class="elodin-recently-edited-slug-text" data-post-id="' . intval( $post->ID ) . '" data-full-slug="' . esc_attr( $post->post_name ) . '" data-copy-text="' . esc_url( $copy_url ) . '">' . $slug . '</span>'
		. '</span>'
		. '<span class="elodin-recently-edited-action elodin-recently-edited-edit" data-url="' . esc_url( $edit_url ) . '"' . $edit_new_tab_attr . '>' . esc_html__( 'Edit', 'elodin-recently-edited' ) . '</span>'
		. '<select class="elodin-recently-edited-status-select" name="elodin_recently_edited_status_' . intval( $post->ID ) . '" data-post-id="' . intval( $post->ID ) . '" data-original="' . esc_attr( $post->post_status ) . '">' . $status_options . '</select>'
		. '<select class="elodin-recently-edited-post-type-select" name="elodin_recently_edited_post_type_' . intval( $post->ID ) . '" data-post-id="' . intval( $post->ID ) . '" data-original="' . esc_attr( $post->post_type ) . '">' . $post_type_options_html . '</select>'
		. '<span class="elodin-recently-edited-published" title="' . esc_attr( $published_title ) . '">' . esc_html( $published ) . '</span>'
		. '<span class="elodin-recently-edited-modified" title="' . esc_attr( $modified_title ) . '">' . esc_html( $modified ) . '</span>'
		. '<span class="elodin-recently-edited-id" data-id="' . intval( $post->ID ) . '">' . intval( $post->ID ) . '</span>'
		. '</span>';
}

/**
 * Build the row HTML for a Gravity Forms form.
 *
 * @since 1.3.0
 *
 * @param array  $form_item Form menu item.
 * @param string $group Related group slug.
 * @return string Row HTML.
 */
function elodin_recently_edited_get_gravity_form_row( $form_item, $group = 'gravity_forms' ) {
	$id = isset( $form_item['id'] ) ? intval( $form_item['id'] ) : 0;
	if ( ! $id ) {
		return '';
	}

	$title       = esc_html( elodin_recently_edited_get_gravity_form_display_title( $form_item ) );
	$full_title  = isset( $form_item['title'] ) ? (string) $form_item['title'] : '';
	$is_active   = ! empty( $form_item['is_active'] );
	$created_raw = ! empty( $form_item['date_created'] ) ? strtotime( $form_item['date_created'] ) : 0;
	$updated_raw = ! empty( $form_item['date_updated'] ) ? strtotime( $form_item['date_updated'] ) : 0;
	$date_format = 'n/j/y';
	$created     = $created_raw ? date_i18n( $date_format, $created_raw ) : '';
	$updated     = $updated_raw ? date_i18n( $date_format, $updated_raw ) : '';
	$search_text = trim( wp_strip_all_tags( $full_title ) . ' ' . $id );
	$status      = $is_active ? 'active' : 'inactive';
	$can_edit    = function_exists( 'elodin_recently_edited_can_edit_gravity_forms' )
		? elodin_recently_edited_can_edit_gravity_forms()
		: current_user_can( 'gravityforms_edit_forms' );

	$status_options = '';
	$status_labels  = array(
		'active'   => esc_html__( 'Active', 'elodin-recently-edited' ),
		'inactive' => esc_html__( 'Inactive', 'elodin-recently-edited' ),
	);
	foreach ( $status_labels as $value => $label ) {
		$selected        = $status === $value ? ' selected' : '';
		$status_options .= '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
	}

	$title_class = $can_edit ? 'elodin-recently-edited-title' : 'elodin-recently-edited-title elodin-recently-edited-title--locked';
	$status_html = $can_edit
		? '<select class="elodin-recently-edited-form-status-select" name="elodin_recently_edited_form_status_' . intval( $id ) . '" data-form-id="' . intval( $id ) . '" data-original="' . esc_attr( $status ) . '">' . $status_options . '</select>'
		: '<span class="elodin-recently-edited-status-label">' . esc_html( $status_labels[ $status ] ) . '</span>';
	$shortcode = '[gravityform id=' . intval( $id ) . ' title=false description=false ajax=true]';

	return '<span class="elodin-recently-edited-row elodin-recently-edited-row--gravity-form" data-related-group="' . esc_attr( $group ) . '" data-post-type="gravity_forms" data-search-text="' . esc_attr( $search_text ) . '">'
		. '<span></span>'
		. '<span class="' . esc_attr( $title_class ) . '">'
		. '<span class="elodin-recently-edited-action elodin-recently-edited-title-link" data-url="' . esc_url( $form_item['view_url'] ) . '" data-new-tab="true" data-resource-type="gravity_form" data-resource-id="' . intval( $id ) . '" data-full-title="' . esc_attr( $full_title ) . '">' . $title . '</span>'
		. '</span>'
		. '<span class="elodin-recently-edited-slug elodin-recently-edited-slug--locked">'
		. '<span class="elodin-recently-edited-action elodin-recently-edited-form-notifications" data-url="' . esc_url( $form_item['notifications_url'] ) . '">' . esc_html__( 'Notifications', 'elodin-recently-edited' ) . '</span>'
		. '</span>'
		. '<span class="elodin-recently-edited-action elodin-recently-edited-edit" data-url="' . esc_url( $form_item['edit_url'] ) . '">' . esc_html__( 'Edit', 'elodin-recently-edited' ) . '</span>'
		. $status_html
		. '<span class="elodin-recently-edited-post-type-label">' . esc_html__( 'Form', 'elodin-recently-edited' ) . '</span>'
		. '<span class="elodin-recently-edited-published" title="' . esc_attr__( 'Created', 'elodin-recently-edited' ) . '">' . esc_html( $created ) . '</span>'
		. '<span class="elodin-recently-edited-modified" title="' . esc_attr__( 'Last updated', 'elodin-recently-edited' ) . '">' . esc_html( $updated ) . '</span>'
		. '<span class="elodin-recently-edited-id" data-id="' . intval( $id ) . '" data-copy-text="' . esc_attr( $shortcode ) . '">' . intval( $id ) . '</span>'
		. '</span>';
}

/**
 * Build post rows for a menu under a specific related group.
 *
 * @since 1.3.0
 *
 * @param array  $pinned_posts Pinned posts list.
 * @param array  $recent_posts Recent posts list.
 * @param array  $pinned_ids Pinned post IDs.
 * @param string $group Related group slug.
 * @param bool   $is_active Whether this group is initially visible.
 * @param int    $current_post_id Current post ID.
 * @return array Row HTML fragments.
 */
function elodin_recently_edited_get_group_rows( $pinned_posts, $recent_posts, $pinned_ids, $group, $is_active = false, $current_post_id = 0 ) {
	$count     = 0;
	$max_items = elodin_recently_edited_get_menu_item_limit();
	$seen_ids  = array();
	$all_posts = array_merge( $pinned_posts, $recent_posts );
	$rows      = array();

	foreach ( $all_posts as $post ) {
		if ( ! is_a( $post, 'WP_Post' ) ) {
			continue;
		}

		if ( isset( $seen_ids[ $post->ID ] ) ) {
			continue;
		}
		$seen_ids[ $post->ID ] = true;

		$row = elodin_recently_edited_get_post_row( $post, $pinned_ids, $group, $current_post_id );
		if ( '' === $row ) {
			continue;
		}

		if ( $count >= $max_items ) {
			break;
		}
		$count++;

		$item_class = 'elodin-recently-edited-list-item elodin-recently-edited-list-item--' . sanitize_html_class( $group );
		if ( $is_active ) {
			$item_class .= ' is-active';
		}

		$rows[] = '<div class="' . esc_attr( $item_class ) . '">' . $row . '</div>';
	}

	return $rows;
}

/**
 * Build Gravity Forms rows for a menu group.
 *
 * @since 1.3.0
 *
 * @param array  $forms Forms as menu item arrays.
 * @param string $group Related group slug.
 * @param bool   $is_active Whether this group is initially visible.
 * @return array Row HTML fragments.
 */
function elodin_recently_edited_get_gravity_forms_group_rows( $forms, $group = 'gravity_forms', $is_active = false ) {
	$rows      = array();
	$max_items = elodin_recently_edited_get_menu_item_limit();
	$count     = 0;

	foreach ( $forms as $form_item ) {
		if ( $count >= $max_items ) {
			break;
		}

		$row = elodin_recently_edited_get_gravity_form_row( $form_item, $group );
		if ( '' === $row ) {
			continue;
		}

		$count++;
		$item_class = 'elodin-recently-edited-list-item elodin-recently-edited-list-item--' . sanitize_html_class( $group );
		if ( $is_active ) {
			$item_class .= ' is-active';
		}

		$rows[] = '<div class="' . esc_attr( $item_class ) . '">' . $row . '</div>';
	}

	return $rows;
}

/**
 * Build one canonical row set from all post-type groups.
 *
 * Each content type may contribute up to the menu item limit, but rows are only
 * rendered once and then filtered client-side by post type.
 *
 * @since 1.4.2
 *
 * @param array  $type_groups Groups keyed by post type.
 * @param array  $pinned_ids Pinned post IDs.
 * @param string $active_group Active group slug.
 * @param int    $current_post_id Current post ID.
 * @return array Row HTML fragments.
 */
function elodin_recently_edited_get_canonical_post_rows( $type_groups, $pinned_ids, $active_group = 'all', $current_post_id = 0 ) {
	$rows = array();

	if ( ! is_array( $type_groups ) ) {
		return $rows;
	}

	foreach ( $type_groups as $pt_slug => $type_group ) {
		if ( empty( $type_group['pinned'] ) && empty( $type_group['recent'] ) ) {
			continue;
		}

		$group_rows = elodin_recently_edited_get_group_rows(
			isset( $type_group['pinned'] ) ? $type_group['pinned'] : array(),
			isset( $type_group['recent'] ) ? $type_group['recent'] : array(),
			$pinned_ids,
			'all',
			'all' === $active_group || $pt_slug === $active_group,
			$current_post_id
		);

		$rows = array_merge( $rows, $group_rows );
	}

	return $rows;
}

/**
 * Count rows that can actually render for a related group.
 *
 * @since 1.3.0
 *
 * @param array $pinned_posts Pinned posts list.
 * @param array $recent_posts Recent posts list.
 * @return int Renderable row count.
 */
function elodin_recently_edited_count_group_rows( $pinned_posts, $recent_posts ) {
	$count     = 0;
	$max_items = elodin_recently_edited_get_menu_item_limit();
	$seen_ids  = array();
	$all_posts = array_merge( $pinned_posts, $recent_posts );

	foreach ( $all_posts as $post ) {
		if ( ! is_a( $post, 'WP_Post' ) ) {
			continue;
		}

		if ( isset( $seen_ids[ $post->ID ] ) ) {
			continue;
		}
		$seen_ids[ $post->ID ] = true;

		if ( ! elodin_recently_edited_should_include_post( $post ) ) {
			continue;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			continue;
		}

		if ( ! get_edit_post_link( $post->ID ) ) {
			continue;
		}

		if ( $count >= $max_items ) {
			break;
		}
		$count++;
	}

	return $count;
}

/**
 * Merge post lists while preserving first-seen order.
 *
 * @since 1.3.0
 *
 * @param array $post_lists Lists of post objects.
 * @return array Unique posts.
 */
function elodin_recently_edited_merge_unique_posts( $post_lists ) {
	$posts = array();
	$seen  = array();

	foreach ( $post_lists as $post_list ) {
		if ( ! is_array( $post_list ) ) {
			continue;
		}

		foreach ( $post_list as $post ) {
			if ( ! is_a( $post, 'WP_Post' ) || isset( $seen[ $post->ID ] ) ) {
				continue;
			}

			$seen[ $post->ID ] = true;
			$posts[]           = $post;
		}
	}

	return $posts;
}

/**
 * Sort posts by modified date descending.
 *
 * @since 1.3.0
 *
 * @param array $posts List of post objects.
 * @return array Sorted posts.
 */
function elodin_recently_edited_sort_posts_by_modified_desc( $posts ) {
	usort(
		$posts,
		function ( $a, $b ) {
			$a_modified = is_a( $a, 'WP_Post' ) ? get_post_modified_time( 'U', false, $a ) : 0;
			$b_modified = is_a( $b, 'WP_Post' ) ? get_post_modified_time( 'U', false, $b ) : 0;

			if ( $a_modified === $b_modified ) {
				return 0;
			}

			return ( $a_modified > $b_modified ) ? -1 : 1;
		}
	);

	return $posts;
}

/**
 * Add the admin-bar menu from cached rendered fragments.
 *
 * @since 1.4.2
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 * @param string       $menu_id Menu ID.
 * @param array        $cached_menu Cached fragments.
 * @return void
 */
function elodin_recently_edited_add_cached_admin_bar_menu( $wp_admin_bar, $menu_id, $cached_menu ) {
	$main_href       = isset( $cached_menu['main_href'] ) ? (string) $cached_menu['main_href'] : '#';
	$post_type_links = isset( $cached_menu['post_type_links'] ) && is_array( $cached_menu['post_type_links'] ) ? $cached_menu['post_type_links'] : array();
	$post_list_rows  = isset( $cached_menu['post_list_rows'] ) && is_array( $cached_menu['post_list_rows'] ) ? $cached_menu['post_list_rows'] : array();

	$wp_admin_bar->add_menu(
		array(
			'id'       => $menu_id,
			'title'    => '<span class="elodin-recently-edited-menu-star" aria-hidden="true">★</span> ' . esc_html__( 'Recently Edited', 'elodin-recently-edited' ),
			'href'     => esc_url( $main_href ),
			'parent'   => 'top-secondary',
			'position' => 999,
			'meta'     => array( 'class' => 'elodin-recently-edited-combined-menu elodin-related-menu' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-search',
			'parent' => $menu_id,
			'title'  => elodin_recently_edited_get_search_toolbar_html(),
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-search-item' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-types',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-related-pill-band">' . implode( '', $post_type_links ) . '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-related-pill-item' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-no-matches',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-no-matches-label">' . esc_html__( 'No matches found', 'elodin-recently-edited' ) . '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-no-matches' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-column-header',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-column-header" aria-hidden="true">'
				. '<span></span>'
				. '<span>' . esc_html__( 'Title', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Slug', 'elodin-recently-edited' ) . '</span>'
				. '<span></span>'
				. '<span>' . esc_html__( 'Status', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Type', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Published', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Edited', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'ID', 'elodin-recently-edited' ) . '</span>'
				. '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-column-header-item' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-post-list',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-post-list">' . implode( '', $post_list_rows ) . '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-post-list-item' ),
		)
	);
}

/**
 * Add a lightweight shell that lazy-loads menu contents.
 *
 * @since 1.4.2
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 * @param string       $menu_id Menu ID.
 * @return void
 */
function elodin_recently_edited_add_lazy_admin_bar_shell( $wp_admin_bar, $menu_id ) {
	$wp_admin_bar->add_menu(
		array(
			'id'       => $menu_id,
			'title'    => '<span class="elodin-recently-edited-menu-star" aria-hidden="true">★</span> ' . esc_html__( 'Recently Edited', 'elodin-recently-edited' ),
			'href'     => '#',
			'parent'   => 'top-secondary',
			'position' => 999,
			'meta'     => array( 'class' => 'elodin-recently-edited-combined-menu elodin-related-menu elodin-recently-edited-is-lazy elodin-recently-edited-shell-pending' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-search',
			'parent' => $menu_id,
			'title'  => '',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-search-item elodin-recently-edited-shell-hidden' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-types',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-shell"><div class="elodin-recently-edited-shell-title">' . esc_html__( 'Recently Edited index', 'elodin-recently-edited' ) . '</div><div class="elodin-recently-edited-shell-copy">' . esc_html__( 'Build the local index when you need to search or edit recent content.', 'elodin-recently-edited' ) . '</div><button type="button" class="elodin-recently-edited-load-button">' . esc_html__( 'Build Initial Index', 'elodin-recently-edited' ) . '</button></div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-related-pill-item elodin-recently-edited-shell-item' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-no-matches',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-no-matches-label">' . esc_html__( 'No matches found', 'elodin-recently-edited' ) . '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-no-matches' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-column-header',
			'parent' => $menu_id,
			'title'  => '',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-column-header-item elodin-recently-edited-shell-hidden' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-post-list',
			'parent' => $menu_id,
			'title'  => '',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-post-list-item elodin-recently-edited-shell-hidden' ),
		)
	);
}

/**
 * Add recently edited posts menu to the WordPress admin bar.
 *
 * @since 0.1
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar object.
 */
function elodin_recently_edited_admin_bar( $wp_admin_bar ) {
	if ( ! elodin_recently_edited_should_load_admin_bar() ) {
		return;
	}

	// Validate admin bar object
	if ( ! is_a( $wp_admin_bar, 'WP_Admin_Bar' ) ) {
		return;
	}

	$user_id = get_current_user_id();

	// Ensure we have a valid user
	if ( ! $user_id ) {
		return;
	}

	$menu_id = 'recently-edited';
	if ( empty( $GLOBALS['elodin_recently_edited_render_full_menu'] ) ) {
		elodin_recently_edited_add_lazy_admin_bar_shell( $wp_admin_bar, $menu_id );
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
	$user_pinned_ids = $pinned_ids;

	$current_post_type = elodin_recently_edited_get_current_post_type();
	$current_post_id   = elodin_recently_edited_get_current_post_id();
	$cached_menu       = elodin_recently_edited_get_global_menu_cache_item();
	if ( false !== $cached_menu ) {
		$cached_menu = elodin_recently_edited_decorate_cached_menu( $cached_menu, $current_post_type, $current_post_id, $user_pinned_ids );
		elodin_recently_edited_add_cached_admin_bar_menu( $wp_admin_bar, $menu_id, $cached_menu );
		return;
	}

	// The shared cache is intentionally not keyed by user pins. Pins are applied
	// to matching cached rows when the menu is served.
	$pinned_ids = array();

	$menu_post_statuses = elodin_recently_edited_get_menu_post_statuses();
	$menu_item_limit    = elodin_recently_edited_get_menu_item_limit();
	$pinned_posts       = array();
	if ( ! empty( $pinned_ids ) ) {
		$pinned_posts = get_posts(
			elodin_recently_edited_prepare_menu_query_args(
				array(
					'post_type'      => 'any',
					'post__in'       => $pinned_ids,
					'orderby'        => 'post__in',
					'post_status'    => $menu_post_statuses,
					'posts_per_page' => 20,
				)
			)
		);
		$pinned_posts = elodin_recently_edited_filter_menu_posts( $pinned_posts );
	}

	// Get recent posts with security considerations
	$args = elodin_recently_edited_prepare_menu_query_args(
		array(
			'post_type'         => 'any',
			'post_type__not_in' => array( 'attachment' ), // Exclude media attachments
			'post_status'       => $menu_post_statuses,
			'posts_per_page'    => $menu_item_limit,
			'orderby'           => 'modified',
			'order'             => 'DESC',
		)
	);

	$recent_posts = get_posts( $args );
	$recent_posts = elodin_recently_edited_filter_menu_posts( $recent_posts );

	$most_recent_edit_url = '#';
	$most_recent_post_id  = 0;
	foreach ( array_merge( $pinned_posts, $recent_posts ) as $post ) {
		if ( ! is_a( $post, 'WP_Post' ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			continue;
		}

		$edit_url = get_edit_post_link( $post->ID );
		if ( $edit_url ) {
			$most_recent_edit_url = $edit_url;
			$most_recent_post_id  = $post->ID;
			break;
		}
	}

	$main_href = $most_recent_edit_url;
	if ( is_admin() && isset( $_GET['post'] ) && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && intval( $_GET['post'] ) === $most_recent_post_id ) {
		$main_href = elodin_recently_edited_get_view_link( get_post( $most_recent_post_id ) );
	}

	$wp_admin_bar->add_menu(
		array(
			'id'       => $menu_id,
			'title'    => '<span class="elodin-recently-edited-menu-star" aria-hidden="true">★</span> ' . esc_html__( 'Recently Edited', 'elodin-recently-edited' ),
			'href'     => esc_url( $main_href ),
			'parent'   => 'top-secondary',
			'position' => 999,
			'meta'     => array( 'class' => 'elodin-recently-edited-combined-menu elodin-related-menu' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-search',
			'parent' => $menu_id,
			'title'  => elodin_recently_edited_get_search_toolbar_html(),
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-search-item' ),
		)
	);

	$post_types        = elodin_recently_edited_get_switchable_post_types();
	$type_groups       = array();

	foreach ( $post_types as $pt_slug => $pt_obj ) {
		$type_pinned_posts = array();
		if ( ! empty( $pinned_ids ) ) {
			$type_pinned_posts = get_posts(
				elodin_recently_edited_prepare_menu_query_args(
					array(
						'post_type'      => $pt_slug,
						'post__in'       => $pinned_ids,
						'orderby'        => 'post__in',
						'post_status'    => $menu_post_statuses,
						'posts_per_page' => $menu_item_limit,
					)
				)
			);
			$type_pinned_posts = elodin_recently_edited_filter_menu_posts( $type_pinned_posts );
		}

		$type_recent_posts = get_posts(
			elodin_recently_edited_prepare_menu_query_args(
				array(
					'post_type'      => $pt_slug,
					'post_status'    => $menu_post_statuses,
					'posts_per_page' => $menu_item_limit,
					'orderby'        => array(
						'menu_order' => 'ASC',
						'modified'   => 'DESC',
					),
				),
			)
		);
		$type_recent_posts = elodin_recently_edited_filter_menu_posts( $type_recent_posts );

		$type_count = elodin_recently_edited_count_group_rows( $type_pinned_posts, $type_recent_posts );
		if ( 0 === $type_count ) {
			continue;
		}

		$type_groups[ $pt_slug ] = array(
			'object' => $pt_obj,
			'pinned' => $type_pinned_posts,
			'recent' => $type_recent_posts,
			'count'  => $type_count,
		);
	}

	$gravity_forms_items    = elodin_recently_edited_get_gravity_forms_items();
	$gravity_forms_count    = count( $gravity_forms_items );
	$available_groups       = array_merge( array( 'all' ), array_keys( $type_groups ) );
	if ( $gravity_forms_count ) {
		$available_groups[] = 'gravity_forms';
	}
	$active_group           = ( isset( $type_groups[ $current_post_type ] ) || ( 'gravity_forms' === $current_post_type && $gravity_forms_count ) ) ? $current_post_type : 'all';
	$all_count              = array_sum( wp_list_pluck( $type_groups, 'count' ) ) + $gravity_forms_count;
	$post_type_links        = array(
		'<a class="elodin-related-pill' . ( 'all' === $active_group ? ' is-active' : '' ) . '" href="#" data-related-target="all" title="' . esc_attr__( 'All content types', 'elodin-recently-edited' ) . '">' . esc_html__( 'All', 'elodin-recently-edited' ) . '<span class="elodin-related-pill-count">' . number_format_i18n( $all_count ) . '</span></a>',
	);

	foreach ( $type_groups as $pt_slug => $type_group ) {
		$pt_obj = $type_group['object'];

		$type_classes = 'elodin-related-pill';
		if ( $pt_slug === $current_post_type ) {
			$type_classes .= ' is-current';
		}
		if ( $pt_slug === $active_group ) {
			$type_classes .= ' is-active';
		}

		$post_type_links[] = '<a class="' . esc_attr( $type_classes ) . '" href="' . esc_url( elodin_recently_edited_get_post_type_admin_url( $pt_slug ) ) . '" data-related-target="' . esc_attr( $pt_slug ) . '" title="' . esc_attr( $pt_slug ) . '">' . esc_html( $pt_obj->labels->name ) . '<span class="elodin-related-pill-count">' . number_format_i18n( $type_group['count'] ) . '</span></a>';
	}

	if ( elodin_recently_edited_is_gravity_forms_available() ) {
		$type_classes = 'elodin-related-pill';
		if ( 'gravity_forms' === $current_post_type ) {
			$type_classes .= ' is-current';
		}
		if ( 'gravity_forms' === $active_group ) {
			$type_classes .= ' is-active';
		}

		$post_type_links[] = '<a class="' . esc_attr( $type_classes ) . '" href="' . esc_url( admin_url( 'admin.php?page=gf_edit_forms' ) ) . '" data-related-target="gravity_forms" title="' . esc_attr__( 'Gravity Forms', 'elodin-recently-edited' ) . '">' . esc_html__( 'Forms', 'elodin-recently-edited' ) . '<span class="elodin-related-pill-count">' . number_format_i18n( $gravity_forms_count ) . '</span></a>';
	}

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-types',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-related-pill-band">' . implode( '', $post_type_links ) . '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-related-pill-item' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-no-matches',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-no-matches-label">' . esc_html__( 'No matches found', 'elodin-recently-edited' ) . '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-no-matches' ),
		)
	);

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-column-header',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-column-header" aria-hidden="true">'
				. '<span></span>'
				. '<span>' . esc_html__( 'Title', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Slug', 'elodin-recently-edited' ) . '</span>'
				. '<span></span>'
				. '<span>' . esc_html__( 'Status', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Type', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Published', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'Edited', 'elodin-recently-edited' ) . '</span>'
				. '<span>' . esc_html__( 'ID', 'elodin-recently-edited' ) . '</span>'
				. '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-column-header-item' ),
		)
	);

	$post_list_rows = elodin_recently_edited_get_canonical_post_rows( $type_groups, $pinned_ids, $active_group, $current_post_id );
	$post_list_rows = array_merge(
		$post_list_rows,
		elodin_recently_edited_get_gravity_forms_group_rows( $gravity_forms_items, 'all', 'all' === $active_group )
	);

	$cached_value = elodin_recently_edited_decorate_cached_menu(
		array(
			'main_href'         => $main_href,
			'post_type_links'   => $post_type_links,
			'post_list_rows'    => $post_list_rows,
			'available_groups'  => $available_groups,
		),
		'',
		0,
		array()
	);
	elodin_recently_edited_set_global_menu_cache_item( $cached_value );
	$current_value   = elodin_recently_edited_decorate_cached_menu( $cached_value, $current_post_type, $current_post_id, $user_pinned_ids );
	$post_type_links = isset( $current_value['post_type_links'] ) ? $current_value['post_type_links'] : $post_type_links;
	$post_list_rows  = isset( $current_value['post_list_rows'] ) ? $current_value['post_list_rows'] : $post_list_rows;

	$wp_admin_bar->add_menu(
		array(
			'id'     => $menu_id . '-post-list',
			'parent' => $menu_id,
			'title'  => '<div class="elodin-recently-edited-post-list">' . implode( '', $post_list_rows ) . '</div>',
			'href'   => false,
			'meta'   => array( 'class' => 'elodin-recently-edited-post-list-item' ),
		)
	);
}

add_action( 'save_post', 'elodin_recently_edited_clear_menu_cache_on_content_change' );
add_action( 'deleted_post', 'elodin_recently_edited_clear_menu_cache_on_content_change' );
add_action( 'transition_post_status', 'elodin_recently_edited_clear_menu_cache_on_content_change' );
add_action( 'elodin_recently_edited_rebuild_menu_cache', 'elodin_recently_edited_rebuild_menu_cache' );

/**
 * Register REST endpoint for lazy-loaded menu contents.
 *
 * @since 1.4.2
 *
 * @return void
 */
function elodin_recently_edited_register_rest_routes() {
	register_rest_route(
		'elodin-recently-edited/v1',
		'/menu',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'elodin_recently_edited_rest_get_menu',
			'permission_callback' => function () {
				return is_user_logged_in() && elodin_recently_edited_runtime_enabled();
			},
		)
	);
}

/**
 * Render full menu nodes for lazy loading.
 *
 * @since 1.4.2
 *
 * @return WP_REST_Response|WP_Error REST response.
 */
function elodin_recently_edited_rest_get_menu( $request ) {
	if ( $request->get_param( 'preload' ) && ! elodin_recently_edited_should_preload_menu_index() ) {
		return rest_ensure_response(
			array(
				'skipped' => true,
				'reason'  => 'content_count_above_threshold',
			)
		);
	}

	if ( ! class_exists( 'WP_Admin_Bar' ) ) {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
	}

	$wp_admin_bar = new WP_Admin_Bar();
	$previous     = ! empty( $GLOBALS['elodin_recently_edited_render_full_menu'] );
	$previous_post_type = isset( $GLOBALS['elodin_recently_edited_current_post_type'] ) ? $GLOBALS['elodin_recently_edited_current_post_type'] : null;
	$previous_post_id   = isset( $GLOBALS['elodin_recently_edited_current_post_id'] ) ? $GLOBALS['elodin_recently_edited_current_post_id'] : null;

	$GLOBALS['elodin_recently_edited_render_full_menu'] = true;
	$GLOBALS['elodin_recently_edited_current_post_type'] = sanitize_key( $request->get_param( 'current_post_type' ) );
	$GLOBALS['elodin_recently_edited_current_post_id']   = intval( $request->get_param( 'current_post_id' ) );
	elodin_recently_edited_admin_bar( $wp_admin_bar );
	$GLOBALS['elodin_recently_edited_render_full_menu'] = $previous;
	if ( null === $previous_post_type ) {
		unset( $GLOBALS['elodin_recently_edited_current_post_type'] );
	} else {
		$GLOBALS['elodin_recently_edited_current_post_type'] = $previous_post_type;
	}
	if ( null === $previous_post_id ) {
		unset( $GLOBALS['elodin_recently_edited_current_post_id'] );
	} else {
		$GLOBALS['elodin_recently_edited_current_post_id'] = $previous_post_id;
	}

	$node_ids = array(
		'root'          => 'recently-edited',
		'search'        => 'recently-edited-search',
		'types'         => 'recently-edited-types',
		'noMatches'     => 'recently-edited-no-matches',
		'columnHeader'  => 'recently-edited-column-header',
		'postList'      => 'recently-edited-post-list',
	);
	$nodes    = array();

	foreach ( $node_ids as $key => $node_id ) {
		$node = $wp_admin_bar->get_node( $node_id );
		if ( ! $node ) {
			continue;
		}

		$nodes[ $key ] = array(
			'id'    => $node_id,
			'title' => $node->title,
			'href'  => isset( $node->href ) ? $node->href : false,
		);
	}

	return rest_ensure_response(
		array(
			'nodes' => $nodes,
		)
	);
}

add_action( 'rest_api_init', 'elodin_recently_edited_register_rest_routes' );
