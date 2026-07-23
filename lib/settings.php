<?php
/**
 * Settings functionality for Recently Edited Quick Links.
 *
 * @package ElodinRecentlyEdited
 */

/**
 * Get the settings option name.
 *
 * @return string Option name.
 */
function elodin_recently_edited_get_settings_option_name() {
	return 'elodin_recently_edited_settings';
}

/**
 * Get the default review workflow states.
 *
 * The workflow itself remains off until one or more content types are enabled.
 *
 * @since 1.8.0
 *
 * @return array<string,array<string,mixed>> Review state configuration.
 */
function elodin_recently_edited_get_default_review_states() {
	return array(
		'state_1' => array(
			'label'   => __( 'Unfinished', 'elodin-recently-edited' ),
			'color'   => '#d7473f',
			'enabled' => 1,
		),
		'state_2' => array(
			'label'   => __( 'In progress', 'elodin-recently-edited' ),
			'color'   => '#c9961a',
			'enabled' => 1,
		),
		'state_3' => array(
			'label'   => __( 'Complete', 'elodin-recently-edited' ),
			'color'   => '#2f9e44',
			'enabled' => 1,
		),
		'state_4' => array(
			'label'   => __( 'Needs review', 'elodin-recently-edited' ),
			'color'   => '#8957c7',
			'enabled' => 1,
		),
	);
}

/**
 * Get default settings.
 *
 * @return array<string,mixed> Default settings.
 */
function elodin_recently_edited_get_default_settings() {
	return array(
		'disable_block_editor_fullscreen' => 1,
		'disable_admin_bar_search'        => 1,
		'enable_meta_inspector'           => 0,
		'enabled_post_types'              => array(),
		'enabled_post_types_configured'   => 0,
		'review_post_types'               => array(),
		'review_states'                   => elodin_recently_edited_get_default_review_states(),
	);
}

/**
 * Get plugin settings merged with defaults.
 *
 * @return array<string,mixed> Settings.
 */
