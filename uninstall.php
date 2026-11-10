<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function woo_ai_content_delete_settings(): void {
	delete_option( 'woo_ai_content_settings' );
	delete_option( 'woo_ai_content_next_request' );
}

if ( ! is_multisite() ) {
	woo_ai_content_delete_settings();
	return;
}

$offset = 0;
do {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $offset ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		woo_ai_content_delete_settings();
		restore_current_blog();
	}
	$offset += count( $site_ids );
} while ( count( $site_ids ) === 100 );
