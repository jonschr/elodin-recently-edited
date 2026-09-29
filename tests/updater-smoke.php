<?php
// Run: wp --require=tests/updater-smoke.php eval-file tests/updater-smoke.php
if ( ! defined( 'ABSPATH' ) ) {
	define( 'DOING_AJAX', true );
	$_REQUEST['action'] = 'update-plugin';
	return;
}

$manifest = json_decode( file_get_contents( dirname( __DIR__ ) . '/update.json' ), true );
if ( ! is_array( $manifest ) ) {
	throw new RuntimeException( 'Update metadata is not valid JSON.' );
}
$plugin = get_plugin_data( dirname( __DIR__ ) . '/elodin-recently-edited.php', false, false );
$url = 'https://raw.githubusercontent.com/jonschr/elodin-recently-edited/master/update.json';
$found = false;

foreach ( $GLOBALS['wp_filter']['site_transient_update_plugins']->callbacks as $callbacks ) {
	foreach ( $callbacks as $callback ) {
		$function = $callback['function'];
		if ( is_array( $function ) && $function[0] instanceof Puc_v4p9_Plugin_UpdateChecker && $function[0]->metadataUrl === $url ) {
			$found = true;
		}
	}
}

if ( ! wp_doing_ajax() || elodin_recently_edited_should_boot_runtime() || ! $found
	|| $manifest['version'] !== $plugin['Version']
	|| $manifest['version'] !== ELODIN_RECENTLY_EDITED_VERSION
	|| $manifest['download_url'] !== 'https://github.com/jonschr/elodin-recently-edited/archive/refs/tags/' . $manifest['version'] . '.zip' ) {
	throw new RuntimeException( 'Recently Edited update checker smoke test failed.' );
}

echo "Recently Edited updater booted during update AJAX; manifest matches plugin version.\n";
