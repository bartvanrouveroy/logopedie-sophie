<?php
/**
 * Aanvragen uit de chat en het contactformulier: bewaren en mailen naar de praktijk.
 * Gratis: geen AI en geen externe dienst; de mail gaat via WordPress (stel WP Mail SMTP in op de echte site).
 *
 * @package sophie-oefenportaal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Velden van een aanvraag, met label.
 *
 * @return array<string,string>
 */
function ls_op_aanvraag_velden() {
	return array(
		'naam'     => 'Naam',
		'voor'     => 'Voor',
		'leeftijd' => 'Leeftijd of leerjaar',
		'hulp'     => 'Hulpvraag',
		'toel'     => 'Toelichting',
		'tel'      => 'Telefoon',
		'mail'     => 'E-mail',
		'pref'     => 'Contact via',
		'bron'     => 'Via',
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ls/v1',
			'/aanvraag',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'ls_op_aanvraag_ontvangen',
			)
		);
	}
);

/**
 * Aanvraag verwerken.
 *
 * @param WP_REST_Request $req Verzoek.
 * @return WP_REST_Response|WP_Error
 */
function ls_op_aanvraag_ontvangen( WP_REST_Request $req ) {
	$p = $req->get_json_params() ?: $req->get_body_params();
	if ( ! empty( $p['website'] ) ) { // Honeypot: doe alsof het lukte.
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	$ip   = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$key  = 'ls_op_rl_' . md5( $ip );
	$tell = (int) get_transient( $key );
	if ( $tell >= 5 ) {
		return new WP_Error( 'ls_te_veel', 'Te veel aanvragen na elkaar. Probeer later opnieuw of bel de praktijk.', array( 'status' => 429 ) );
	}
	$d = array();
	foreach ( array_keys( ls_op_aanvraag_velden() ) as $k ) {
		$d[ $k ] = 'toel' === $k ? sanitize_textarea_field( mb_substr( (string) ( $p[ $k ] ?? '' ), 0, 800 ) ) : sanitize_text_field( mb_substr( (string) ( $p[ $k ] ?? '' ), 0, 160 ) );
	}
	if ( mb_strlen( $d['naam'] ) < 2 || ( ! is_email( $d['mail'] ) && strlen( preg_replace( '/\D/', '', $d['tel'] ) ) < 9 ) ) {
		return new WP_Error( 'ls_onvolledig', 'Vul je naam en een telefoonnummer of e-mailadres in.', array( 'status' => 400 ) );
	}
	if ( empty( $p['toestemming'] ) ) {
		return new WP_Error( 'ls_toestemming', 'Geef toestemming om je gegevens door te sturen.', array( 'status' => 400 ) );
	}
	set_transient( $key, $tell + 1, 10 * MINUTE_IN_SECONDS );

	$titel = $d['naam'] . ( $d['hulp'] ? ' – ' . $d['hulp'] : '' );
	$id    = wp_insert_post(
		array(
			'post_type'   => 'ls_aanvraag',
			'post_status' => 'private',
			'post_title'  => $titel,
		)
	);
	foreach ( $d as $k => $v ) {
		update_post_meta( $id, '_ls_' . $k, $v );
	}
	update_post_meta( $id, '_ls_status', 'nieuw' );

	$regels = array();
	foreach ( ls_op_aanvraag_velden() as $k => $label ) {
		if ( '' !== $d[ $k ] ) {
			$regels[] = $label . ': ' . $d[ $k ];
		}
	}
	$regels[] = 'Toestemming gegeven: ja';
	$regels[] = '';
	$regels[] = 'Ook te zien in de website: ' . admin_url( 'post.php?post=' . $id . '&action=edit' );
	$headers  = array();
	if ( is_email( $d['mail'] ) ) {
		$headers[] = 'Reply-To: ' . $d['naam'] . ' <' . $d['mail'] . '>';
	}
	$verstuurd = wp_mail( ls_op_optie( 'ontvanger' ), 'Nieuwe aanvraag via de ' . ( 'chat' === $d['bron'] ? 'chat' : 'website' ) . ' – ' . $d['naam'], implode( "\n", $regels ), $headers );
	update_post_meta( $id, '_ls_gemaild', $verstuurd ? 1 : 0 );
	ls_op_log( 'Nieuwe aanvraag van ' . $d['naam'] . ' via ' . ( $d['bron'] ?: 'website' ), 0 );

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'gemaild' => (bool) $verstuurd,
			'termijn' => ls_op_optie( 'antwoordtermijn' ),
		),
		200
	);
}

