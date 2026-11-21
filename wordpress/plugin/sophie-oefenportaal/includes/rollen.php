<?php
/**
 * Rollen en rechten.
 *
 * Beheerder  – alles in het portaal: alle patiënten, collega's toevoegen, rechten, logboek, instellingen.
 * Logopedist – eigen patiënten, oefeningen openzetten, uitnodigingscodes, oefenbibliotheek.
 * Patiënt    – alleen het eigen oefenportaal op de website, geen toegang tot wp-admin.
 * WordPress-beheerders (administrator) hebben automatisch alle portaalrechten.
 *
 * @package sophie-oefenportaal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rechten per portaalrol.
 *
 * @return array<string,array<string,bool>>
 */
function ls_op_rechten_per_rol() {
	$oefening = array(
		'edit_ls_oefeningen'           => true,
		'edit_others_ls_oefeningen'    => true,
		'edit_published_ls_oefeningen' => true,
		'publish_ls_oefeningen'        => true,
		'delete_ls_oefeningen'         => true,
		'delete_published_ls_oefeningen' => true,
		'delete_others_ls_oefeningen'  => true,
		'read_private_ls_oefeningen'   => true,
	);
	$logopedist = array_merge(
		array(
			'read'              => true,
			'upload_files'      => true,
			'ls_portaal_beheer' => true,
		),
		$oefening
	);
	$beheerder = array_merge(
		$logopedist,
		array(
			'ls_alle_patienten'    => true,
			'ls_gebruikers_beheer' => true,
			'ls_logboek_alles'     => true,
			'ls_instellingen'      => true,
			'list_users'           => true,
		)
	);
	return array(
		'sophie_beheerder' => $beheerder,
		'sophie_logopedist' => $logopedist,
		'sophie_patient'   => array( 'read' => true ),
	);
}

/**
 * Leesbare namen van de rollen.
 *
 * @return array<string,string>
 */
function ls_op_rolnamen() {
	return array(
		'sophie_beheerder'  => 'Beheerder',
		'sophie_logopedist' => 'Logopedist',
		'sophie_patient'    => 'Patiënt of ouder',
	);
}

/**
 * Rollen aanmaken of bijwerken; administrators krijgen alle portaalrechten.
 */
function ls_op_rollen_installeren() {
	$rechten = ls_op_rechten_per_rol();
	$namen   = ls_op_rolnamen();
	foreach ( $rechten as $rol => $caps ) {
		remove_role( $rol );
		add_role( $rol, $namen[ $rol ], $caps );
	}
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( $rechten['sophie_beheerder'] as $cap => $ok ) {
			$admin->add_cap( $cap );
		}
	}
}

/**
 * Is deze gebruiker praktijkpersoneel (beheerder of logopedist)?
 *
 * @param int|WP_User|null $user Gebruiker (standaard: ingelogde gebruiker).
 * @return bool
 */
function ls_op_is_personeel( $user = null ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_userdata( $user ) ) : wp_get_current_user();
	return $user && $user->exists() && user_can( $user, 'ls_portaal_beheer' );
}

/**
 * Is deze gebruiker een patiënt of ouder?
 *
 * @param int|WP_User|null $user Gebruiker.
 * @return bool
 */
function ls_op_is_patient( $user = null ) {
	$user = $user ? ( $user instanceof WP_User ? $user : get_userdata( $user ) ) : wp_get_current_user();
	return $user && $user->exists() && in_array( 'sophie_patient', (array) $user->roles, true );
}

/**
 * Rol van een gebruiker in het portaal, als leesbare tekst.
 *
 * @param WP_User $user Gebruiker.
 * @return string
 */
function ls_op_rol_label( $user ) {
	if ( user_can( $user, 'manage_options' ) || in_array( 'sophie_beheerder', (array) $user->roles, true ) ) {
		return 'Beheerder';
	}
	if ( in_array( 'sophie_logopedist', (array) $user->roles, true ) ) {
		return 'Logopedist';
	}
	if ( in_array( 'sophie_patient', (array) $user->roles, true ) ) {
		return 'Patiënt of ouder';
	}
	return 'Andere rol';
}

/* ---------- Geblokkeerde accounts ---------- */
add_filter(
	'authenticate',
	function ( $user ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, '_ls_geblokkeerd', true ) ) {
			return new WP_Error( 'ls_geblokkeerd', 'Dit account is geblokkeerd. Neem contact op met de praktijk.' );
		}
		return $user;
	},
	99
);

/* ---------- Patiënten blijven uit wp-admin ---------- */
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ! ls_op_is_patient() || current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_safe_redirect( ls_op_pagina_url( 'portaal' ) );
		exit;
	}
);
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return ls_op_is_patient() && ! current_user_can( 'edit_posts' ) ? false : $show;
	}
);

/* ---------- Na het inloggen naar de juiste plek ---------- */
add_filter(
	'login_redirect',
	function ( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User ) {
			return $redirect_to;
		}
		if ( ls_op_is_patient( $user ) ) {
			return ls_op_pagina_url( 'portaal' );
		}
		if ( ls_op_is_personeel( $user ) && ! user_can( $user, 'manage_options' ) ) {
			return admin_url( 'admin.php?page=ls-portaal' );
		}
		return $redirect_to;
	},
	10,
	3
);

add_action(
	'wp_login',
	function ( $login, $user ) {
		if ( ls_op_is_personeel( $user ) ) {
			ls_op_log( 'Ingelogd', $user->ID );
		}
	},
	10,
	2
);

/* ---------- WooCommerce: personeel mag wp-admin gebruiken ---------- */
add_filter(
	'woocommerce_prevent_admin_access',
	function ( $prevent ) {
		return ls_op_is_personeel() ? false : $prevent;
	}
);

/* ---------- Personeel ziet een opgeruimd wp-admin ---------- */
add_action(
	'admin_menu',
	function () {
		if ( current_user_can( 'edit_posts' ) || ! ls_op_is_personeel() ) {
			return;
		}
		remove_menu_page( 'index.php' );
		remove_menu_page( 'upload.php' );
		remove_menu_page( 'tools.php' );
	},
	999
);
