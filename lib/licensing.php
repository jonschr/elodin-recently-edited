<?php
/**
 * Licensing functionality for Recently Edited Quick Links.
 *
 * @package ElodinRecentlyEdited
 */

/**
 * Get the option name used for license storage.
 *
 * @return string
 */
function elodin_recently_edited_get_license_option_name() {
	return 'elodin_recently_edited_license';
}

/**
 * Get the admin page slug for licensing.
 *
 * @return string
 */
function elodin_recently_edited_get_license_page_slug() {
	return 'elodin-recently-edited-license';
}

/**
 * Get default license data.
 *
 * @return array<string,mixed>
 */
function elodin_recently_edited_get_license_defaults() {
	return array(
		'license_key'       => '',
		'instance_id'       => '',
		'instance_name'     => '',
		'status'            => 'unlicensed',
		'license_id'        => 0,
		'activation_limit'  => 0,
		'activation_usage'  => 0,
		'expires_at'        => '',
		'store_id'          => 0,
		'order_id'          => 0,
		'order_item_id'     => 0,
		'product_id'        => 0,
		'product_name'      => '',
		'variant_id'        => 0,
		'variant_name'      => '',
		'customer_id'       => 0,
		'customer_name'     => '',
		'customer_email'    => '',
		'last_validated_at' => 0,
		'last_error'        => '',
	);
}

/**
 * Get stored license data.
 *
 * @return array<string,mixed>
 */
function elodin_recently_edited_get_license_data() {
	$data = get_option( elodin_recently_edited_get_license_option_name(), array() );
	if ( ! is_array( $data ) ) {
		$data = array();
	}

	return wp_parse_args( $data, elodin_recently_edited_get_license_defaults() );
}

/**
 * Persist license data.
 *
 * @param array<string,mixed> $data License data.
 * @return bool
 */
function elodin_recently_edited_update_license_data( $data ) {
	$current = elodin_recently_edited_get_license_data();
	$updated = wp_parse_args( $data, $current );

	return update_option( elodin_recently_edited_get_license_option_name(), $updated, false );
}

/**
 * Clear license data.
 *
 * @param string $license_key Optional license key to retain for convenience.
 * @return bool
 */
function elodin_recently_edited_clear_license_data( $license_key = '' ) {
	$data                = elodin_recently_edited_get_license_defaults();
	$data['license_key'] = trim( (string) $license_key );

	return update_option( elodin_recently_edited_get_license_option_name(), $data, false );
}

/**
 * Get expected Lemon Squeezy identifiers for this plugin.
 *
 * Any non-zero value is enforced during activation/validation.
 *
 * @return array<string,int>
 */
function elodin_recently_edited_get_license_constraints() {
	$constraints = array(
		'store_id'   => defined( 'ELODIN_RECENTLY_EDITED_LEMON_STORE_ID' ) ? absint( ELODIN_RECENTLY_EDITED_LEMON_STORE_ID ) : 0,
		'product_id' => defined( 'ELODIN_RECENTLY_EDITED_LEMON_PRODUCT_ID' ) ? absint( ELODIN_RECENTLY_EDITED_LEMON_PRODUCT_ID ) : 0,
		'variant_id' => defined( 'ELODIN_RECENTLY_EDITED_LEMON_VARIANT_ID' ) ? absint( ELODIN_RECENTLY_EDITED_LEMON_VARIANT_ID ) : 0,
	);

	/**
	 * Filter enforced Lemon Squeezy identifiers for licensing.
	 *
	 * @since 1.4.0
	 *
	 * @param array<string,int> $constraints Expected store/product/variant IDs.
	 */
	$constraints = apply_filters( 'elodin_recently_edited_license_constraints', $constraints );

	if ( ! is_array( $constraints ) ) {
		return array(
			'store_id'   => 0,
			'product_id' => 0,
			'variant_id' => 0,
		);
	}

	return array(
		'store_id'   => isset( $constraints['store_id'] ) ? absint( $constraints['store_id'] ) : 0,
		'product_id' => isset( $constraints['product_id'] ) ? absint( $constraints['product_id'] ) : 0,
		'variant_id' => isset( $constraints['variant_id'] ) ? absint( $constraints['variant_id'] ) : 0,
	);
}

/**
 * Get the Lemon Squeezy instance name used for activation.
 *
 * @return string
 */
