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
 * Get default settings.
 *
 * @return array<string,mixed> Default settings.
 */
function elodin_recently_edited_get_default_settings() {
	return array(
		'disable_block_editor_fullscreen' => 1,
		'disable_admin_bar_search'        => 1,
		'enabled_post_types'              => array(),
		'enabled_post_types_configured'   => 0,
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

	return array(
		'disable_block_editor_fullscreen' => empty( $settings['disable_block_editor_fullscreen'] ) ? 0 : 1,
		'disable_admin_bar_search'        => empty( $settings['disable_admin_bar_search'] ) ? 0 : 1,
		'enabled_post_types'              => $enabled_post_types,
		'enabled_post_types_configured'   => empty( $settings['enabled_post_types_configured'] ) ? 0 : 1,
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
			'description' => __( 'Move the highlighted row.', 'elodin-recently-edited' ),
		),
		array(
			'keys'        => __( 'Left / Right', 'elodin-recently-edited' ),
			'description' => __( 'Switch content type views, wrapping at the ends.', 'elodin-recently-edited' ),
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
			'description' => __( 'Clear the active search.', 'elodin-recently-edited' ),
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
	<script>
		( function () {
			var form = document.getElementById( 'elodin-recently-edited-settings-form' );
			var status = document.getElementById( 'elodin-recently-edited-settings-status' );
			var timeout = null;
			var request = null;

			if ( ! form ) {
				return;
			}

			function setStatus( message, isError ) {
				if ( ! status ) {
					return;
				}

				status.textContent = message;
				status.style.color = isError ? '#b32d2e' : '';
			}

			function saveSettings() {
				var data = new window.FormData( form );
				data.set( 'action', 'elodin_recently_edited_save_settings' );
				data.set( 'nonce', data.get( 'elodin_recently_edited_settings_nonce' ) || '' );

				if ( request && typeof request.abort === 'function' ) {
					request.abort();
				}

				setStatus( <?php echo wp_json_encode( __( 'Saving...', 'elodin-recently-edited' ) ); ?>, false );

				request = new window.XMLHttpRequest();
				request.open( 'POST', window.ajaxurl || <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, true );
				request.onload = function () {
					var response = null;

					try {
						response = JSON.parse( request.responseText );
					} catch ( error ) {
						response = null;
					}

					if ( request.status >= 200 && request.status < 300 && response && response.success ) {
						setStatus( response.data && response.data.message ? response.data.message : <?php echo wp_json_encode( __( 'Saved.', 'elodin-recently-edited' ) ); ?>, false );
						return;
					}

					setStatus( response && response.data && response.data.message ? response.data.message : <?php echo wp_json_encode( __( 'Unable to save settings.', 'elodin-recently-edited' ) ); ?>, true );
				};
				request.onerror = function () {
					setStatus( <?php echo wp_json_encode( __( 'Unable to save settings.', 'elodin-recently-edited' ) ); ?>, true );
				};
				request.send( data );
			}

			form.addEventListener( 'change', function ( event ) {
				if ( ! event.target || 'checkbox' !== event.target.type ) {
					return;
				}

				window.clearTimeout( timeout );
				timeout = window.setTimeout( saveSettings, 250 );
			} );
		}() );
	</script>
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
add_action( 'wp_ajax_elodin_recently_edited_save_settings', 'elodin_recently_edited_ajax_save_settings' );
add_action( 'admin_head', 'elodin_recently_edited_hide_admin_bar_search_styles' );
add_action( 'wp_head', 'elodin_recently_edited_hide_admin_bar_search_styles' );
add_action( 'enqueue_block_editor_assets', 'elodin_recently_edited_disable_block_editor_fullscreen' );
