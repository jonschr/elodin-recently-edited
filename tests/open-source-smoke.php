<?php
// Run on a disposable test site: wp eval-file tests/open-source-smoke.php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

function recently_edited_smoke_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
class RecentlyEditedSmokeAjaxExit extends RuntimeException {}
function recently_edited_smoke_ajax( $action, $payload ) {
	$_POST = $payload;
	ob_start();
	try {
		do_action( 'wp_ajax_elodin_recently_edited_' . $action );
	} catch ( RecentlyEditedSmokeAjaxExit $exception ) {
		// Capture wp_send_json() without ending the test process.
	} finally {
		$output = ob_get_clean();
	}
	$result = json_decode( $output, true );
	recently_edited_smoke_check( is_array( $result ), 'AJAX did not return JSON: ' . $action );
	return $result;
}

$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
recently_edited_smoke_check( ! empty( $admins ), 'A test administrator is required.' );
$original_user = get_current_user_id();
$original_post = $_POST;
$original_license = get_option( 'elodin_recently_edited_license', null );
$post_id = 0;
$subscriber_id = 0;
$deny_license_http = function ( $preempt, $args, $url ) {
	if ( false !== strpos( $url, 'lemonsqueezy.com' ) ) { throw new RuntimeException( 'Unexpected licensing request.' ); }
	return $preempt;
};
add_filter( 'pre_http_request', $deny_license_http, 10, 3 );
try {
	wp_set_current_user( $admins[0]->ID );
	show_admin_bar( true );
	$post_id = wp_insert_post( array( 'post_title' => 'Open source smoke fixture', 'post_status' => 'draft' ), true );
	recently_edited_smoke_check( ! is_wp_error( $post_id ), 'Could not create test post.' );
	$subscriber_id = wp_insert_user( array( 'user_login' => 'recently-edited-smoke-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	recently_edited_smoke_check( ! is_wp_error( $subscriber_id ), 'Could not create test subscriber.' );
	foreach ( array( null, array( 'status' => 'expired', 'license_key' => 'smoke-expired', 'instance_id' => 'smoke' ) ) as $license ) {
		if ( null === $license ) { delete_option( 'elodin_recently_edited_license' ); }
		else { update_option( 'elodin_recently_edited_license', $license, false ); }
		recently_edited_smoke_check( elodin_recently_edited_should_load_admin_bar(), 'Menu is blocked by license state.' );
		$menu = rest_do_request( new WP_REST_Request( 'GET', '/elodin-recently-edited/v1/menu' ) );
		recently_edited_smoke_check( 200 === $menu->get_status() && ! empty( $menu->get_data()['nodes']['root'] ), 'Menu endpoint did not render.' );
	}
	elodin_recently_edited_enqueue_assets();
	recently_edited_smoke_check( wp_script_is( 'elodin-recently-edited-js', 'enqueued' ), 'Admin-bar assets are missing.' );
	recently_edited_smoke_check( ! function_exists( 'elodin_recently_edited_license_request' ), 'Licensing runtime is still loaded.' );
	foreach ( array( 'admin_notices' => 'elodin_recently_edited_maybe_show_unlicensed_notice', 'admin_menu' => 'elodin_recently_edited_register_license_page', 'admin_init' => 'elodin_recently_edited_maybe_revalidate_license' ) as $hook => $callback ) {
		recently_edited_smoke_check( false === has_action( $hook, $callback ), 'Licensing hook remains: ' . $callback );
	}
	foreach ( array( 'activate', 'refresh', 'deactivate' ) as $action ) {
		recently_edited_smoke_check( false === has_action( 'wp_ajax_elodin_recently_edited_license_' . $action ), 'License AJAX handler remains.' );
	}
	$links = apply_filters( 'plugin_action_links_' . ELODIN_RECENTLY_EDITED_BASENAME, array() );
	recently_edited_smoke_check( false === stripos( implode( '', $links ), 'licensing' ), 'License shortcut remains.' );
	wp_set_current_user( 0 );
	recently_edited_smoke_check( rest_do_request( new WP_REST_Request( 'GET', '/elodin-recently-edited/v1/menu' ) )->get_status() >= 400, 'Guest can access menu REST endpoint.' );
	wp_set_current_user( $subscriber_id );
	recently_edited_smoke_check( rest_do_request( new WP_REST_Request( 'GET', '/elodin-recently-edited/v1/media' ) )->get_status() >= 400, 'Subscriber can access media REST endpoint.' );
	define( 'DOING_AJAX', true );
	add_filter( 'wp_die_ajax_handler', function () { return function () { throw new RecentlyEditedSmokeAjaxExit(); }; } );
	wp_set_current_user( $admins[0]->ID );
	foreach ( array( 'toggle_pin', 'update_review_status', 'update_status', 'update_gravity_form_status', 'update_post_type', 'update_title', 'update_slug' ) as $action ) {
		$result = recently_edited_smoke_ajax( $action, array( 'nonce' => 'invalid' ) );
		recently_edited_smoke_check( false === $result['success'], 'Invalid nonce was accepted: ' . $action );
	}
	$result = recently_edited_smoke_ajax( 'update_title', array( 'post_id' => $post_id, 'title' => 'Updated without a license', 'nonce' => wp_create_nonce( 'elodin_recently_edited_title' ) ) );
	recently_edited_smoke_check( $result['success'] && 'Updated without a license' === get_post( $post_id )->post_title, 'Authorized editing failed without a license.' );
	foreach ( array( $subscriber_id, 0 ) as $user_id ) {
		wp_set_current_user( $user_id );
		$result = recently_edited_smoke_ajax( 'update_title', array( 'post_id' => $post_id, 'title' => 'Unauthorized edit', 'nonce' => wp_create_nonce( 'elodin_recently_edited_title' ) ) );
		recently_edited_smoke_check( false === $result['success'] && 'Updated without a license' === get_post( $post_id )->post_title, 'Unauthorized editing was accepted.' );
	}
} finally {
	wp_set_current_user( $admins[0]->ID );
	if ( $post_id && ! is_wp_error( $post_id ) ) { wp_delete_post( $post_id, true ); }
	if ( $subscriber_id && ! is_wp_error( $subscriber_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $subscriber_id );
	}
	if ( null === $original_license ) { delete_option( 'elodin_recently_edited_license' ); }
	else { update_option( 'elodin_recently_edited_license', $original_license, false ); }
	wp_set_current_user( $original_user );
	$_POST = $original_post;
	remove_filter( 'pre_http_request', $deny_license_http, 10 );
}
echo "Open-source smoke passed: no license required; menu, assets, and editing work; access checks remain.\n";