function elodin_recently_edited_get_license_instance_name() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! is_string( $host ) || '' === trim( $host ) ) {
		$host = get_bloginfo( 'name' );
	}

	$instance_name = trim( (string) $host );

	/**
	 * Filter the instance name sent to Lemon Squeezy.
	 *
	 * @since 1.4.0
	 *
	 * @param string $instance_name Default instance name.
	 */
	$instance_name = (string) apply_filters( 'elodin_recently_edited_license_instance_name', $instance_name );

	return '' !== trim( $instance_name ) ? $instance_name : 'WordPress Site';
}

/**
 * Get the licensing page URL.
 *
 * @return string
 */
function elodin_recently_edited_get_license_page_url() {
	return admin_url( 'admin.php?page=' . elodin_recently_edited_get_license_page_slug() );
}

/**
 * Redirect back to the licensing page with a fallback that never ends blank.
 *
 * @return void
 */
function elodin_recently_edited_redirect_to_license_page() {
	$url = elodin_recently_edited_get_license_page_url();

	if ( wp_safe_redirect( $url ) ) {
		exit;
	}

	if ( wp_redirect( $url ) ) {
		exit;
	}

	wp_die(
		sprintf(
			'<p>%s</p><p><a href="%s">%s</a></p>',
			esc_html__( 'Continue to the licensing page.', 'elodin-recently-edited' ),
			esc_url( $url ),
			esc_html__( 'Open Licensing', 'elodin-recently-edited' )
		)
	);
}

/**
 * Determine whether the current admin request is the licensing page.
 *
 * @return bool
 */
function elodin_recently_edited_is_license_page() {
	return is_admin()
		&& isset( $_GET['page'] )
		&& elodin_recently_edited_get_license_page_slug() === sanitize_key( $_GET['page'] );
}

/**
 * Determine whether the plugin currently has an active license.
 *
 * @return bool
 */
function elodin_recently_edited_is_licensed() {
	$data = elodin_recently_edited_get_license_data();

	return (
		'active' === $data['status']
		&& '' !== trim( (string) $data['license_key'] )
		&& '' !== trim( (string) $data['instance_id'] )
	);
}

/**
 * Determine whether the plugin runtime should be enabled.
 *
 * @return bool
 */
function elodin_recently_edited_runtime_enabled() {
	return elodin_recently_edited_is_licensed();
}

/**
 * Require an active license during AJAX operations.
 *
 * @return void
 */
function elodin_recently_edited_require_active_license_for_ajax() {
	if ( elodin_recently_edited_is_licensed() ) {
		return;
	}

	wp_send_json_error(
		array(
			'message'    => __( 'Recently Edited Quick Links requires an active license.', 'elodin-recently-edited' ),
			'licenseUrl' => elodin_recently_edited_get_license_page_url(),
		),
		403
	);
}

/**
 * Store a short-lived per-user admin notice.
 *
 * @param string $type Notice type.
 * @param string $message Notice text.
 * @return void
 */
function elodin_recently_edited_set_license_flash_notice( $type, $message ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	set_transient(
		'elodin_recently_edited_license_notice_' . $user_id,
		array(
			'type'    => sanitize_key( $type ),
			'message' => (string) $message,
		),
		MINUTE_IN_SECONDS
	);
}

/**
 * Retrieve and clear the current user's flash notice.
 *
 * @return array<string,string>|null
 */
function elodin_recently_edited_get_license_flash_notice() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return null;
	}

	$key    = 'elodin_recently_edited_license_notice_' . $user_id;
	$notice = get_transient( $key );
	delete_transient( $key );

	if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
		return null;
	}

	return array(
		'type'    => ! empty( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'info',
		'message' => (string) $notice['message'],
	);
}

/**
 * Execute a Lemon Squeezy license request.
 *
 * @param string               $endpoint API endpoint path.
 * @param array<string,string> $body Request body.
 * @return array<string,mixed>|WP_Error
 */
