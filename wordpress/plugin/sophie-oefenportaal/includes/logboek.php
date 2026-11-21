<?php
/**
 * Logboek: wie deed wat in het portaal.
 *
 * @package sophie-oefenportaal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Naam van de logboektabel.
 *
 * @return string
 */
function ls_op_logboek_tabel() {
	global $wpdb;
	return $wpdb->prefix . 'ls_logboek';
}

/**
 * Tabel aanmaken.
 */
function ls_op_logboek_installeren() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$tabel   = ls_op_logboek_tabel();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$tabel} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tijd datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actie text NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) {$charset};"
	);
}

/**
 * Iets in het logboek schrijven.
 *
 * @param string   $actie   Wat er gebeurde.
 * @param int|null $user_id Wie (standaard: ingelogde gebruiker).
 */
function ls_op_log( $actie, $user_id = null ) {
	global $wpdb;
	$wpdb->insert(
		ls_op_logboek_tabel(),
		array(
			'tijd'    => current_time( 'mysql' ),
			'user_id' => null === $user_id ? get_current_user_id() : (int) $user_id,
			'actie'   => wp_strip_all_tags( $actie ),
		),
		array( '%s', '%d', '%s' )
	);
}

/**
 * Logboekregels ophalen.
 *
 * @param int|null $user_id Alleen van deze gebruiker (null = iedereen).
 * @param int      $limit   Maximum aantal.
 * @return array
 */
function ls_op_logboek_regels( $user_id = null, $limit = 200 ) {
	global $wpdb;
	$tabel = ls_op_logboek_tabel();
	if ( $user_id ) {
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabel} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabel} ORDER BY id DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