function elodin_recently_edited_get_settings() {
	$settings = get_option( elodin_recently_edited_get_settings_option_name(), array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return wp_parse_args( $settings, elodin_recently_edited_get_default_settings() );
}

/**
 * Determine whether the plugin should disable block-editor fullscreen mode.
 *
 * @return bool Whether fullscreen mode should be disabled.
 */
function elodin_recently_edited_should_disable_block_editor_fullscreen() {
	$settings = elodin_recently_edited_get_settings();

	return ! empty( $settings['disable_block_editor_fullscreen'] );
}

/**
 * Determine whether the plugin should disable WordPress admin-bar search.
 *
 * @return bool Whether admin-bar search should be disabled.
 */
function elodin_recently_edited_should_disable_admin_bar_search() {
	$settings = elodin_recently_edited_get_settings();

	return ! empty( $settings['disable_admin_bar_search'] );
}

/**
 * Determine whether the optional Meta Inspector is enabled.
 *
 * @since 1.8.0
 *
 * @return bool Whether Meta Inspector should be available.
 */
function elodin_recently_edited_should_enable_meta_inspector() {
	$settings = elodin_recently_edited_get_settings();

	return ! empty( $settings['enable_meta_inspector'] );
}

/**
 * Get post type slugs that should never appear as configurable content types.
 *
 * @return array<int,string> Excluded post type slugs.
 */
function elodin_recently_edited_get_excluded_content_type_slugs() {
	$excluded = array(
		'attachment',
		'wp_taxonomy',
		'wp_post_type',
		'nav_menu_item',
		'acf-field-group',
		'acf-field',
		'acf-taxonomy',
		'acf-post-type',
		'acf-ui-options-page',
		'gp_elements',
		'gblocks_pattern',
	);

	/**
	 * Filter post type slugs excluded from Recently Edited content type settings.
	 *
	 * @since 1.5.0
	 *
	 * @param array<int,string> $excluded Excluded post type slugs.
	 */
	$excluded = apply_filters( 'elodin_recently_edited_excluded_content_type_slugs', $excluded );

	return is_array( $excluded ) ? array_values( array_map( 'sanitize_key', $excluded ) ) : array();
}

/**
 * Get post type labels that should never appear as configurable content types.
 *
 * @return array<int,string> Excluded lowercase labels.
 */
function elodin_recently_edited_get_excluded_content_type_labels() {
	$excluded = array(
		'navigation menus',
		'options pages',
		'field groups',
		'overlay panels',
		'legacy local patterns',
	);

	/**
	 * Filter post type labels excluded from Recently Edited content type settings.
	 *
	 * @since 1.5.0
	 *
	 * @param array<int,string> $excluded Excluded lowercase labels.
	 */
	$excluded = apply_filters( 'elodin_recently_edited_excluded_content_type_labels', $excluded );

	return is_array( $excluded ) ? array_values( array_map( 'strtolower', array_map( 'sanitize_text_field', $excluded ) ) ) : array();
}

/**
 * Determine whether a post type is an internal configuration object rather than user content.
 *
 * @param string            $post_type Post type slug.
 * @param WP_Post_Type|null $post_type_object Optional post type object.
 * @return bool Whether the post type should be excluded.
 */
function elodin_recently_edited_is_excluded_content_type( $post_type, $post_type_object = null ) {
	if ( in_array( sanitize_key( $post_type ), elodin_recently_edited_get_excluded_content_type_slugs(), true ) ) {
		return true;
	}

	if ( is_object( $post_type_object ) && isset( $post_type_object->labels->name ) ) {
		$label = strtolower( sanitize_text_field( $post_type_object->labels->name ) );
		return in_array( $label, elodin_recently_edited_get_excluded_content_type_labels(), true );
	}

	return false;
}

/**
 * Get post types that can be configured for Recently Edited.
 *
 * @return array<string,WP_Post_Type> Post type objects keyed by slug.
 */
function elodin_recently_edited_get_settings_post_types() {
	if ( function_exists( 'elodin_recently_edited_get_available_switchable_post_types' ) ) {
		return elodin_recently_edited_get_available_switchable_post_types();
	}

	$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
	foreach ( $post_types as $post_type_slug => $post_type_object ) {
		if ( elodin_recently_edited_is_excluded_content_type( $post_type_slug, $post_type_object ) ) {
			unset( $post_types[ $post_type_slug ] );
		}
	}

	return $post_types;
}

/**
 * Get enabled Recently Edited post type slugs.
 *
 * Defaults to all configurable post types when no explicit setting has been saved.
 *
 * @return array<int,string> Enabled post type slugs.
 */
function elodin_recently_edited_get_enabled_post_types() {
	$settings        = elodin_recently_edited_get_settings();
	$available_slugs = array_keys( elodin_recently_edited_get_settings_post_types() );

	if ( empty( $settings['enabled_post_types_configured'] ) ) {
		return $available_slugs;
	}

	if ( ! is_array( $settings['enabled_post_types'] ) ) {
		return array();
	}

	return array_values( array_intersect( array_map( 'sanitize_key', $settings['enabled_post_types'] ), $available_slugs ) );
}

/**
 * Get post types for which the optional review workflow is enabled.
 *
 * Review tracking is intentionally disabled by default.
 *
 * @since 1.8.0
 *
 * @return array<int,string> Enabled post type slugs.
 */
function elodin_recently_edited_get_review_post_types() {
	$settings        = elodin_recently_edited_get_settings();
	$available_slugs = array_keys( elodin_recently_edited_get_settings_post_types() );
	$review_types    = isset( $settings['review_post_types'] ) && is_array( $settings['review_post_types'] )
		? array_map( 'sanitize_key', $settings['review_post_types'] )
		: array();

	return array_values( array_intersect( $review_types, $available_slugs ) );
}

/**
 * Get sanitized review-state configuration in cycle order.
 *
 * @since 1.8.0
 *
 * @param bool $enabled_only Whether to return only states included in the click cycle.
 * @return array<string,array<string,mixed>> Review states keyed by stable slot ID.
 */
function elodin_recently_edited_get_review_states_config( $enabled_only = false ) {
	$settings = elodin_recently_edited_get_settings();
	$stored   = isset( $settings['review_states'] ) && is_array( $settings['review_states'] )
		? $settings['review_states']
		: array();
	$defaults = elodin_recently_edited_get_default_review_states();
	foreach ( $defaults as $default_key => $default_state ) {
		if ( ! isset( $stored[ $default_key ] ) || ! is_array( $stored[ $default_key ] ) ) {
			$default_state['enabled'] = 0;
			$stored[ $default_key ] = $default_state;
		}
	}
	$states = array();
	$legacy_colors = array(
		'red'    => '#d7473f',
		'yellow' => '#c9961a',
		'green'  => '#2f9e44',
		'purple' => '#8957c7',
		'blue'   => '#3788d8',
		'gray'   => '#737c89',
	);

	foreach ( array_slice( $stored, 0, 12, true ) as $raw_key => $state ) {
		if ( ! is_array( $state ) ) {
			continue;
		}
		$key   = sanitize_key( $raw_key );
		$label = isset( $state['label'] ) ? sanitize_text_field( $state['label'] ) : '';
		$enabled = ! array_key_exists( 'enabled', $state ) || ! empty( $state['enabled'] );
		$raw_color = isset( $state['color'] ) ? strtolower( trim( (string) $state['color'] ) ) : '';
		if ( isset( $legacy_colors[ $raw_color ] ) ) {
			$raw_color = $legacy_colors[ $raw_color ];
		}
		$color = sanitize_hex_color( $raw_color );
		if ( '' === $key || '' === $label ) {
			continue;
		}
		if ( $enabled_only && ! $enabled ) {
			continue;
		}
		$states[ $key ] = array(
			'key'     => $key,
			'label'   => $label,
			'color'   => $color ? $color : '#737c89',
			'enabled' => $enabled,
			'builtin' => isset( $defaults[ $key ] ),
		);
	}

	return $states;
}

/**
 * Get the current user's review states without reading or changing post meta.
 *
 * @since 1.8.0
 *
 * @param int $user_id Optional user ID. Defaults to the current user.
 * @return array<int,string> Review states keyed by post ID.
 */
function elodin_recently_edited_get_user_review_states( $user_id = 0 ) {
	$user_id = $user_id ? intval( $user_id ) : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}

	$stored = get_user_meta( $user_id, 'elodin_recently_edited_review_states', true );
	if ( ! is_array( $stored ) ) {
		return array();
	}

	$config  = elodin_recently_edited_get_review_states_config( true );
	$allowed = array_keys( $config );
	$legacy  = array(
		'unfinished'  => 'state_1',
		'in_progress' => 'state_2',
		'complete'    => 'state_3',
	);
	$states  = array();
	foreach ( $stored as $post_id => $state ) {
		$post_id = intval( $post_id );
		$state   = sanitize_key( $state );
		if ( isset( $legacy[ $state ] ) ) {
			$state = $legacy[ $state ];
		}
		if ( $post_id && in_array( $state, $allowed, true ) ) {
			$states[ $post_id ] = $state;
		}
	}

	return $states;
}