function elodin_recently_edited_license_request( $endpoint, $body ) {
	$response = wp_remote_post(
		'https://api.lemonsqueezy.com/v1/licenses/' . ltrim( $endpoint, '/' ),
		array(
			'timeout' => 15,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'    => $body,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body_text = wp_remote_retrieve_body( $response );
	$payload   = json_decode( $body_text, true );
	if ( ! is_array( $payload ) ) {
		return new WP_Error(
			'elodin_recently_edited_license_invalid_response',
			__( 'Licensing server returned an invalid response.', 'elodin-recently-edited' )
		);
	}

	$status_code = (int) wp_remote_retrieve_response_code( $response );
	if ( $status_code >= 400 ) {
		return new WP_Error(
			'elodin_recently_edited_license_api_error',
			! empty( $payload['error'] ) ? (string) $payload['error'] : __( 'Licensing request failed.', 'elodin-recently-edited' )
		);
	}

	return $payload;
}

/**
 * Ensure a license response matches the configured product constraints.
 *
 * @param array<string,mixed> $payload License API payload.
 * @return true|WP_Error
 */
function elodin_recently_edited_license_payload_matches_constraints( $payload ) {
	$constraints = elodin_recently_edited_get_license_constraints();
	$meta        = isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();

	foreach ( $constraints as $key => $expected ) {
		if ( ! $expected ) {
			continue;
		}

		$actual = isset( $meta[ $key ] ) ? absint( $meta[ $key ] ) : 0;
		if ( $actual !== $expected ) {
			return new WP_Error(
				'elodin_recently_edited_license_product_mismatch',
				__( 'This license key does not belong to the configured product for this plugin.', 'elodin-recently-edited' )
			);
		}
	}

	return true;
}

/**
 * Normalize a Lemon Squeezy response into stored license data.
 *
 * @param array<string,mixed> $payload Lemon Squeezy response payload.
 * @param array<string,mixed> $overrides Explicit values to merge last.
 * @return array<string,mixed>
 */
function elodin_recently_edited_normalize_license_payload( $payload, $overrides = array() ) {
	$defaults    = elodin_recently_edited_get_license_defaults();
	$license_key = isset( $payload['license_key'] ) && is_array( $payload['license_key'] ) ? $payload['license_key'] : array();
	$instance    = isset( $payload['instance'] ) && is_array( $payload['instance'] ) ? $payload['instance'] : array();
	$meta        = isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();

	$data = array(
		'license_key'       => isset( $overrides['license_key'] ) ? trim( (string) $overrides['license_key'] ) : ( isset( $license_key['key'] ) ? trim( (string) $license_key['key'] ) : $defaults['license_key'] ),
		'instance_id'       => isset( $overrides['instance_id'] ) ? trim( (string) $overrides['instance_id'] ) : ( isset( $instance['id'] ) ? trim( (string) $instance['id'] ) : $defaults['instance_id'] ),
		'instance_name'     => isset( $overrides['instance_name'] ) ? trim( (string) $overrides['instance_name'] ) : ( isset( $instance['name'] ) ? trim( (string) $instance['name'] ) : $defaults['instance_name'] ),
		'status'            => isset( $overrides['status'] ) ? sanitize_key( $overrides['status'] ) : ( isset( $license_key['status'] ) ? sanitize_key( $license_key['status'] ) : $defaults['status'] ),
		'license_id'        => isset( $license_key['id'] ) ? absint( $license_key['id'] ) : 0,
		'activation_limit'  => isset( $license_key['activation_limit'] ) ? (int) $license_key['activation_limit'] : 0,
		'activation_usage'  => isset( $license_key['activation_usage'] ) ? (int) $license_key['activation_usage'] : 0,
		'expires_at'        => isset( $license_key['expires_at'] ) ? (string) $license_key['expires_at'] : '',
		'store_id'          => isset( $meta['store_id'] ) ? absint( $meta['store_id'] ) : 0,
		'order_id'          => isset( $meta['order_id'] ) ? absint( $meta['order_id'] ) : 0,
		'order_item_id'     => isset( $meta['order_item_id'] ) ? absint( $meta['order_item_id'] ) : 0,
		'product_id'        => isset( $meta['product_id'] ) ? absint( $meta['product_id'] ) : 0,
		'product_name'      => isset( $meta['product_name'] ) ? (string) $meta['product_name'] : '',
		'variant_id'        => isset( $meta['variant_id'] ) ? absint( $meta['variant_id'] ) : 0,
		'variant_name'      => isset( $meta['variant_name'] ) ? (string) $meta['variant_name'] : '',
		'customer_id'       => isset( $meta['customer_id'] ) ? absint( $meta['customer_id'] ) : 0,
		'customer_name'     => isset( $meta['customer_name'] ) ? (string) $meta['customer_name'] : '',
		'customer_email'    => isset( $meta['customer_email'] ) ? (string) $meta['customer_email'] : '',
		'last_validated_at' => isset( $overrides['last_validated_at'] ) ? absint( $overrides['last_validated_at'] ) : time(),
		'last_error'        => isset( $overrides['last_error'] ) ? (string) $overrides['last_error'] : '',
	);

	return wp_parse_args( $data, $defaults );
}

/**
 * Format a stored license key for display.
 *
 * @param string $license_key License key.
 * @return string
 */
function elodin_recently_edited_get_masked_license_key( $license_key ) {
	$license_key = trim( (string) $license_key );
	if ( strlen( $license_key ) <= 8 ) {
		return $license_key;
	}

	return substr( $license_key, 0, 4 ) . str_repeat( '•', max( strlen( $license_key ) - 8, 4 ) ) . substr( $license_key, -4 );
}

/**
 * Format an ISO-like date string for display.
 *
 * @param string $date_string Date string.
 * @return string
 */
function elodin_recently_edited_format_license_date( $date_string ) {
	$date_string = trim( (string) $date_string );
	if ( '' === $date_string ) {
		return __( 'Never', 'elodin-recently-edited' );
	}

	$timestamp = strtotime( $date_string );
	if ( ! $timestamp ) {
		return $date_string;
	}

	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
}

/**
 * Get the formatted last-validation timestamp for the licensing page.
 *
 * @param array<string,mixed> $data Stored license data.
 * @return string
 */
function elodin_recently_edited_get_last_validation_display( $data ) {
	if ( empty( $data['last_validated_at'] ) ) {
		return __( 'Never', 'elodin-recently-edited' );
	}

	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $data['last_validated_at'] );
}

/**
 * Get the licensing page state used by the server-rendered page and Ajax responses.
 *
 * @return array<string,mixed>
 */
function elodin_recently_edited_get_license_page_state() {
	$data        = elodin_recently_edited_get_license_data();
	$is_licensed = elodin_recently_edited_is_licensed();

	return array(
		'licenseKey'            => (string) $data['license_key'],
		'status'                => (string) $data['status'],
		'statusLabel'           => $is_licensed ? __( 'Licensed', 'elodin-recently-edited' ) : __( 'Not licensed', 'elodin-recently-edited' ),
		'statusClass'           => $is_licensed ? 'success' : 'warning',
		'statusDescription'     => $is_licensed ? '' : __( 'Plugin functionality is disabled until a valid license key is activated.', 'elodin-recently-edited' ),
		'instanceName'          => ! empty( $data['instance_id'] ) ? (string) $data['instance_name'] : __( 'None', 'elodin-recently-edited' ),
		'customerName'          => ! empty( $data['customer_name'] ) ? (string) $data['customer_name'] : '—',
		'customerEmail'         => (string) $data['customer_email'],
		'productName'           => ! empty( $data['product_name'] ) ? (string) $data['product_name'] : '—',
		'variantName'           => (string) $data['variant_name'],
		'expiresDisplay'        => elodin_recently_edited_format_license_date( (string) $data['expires_at'] ),
		'lastValidationDisplay' => elodin_recently_edited_get_last_validation_display( $data ),
		'lastError'             => (string) $data['last_error'],
		'canRefresh'            => '' !== trim( (string) $data['license_key'] ),
		'canDeactivate'         => '' !== trim( (string) $data['instance_id'] ),
	);
}

/**
 * Build a normalized result payload for license actions.
 *
 * @param bool   $success Whether the action succeeded.
 * @param string $message User-facing message.
 * @return array<string,mixed>
 */
function elodin_recently_edited_get_license_action_result( $success, $message ) {
	return array(
		'success' => (bool) $success,
		'message' => (string) $message,
		'state'   => elodin_recently_edited_get_license_page_state(),
	);
}

/**
 * Register the plugin licensing admin page.
 *
 * @return void
 */
function elodin_recently_edited_register_license_page() {
	add_submenu_page(
		null,
		__( 'Recently Edited Licensing', 'elodin-recently-edited' ),
		__( 'Recently Edited Licensing', 'elodin-recently-edited' ),
		'manage_options',
		elodin_recently_edited_get_license_page_slug(),
		'elodin_recently_edited_render_license_page'
	);
}

/**
 * Suppress unrelated admin notices on the licensing page.
 *
 * This keeps the hidden licensing screen focused on license feedback only.
 *
 * @return void
 */
function elodin_recently_edited_suppress_other_admin_notices() {
	if ( ! elodin_recently_edited_is_license_page() ) {
		return;
	}

	remove_all_actions( 'admin_notices' );
	remove_all_actions( 'all_admin_notices' );
	remove_all_actions( 'network_admin_notices' );
	remove_all_actions( 'user_admin_notices' );
}

/**
 * Enqueue assets for the licensing page.
 *
 * @return void
 */
function elodin_recently_edited_enqueue_license_assets() {
	if ( ! elodin_recently_edited_is_license_page() ) {
		return;
	}

	wp_enqueue_script( 'jquery' );

	$js_path = plugin_dir_path( __FILE__ ) . '../assets/js/licensing.js';
	$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : ELODIN_RECENTLY_EDITED_VERSION;

	wp_enqueue_script(
		'elodin-recently-edited-license-js',
		plugin_dir_url( __FILE__ ) . '../assets/js/licensing.js',
		array( 'jquery' ),
		$js_ver,
		true
	);

	wp_localize_script(
		'elodin-recently-edited-license-js',
		'ElodinRecentlyEditedLicense',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'elodin_recently_edited_license_ajax' ),
			'initialState' => elodin_recently_edited_get_license_page_state(),
			'strings'      => array(
				'checking'     => __( 'Checking license key...', 'elodin-recently-edited' ),
				'refreshing'   => __( 'Refreshing license status...', 'elodin-recently-edited' ),
				'deactivating' => __( 'Deactivating license...', 'elodin-recently-edited' ),
				'empty'        => __( 'Enter a license key first.', 'elodin-recently-edited' ),
				'requestFailed'=> __( 'The request could not be completed. Reload the page and try again.', 'elodin-recently-edited' ),
			),
		)
	);
}

