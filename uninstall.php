<?php
/**
 * Uninstall routine.
 *
 * Nothing is deleted unless the site explicitly asks for it, so removing the
 * plugin to troubleshoot never destroys the received requests. To wipe
 * everything, set the option before deleting the plugin:
 *
 *     update_option( 'gzf_delete_data_on_uninstall', 1 );
 *
 * @package Roga
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'gzf_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

$forms = get_posts(
	array(
		'post_type'   => 'gzf_form',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( $forms as $form_id ) {
	wp_delete_post( $form_id, true );
}

$table = $wpdb->prefix . 'gzf_entries';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB

delete_option( 'gzf_db_version' );
delete_option( 'gzf_seeded' );
delete_option( 'gzf_delete_data_on_uninstall' );