/* ---------- Aanvragen in wp-admin ---------- */
add_filter(
	'manage_ls_aanvraag_posts_columns',
	function () {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => 'Aanvraag',
			'ls_tel'    => 'Telefoon',
			'ls_mail'   => 'E-mail',
			'ls_bron'   => 'Via',
			'ls_status' => 'Status',
			'date'      => 'Ontvangen',
		);
	}
);
add_action(
	'manage_ls_aanvraag_posts_custom_column',
	function ( $col, $id ) {
		switch ( $col ) {
			case 'ls_tel':
				echo esc_html( get_post_meta( $id, '_ls_tel', true ) );
				break;
			case 'ls_mail':
				echo esc_html( get_post_meta( $id, '_ls_mail', true ) );
				break;
			case 'ls_bron':
				echo esc_html( get_post_meta( $id, '_ls_bron', true ) ?: 'website' );
				break;
			case 'ls_status':
				$s = get_post_meta( $id, '_ls_status', true );
				echo 'nieuw' === $s ? '<span class="ls-op-pill new">Nieuw</span>' : '<span class="ls-op-pill ok">Behandeld</span>';
				break;
		}
	},
	10,
	2
);
add_action(
	'add_meta_boxes_ls_aanvraag',
	function () {
		add_meta_box( 'ls_aanvraag_info', 'Gegevens', 'ls_op_aanvraag_metabox', 'ls_aanvraag', 'normal', 'high' );
	}
);

/**
 * Inhoud van een aanvraag.
 *
 * @param WP_Post $post Aanvraag.
 */
function ls_op_aanvraag_metabox( $post ) {
	wp_nonce_field( 'ls_aanvraag_status', 'ls_aanvraag_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( ls_op_aanvraag_velden() as $k => $label ) {
		$v = get_post_meta( $post->ID, '_ls_' . $k, true );
		if ( '' === $v ) {
			continue;
		}
		if ( 'mail' === $k && is_email( $v ) ) {
			$v = '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
		} elseif ( 'tel' === $k ) {
			$v = '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $v ) ) . '">' . esc_html( $v ) . '</a>';
		} else {
			$v = nl2br( esc_html( $v ) );
		}
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . wp_kses_post( $v ) . '</td></tr>';
	}
	$s = get_post_meta( $post->ID, '_ls_status', true );
	echo '<tr><th>Status</th><td><label><input type="checkbox" name="ls_behandeld" value="1" ' . checked( 'nieuw' !== $s, true, false ) . '> Behandeld</label></td></tr>';
	echo '<tr><th>Mail</th><td>' . ( get_post_meta( $post->ID, '_ls_gemaild', true ) ? 'Verstuurd naar ' . esc_html( ls_op_optie( 'ontvanger' ) ) : 'Niet verstuurd (controleer de mailinstellingen van de website)' ) . '</td></tr>';
	echo '</table>';
}
add_action(
	'save_post_ls_aanvraag',
	function ( $id ) {
		if ( ! isset( $_POST['ls_aanvraag_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ls_aanvraag_nonce'] ), 'ls_aanvraag_status' ) || ! current_user_can( 'ls_portaal_beheer' ) ) {
			return;
		}
		update_post_meta( $id, '_ls_status', empty( $_POST['ls_behandeld'] ) ? 'nieuw' : 'behandeld' );
	}
);
/* Aanvragen blijven privé, ook na opslaan. */
add_filter(
	'wp_insert_post_data',
	function ( $data ) {
		if ( 'ls_aanvraag' === $data['post_type'] && ! in_array( $data['post_status'], array( 'trash', 'auto-draft' ), true ) ) {
			$data['post_status'] = 'private';
		}
		return $data;
	}
);
/* Aantal nieuwe aanvragen naast het menu. */
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( empty( $submenu['ls-portaal'] ) ) {
			return;
		}
		$nieuw = count( get_posts( array( 'post_type' => 'ls_aanvraag', 'post_status' => 'private', 'meta_key' => '_ls_status', 'meta_value' => 'nieuw', 'fields' => 'ids', 'numberposts' => 99 ) ) );
		if ( ! $nieuw ) {
			return;
		}
		foreach ( $submenu['ls-portaal'] as &$item ) {
			if ( 'edit.php?post_type=ls_aanvraag' === $item[2] ) {
				$item[0] .= ' <span class="awaiting-mod">' . (int) $nieuw . '</span>';
			}
		}
	},
	1000
);