/**
 * Add a Plugins-page shortcut to licensing.
 *
 * @param array<int,string> $links Existing action links.
 * @return array<int,string>
 */
function elodin_recently_edited_add_plugin_action_links( $links ) {
	array_unshift(
		$links,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url( elodin_recently_edited_get_license_page_url() ),
			esc_html__( 'Licensing', 'elodin-recently-edited' )
		)
	);

	return $links;
}

/**
 * Render the licensing admin page.
 *
 * @return void
 */
function elodin_recently_edited_render_license_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'elodin-recently-edited' ) );
	}

	$state  = elodin_recently_edited_get_license_page_state();
	$notice = elodin_recently_edited_get_license_flash_notice();
	?>
	<div class="wrap" id="elodin-recently-edited-license-page">
		<h1><?php echo esc_html__( 'Recently Edited Licensing', 'elodin-recently-edited' ); ?></h1>

		<div id="elodin-recently-edited-license-notices">
			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<div class="notice notice-<?php echo esc_attr( $state['statusClass'] ); ?>" id="elodin-recently-edited-license-status">
			<p><strong id="elodin-recently-edited-license-status-label"><?php echo esc_html( $state['statusLabel'] ); ?></strong></p>
			<p id="elodin-recently-edited-license-status-description"<?php echo $state['statusDescription'] ? '' : ' style="display:none;"'; ?>>
				<?php echo esc_html( $state['statusDescription'] ); ?>
			</p>
		</div>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="elodin_recently_edited_license_key"><?php echo esc_html__( 'License Key', 'elodin-recently-edited' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							class="regular-text code"
							id="elodin_recently_edited_license_key"
							name="license_key"
							value="<?php echo esc_attr( $state['licenseKey'] ); ?>"
							data-current-license-key="<?php echo esc_attr( $state['licenseKey'] ); ?>"
							autocomplete="off"
							spellcheck="false"
						/>
						<p class="description"><?php echo esc_html__( 'Enter the license key for this site. It will be checked automatically when you leave the field or paste a new key.', 'elodin-recently-edited' ); ?></p>
						<p class="description" id="elodin-recently-edited-license-activity" style="display:none;"></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Current Activation', 'elodin-recently-edited' ); ?></th>
					<td>
						<p id="elodin-recently-edited-license-instance-name"><?php echo esc_html( $state['instanceName'] ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Customer', 'elodin-recently-edited' ); ?></th>
					<td>
						<p id="elodin-recently-edited-license-customer-name"><?php echo esc_html( $state['customerName'] ); ?></p>
						<p class="description" id="elodin-recently-edited-license-customer-email"<?php echo $state['customerEmail'] ? '' : ' style="display:none;"'; ?>>
							<?php echo esc_html( $state['customerEmail'] ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="button" class="button button-secondary" id="elodin-recently-edited-license-refresh"<?php echo $state['canRefresh'] ? '' : ' disabled'; ?>>
				<?php echo esc_html__( 'Refresh Status', 'elodin-recently-edited' ); ?>
			</button>
			<button type="button" class="button button-link-delete" id="elodin-recently-edited-license-deactivate"<?php echo $state['canDeactivate'] ? '' : ' disabled'; ?>>
				<?php echo esc_html__( 'Deactivate License', 'elodin-recently-edited' ); ?>
			</button>
			<span class="spinner" id="elodin-recently-edited-license-spinner" style="float:none;"></span>
		</p>

		<?php
		$constraints = elodin_recently_edited_get_license_constraints();
		if ( ! $constraints['store_id'] && ! $constraints['product_id'] && ! $constraints['variant_id'] ) :
			?>
			<p class="description" style="margin-top: 16px;">
				<?php echo esc_html__( 'No store/product/variant ID constraints are configured yet. The license check will still work, but you should hard-code at least the product ID before release.', 'elodin-recently-edited' ); ?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Show an admin notice when the plugin is unlicensed.
 *
 * @return void
 */
function elodin_recently_edited_maybe_show_unlicensed_notice() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) || elodin_recently_edited_is_license_page() || elodin_recently_edited_is_licensed() ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p>
			<?php echo esc_html__( 'Recently Edited Quick Links is inactive until it has a valid license key.', 'elodin-recently-edited' ); ?>
			<a href="<?php echo esc_url( elodin_recently_edited_get_license_page_url() ); ?>"><?php echo esc_html__( 'Open licensing.', 'elodin-recently-edited' ); ?></a>
		</p>
	</div>
	<?php
}

/**
 * Activate or replace the stored license key.
 *
 * @param string $license_key License key.
 * @return array<string,mixed>
 */
function elodin_recently_edited_activate_license_key( $license_key ) {
	$license_key = trim( (string) $license_key );
	if ( '' === $license_key ) {
		return elodin_recently_edited_get_license_action_result( false, __( 'Enter a license key first.', 'elodin-recently-edited' ) );
	}

	$current_data = elodin_recently_edited_get_license_data();
	if ( $license_key === $current_data['license_key'] && $current_data['instance_id'] ) {
		return elodin_recently_edited_refresh_license_state();
	}

	$instance_name = elodin_recently_edited_get_license_instance_name();
	$result        = elodin_recently_edited_license_request(
		'activate',
		array(
			'license_key'   => $license_key,
			'instance_name' => $instance_name,
		)
	);

	if ( is_wp_error( $result ) ) {
		elodin_recently_edited_update_license_data(
			array(
				'license_key' => $license_key,
				'status'      => 'unlicensed',
				'instance_id' => '',
				'last_error'  => $result->get_error_message(),
			)
		);

		return elodin_recently_edited_get_license_action_result( false, $result->get_error_message() );
	}

	if ( empty( $result['activated'] ) || empty( $result['instance']['id'] ) ) {
		$message = ! empty( $result['error'] ) ? (string) $result['error'] : __( 'License activation failed.', 'elodin-recently-edited' );
		elodin_recently_edited_update_license_data(
			array(
				'license_key' => $license_key,
				'status'      => 'unlicensed',
				'instance_id' => '',
				'last_error'  => $message,
			)
		);

		return elodin_recently_edited_get_license_action_result( false, $message );
	}

	$constraint_check = elodin_recently_edited_license_payload_matches_constraints( $result );
	if ( is_wp_error( $constraint_check ) ) {
		elodin_recently_edited_license_request(
			'deactivate',
			array(
				'license_key' => $license_key,
				'instance_id' => (string) $result['instance']['id'],
			)
		);
		elodin_recently_edited_update_license_data(
			array(
				'license_key' => $license_key,
				'status'      => 'unlicensed',
				'instance_id' => '',
				'last_error'  => $constraint_check->get_error_message(),
			)
		);

		return elodin_recently_edited_get_license_action_result( false, $constraint_check->get_error_message() );
	}

	$new_data = elodin_recently_edited_normalize_license_payload(
		$result,
		array(
			'license_key'   => $license_key,
			'instance_name' => $instance_name,
			'status'        => 'active',
			'last_error'    => '',
		)
	);
	elodin_recently_edited_update_license_data( $new_data );

	if (
		$current_data['instance_id']
		&& $current_data['license_key']
		&& (
			$current_data['license_key'] !== $new_data['license_key']
			|| $current_data['instance_id'] !== $new_data['instance_id']
		)
	) {
		elodin_recently_edited_license_request(
			'deactivate',
			array(
				'license_key' => (string) $current_data['license_key'],
				'instance_id' => (string) $current_data['instance_id'],
			)
		);
	}

	return elodin_recently_edited_get_license_action_result( true, __( 'The Recently Edited plugin is now licensed.', 'elodin-recently-edited' ) );
}

/**
 * Refresh the stored license state.
 *
 * @return array<string,mixed>
 */
function elodin_recently_edited_refresh_license_state() {
	$data = elodin_recently_edited_get_license_data();
	if ( '' === trim( (string) $data['license_key'] ) ) {
		return elodin_recently_edited_get_license_action_result( false, __( 'There is no saved license key to validate.', 'elodin-recently-edited' ) );
	}

	$request = array(
		'license_key' => (string) $data['license_key'],
	);
	if ( $data['instance_id'] ) {
		$request['instance_id'] = (string) $data['instance_id'];
	}

	$result = elodin_recently_edited_license_request( 'validate', $request );
	if ( is_wp_error( $result ) ) {
		elodin_recently_edited_update_license_data(
			array(
				'last_error' => $result->get_error_message(),
			)
		);

		return elodin_recently_edited_get_license_action_result( false, $result->get_error_message() );
	}

	$constraint_check = elodin_recently_edited_license_payload_matches_constraints( $result );
	if ( ! empty( $result['valid'] ) && ! is_wp_error( $constraint_check ) ) {
		elodin_recently_edited_update_license_data(
			elodin_recently_edited_normalize_license_payload(
				$result,
				array(
					'license_key'   => (string) $data['license_key'],
					'instance_id'   => ! empty( $result['instance']['id'] ) ? (string) $result['instance']['id'] : (string) $data['instance_id'],
					'instance_name' => ! empty( $result['instance']['name'] ) ? (string) $result['instance']['name'] : (string) $data['instance_name'],
					'status'        => 'active',
					'last_error'    => '',
				)
			)
		);

		return elodin_recently_edited_get_license_action_result( true, __( 'License validated.', 'elodin-recently-edited' ) );
	}

	$message = is_wp_error( $constraint_check )
		? $constraint_check->get_error_message()
		: ( ! empty( $result['error'] ) ? (string) $result['error'] : __( 'License is no longer valid.', 'elodin-recently-edited' ) );

	elodin_recently_edited_update_license_data(
		array(
			'status'            => is_wp_error( $constraint_check )
				? 'invalid'
				: ( isset( $result['license_key']['status'] ) ? sanitize_key( $result['license_key']['status'] ) : 'unlicensed' ),
			'last_validated_at' => time(),
			'last_error'        => $message,
		)
	);

	return elodin_recently_edited_get_license_action_result( false, $message );
}

/**
 * Deactivate the stored license instance.
 *
 * @return array<string,mixed>
 */
function elodin_recently_edited_deactivate_license_state() {
	$data = elodin_recently_edited_get_license_data();
	if ( '' === trim( (string) $data['license_key'] ) || '' === trim( (string) $data['instance_id'] ) ) {
		elodin_recently_edited_clear_license_data();
		return elodin_recently_edited_get_license_action_result( true, __( 'Stored license data cleared.', 'elodin-recently-edited' ) );
	}

	$result = elodin_recently_edited_license_request(
		'deactivate',
		array(
			'license_key' => (string) $data['license_key'],
			'instance_id' => (string) $data['instance_id'],
		)
	);

	if ( is_wp_error( $result ) ) {
		return elodin_recently_edited_get_license_action_result( false, $result->get_error_message() );
	}

	if ( empty( $result['deactivated'] ) ) {
		return elodin_recently_edited_get_license_action_result(
			false,
			! empty( $result['error'] ) ? (string) $result['error'] : __( 'License deactivation failed.', 'elodin-recently-edited' )
		);
	}

	elodin_recently_edited_clear_license_data();

	return elodin_recently_edited_get_license_action_result( true, __( 'License deactivated.', 'elodin-recently-edited' ) );
}

/**
 * Activate or replace the stored license.
 *
 * @return void
 */
function elodin_recently_edited_handle_activate_license() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage licensing.', 'elodin-recently-edited' ) );
	}

	check_admin_referer( 'elodin_recently_edited_activate_license' );

	$license_key = isset( $_POST['license_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) ) : '';
	$result = elodin_recently_edited_activate_license_key( $license_key );

	elodin_recently_edited_set_license_flash_notice( $result['success'] ? 'success' : 'error', $result['message'] );
	elodin_recently_edited_redirect_to_license_page();
}

/**
 * Refresh the stored license state from Lemon Squeezy.
 *
 * @param bool $redirect Whether to redirect back to the page.
 * @return void
 */
function elodin_recently_edited_handle_refresh_license( $redirect = true ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage licensing.', 'elodin-recently-edited' ) );
	}

	if ( $redirect ) {
		check_admin_referer( 'elodin_recently_edited_refresh_license' );
	}

	$result = elodin_recently_edited_refresh_license_state();

	if ( $redirect ) {
		elodin_recently_edited_set_license_flash_notice( $result['success'] ? 'success' : 'error', $result['message'] );
		elodin_recently_edited_redirect_to_license_page();
	}
}

