<?php
/**
 * Gegevens: oefeningen, patiëntdossiers, aanvragen en hulpfuncties.
 *
 * @package sophie-oefenportaal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kleuren voor de oefentegels.
 *
 * @return array<string,array{0:string,1:string}>
 */
function ls_op_kleuren() {
	return array(
		'blauw'  => array( '#BFD1DF', 'Blauw' ),
		'zon'    => array( '#EAD6A8', 'Zon' ),
		'perzik' => array( '#F2C9A8', 'Perzik' ),
		'groen'  => array( '#C9D8C0', 'Groen' ),
		'lila'   => array( '#DCD0E8', 'Lila' ),
		'zand'   => array( '#E3C99E', 'Zand' ),
	);
}

/**
 * Leerjaren voor de Oefenhoek.
 *
 * @return array<string,string>
 */
function ls_op_niveaus() {
	return array(
		'k'  => 'Kleuter',
		'1e' => '1e leerjaar',
		'2e' => '2e leerjaar',
		'3e' => '3e leerjaar',
		'4e' => '4e leerjaar',
		'5e' => '5e leerjaar',
		'6e' => '6e leerjaar',
	);
}

/**
 * Posttypes en taxonomie registreren.
 */
function ls_op_data_registreren() {
	register_post_type(
		'ls_oefening',
		array(
			'labels'          => array(
				'name'          => 'Oefeningen',
				'singular_name' => 'Oefening',
				'add_new'       => 'Nieuwe oefening',
				'add_new_item'  => 'Nieuwe oefening',
				'edit_item'     => 'Oefening bewerken',
				'all_items'     => 'Oefenbibliotheek',
				'search_items'  => 'Oefeningen zoeken',
				'not_found'     => 'Nog geen oefeningen',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'ls-portaal',
			'show_in_rest'    => true,
			'supports'        => array( 'title', 'editor', 'thumbnail' ),
			'capability_type' => array( 'ls_oefening', 'ls_oefeningen' ),
			'map_meta_cap'    => true,
			'menu_icon'       => 'dashicons-welcome-learn-more',
		)
	);

	register_taxonomy(
		'ls_domein',
		'ls_oefening',
		array(
			'labels'            => array(
				'name'          => 'Domeinen',
				'singular_name' => 'Domein',
				'add_new_item'  => 'Nieuw domein',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'capabilities'      => array(
				'manage_terms' => 'ls_portaal_beheer',
				'edit_terms'   => 'ls_portaal_beheer',
				'delete_terms' => 'ls_instellingen',
				'assign_terms' => 'ls_portaal_beheer',
			),
		)
	);

	register_post_type(
		'ls_patient',
		array(
			'labels'       => array(
				'name'          => 'Patiënten',
				'singular_name' => 'Patiënt',
			),
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => false,
			'supports'     => array( 'title' ),
		)
	);

	register_post_type(
		'ls_aanvraag',
		array(
			'labels'       => array(
				'name'          => 'Aanvragen',
				'singular_name' => 'Aanvraag',
				'all_items'     => 'Aanvragen',
				'edit_item'     => 'Aanvraag',
				'not_found'     => 'Nog geen aanvragen',
				'search_items'  => 'Aanvragen zoeken',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'ls-portaal',
			'supports'     => array( 'title' ),
			'capabilities' => array(
				'edit_post'          => 'ls_portaal_beheer',
				'read_post'          => 'ls_portaal_beheer',
				'delete_post'        => 'ls_portaal_beheer',
				'edit_posts'         => 'ls_portaal_beheer',
				'edit_others_posts'  => 'ls_portaal_beheer',
				'delete_posts'       => 'ls_portaal_beheer',
				'publish_posts'      => 'do_not_allow',
				'read_private_posts' => 'ls_portaal_beheer',
				'create_posts'       => 'do_not_allow',
			),
		)
	);
}
add_action( 'init', 'ls_op_data_registreren' );

/* ====================================================================
 * Oefeningen: extra velden in het bewerkscherm
 * ==================================================================== */

add_action(
	'add_meta_boxes_ls_oefening',
	function () {
		add_meta_box( 'ls_oefening_info', 'Gegevens van de oefening', 'ls_op_oefening_metabox', 'ls_oefening', 'normal', 'high' );
	}
);

/**
 * Metabox van een oefening.
 *
 * @param WP_Post $post Oefening.
 */
function ls_op_oefening_metabox( $post ) {
	wp_nonce_field( 'ls_oefening_opslaan', 'ls_oefening_nonce' );
	$kort    = get_post_meta( $post->ID, '_ls_kort', true );
	$kleur   = get_post_meta( $post->ID, '_ls_kleur', true ) ?: 'blauw';
	$info    = get_post_meta( $post->ID, '_ls_info', true );
	$duur    = get_post_meta( $post->ID, '_ls_duur', true );
	$link    = get_post_meta( $post->ID, '_ls_link', true );
	$hoek    = get_post_meta( $post->ID, '_ls_oefenhoek', true );
	$niveaus = (array) get_post_meta( $post->ID, '_ls_niveaus', true );
	?>
	<p class="description">De uitleg hierboven ziet de patiënt bij "Start oefening". Zet hier een link als de oefening op een andere site staat (een spel, een video, een pdf).</p>
	<table class="form-table" role="presentation">
		<tr><th><label for="ls_kort">Korte naam op de tegel</label></th><td><input type="text" id="ls_kort" name="ls_kort" value="<?php echo esc_attr( $kort ); ?>" maxlength="6" class="small-text" placeholder="ei/ij"></td></tr>
		<tr><th>Kleur van de tegel</th><td>
			<?php foreach ( ls_op_kleuren() as $slug => $k ) : ?>
				<label style="display:inline-flex;align-items:center;gap:6px;margin-right:14px"><input type="radio" name="ls_kleur" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $kleur, $slug ); ?>><span style="display:inline-block;width:22px;height:22px;border-radius:6px;background:<?php echo esc_attr( $k[0] ); ?>"></span><?php echo esc_html( $k[1] ); ?></label>
			<?php endforeach; ?>
		</td></tr>
		<tr><th><label for="ls_info">Niveau of soort</label></th><td><input type="text" id="ls_info" name="ls_info" value="<?php echo esc_attr( $info ); ?>" class="regular-text" placeholder="AVI M3 · hardop lezen"></td></tr>
		<tr><th><label for="ls_duur">Duur</label></th><td><input type="text" id="ls_duur" name="ls_duur" value="<?php echo esc_attr( $duur ); ?>" class="small-text" placeholder="10 min"></td></tr>
		<tr><th><label for="ls_link">Link naar de oefening</label></th><td><input type="url" id="ls_link" name="ls_link" value="<?php echo esc_attr( $link ); ?>" class="regular-text" placeholder="https://"></td></tr>
		<tr><th>Oefenhoek</th><td><label><input type="checkbox" name="ls_oefenhoek" value="1" <?php checked( $hoek ); ?>> Iedereen met een account mag deze oefening vrij gebruiken in de Oefenhoek</label>
			<p style="margin-top:8px">Voor welk leerjaar?</p>
			<?php foreach ( ls_op_niveaus() as $slug => $label ) : ?>
				<label style="margin-right:12px"><input type="checkbox" name="ls_niveaus[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $niveaus, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
		</td></tr>
	</table>
	<?php
}

add_action(
	'save_post_ls_oefening',
	function ( $post_id ) {
		if ( ! isset( $_POST['ls_oefening_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ls_oefening_nonce'] ), 'ls_oefening_opslaan' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$kleur = sanitize_key( wp_unslash( $_POST['ls_kleur'] ?? 'blauw' ) );
		update_post_meta( $post_id, '_ls_kort', sanitize_text_field( wp_unslash( $_POST['ls_kort'] ?? '' ) ) );
		update_post_meta( $post_id, '_ls_kleur', isset( ls_op_kleuren()[ $kleur ] ) ? $kleur : 'blauw' );
		update_post_meta( $post_id, '_ls_info', sanitize_text_field( wp_unslash( $_POST['ls_info'] ?? '' ) ) );
		update_post_meta( $post_id, '_ls_duur', sanitize_text_field( wp_unslash( $_POST['ls_duur'] ?? '' ) ) );
		update_post_meta( $post_id, '_ls_link', esc_url_raw( wp_unslash( $_POST['ls_link'] ?? '' ) ) );
		update_post_meta( $post_id, '_ls_oefenhoek', empty( $_POST['ls_oefenhoek'] ) ? 0 : 1 );
		$niveaus = array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['ls_niveaus'] ?? array() ) ), array_keys( ls_op_niveaus() ) );
		update_post_meta( $post_id, '_ls_niveaus', array_values( $niveaus ) );
		$was_new = get_post_meta( $post_id, '_ls_gelogd', true );
		if ( ! $was_new ) {
			update_post_meta( $post_id, '_ls_gelogd', 1 );
			ls_op_log( 'Oefening "' . get_the_title( $post_id ) . '" toegevoegd aan de bibliotheek' );
		}
	}
);

