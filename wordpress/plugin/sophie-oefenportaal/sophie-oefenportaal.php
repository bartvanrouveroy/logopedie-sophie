<?php
/**
 * Plugin Name: Sophie Oefenportaal
 * Description: Oefenportaal voor de patiënten van Logopedie Sophie: Sophie zet oefeningen open voor de dagen zonder therapie, patiënten oefenen thuis. Met rollen (beheerder, logopedist, patiënt), uitnodigingscodes, logboek, chat en contactformulier die aanvragen naar de praktijk mailen.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Logopedie Sophie
 * Text Domain: sophie-oefenportaal
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'LS_OP_VERSION', '1.0.0' );
define( 'LS_OP_FILE', __FILE__ );
define( 'LS_OP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LS_OP_URL', plugin_dir_url( __FILE__ ) );

require_once LS_OP_DIR . 'includes/rollen.php';
require_once LS_OP_DIR . 'includes/logboek.php';
require_once LS_OP_DIR . 'includes/data.php';
require_once LS_OP_DIR . 'includes/portaal.php';
require_once LS_OP_DIR . 'includes/beheer.php';
require_once LS_OP_DIR . 'includes/aanvragen.php';

register_activation_hook(
	__FILE__,
	function () {
		ls_op_rollen_installeren();
		ls_op_logboek_installeren();
		ls_op_data_registreren();
		flush_rewrite_rules();
	}
);

/**
 * Na een update: rollen en tabel opnieuw controleren.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'ls_op_version' ) !== LS_OP_VERSION ) {
			ls_op_rollen_installeren();
			ls_op_logboek_installeren();
			update_option( 'ls_op_version', LS_OP_VERSION );
		}
	}
);

/**
 * Instelling ophalen, met standaardwaarde.
 *
 * @param string $key Sleutel.
 * @return mixed
 */
function ls_op_optie( $key ) {
	$defaults = array(
		'ontvanger'      => 'info@logopediesophie.be',
		'antwoordtermijn' => '2 werkdagen',
		'chat_aan'       => 1,
		'telefoon'       => '0472 22 01 62',
	);
	$opts = get_option( 'ls_op_instellingen', array() );
	return isset( $opts[ $key ] ) && '' !== $opts[ $key ] ? $opts[ $key ] : ( $defaults[ $key ] ?? '' );
}

/**
 * URL van een portaalpagina (ingesteld door de setup of in Instellingen).
 *
 * @param string $wat 'portaal' of 'registreren'.
 * @return string
 */
function ls_op_pagina_url( $wat = 'portaal' ) {
	$id = (int) get_option( 'ls_op_pagina_' . $wat );
	if ( $id && get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	return home_url( '/' );
}