/**
 * Deactivate the stored license instance.
 *
 * @return void
 */
function elodin_recently_edited_handle_deactivate_license() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage licensing.', 'elodin-recently-edited' ) );
	}

	check_admin_referer( 'elodin_recently_edited_deactivate_license' );

	$result = elodin_recently_edited_deactivate_license_state();

	elodin_recently_edited_set_license_flash_notice( $result['success'] ? 'success' : 'error', $result['message'] );
	elodin_recently_edited_redirect_to_license_page();
}

/**
 * Revalidate the active license periodically for administrators.
 *
 * @return void
 */
function elodin_recently_edited_maybe_revalidate_license() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) || wp_doing_ajax() || elodin_recently_edited_is_license_page() ) {
		return;
	}

	$data = elodin_recently_edited_get_license_data();
	if ( 'active' !== $data['status'] || ! $data['license_key'] || ! $data['instance_id'] ) {
		return;
	}

	$interval = (int) apply_filters( 'elodin_recently_edited_license_validation_interval', 12 * HOUR_IN_SECONDS );
	if ( $interval < HOUR_IN_SECONDS ) {
		$interval = HOUR_IN_SECONDS;
	}

	if ( $data['last_validated_at'] && ( time() - (int) $data['last_validated_at'] ) < $interval ) {
		return;
	}

	elodin_recently_edited_handle_refresh_license( false );
}