/**
 * Gegevens van een oefening voor weergave.
 *
 * @param int $id Oefening.
 * @return array|null
 */
function ls_op_oefening( $id ) {
	$p = get_post( $id );
	if ( ! $p || 'ls_oefening' !== $p->post_type || 'trash' === $p->post_status ) {
		return null;
	}
	$kleur = get_post_meta( $id, '_ls_kleur', true ) ?: 'blauw';
	$meta  = array_filter( array( get_post_meta( $id, '_ls_info', true ), get_post_meta( $id, '_ls_duur', true ) ) );
	$terms = get_the_terms( $id, 'ls_domein' );
	return array(
		'id'      => $id,
		'titel'   => get_the_title( $p ),
		'kort'    => get_post_meta( $id, '_ls_kort', true ) ?: mb_substr( get_the_title( $p ), 0, 4 ),
		'kleur'   => ls_op_kleuren()[ $kleur ][0] ?? '#BFD1DF',
		'meta'    => implode( ' · ', $meta ),
		'link'    => get_post_meta( $id, '_ls_link', true ),
		'uitleg'  => apply_filters( 'the_content', $p->post_content ),
		'domein'  => $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '',
		'niveaus' => (array) get_post_meta( $id, '_ls_niveaus', true ),
	);
}

/* ====================================================================
 * Patiëntdossiers
 * ==================================================================== */