/* ====================================================================
 * Chat (rechtsonder) en contactformulier
 * ==================================================================== */
add_action(
	'wp_enqueue_scripts',
	function () {
		$portaal = (int) get_option( 'ls_op_pagina_portaal' );
		wp_register_script( 'ls-op-chat', LS_OP_URL . 'assets/chat.js', array(), LS_OP_VERSION, true );
		wp_localize_script(
			'ls-op-chat',
			'LS_CHAT',
			array(
				'endpoint' => esc_url_raw( rest_url( 'ls/v1/aanvraag' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'telefoon' => ls_op_optie( 'telefoon' ),
				'termijn'  => ls_op_optie( 'antwoordtermijn' ),
				'chat'     => ls_op_optie( 'chat_aan' ) && ! ( $portaal && is_page( $portaal ) ) ? 1 : 0,
				'links'    => array(
					'werkwijze' => ( $p = get_page_by_path( 'werkwijze-en-tarieven' ) ) ? get_permalink( $p ) : '',
					'webshop'   => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
					'portaal'   => ls_op_pagina_url( 'portaal' ),
				),
			)
		);
		wp_enqueue_script( 'ls-op-chat' );
	}
);

add_shortcode(
	'ls_contactformulier',
	function () {
		if ( is_admin() || wp_is_json_request() ) {
			return '<p>[Contactformulier]</p>';
		}
		$hulp = array( 'Dyslexie', 'Dysorthografie', 'Dyscalculie', 'Taalontwikkeling', 'Afasie', 'OMFT (mondgewoonten)', 'Blind leren typen', 'Weet ik nog niet' );
		ob_start();
		?>
		<form class="ls-op ls-op-card ls-op-form ls-contact" novalidate data-ls-contact>
			<p class="ls-op-hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></p>
			<label>Naam<input class="ls-op-field" name="naam" required autocomplete="name"></label>
			<div class="ls-op-row">
				<label>Voor wie?<select class="ls-op-field" name="voor"><option>Mijn kind</option><option>Mezelf</option><option>Een familielid</option></select></label>
				<label>Hulpvraag<select class="ls-op-field" name="hulp"><?php foreach ( $hulp as $h ) { echo '<option>' . esc_html( $h ) . '</option>'; } ?></select></label>
			</div>
			<div class="ls-op-row">
				<label>Telefoon<input class="ls-op-field" name="tel" type="tel" autocomplete="tel"></label>
				<label>E-mail<input class="ls-op-field" name="mail" type="email" autocomplete="email"></label>
			</div>
			<label>Korte toelichting <span class="ls-op-muted">(geen medische details nodig)</span><textarea class="ls-op-field" name="toel" rows="4" placeholder="Bijvoorbeeld: de juf merkt dat lezen in het 2e leerjaar moeizaam gaat."></textarea></label>
			<label class="ls-op-check"><input type="checkbox" name="toestemming" value="1"> Ik ga akkoord dat mijn gegevens naar de praktijk gestuurd worden, volgens het privacybeleid.</label>
			<p class="ls-op-error" hidden role="alert"></p>
			<p class="ls-op-ok" hidden role="status"></p>
			<button class="ls-op-btn" type="submit">Aanvraag versturen</button>
		</form>
		<?php
		return ob_get_clean();
	}
);