/**
 * Verify permissions and nonce for licensing Ajax requests.
 *
 * @return void
 */
function elodin_recently_edited_verify_license_ajax_request() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'You do not have permission to manage licensing.', 'elodin-recently-edited' ),
				'state'   => elodin_recently_edited_get_license_page_state(),
			)
		);
	}

	if ( ! check_ajax_referer( 'elodin_recently_edited_license_ajax', 'nonce', false ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'Security check failed. Reload the page and try again.', 'elodin-recently-edited' ),
				'state'   => elodin_recently_edited_get_license_page_state(),
			)
		);
	}
}

/**
 * Ajax handler for license activation.
 *
 * @return void
 */
function elodin_recently_edited_ajax_activate_license() {
	elodin_recently_edited_verify_license_ajax_request();

	$license_key = isset( $_POST['license_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) ) : '';
	$result      = elodin_recently_edited_activate_license_key( $license_key );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	}

	wp_send_json_error( $result );
}

/**
 * Ajax handler for license refresh.
 *
 * @return void
 */
function elodin_recently_edited_ajax_refresh_license() {
	elodin_recently_edited_verify_license_ajax_request();

	$result = elodin_recently_edited_refresh_license_state();

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	}

	wp_send_json_error( $result );
}

/**
 * Ajax handler for license deactivation.
 *
 * @return void
 */