/**
 * Mag deze gebruiker dit dossier zien en bewerken?
 *
 * @param int      $pid  Dossier.
 * @param int|null $user Gebruiker.
 * @return bool
 */
function ls_op_mag_dossier( $pid, $user = null ) {
	$user = $user ?: get_current_user_id();
	if ( 'ls_patient' !== get_post_type( $pid ) ) {
		return false;
	}
	if ( user_can( $user, 'ls_alle_patienten' ) ) {
		return true;
	}
	return user_can( $user, 'ls_portaal_beheer' ) && (int) get_post_meta( $pid, '_ls_owner', true ) === (int) $user;
}

/**
 * Dossiers die een personeelslid mag zien.
 *
 * @param int    $user  Gebruiker.
 * @param string $eigen 'alle' of een user-id als filter (alleen voor beheerders).
 * @param string $zoek  Zoekterm.
 * @return WP_Post[]
 */
function ls_op_dossiers_voor( $user, $eigen = 'alle', $zoek = '' ) {
	$args = array(
		'post_type'      => 'ls_patient',
		'post_status'    => array( 'private', 'publish' ),
		'posts_per_page' => 500,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( $zoek ) {
		$args['s'] = $zoek;
	}
	if ( ! user_can( $user, 'ls_alle_patienten' ) ) {
		$args['meta_query'] = array( array( 'key' => '_ls_owner', 'value' => (int) $user ) );
	} elseif ( 'alle' !== $eigen && $eigen ) {
		$args['meta_query'] = array( array( 'key' => '_ls_owner', 'value' => (int) $eigen ) );
	}
	return get_posts( $args );
}

/**
 * Dossiers waaraan een ouder of patiënt gekoppeld is.
 *
 * @param int $user Gebruiker.
 * @return WP_Post[]
 */
function ls_op_dossiers_van_ouder( $user ) {
	$ids = array_filter( array_map( 'intval', (array) get_user_meta( $user, '_ls_dossiers', true ) ) );
	if ( ! $ids ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'ls_patient',
			'post_status'    => array( 'private', 'publish' ),
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => 20,
		)
	);
}

/**
 * Ouder koppelen aan een dossier.
 *
 * @param int $pid  Dossier.
 * @param int $user Gebruiker.
 */
function ls_op_koppel_ouder( $pid, $user ) {
	$dossiers   = array_filter( array_map( 'intval', (array) get_user_meta( $user, '_ls_dossiers', true ) ) );
	$dossiers[] = (int) $pid;
	update_user_meta( $user, '_ls_dossiers', array_values( array_unique( $dossiers ) ) );
	$ouders   = array_filter( array_map( 'intval', (array) get_post_meta( $pid, '_ls_ouders', true ) ) );
	$ouders[] = (int) $user;
	update_post_meta( $pid, '_ls_ouders', array_values( array_unique( $ouders ) ) );
}

/**
 * Opengezette oefeningen van een dossier.
 *
 * @param int $pid Dossier.
 * @return array<int,array{oef:int,sinds:string,door:int}>
 */
function ls_op_taken( $pid ) {
	$taken = get_post_meta( $pid, '_ls_taken', true );
	return is_array( $taken ) ? array_values( array_filter( $taken, fn( $t ) => ls_op_oefening( $t['oef'] ?? 0 ) ) ) : array();
}

/**
 * Oefening openzetten voor een dossier.
 *
 * @param int $pid Dossier.
 * @param int $oef Oefening.
 * @return bool
 */