/**
 * Sanitize settings before saving.
 *
 * @param array<string,mixed> $settings Raw settings.
 * @return array<string,mixed> Sanitized settings.
 */
function elodin_recently_edited_sanitize_settings( $settings ) {
	$settings = is_array( $settings ) ? $settings : array();
	$enabled_post_types = isset( $settings['enabled_post_types'] ) && is_array( $settings['enabled_post_types'] )
		? array_map( 'sanitize_key', $settings['enabled_post_types'] )
		: array();
	$enabled_post_types = array_values( array_intersect( $enabled_post_types, array_keys( elodin_recently_edited_get_settings_post_types() ) ) );
	$review_post_types = isset( $settings['review_post_types'] ) && is_array( $settings['review_post_types'] )
		? array_map( 'sanitize_key', $settings['review_post_types'] )
		: array();
	$review_post_types = array_values( array_intersect( $review_post_types, array_keys( elodin_recently_edited_get_settings_post_types() ) ) );
	$raw_review_states = isset( $settings['review_states'] ) && is_array( $settings['review_states'] )
		? $settings['review_states']
		: array();
	$review_states = array();
	$legacy_colors = array(
		'red'    => '#d7473f',
		'yellow' => '#c9961a',
		'green'  => '#2f9e44',
		'purple' => '#8957c7',
		'blue'   => '#3788d8',
		'gray'   => '#737c89',
	);
	foreach ( array_slice( $raw_review_states, 0, 12, true ) as $raw_key => $raw_state ) {
		if ( ! is_array( $raw_state ) ) {
			continue;
		}
		$key   = sanitize_key( $raw_key );
		$label = isset( $raw_state['label'] ) ? sanitize_text_field( $raw_state['label'] ) : '';
		$raw_color = isset( $raw_state['color'] ) ? strtolower( trim( (string) $raw_state['color'] ) ) : '';
		if ( isset( $legacy_colors[ $raw_color ] ) ) {
			$raw_color = $legacy_colors[ $raw_color ];
		}
		$color = sanitize_hex_color( $raw_color );
		if ( '' === $key || '' === $label ) {
			continue;
		}
		$review_states[ $key ] = array(
			'label'   => $label,
			'color'   => $color ? $color : '#737c89',
			'enabled' => empty( $raw_state['enabled'] ) ? 0 : 1,
		);
	}

	foreach ( elodin_recently_edited_get_default_review_states() as $default_key => $default_state ) {
		if ( ! isset( $review_states[ $default_key ] ) ) {
			$default_state['enabled'] = 0;
			$review_states[ $default_key ] = $default_state;
		}
	}

	return array(
		'disable_block_editor_fullscreen' => empty( $settings['disable_block_editor_fullscreen'] ) ? 0 : 1,
		'disable_admin_bar_search'        => empty( $settings['disable_admin_bar_search'] ) ? 0 : 1,
		'enable_meta_inspector'           => empty( $settings['enable_meta_inspector'] ) ? 0 : 1,
		'enabled_post_types'              => $enabled_post_types,
		'enabled_post_types_configured'   => empty( $settings['enabled_post_types_configured'] ) ? 0 : 1,
		'review_post_types'               => $review_post_types,
		'review_states'                   => $review_states,
	);
}