function elodin_recently_edited_ajax_deactivate_license() {
	elodin_recently_edited_verify_license_ajax_request();

	$result = elodin_recently_edited_deactivate_license_state();

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	}

	wp_send_json_error( $result );
}

add_action( 'admin_menu', 'elodin_recently_edited_register_license_page' );
add_action( 'in_admin_header', 'elodin_recently_edited_suppress_other_admin_notices', 0 );
add_action( 'admin_notices', 'elodin_recently_edited_maybe_show_unlicensed_notice' );
add_action( 'admin_init', 'elodin_recently_edited_maybe_revalidate_license' );
add_action( 'admin_enqueue_scripts', 'elodin_recently_edited_enqueue_license_assets' );
add_action( 'admin_post_elodin_recently_edited_activate_license', 'elodin_recently_edited_handle_activate_license' );
add_action( 'admin_post_elodin_recently_edited_refresh_license', 'elodin_recently_edited_handle_refresh_license' );
add_action( 'admin_post_elodin_recently_edited_deactivate_license', 'elodin_recently_edited_handle_deactivate_license' );
add_action( 'wp_ajax_elodin_recently_edited_license_activate', 'elodin_recently_edited_ajax_activate_license' );
add_action( 'wp_ajax_elodin_recently_edited_license_refresh', 'elodin_recently_edited_ajax_refresh_license' );
add_action( 'wp_ajax_elodin_recently_edited_license_deactivate', 'elodin_recently_edited_ajax_deactivate_license' );
add_filter( 'plugin_action_links_' . ELODIN_RECENTLY_EDITED_BASENAME, 'elodin_recently_edited_add_plugin_action_links' );