function ls_op_taak_toevoegen( $pid, $oef ) {
	$taken = ls_op_taken( $pid );
	foreach ( $taken as $t ) {
		if ( (int) $t['oef'] === (int) $oef ) {
			return false;
		}
	}
	$taken[] = array( 'oef' => (int) $oef, 'sinds' => current_time( 'Y-m-d' ), 'door' => get_current_user_id() );
	update_post_meta( $pid, '_ls_taken', $taken );
	ls_op_log( 'Oefening "' . get_the_title( $oef ) . '" opengezet voor ' . get_the_title( $pid ) );
	return true;
}

/**
 * Oefening weer sluiten.
 *
 * @param int $pid Dossier.
 * @param int $oef Oefening.
 */
function ls_op_taak_verwijderen( $pid, $oef ) {
	$taken = array_values( array_filter( ls_op_taken( $pid ), fn( $t ) => (int) $t['oef'] !== (int) $oef ) );
	update_post_meta( $pid, '_ls_taken', $taken );
	ls_op_log( 'Oefening "' . get_the_title( $oef ) . '" gesloten voor ' . get_the_title( $pid ) );
}

/**
 * Wanneer werd een oefening gedaan?
 *
 * @param int $pid Dossier.
 * @return array<int,string[]> oefening-id => lijst van datums (Y-m-d)
 */
function ls_op_gedaan( $pid ) {
	$g = get_post_meta( $pid, '_ls_gedaan', true );
	return is_array( $g ) ? $g : array();
}

/**
 * Oefening vandaag als gedaan markeren (of terugdraaien).
 *
 * @param int  $pid    Dossier.
 * @param int  $oef    Oefening.
 * @param bool $gedaan Gedaan of niet.
 */
function ls_op_markeer( $pid, $oef, $gedaan ) {
	$g     = ls_op_gedaan( $pid );
	$today = current_time( 'Y-m-d' );
	$lijst = $g[ $oef ] ?? array();
	$lijst = array_values( array_diff( $lijst, array( $today ) ) );
	if ( $gedaan ) {
		$lijst[] = $today;
	}
	$g[ $oef ] = array_slice( $lijst, -60 );
	update_post_meta( $pid, '_ls_gedaan', $g );
}

/**
 * Korte tekst voor de logopedist: wanneer werd deze week geoefend?
 *
 * @param int $pid Dossier.
 * @param int $oef Oefening.
 * @return string
 */
function ls_op_resultaat_tekst( $pid, $oef ) {
	$dagen = array( 1 => 'ma', 'di', 'wo', 'do', 'vr', 'za', 'zo' );
	$start = strtotime( 'monday this week', current_time( 'timestamp' ) );
	$deze  = array();
	foreach ( ls_op_gedaan( $pid )[ $oef ] ?? array() as $d ) {
		$t = strtotime( $d );
		if ( $t >= $start ) {
			$deze[] = $dagen[ (int) gmdate( 'N', $t ) ];
		}
	}
	return $deze ? 'deze week gedaan op ' . implode( ', ', $deze ) : 'deze week nog niet gedaan';
}

/* ---------- Uitnodigingscodes ---------- */

/**
 * Nieuwe uitnodigingscode maken voor een dossier.
 *
 * @param int $pid Dossier.
 * @return string
 */
function ls_op_code_maken( $pid ) {
	do {
		$code = 'LS-' . wp_rand( 1000, 9999 );
	} while ( ls_op_code_zoeken( $code ) );
	add_post_meta( $pid, '_ls_code', $code );
	ls_op_log( 'Uitnodigingscode ' . $code . ' aangemaakt voor ' . get_the_title( $pid ) );
	return $code;
}

/**
 * Dossier bij een (nog niet gebruikte) code.
 *
 * @param string $code Code.
 * @return int
 */
function ls_op_code_zoeken( $code ) {
	$code = strtoupper( trim( $code ) );
	if ( ! preg_match( '/^LS-\d{4}$/', $code ) ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'   => 'ls_patient',
			'post_status' => array( 'private', 'publish' ),
			'meta_key'    => '_ls_code',
			'meta_value'  => $code,
			'fields'      => 'ids',
			'numberposts' => 1,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Code inwisselen: ouder koppelen en code ongeldig maken.
 *
 * @param string $code Code.
 * @param int    $user Gebruiker.
 * @return int Dossier-id of 0.
 */
function ls_op_code_gebruiken( $code, $user ) {
	$pid = ls_op_code_zoeken( $code );
	if ( ! $pid ) {
		return 0;
	}
	$code = strtoupper( trim( $code ) );
	delete_post_meta( $pid, '_ls_code', $code );
	add_post_meta( $pid, '_ls_code_gebruikt', $code . ' · ' . current_time( 'd/m/Y' ) );
	ls_op_koppel_ouder( $pid, $user );
	ls_op_log( 'Code ' . $code . ' gebruikt: account gekoppeld aan ' . get_the_title( $pid ), $user );
	return $pid;
}