/**
 * Register settings.
 *
 * @return void
 */
function elodin_recently_edited_register_settings() {
	register_setting(
		'elodin_recently_edited_settings',
		elodin_recently_edited_get_settings_option_name(),
		array(
			'type'              => 'array',
			'sanitize_callback' => 'elodin_recently_edited_sanitize_settings',
			'default'           => elodin_recently_edited_get_default_settings(),
		)
	);
}

/**
 * Register the plugin settings page.
 *
 * @return void
 */
function elodin_recently_edited_register_settings_page() {
	add_options_page(
		__( 'Recently Edited Settings', 'elodin-recently-edited' ),
		__( 'Recently Edited', 'elodin-recently-edited' ),
		'manage_options',
		'elodin-recently-edited-settings',
		'elodin_recently_edited_render_settings_page'
	);
}

/**
 * Load the compact Gutenberg-powered workflow editor only on this plugin's
 * settings screen.
 *
 * @since 1.8.0
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function elodin_recently_edited_enqueue_settings_assets( $hook_suffix ) {
	if ( 'settings_page_elodin-recently-edited-settings' !== $hook_suffix ) {
		return;
	}

	$script_path = ELODIN_RECENTLY_EDITED_DIR . 'assets/js/settings.js';
	$style_path  = ELODIN_RECENTLY_EDITED_DIR . 'assets/css/settings.css';

	wp_enqueue_style( 'wp-components' );
	wp_enqueue_style(
		'elodin-recently-edited-settings',
		ELODIN_RECENTLY_EDITED_URL . 'assets/css/settings.css',
		array( 'wp-components' ),
		file_exists( $style_path ) ? filemtime( $style_path ) : ELODIN_RECENTLY_EDITED_VERSION
	);
	wp_enqueue_script(
		'elodin-recently-edited-settings',
		ELODIN_RECENTLY_EDITED_URL . 'assets/js/settings.js',
		array( 'wp-components', 'wp-element' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : ELODIN_RECENTLY_EDITED_VERSION,
		true
	);
	wp_localize_script(
		'elodin-recently-edited-settings',
		'ElodinRecentlyEditedSettings',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'optionName' => elodin_recently_edited_get_settings_option_name(),
			'strings'    => array(
				'addState'          => __( 'Add state', 'elodin-recently-edited' ),
				'stateLabel'        => __( 'State label', 'elodin-recently-edited' ),
				'remove'            => __( 'Remove', 'elodin-recently-edited' ),
				'removeConfirm'     => __( 'Remove this custom review state? Items using it will return to blank.', 'elodin-recently-edited' ),
				'maxStates'         => __( 'A maximum of 12 review states is supported.', 'elodin-recently-edited' ),
				'saving'           => __( 'Saving...', 'elodin-recently-edited' ),
				'saved'            => __( 'Saved.', 'elodin-recently-edited' ),
				'saveFailed'       => __( 'Unable to save settings.', 'elodin-recently-edited' ),
				'clearing'         => __( 'Clearing...', 'elodin-recently-edited' ),
				'clearConfirm'     => __( 'Clear all of your review states for this content type?', 'elodin-recently-edited' ),
				'clearFailed'      => __( 'Unable to clear review states.', 'elodin-recently-edited' ),
				'chooseColor'      => __( 'Choose color', 'elodin-recently-edited' ),
			),
		)
	);
}

/**
 * Save settings from the settings page autosave request.
 *
 * @return void
 */
function elodin_recently_edited_ajax_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to save these settings.', 'elodin-recently-edited' ) ),
			403
		);
	}

	check_ajax_referer( 'elodin_recently_edited_save_settings', 'nonce' );

	$option_name = elodin_recently_edited_get_settings_option_name();
	$raw_settings = isset( $_POST[ $option_name ] ) && is_array( $_POST[ $option_name ] )
		? wp_unslash( $_POST[ $option_name ] )
		: array();
	$settings = elodin_recently_edited_sanitize_settings( $raw_settings );

	update_option( $option_name, $settings );

	if ( function_exists( 'elodin_recently_edited_clear_menu_cache' ) ) {
		elodin_recently_edited_clear_menu_cache();
	}

	wp_send_json_success(
		array(
			'message' => __( 'Saved.', 'elodin-recently-edited' ),
			'settings' => $settings,
		)
	);
}

/**
 * Clear the current user's review states for one content type.
 *
 * Review data is user-specific, so this intentionally does not affect other
 * editors and never writes to the posts themselves.
 *
 * @since 1.8.0
 *
 * @return void
 */
function elodin_recently_edited_ajax_clear_review_states() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to clear these review states.', 'elodin-recently-edited' ) ),
			403
		);
	}

	check_ajax_referer( 'elodin_recently_edited_save_settings', 'nonce' );

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
	if ( ! $post_type || ! isset( elodin_recently_edited_get_settings_post_types()[ $post_type ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid content type.', 'elodin-recently-edited' ) ), 400 );
	}

	$user_id = get_current_user_id();
	$states  = get_user_meta( $user_id, 'elodin_recently_edited_review_states', true );
	$states  = is_array( $states ) ? $states : array();
	$cleared = 0;

	foreach ( array_keys( $states ) as $post_id ) {
		if ( $post_type === get_post_type( intval( $post_id ) ) ) {
			unset( $states[ $post_id ] );
			$cleared++;
		}
	}

	if ( $states ) {
		update_user_meta( $user_id, 'elodin_recently_edited_review_states', $states );
	} else {
		delete_user_meta( $user_id, 'elodin_recently_edited_review_states' );
	}

	wp_send_json_success(
		array(
			'cleared' => $cleared,
			'message' => 1 === $cleared
				? __( 'Cleared 1 review state.', 'elodin-recently-edited' )
				: sprintf( __( 'Cleared %d review states.', 'elodin-recently-edited' ), $cleared ),
		)
	);
}

/**
 * Render the settings page.
 *
 * @return void
 */
function elodin_recently_edited_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings    = elodin_recently_edited_get_settings();
	$option_name = elodin_recently_edited_get_settings_option_name();
	$post_types  = elodin_recently_edited_get_settings_post_types();
	$enabled_post_types = elodin_recently_edited_get_enabled_post_types();
	$review_post_types  = elodin_recently_edited_get_review_post_types();
	$review_states_config = elodin_recently_edited_get_review_states_config();
	$is_mac      = false !== stripos( isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '', 'mac' );
	$shortcut_modifier = $is_mac ? __( 'Cmd', 'elodin-recently-edited' ) : __( 'Ctrl', 'elodin-recently-edited' );
	$edit_modifier     = $is_mac ? __( 'Cmd+Option', 'elodin-recently-edited' ) : __( 'Ctrl+Alt', 'elodin-recently-edited' );
	$shortcuts = array(
		array(
			'keys'        => $shortcut_modifier . '+Shift+E',
			'description' => __( 'Open Recently Edited and focus search.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => $edit_modifier . '+E',
			'description' => __( 'Toggle the current item between front-end view and backend edit screens.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => __( 'Up / Down', 'elodin-recently-edited' ),
			'description' => __( 'Move the highlighted result; in Media, move between grid rows.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => __( 'Left / Right', 'elodin-recently-edited' ),
			'description' => __( 'Switch content type views; in Media, move between files.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => __( 'Enter', 'elodin-recently-edited' ),
			'description' => __( 'Open the highlighted item.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => $shortcut_modifier . '+Enter',
			'description' => __( 'Edit the highlighted item.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => __( 'Shift+S', 'elodin-recently-edited' ),
			'description' => __( 'Star or unstar the highlighted item.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => __( 'Backspace', 'elodin-recently-edited' ),
			'description' => __( 'Edit normally in the search field; from the results, clear the search and return focus to it.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => __( 'Escape', 'elodin-recently-edited' ),
			'description' => __( 'Close Recently Edited.', 'elodin-recently-edited' ),
		),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Recently Edited Settings', 'elodin-recently-edited' ); ?></h1>
		<form id="elodin-recently-edited-settings-form" action="options.php" method="post">
			<?php settings_fields( 'elodin_recently_edited_settings' ); ?>
			<input type="hidden" name="elodin_recently_edited_settings_nonce" value="<?php echo esc_attr( wp_create_nonce( 'elodin_recently_edited_save_settings' ) ); ?>" />
			<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[enabled_post_types_configured]" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Block editor', 'elodin-recently-edited' ); ?></th>
					<td>
						<label for="elodin-recently-edited-disable-fullscreen">
							<input
								type="checkbox"
								id="elodin-recently-edited-disable-fullscreen"
								name="<?php echo esc_attr( $option_name ); ?>[disable_block_editor_fullscreen]"
								value="1"
								<?php checked( ! empty( $settings['disable_block_editor_fullscreen'] ) ); ?>
							/>
							<?php esc_html_e( 'Force fullscreen mode off in the Gutenberg editor.', 'elodin-recently-edited' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enabled by default. This keeps the WordPress admin bar visible while editing posts and pages.', 'elodin-recently-edited' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Admin bar', 'elodin-recently-edited' ); ?></th>
					<td>
						<label for="elodin-recently-edited-disable-admin-bar-search">
							<input
								type="checkbox"
								id="elodin-recently-edited-disable-admin-bar-search"
								name="<?php echo esc_attr( $option_name ); ?>[disable_admin_bar_search]"
								value="1"
								<?php checked( ! empty( $settings['disable_admin_bar_search'] ) ); ?>
							/>
							<?php esc_html_e( 'Disable the WordPress admin bar search.', 'elodin-recently-edited' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enabled by default. This prevents the front-end search icon from shifting the Recently Edited toolbar item.', 'elodin-recently-edited' ); ?>
						</p>
						<label for="elodin-recently-edited-enable-meta-inspector" style="display:block;margin-top:12px;">
							<input
								type="checkbox"
								id="elodin-recently-edited-enable-meta-inspector"
								name="<?php echo esc_attr( $option_name ); ?>[enable_meta_inspector]"
								value="1"
								<?php checked( ! empty( $settings['enable_meta_inspector'] ) ); ?>
							/>
							<?php esc_html_e( 'Enable the Meta Inspector.', 'elodin-recently-edited' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Disabled by default. When enabled, editable content receives a read-only metadata inspector.', 'elodin-recently-edited' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Content types', 'elodin-recently-edited' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Content types shown in Recently Edited', 'elodin-recently-edited' ); ?></legend>
							<?php foreach ( $post_types as $post_type_slug => $post_type ) : ?>
								<label style="display:block;margin:0 0 6px;">
									<input
										type="checkbox"
										name="<?php echo esc_attr( $option_name ); ?>[enabled_post_types][]"
										value="<?php echo esc_attr( $post_type_slug ); ?>"
										<?php checked( in_array( $post_type_slug, $enabled_post_types, true ) ); ?>
									/>
									<?php echo esc_html( $post_type->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Unchecked content types will no longer appear in the Recently Edited menu or search index.', 'elodin-recently-edited' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Review tracking', 'elodin-recently-edited' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Content types with review tracking', 'elodin-recently-edited' ); ?></legend>
							<?php foreach ( $post_types as $post_type_slug => $post_type ) : ?>
								<div style="display:flex;align-items:center;gap:8px;margin:0 0 6px;">
								<label>
									<input
										type="checkbox"
										name="<?php echo esc_attr( $option_name ); ?>[review_post_types][]"
										value="<?php echo esc_attr( $post_type_slug ); ?>"
										<?php checked( in_array( $post_type_slug, $review_post_types, true ) ); ?>
									/>
									<?php echo esc_html( $post_type->labels->name ); ?>
								</label>
								<button
									type="button"
									class="button-link-delete elodin-recently-edited-clear-review-states"
									data-post-type="<?php echo esc_attr( $post_type_slug ); ?>"
									data-post-type-label="<?php echo esc_attr( $post_type->labels->name ); ?>"
								><?php esc_html_e( 'Clear my states', 'elodin-recently-edited' ); ?></button>
								</div>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php esc_html_e( 'Off by default. Enabled types receive a personal review marker beside the star. States are stored on your user account and never change the post or its edited date.', 'elodin-recently-edited' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Review states', 'elodin-recently-edited' ); ?></th>
					<td>
						<div class="elodin-review-states-toolbar">
							<p class="description">
								<?php esc_html_e( 'Enabled states appear in this order when a marker is clicked. Every item still begins blank.', 'elodin-recently-edited' ); ?>
							</p>
							<button type="button" class="button button-secondary" id="elodin-recently-edited-add-review-state">
								<span aria-hidden="true">＋</span> <?php esc_html_e( 'Add state', 'elodin-recently-edited' ); ?>
							</button>
						</div>
						<div class="elodin-review-states-editor" id="elodin-recently-edited-review-states">
							<div class="elodin-review-state-header" aria-hidden="true">
								<span><?php esc_html_e( 'Use', 'elodin-recently-edited' ); ?></span>
								<span><?php esc_html_e( 'State', 'elodin-recently-edited' ); ?></span>
								<span><?php esc_html_e( 'Color', 'elodin-recently-edited' ); ?></span>
								<span></span>
							</div>
							<div class="elodin-review-state-rows">
								<?php foreach ( $review_states_config as $state_key => $state ) : ?>
									<div class="elodin-review-state-row<?php echo $state['enabled'] ? '' : ' is-disabled'; ?>" data-state-key="<?php echo esc_attr( $state_key ); ?>" data-builtin="<?php echo $state['builtin'] ? '1' : '0'; ?>">
										<div class="elodin-review-state-enabled">
											<input type="hidden" name="<?php echo esc_attr( $option_name ); ?>[review_states][<?php echo esc_attr( $state_key ); ?>][enabled]" value="0" />
											<input
												type="checkbox"
												name="<?php echo esc_attr( $option_name ); ?>[review_states][<?php echo esc_attr( $state_key ); ?>][enabled]"
												value="1"
												<?php checked( ! empty( $state['enabled'] ) ); ?>
												aria-label="<?php echo esc_attr( sprintf( __( 'Enable %s', 'elodin-recently-edited' ), $state['label'] ) ); ?>"
											/>
										</div>
										<div class="elodin-review-state-name">
											<input
												type="text"
												class="elodin-review-state-label"
												name="<?php echo esc_attr( $option_name ); ?>[review_states][<?php echo esc_attr( $state_key ); ?>][label]"
												value="<?php echo esc_attr( $state['label'] ); ?>"
												aria-label="<?php esc_attr_e( 'Review state label', 'elodin-recently-edited' ); ?>"
											/>
											<?php if ( $state['builtin'] ) : ?>
												<span class="elodin-review-state-default"><?php esc_html_e( 'Default', 'elodin-recently-edited' ); ?></span>
											<?php endif; ?>
										</div>
										<div class="elodin-review-state-color">
											<input
												type="color"
												class="elodin-review-state-color-fallback"
												id="elodin-review-color-<?php echo esc_attr( $state_key ); ?>"
												name="<?php echo esc_attr( $option_name ); ?>[review_states][<?php echo esc_attr( $state_key ); ?>][color]"
												value="<?php echo esc_attr( $state['color'] ); ?>"
												aria-label="<?php echo esc_attr( sprintf( __( 'Color for %s', 'elodin-recently-edited' ), $state['label'] ) ); ?>"
											/>
											<div class="elodin-review-state-color-mount" data-color-input="elodin-review-color-<?php echo esc_attr( $state_key ); ?>"></div>
										</div>
										<div class="elodin-review-state-action">
											<?php if ( ! $state['builtin'] ) : ?>
												<button type="button" class="button-link-delete elodin-recently-edited-remove-review-state"><?php esc_html_e( 'Remove', 'elodin-recently-edited' ); ?></button>
											<?php else : ?>
												<span aria-hidden="true"></span>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
						<p class="description">
							<?php esc_html_e( 'Default states can be disabled but not removed. Custom states can be removed; items using a disabled or removed state return to blank.', 'elodin-recently-edited' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<h2><?php esc_html_e( 'Keyboard Shortcuts', 'elodin-recently-edited' ); ?></h2>
			<table class="widefat striped" style="max-width:720px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Shortcut', 'elodin-recently-edited' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'elodin-recently-edited' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $shortcuts as $shortcut ) : ?>
						<tr>
							<td><code><?php echo esc_html( $shortcut['keys'] ); ?></code></td>
							<td><?php echo esc_html( $shortcut['description'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p id="elodin-recently-edited-settings-status" class="description" aria-live="polite"></p>
			<noscript>
				<?php submit_button(); ?>
			</noscript>
		</form>
	</div>
	<?php
}

/**
 * Hide the default WordPress admin-bar search when configured.
 *
 * @return void
 */
function elodin_recently_edited_hide_admin_bar_search_styles() {
	if ( ! elodin_recently_edited_should_disable_admin_bar_search() ) {
		return;
	}
	?>
	<style id="elodin-recently-edited-hide-admin-bar-search">
		#wp-admin-bar-search {
			display: none !important;
		}
	</style>
	<?php
}

/**
 * Disable the block editor fullscreen preference when configured.
 *
 * @return void
 */
function elodin_recently_edited_disable_block_editor_fullscreen() {
	if ( ! elodin_recently_edited_should_disable_block_editor_fullscreen() ) {
		return;
	}

	$script = <<<'JS'
( function () {
	function disableFullscreen() {
		if ( ! window.wp || ! wp.data || ! wp.data.select || ! wp.data.dispatch ) {
			return;
		}

		var editPost = wp.data.select( 'core/edit-post' );
		var editPostDispatch = wp.data.dispatch( 'core/edit-post' );

		if (
			editPost &&
			editPostDispatch &&
			typeof editPost.isFeatureActive === 'function' &&
			typeof editPostDispatch.toggleFeature === 'function' &&
			editPost.isFeatureActive( 'fullscreenMode' )
		) {
			editPostDispatch.toggleFeature( 'fullscreenMode' );
			return;
		}

		var preferences = wp.data.select( 'core/preferences' );
		var preferencesDispatch = wp.data.dispatch( 'core/preferences' );
		if (
			preferences &&
			preferencesDispatch &&
			typeof preferences.get === 'function' &&
			typeof preferencesDispatch.set === 'function' &&
			preferences.get( 'core/edit-post', 'fullscreenMode' )
		) {
			preferencesDispatch.set( 'core/edit-post', 'fullscreenMode', false );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', disableFullscreen );
	} else {
		disableFullscreen();
	}
	window.setTimeout( disableFullscreen, 250 );
} )();
JS;

	wp_add_inline_script( 'wp-edit-post', $script );
}

add_action( 'admin_init', 'elodin_recently_edited_register_settings' );
add_action( 'admin_menu', 'elodin_recently_edited_register_settings_page' );
add_action( 'admin_enqueue_scripts', 'elodin_recently_edited_enqueue_settings_assets' );
add_action( 'wp_ajax_elodin_recently_edited_save_settings', 'elodin_recently_edited_ajax_save_settings' );
add_action( 'wp_ajax_elodin_recently_edited_clear_review_states', 'elodin_recently_edited_ajax_clear_review_states' );
add_action( 'admin_head', 'elodin_recently_edited_hide_admin_bar_search_styles' );
add_action( 'wp_head', 'elodin_recently_edited_hide_admin_bar_search_styles' );
add_action( 'enqueue_block_editor_assets', 'elodin_recently_edited_disable_block_editor_fullscreen' );
