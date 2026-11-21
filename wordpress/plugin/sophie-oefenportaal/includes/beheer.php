<?php
/**
 * Beheer van het oefenportaal in wp-admin.
 *
 * @package sophie-oefenportaal
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'Oefenportaal', 'Oefenportaal', 'ls_portaal_beheer', 'ls-portaal', 'ls_op_pagina_patienten', 'dashicons-groups', 3 );
		add_submenu_page( 'ls-portaal', 'Patiënten', 'Patiënten', 'ls_portaal_beheer', 'ls-portaal', 'ls_op_pagina_patienten' );
		add_submenu_page( 'ls-portaal', 'Domeinen', 'Domeinen', 'ls_portaal_beheer', 'edit-tags.php?taxonomy=ls_domein&post_type=ls_oefening' );
		add_submenu_page( 'ls-portaal', 'Gebruikers en rechten', 'Gebruikers en rechten', 'ls_gebruikers_beheer', 'ls-gebruikers', 'ls_op_pagina_gebruikers' );
		add_submenu_page( 'ls-portaal', 'Logboek', 'Logboek', 'ls_portaal_beheer', 'ls-logboek', 'ls_op_pagina_logboek' );
		add_submenu_page( 'ls-portaal', 'Instellingen', 'Instellingen', 'ls_instellingen', 'ls-instellingen', 'ls_op_pagina_instellingen' );
	}
);

/* Het Domeinen-menu openklappen onder Oefenportaal. */
add_filter(
	'parent_file',
	function ( $parent ) {
		global $current_screen;
		if ( $current_screen && 'ls_domein' === $current_screen->taxonomy ) {
			return 'ls-portaal';
		}
		return $parent;
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = get_current_screen();
		if ( false !== strpos( $hook, 'ls-' ) || ( $screen && in_array( $screen->post_type, array( 'ls_oefening', 'ls_aanvraag' ), true ) ) ) {
			wp_enqueue_style( 'ls-op-admin', LS_OP_URL . 'assets/admin.css', array(), LS_OP_VERSION );
		}
	}
);

add_action(
	'wp_login',
	function ( $login, $user ) {
		update_user_meta( $user->ID, '_ls_laatst', current_time( 'mysql' ) );
	},
	10,
	2
);

/**
 * Melding bovenaan een beheerpagina (na een actie).
 */
function ls_op_admin_melding() {
	$m = sanitize_text_field( wp_unslash( $_GET['ls_msg'] ?? '' ) );
	if ( $m ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $m ) );
	}
	$f = sanitize_text_field( wp_unslash( $_GET['ls_fout'] ?? '' ) );
	if ( $f ) {
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $f ) );
	}
}

/**
 * Terug naar een beheerpagina met een melding.
 *
 * @param array  $args Query-argumenten.
 * @param string $msg  Melding.
 * @param bool   $fout Is het een fout?
 */
function ls_op_terug( $args, $msg, $fout = false ) {
	$args[ $fout ? 'ls_fout' : 'ls_msg' ] = rawurlencode( $msg );
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Personeelsleden (voor de keuzelijst "logopedist").
 *
 * @return WP_User[]
 */
function ls_op_personeel() {
	return get_users(
		array(
			'role__in' => array( 'administrator', 'sophie_beheerder', 'sophie_logopedist' ),
			'orderby'  => 'display_name',
		)
	);
}

/* ====================================================================
 * Patiënten
 * ==================================================================== */

/**
 * Pagina Patiënten (overzicht of één dossier).
 */
function ls_op_pagina_patienten() {
	if ( ! current_user_can( 'ls_portaal_beheer' ) ) {
		wp_die( 'Geen toegang.' );
	}
	$pid = absint( $_GET['dossier'] ?? 0 );
	echo '<div class="wrap ls-op-admin">';
	ls_op_admin_melding();
	if ( $pid ) {
		if ( ! ls_op_mag_dossier( $pid ) ) {
			echo '<h1>Geen toegang</h1><p>Dit dossier hoort bij een andere logopedist.</p></div>';
			return;
		}
		ls_op_dossier_detail( $pid );
	} else {
		ls_op_dossier_lijst();
	}
	echo '</div>';
}

/**
 * Overzicht van dossiers.
 */
function ls_op_dossier_lijst() {
	$user  = get_current_user_id();
	$admin = current_user_can( 'ls_alle_patienten' );
	$eigen = $admin ? sanitize_text_field( wp_unslash( $_GET['eigen'] ?? 'alle' ) ) : (string) $user;
	$zoek  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
	$lijst = ls_op_dossiers_voor( $user, $eigen, $zoek );
	$staff = ls_op_personeel();
	?>
	<h1 class="wp-heading-inline">Patiënten</h1>
	<a href="#nieuw" class="page-title-action">Nieuwe patiënt</a>
	<p class="ls-op-intro"><?php echo $admin ? 'Je ziet de patiënten van alle logopedisten van de praktijk.' : 'Je ziet alleen je eigen patiënten.'; ?> Klik op een naam om oefeningen open te zetten en een uitnodigingscode te maken.</p>
	<form method="get" class="ls-op-filter">
		<input type="hidden" name="page" value="ls-portaal">
		<?php if ( $admin ) : ?>
			<label>Logopedist <select name="eigen" onchange="this.form.submit()">
				<option value="alle">Iedereen</option>
				<?php foreach ( $staff as $s ) : ?>
					<option value="<?php echo (int) $s->ID; ?>" <?php selected( $eigen, (string) $s->ID ); ?>><?php echo esc_html( $s->display_name ); ?></option>
				<?php endforeach; ?>
			</select></label>
		<?php endif; ?>
		<input type="search" name="s" value="<?php echo esc_attr( $zoek ); ?>" placeholder="Zoek een patiënt">
		<button class="button">Zoeken</button>
	</form>
	<table class="widefat striped ls-op-table">
		<thead><tr><th>Patiënt</th><th>Hulpvraag</th><th>Sessies</th><?php if ( $admin ) : ?><th>Logopedist</th><?php endif; ?><th>Oefeningen open</th><th>Deze week geoefend</th><th>Account</th></tr></thead>
		<tbody>
		<?php if ( ! $lijst ) : ?>
			<tr><td colspan="7">Nog geen patiënten. Maak hieronder een dossier aan.</td></tr>
		<?php endif; ?>
		<?php
		$start = strtotime( 'monday this week', current_time( 'timestamp' ) );
		foreach ( $lijst as $p ) :
			$taken  = ls_op_taken( $p->ID );
			$dagen  = array();
			foreach ( ls_op_gedaan( $p->ID ) as $dates ) {
				foreach ( (array) $dates as $d ) {
					if ( strtotime( $d ) >= $start ) {
						$dagen[ $d ] = true;
					}
				}
			}
			$owner  = get_userdata( (int) get_post_meta( $p->ID, '_ls_owner', true ) );
			$ouders = array_filter( (array) get_post_meta( $p->ID, '_ls_ouders', true ) );
			$url    = add_query_arg( array( 'page' => 'ls-portaal', 'dossier' => $p->ID ), admin_url( 'admin.php' ) );
			?>
			<tr>
				<td><strong><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $p->post_title ); ?></a></strong></td>
				<td><?php echo esc_html( get_post_meta( $p->ID, '_ls_focus', true ) ); ?></td>
				<td><?php echo esc_html( ls_op_sessiedagen_tekst( $p->ID ) ); ?></td>
				<?php if ( $admin ) : ?><td><?php echo esc_html( $owner ? $owner->display_name : '–' ); ?></td><?php endif; ?>
				<td><?php echo count( $taken ); ?></td>
				<td><?php echo count( $dagen ); ?> <?php echo 1 === count( $dagen ) ? 'dag' : 'dagen'; ?></td>
				<td><?php echo $ouders ? '<span class="ls-op-pill ok">Gekoppeld</span>' : '<span class="ls-op-pill">Nog geen</span>'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2 id="nieuw">Nieuwe patiënt</h2>
	<?php ls_op_dossier_form( 0 ); ?>
	<?php
}

/**
 * Sessiedagen als tekst.
 *
 * @param int $pid Dossier.
 * @return string
 */
function ls_op_sessiedagen_tekst( $pid ) {
	$namen = array( 1 => 'ma', 'di', 'wo', 'do', 'vr', 'za', 'zo' );
	$d     = array_map( 'intval', (array) get_post_meta( $pid, '_ls_sessiedagen', true ) );
	$out   = array();
	foreach ( $d as $n ) {
		if ( isset( $namen[ $n ] ) ) {
			$out[] = $namen[ $n ];
		}
	}
	return $out ? implode( ' en ', $out ) : '–';
}

/**
 * Formulier om een dossier te maken of te bewerken.
 *
 * @param int $pid Dossier (0 = nieuw).
 */
function ls_op_dossier_form( $pid ) {
	$admin = current_user_can( 'ls_alle_patienten' );
	$owner = $pid ? (int) get_post_meta( $pid, '_ls_owner', true ) : get_current_user_id();
	$dagen = $pid ? array_map( 'intval', (array) get_post_meta( $pid, '_ls_sessiedagen', true ) ) : array();
	$niv   = $pid ? get_post_meta( $pid, '_ls_niveau', true ) : '2e';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ls-op-box">
		<?php wp_nonce_field( 'ls_op_dossier_opslaan' ); ?>
		<input type="hidden" name="action" value="ls_op_dossier_opslaan">
		<input type="hidden" name="pid" value="<?php echo (int) $pid; ?>">
		<table class="form-table" role="presentation">
			<tr><th><label for="ls-naam">Naam in het dossier</label></th><td><input id="ls-naam" name="naam" class="regular-text" required value="<?php echo esc_attr( $pid ? get_the_title( $pid ) : '' ); ?>" placeholder="Lotte P."><p class="description">Gebruik voornaam en initiaal. Het dossier bevat geen medische gegevens.</p></td></tr>
			<tr><th><label for="ls-voornaam">Voornaam in het portaal</label></th><td><input id="ls-voornaam" name="voornaam" class="regular-text" value="<?php echo esc_attr( $pid ? get_post_meta( $pid, '_ls_voornaam', true ) : '' ); ?>" placeholder="Lotte"></td></tr>
			<tr><th><label for="ls-focus">Hulpvraag</label></th><td><input id="ls-focus" name="focus" class="regular-text" value="<?php echo esc_attr( $pid ? get_post_meta( $pid, '_ls_focus', true ) : '' ); ?>" placeholder="Dyslexie · AVI M3"></td></tr>
			<tr><th>Sessiedagen</th><td>
				<?php foreach ( array( 1 => 'ma', 'di', 'wo', 'do', 'vr', 'za' ) as $n => $l ) : ?>
					<label style="margin-right:12px"><input type="checkbox" name="dagen[]" value="<?php echo (int) $n; ?>" <?php checked( in_array( $n, $dagen, true ) ); ?>> <?php echo esc_html( $l ); ?></label>
				<?php endforeach; ?>
			</td></tr>
			<tr><th><label for="ls-niv">Leerjaar in de Oefenhoek</label></th><td><select id="ls-niv" name="niveau">
				<?php foreach ( ls_op_niveaus() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $niv, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
			<?php if ( $admin ) : ?>
			<tr><th><label for="ls-owner">Logopedist</label></th><td><select id="ls-owner" name="owner">
				<?php foreach ( ls_op_personeel() as $s ) : ?>
					<option value="<?php echo (int) $s->ID; ?>" <?php selected( $owner, $s->ID ); ?>><?php echo esc_html( $s->display_name ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
			<?php endif; ?>
		</table>
		<?php submit_button( $pid ? 'Wijzigingen opslaan' : 'Patiënt toevoegen', 'primary', 'submit', false ); ?>
	</form>
	<?php
}

add_action(
	'admin_post_ls_op_dossier_opslaan',
	function () {
		check_admin_referer( 'ls_op_dossier_opslaan' );
		if ( ! current_user_can( 'ls_portaal_beheer' ) ) {
			wp_die( 'Geen toegang.' );
		}
		$pid = absint( $_POST['pid'] ?? 0 );
		if ( $pid && ! ls_op_mag_dossier( $pid ) ) {
			wp_die( 'Geen toegang tot dit dossier.' );
		}
		$naam = sanitize_text_field( wp_unslash( $_POST['naam'] ?? '' ) );
		if ( ! $naam ) {
			ls_op_terug( array( 'page' => 'ls-portaal' ), 'Vul een naam in.', true );
		}
		$data = array(
			'post_type'   => 'ls_patient',
			'post_status' => 'private',
			'post_title'  => $naam,
		);
		if ( $pid ) {
			$data['ID'] = $pid;
			wp_update_post( $data );
		} else {
			$pid = wp_insert_post( $data );
			ls_op_log( 'Dossier aangemaakt voor ' . $naam );
		}
		$owner = current_user_can( 'ls_alle_patienten' ) ? absint( $_POST['owner'] ?? get_current_user_id() ) : ( (int) get_post_meta( $pid, '_ls_owner', true ) ?: get_current_user_id() );
		if ( ! ls_op_is_personeel( $owner ) ) {
			$owner = get_current_user_id();
		}
		$oud = (int) get_post_meta( $pid, '_ls_owner', true );
		update_post_meta( $pid, '_ls_owner', $owner );
		if ( $oud && $oud !== $owner ) {
			$nieuw = get_userdata( $owner );
			ls_op_log( 'Dossier ' . $naam . ' overgedragen aan ' . ( $nieuw ? $nieuw->display_name : '?' ) );
		}
		update_post_meta( $pid, '_ls_voornaam', sanitize_text_field( wp_unslash( $_POST['voornaam'] ?? '' ) ) );
		update_post_meta( $pid, '_ls_focus', sanitize_text_field( wp_unslash( $_POST['focus'] ?? '' ) ) );
		update_post_meta( $pid, '_ls_sessiedagen', array_values( array_intersect( array_map( 'intval', (array) wp_unslash( $_POST['dagen'] ?? array() ) ), range( 1, 6 ) ) ) );
		$niv = sanitize_key( wp_unslash( $_POST['niveau'] ?? '2e' ) );
		update_post_meta( $pid, '_ls_niveau', isset( ls_op_niveaus()[ $niv ] ) ? $niv : '2e' );
		ls_op_terug( array( 'page' => 'ls-portaal', 'dossier' => $pid ), 'Opgeslagen.' );
	}
);

/**
 * Eén dossier: oefeningen, codes, accounts.
 *
 * @param int $pid Dossier.
 */
function ls_op_dossier_detail( $pid ) {
	$terug  = add_query_arg( 'page', 'ls-portaal', admin_url( 'admin.php' ) );
	$taken  = ls_op_taken( $pid );
	$open   = array_map( 'intval', wp_list_pluck( $taken, 'oef' ) );
	$codes  = get_post_meta( $pid, '_ls_code' );
	$used   = get_post_meta( $pid, '_ls_code_gebruikt' );
	$ouders = array_filter( array_map( 'get_userdata', array_filter( array_map( 'intval', (array) get_post_meta( $pid, '_ls_ouders', true ) ) ) ) );
	$lib    = get_posts( array( 'post_type' => 'ls_oefening', 'post_status' => 'publish', 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );
	$groep  = array();
	foreach ( $lib as $o ) {
		$info                                   = ls_op_oefening( $o->ID );
		$groep[ $info['domein'] ?: 'Andere' ][] = $info;
	}
	ksort( $groep );
	$post_url = admin_url( 'admin-post.php' );
	?>
	<p><a href="<?php echo esc_url( $terug ); ?>">← Alle patiënten</a></p>
	<h1><?php echo esc_html( get_the_title( $pid ) ); ?> <span class="ls-op-sub"><?php echo esc_html( get_post_meta( $pid, '_ls_focus', true ) ); ?></span></h1>
	<div class="ls-op-cols">
		<div class="ls-op-col">
			<div class="ls-op-box">
				<h2>Opengezette oefeningen</h2>
				<?php if ( ! $taken ) : ?><p>Nog niets opengezet. Kies hieronder een oefening uit de bibliotheek.</p><?php endif; ?>
				<ul class="ls-op-lib">
				<?php
				foreach ( $taken as $t ) :
					$o = ls_op_oefening( $t['oef'] );
					?>
					<li>
						<span class="ls-op-badge" style="background:<?php echo esc_attr( $o['kleur'] ); ?>"><?php echo esc_html( $o['kort'] ); ?></span>
						<span class="ls-op-libtxt"><strong><?php echo esc_html( $o['titel'] ); ?></strong><br><span class="description"><?php echo esc_html( $o['meta'] ); ?> · <?php echo esc_html( ls_op_resultaat_tekst( $pid, $o['id'] ) ); ?></span></span>
						<form method="post" action="<?php echo esc_url( $post_url ); ?>">
							<?php wp_nonce_field( 'ls_op_taak' ); ?>
							<input type="hidden" name="action" value="ls_op_taak"><input type="hidden" name="pid" value="<?php echo (int) $pid; ?>"><input type="hidden" name="oef" value="<?php echo (int) $o['id']; ?>"><input type="hidden" name="doe" value="weg">
							<button class="button-link ls-op-danger">Sluiten</button>
						</form>
					</li>
				<?php endforeach; ?>
				</ul>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="ls-op-inline">
					<?php wp_nonce_field( 'ls_op_taak' ); ?>
					<input type="hidden" name="action" value="ls_op_taak"><input type="hidden" name="pid" value="<?php echo (int) $pid; ?>"><input type="hidden" name="doe" value="open">
					<label class="screen-reader-text" for="ls-oef">Oefening</label>
					<select id="ls-oef" name="oef" required>
						<option value="">Kies een oefening…</option>
						<?php foreach ( $groep as $dom => $oefs ) : ?>
							<optgroup label="<?php echo esc_attr( $dom ); ?>">
							<?php foreach ( $oefs as $o ) : ?>
								<option value="<?php echo (int) $o['id']; ?>" <?php disabled( in_array( $o['id'], $open, true ) ); ?>><?php echo esc_html( $o['titel'] . ( $o['meta'] ? ' – ' . $o['meta'] : '' ) ); ?></option>
							<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
					<button class="button button-primary">Openzetten</button>
				</form>
				<p class="description"><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ls_oefening' ) ); ?>">Nieuwe oefening in de bibliotheek</a></p>
			</div>

			<div class="ls-op-box">
				<h2>Gegevens</h2>
				<?php ls_op_dossier_form( $pid ); ?>
			</div>
		</div>
		<div class="ls-op-col ls-op-side">
			<div class="ls-op-box">
				<h2>Uitnodigingscode</h2>
				<p>Geef de ouder of patiënt deze code. Daarmee maakt die een account op de website en ziet die de oefeningen. Een code werkt één keer.</p>
				<?php foreach ( $codes as $c ) : ?>
					<p class="ls-op-code"><?php echo esc_html( $c ); ?></p>
				<?php endforeach; ?>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>">
					<?php wp_nonce_field( 'ls_op_code' ); ?>
					<input type="hidden" name="action" value="ls_op_code"><input type="hidden" name="pid" value="<?php echo (int) $pid; ?>">
					<button class="button">Nieuwe code maken</button>
				</form>
				<p class="description">Registreren gaat via <a href="<?php echo esc_url( ls_op_pagina_url( 'registreren' ) ); ?>" target="_blank"><?php echo esc_html( wp_parse_url( ls_op_pagina_url( 'registreren' ), PHP_URL_PATH ) ); ?></a></p>
			</div>
			<div class="ls-op-box">
				<h2>Gekoppelde accounts</h2>
				<?php if ( ! $ouders ) : ?><p>Nog niemand. Dat gebeurt zodra de code gebruikt is.</p><?php endif; ?>
				<ul>
				<?php foreach ( $ouders as $o ) : ?>
					<li><strong><?php echo esc_html( $o->display_name ); ?></strong><br><span class="description"><?php echo esc_html( $o->user_email ); ?><?php echo get_user_meta( $o->ID, '_ls_geblokkeerd', true ) ? ' · geblokkeerd' : ''; ?></span></li>
				<?php endforeach; ?>
				</ul>
				<?php if ( $used ) : ?><p class="description">Gebruikte codes: <?php echo esc_html( implode( ', ', $used ) ); ?></p><?php endif; ?>
			</div>
			<?php if ( current_user_can( 'ls_alle_patienten' ) ) : ?>
			<div class="ls-op-box">
				<h2>Dossier verwijderen</h2>
				<p>Verwijdert het dossier en de oefengeschiedenis. Accounts van ouders blijven bestaan.</p>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" onsubmit="return confirm('Dit dossier definitief verwijderen?');">
					<?php wp_nonce_field( 'ls_op_dossier_weg' ); ?>
					<input type="hidden" name="action" value="ls_op_dossier_weg"><input type="hidden" name="pid" value="<?php echo (int) $pid; ?>">
					<button class="button ls-op-danger">Verwijderen</button>
				</form>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

add_action(
	'admin_post_ls_op_taak',
	function () {
		check_admin_referer( 'ls_op_taak' );
		$pid = absint( $_POST['pid'] ?? 0 );
		$oef = absint( $_POST['oef'] ?? 0 );
		if ( ! ls_op_mag_dossier( $pid ) || ! ls_op_oefening( $oef ) ) {
			wp_die( 'Geen toegang.' );
		}
		if ( 'weg' === ( $_POST['doe'] ?? '' ) ) {
			ls_op_taak_verwijderen( $pid, $oef );
			ls_op_terug( array( 'page' => 'ls-portaal', 'dossier' => $pid ), 'Oefening gesloten.' );
		}
		ls_op_taak_toevoegen( $pid, $oef );
		ls_op_terug( array( 'page' => 'ls-portaal', 'dossier' => $pid ), 'Opengezet: ' . get_the_title( $oef ) . '.' );
	}
);

add_action(
	'admin_post_ls_op_code',
	function () {
		check_admin_referer( 'ls_op_code' );
		$pid = absint( $_POST['pid'] ?? 0 );
		if ( ! ls_op_mag_dossier( $pid ) ) {
			wp_die( 'Geen toegang.' );
		}
		$code = ls_op_code_maken( $pid );
		ls_op_terug( array( 'page' => 'ls-portaal', 'dossier' => $pid ), 'Nieuwe code: ' . $code );
	}
);

add_action(
	'admin_post_ls_op_dossier_weg',
	function () {
		check_admin_referer( 'ls_op_dossier_weg' );
		$pid = absint( $_POST['pid'] ?? 0 );
		if ( ! current_user_can( 'ls_alle_patienten' ) || 'ls_patient' !== get_post_type( $pid ) ) {
			wp_die( 'Geen toegang.' );
		}
		$naam = get_the_title( $pid );
		foreach ( array_filter( array_map( 'intval', (array) get_post_meta( $pid, '_ls_ouders', true ) ) ) as $uid ) {
			$d = array_values( array_diff( array_map( 'intval', (array) get_user_meta( $uid, '_ls_dossiers', true ) ), array( $pid ) ) );
			update_user_meta( $uid, '_ls_dossiers', $d );
		}
		wp_delete_post( $pid, true );
		ls_op_log( 'Dossier ' . $naam . ' verwijderd' );
		ls_op_terug( array( 'page' => 'ls-portaal' ), 'Dossier verwijderd.' );
	}
);

/* ====================================================================
 * Gebruikers en rechten (alleen beheerders)
 * ==================================================================== */

/**
 * Pagina Gebruikers en rechten.
 */
function ls_op_pagina_gebruikers() {
	if ( ! current_user_can( 'ls_gebruikers_beheer' ) ) {
		wp_die( 'Geen toegang.' );
	}
	$post_url = admin_url( 'admin-post.php' );
	$team     = ls_op_personeel();
	$pat      = get_users( array( 'role' => 'sophie_patient', 'orderby' => 'display_name' ) );
	echo '<div class="wrap ls-op-admin">';
	ls_op_admin_melding();
	?>
	<h1>Gebruikers en rechten</h1>
	<p class="ls-op-intro">Alleen beheerders zien deze pagina. Logopedisten zien in het portaal alleen hun eigen patiënten; beheerders zien alles.</p>

	<h2>Praktijkteam</h2>
	<table class="widefat striped ls-op-table">
		<thead><tr><th>Naam</th><th>E-mail</th><th>Rol</th><th>Laatst ingelogd</th><th>Patiënten</th><th></th></tr></thead>
		<tbody>
		<?php
		foreach ( $team as $u ) :
			$is_wp_admin = user_can( $u, 'manage_options' );
			$aantal      = count( ls_op_dossiers_voor( $u->ID, (string) $u->ID ) );
			if ( user_can( $u, 'ls_alle_patienten' ) ) {
				$aantal = count(
					get_posts(
						array(
							'post_type'   => 'ls_patient',
							'post_status' => 'private',
							'meta_key'    => '_ls_owner',
							'meta_value'  => $u->ID,
							'numberposts' => 500,
							'fields'      => 'ids',
						)
					)
				);
			}
			$laatst = get_user_meta( $u->ID, '_ls_laatst', true );
			$blok   = get_user_meta( $u->ID, '_ls_geblokkeerd', true );
			?>
			<tr>
				<td><strong><?php echo esc_html( $u->display_name ); ?></strong><?php echo $blok ? ' <span class="ls-op-pill">geblokkeerd</span>' : ''; ?></td>
				<td><?php echo esc_html( $u->user_email ); ?></td>
				<td>
					<?php if ( $is_wp_admin || get_current_user_id() === $u->ID ) : ?>
						<?php echo esc_html( ls_op_rol_label( $u ) ); ?><?php echo $is_wp_admin ? ' <span class="description">(sitebeheerder)</span>' : ''; ?>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="ls-op-inline">
							<?php wp_nonce_field( 'ls_op_rol' ); ?>
							<input type="hidden" name="action" value="ls_op_rol"><input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
							<select name="rol" onchange="this.form.submit()">
								<option value="sophie_logopedist" <?php selected( in_array( 'sophie_logopedist', $u->roles, true ) ); ?>>Logopedist</option>
								<option value="sophie_beheerder" <?php selected( in_array( 'sophie_beheerder', $u->roles, true ) ); ?>>Beheerder</option>
							</select>
						</form>
					<?php endif; ?>
				</td>
				<td><?php echo $laatst ? esc_html( mysql2date( 'd/m/Y H:i', $laatst ) ) : '–'; ?></td>
				<td><?php echo (int) $aantal; ?></td>
				<td><?php if ( ! $is_wp_admin && get_current_user_id() !== $u->ID ) { ls_op_blokkeer_knop( $u ); } ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Collega toevoegen</h2>
	<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="ls-op-box">
		<?php wp_nonce_field( 'ls_op_collega' ); ?>
		<input type="hidden" name="action" value="ls_op_collega">
		<table class="form-table" role="presentation">
			<tr><th><label for="c-naam">Naam</label></th><td><input id="c-naam" name="naam" class="regular-text" required></td></tr>
			<tr><th><label for="c-mail">E-mailadres</label></th><td><input id="c-mail" name="email" type="email" class="regular-text" required placeholder="naam@logopediesophie.be"><p class="description">Alleen adressen van de praktijk. De collega krijgt een mail om zelf een wachtwoord te kiezen.</p></td></tr>
			<tr><th><label for="c-rol">Rol</label></th><td><select id="c-rol" name="rol"><option value="sophie_logopedist">Logopedist</option><option value="sophie_beheerder">Beheerder</option></select></td></tr>
		</table>
		<?php submit_button( 'Collega uitnodigen', 'primary', 'submit', false ); ?>
	</form>

	<h2>Accounts van patiënten en ouders</h2>
	<table class="widefat striped ls-op-table">
		<thead><tr><th>Naam</th><th>E-mail</th><th>Gekoppeld aan</th><th>Status</th><th></th></tr></thead>
		<tbody>
		<?php if ( ! $pat ) : ?><tr><td colspan="5">Nog geen accounts.</td></tr><?php endif; ?>
		<?php
		foreach ( $pat as $u ) :
			$ds = wp_list_pluck( ls_op_dossiers_van_ouder( $u->ID ), 'post_title' );
			?>
			<tr>
				<td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
				<td><?php echo esc_html( $u->user_email ); ?></td>
				<td><?php echo esc_html( $ds ? implode( ', ', $ds ) : '–' ); ?></td>
				<td><?php echo get_user_meta( $u->ID, '_ls_geblokkeerd', true ) ? '<span class="ls-op-pill">Geblokkeerd</span>' : '<span class="ls-op-pill ok">Actief</span>'; ?></td>
				<td><?php ls_op_blokkeer_knop( $u ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Wie mag wat?</h2>
	<table class="widefat ls-op-table ls-op-rechten">
		<thead><tr><th>Recht</th><th>Patiënt of ouder</th><th>Logopedist</th><th>Beheerder</th></tr></thead>
		<tbody>
		<?php
		$rows = array(
			array( 'Eigen oefeningen zien, afvinken en de Oefenhoek gebruiken', 1, 0, 0 ),
			array( 'Eigen patiënten bekijken en oefeningen openzetten', 0, 1, 1 ),
			array( 'Uitnodigingscodes maken voor eigen patiënten', 0, 1, 1 ),
			array( 'Oefenbibliotheek aanvullen', 0, 1, 1 ),
			array( 'Aanvragen uit de chat en het contactformulier lezen', 0, 1, 1 ),
			array( 'Patiënten van alle logopedisten zien en overdragen', 0, 0, 1 ),
			array( 'Collega\'s toevoegen, rollen wijzigen, accounts blokkeren', 0, 0, 1 ),
			array( 'Volledig logboek en instellingen', 0, 0, 1 ),
		);
		foreach ( $rows as $r ) {
			echo '<tr><td>' . esc_html( $r[0] ) . '</td>';
			for ( $i = 1; $i <= 3; $i++ ) {
				echo '<td>' . ( $r[ $i ] ? '<span class="ls-op-yes" aria-label="ja">✓</span>' : '<span class="ls-op-no" aria-label="nee">–</span>' ) . '</td>';
			}
			echo '</tr>';
		}
		?>
		</tbody>
	</table>
	<p class="description">Tip: zet voor beheerders en logopedisten tweestapsverificatie aan met de plugin <strong>Two Factor</strong> (gratis). Elk personeelslid stelt dat in via Profiel.</p>
	</div>
	<?php
}

/**
 * Knop blokkeren/deblokkeren.
 *
 * @param WP_User $u Gebruiker.
 */
function ls_op_blokkeer_knop( $u ) {
	$blok = get_user_meta( $u->ID, '_ls_geblokkeerd', true );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ls_op_blokkeer' ); ?>
		<input type="hidden" name="action" value="ls_op_blokkeer"><input type="hidden" name="uid" value="<?php echo (int) $u->ID; ?>">
		<button class="button-link<?php echo $blok ? '' : ' ls-op-danger'; ?>"><?php echo $blok ? 'Deblokkeren' : 'Blokkeren'; ?></button>
	</form>
	<?php
}

add_action(
	'admin_post_ls_op_blokkeer',
	function () {
		check_admin_referer( 'ls_op_blokkeer' );
		$uid = absint( $_POST['uid'] ?? 0 );
		$u   = get_userdata( $uid );
		if ( ! current_user_can( 'ls_gebruikers_beheer' ) || ! $u || user_can( $u, 'manage_options' ) || get_current_user_id() === $uid ) {
			wp_die( 'Geen toegang.' );
		}
		if ( get_user_meta( $uid, '_ls_geblokkeerd', true ) ) {
			delete_user_meta( $uid, '_ls_geblokkeerd' );
			ls_op_log( 'Account van ' . $u->display_name . ' gedeblokkeerd' );
			ls_op_terug( array( 'page' => 'ls-gebruikers' ), $u->display_name . ' kan weer inloggen.' );
		}
		update_user_meta( $uid, '_ls_geblokkeerd', 1 );
		if ( class_exists( 'WP_Session_Tokens' ) ) {
			WP_Session_Tokens::get_instance( $uid )->destroy_all();
		}
		ls_op_log( 'Account van ' . $u->display_name . ' geblokkeerd' );
		ls_op_terug( array( 'page' => 'ls-gebruikers' ), $u->display_name . ' is geblokkeerd en uitgelogd.' );
	}
);

add_action(
	'admin_post_ls_op_rol',
	function () {
		check_admin_referer( 'ls_op_rol' );
		$uid = absint( $_POST['uid'] ?? 0 );
		$rol = sanitize_key( wp_unslash( $_POST['rol'] ?? '' ) );
		$u   = get_userdata( $uid );
		if ( ! current_user_can( 'ls_gebruikers_beheer' ) || ! $u || user_can( $u, 'manage_options' ) || get_current_user_id() === $uid || ! in_array( $rol, array( 'sophie_logopedist', 'sophie_beheerder' ), true ) ) {
			wp_die( 'Geen toegang.' );
		}
		$u->set_role( $rol );
		ls_op_log( 'Rol van ' . $u->display_name . ' gewijzigd naar ' . ls_op_rolnamen()[ $rol ] );
		ls_op_terug( array( 'page' => 'ls-gebruikers' ), 'Rol gewijzigd.' );
	}
);

add_action(
	'admin_post_ls_op_collega',
	function () {
		check_admin_referer( 'ls_op_collega' );
		if ( ! current_user_can( 'ls_gebruikers_beheer' ) ) {
			wp_die( 'Geen toegang.' );
		}
		$naam = sanitize_text_field( wp_unslash( $_POST['naam'] ?? '' ) );
		$mail = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$rol  = sanitize_key( wp_unslash( $_POST['rol'] ?? 'sophie_logopedist' ) );
		if ( ! in_array( $rol, array( 'sophie_logopedist', 'sophie_beheerder' ), true ) ) {
			$rol = 'sophie_logopedist';
		}
		if ( ! $naam || ! is_email( $mail ) ) {
			ls_op_terug( array( 'page' => 'ls-gebruikers' ), 'Vul een naam en een geldig e-mailadres in.', true );
		}
		if ( email_exists( $mail ) ) {
			ls_op_terug( array( 'page' => 'ls-gebruikers' ), 'Er bestaat al een account met dit e-mailadres.', true );
		}
		$login = sanitize_user( current( explode( '@', $mail ) ), true );
		while ( username_exists( $login ) ) {
			$login .= wp_rand( 1, 9 );
		}
		$uid = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $mail,
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => $naam,
				'first_name'   => current( explode( ' ', $naam ) ),
				'role'         => $rol,
			)
		);
		if ( is_wp_error( $uid ) ) {
			ls_op_terug( array( 'page' => 'ls-gebruikers' ), $uid->get_error_message(), true );
		}
		wp_new_user_notification( $uid, null, 'user' );
		ls_op_log( 'Collega ' . $naam . ' toegevoegd als ' . ls_op_rolnamen()[ $rol ] );
		ls_op_terug( array( 'page' => 'ls-gebruikers' ), $naam . ' is toegevoegd en krijgt een mail om een wachtwoord te kiezen.' );
	}
);

/* ====================================================================
 * Logboek
 * ==================================================================== */

/**
 * Pagina Logboek.
 */
function ls_op_pagina_logboek() {
	$alles  = current_user_can( 'ls_logboek_alles' );
	$regels = ls_op_logboek_regels( $alles ? null : get_current_user_id() );
	?>
	<div class="wrap ls-op-admin">
		<h1>Logboek</h1>
		<p class="ls-op-intro"><?php echo $alles ? 'Alle acties in het portaal, de nieuwste eerst.' : 'Jouw acties in het portaal, de nieuwste eerst.'; ?></p>
		<table class="widefat striped ls-op-table">
			<thead><tr><th style="width:160px">Wanneer</th><th style="width:220px">Wie</th><th>Wat</th></tr></thead>
			<tbody>
			<?php if ( ! $regels ) : ?><tr><td colspan="3">Nog niets gebeurd.</td></tr><?php endif; ?>
			<?php
			foreach ( $regels as $r ) :
				$u = $r->user_id ? get_userdata( $r->user_id ) : null;
				?>
				<tr>
					<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $r->tijd ) ); ?></td>
					<td><?php echo $u ? esc_html( $u->display_name ) . ' <span class="description">· ' . esc_html( ls_op_rol_label( $u ) ) . '</span>' : 'Website'; ?></td>
					<td><?php echo esc_html( $r->actie ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/* ====================================================================
 * Instellingen
 * ==================================================================== */

/**
 * Pagina Instellingen.
 */
function ls_op_pagina_instellingen() {
	if ( ! current_user_can( 'ls_instellingen' ) ) {
		wp_die( 'Geen toegang.' );
	}
	echo '<div class="wrap ls-op-admin">';
	ls_op_admin_melding();
	?>
	<h1>Instellingen van het portaal</h1>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ls-op-box">
		<?php wp_nonce_field( 'ls_op_instellingen' ); ?>
		<input type="hidden" name="action" value="ls_op_instellingen">
		<table class="form-table" role="presentation">
			<tr><th><label for="o-ontv">Aanvragen mailen naar</label></th><td><input id="o-ontv" name="ontvanger" type="email" class="regular-text" value="<?php echo esc_attr( ls_op_optie( 'ontvanger' ) ); ?>"><p class="description">Aanvragen uit de chat en het contactformulier gaan naar dit adres. Ze staan ook altijd onder Oefenportaal › Aanvragen.</p></td></tr>
			<tr><th><label for="o-term">Antwoordtermijn</label></th><td><input id="o-term" name="antwoordtermijn" class="regular-text" value="<?php echo esc_attr( ls_op_optie( 'antwoordtermijn' ) ); ?>"><p class="description">Komt in de bevestiging: "Sophie neemt binnen … contact op."</p></td></tr>
			<tr><th><label for="o-tel">Telefoonnummer</label></th><td><input id="o-tel" name="telefoon" class="regular-text" value="<?php echo esc_attr( ls_op_optie( 'telefoon' ) ); ?>"></td></tr>
			<tr><th>Chat</th><td><label><input type="checkbox" name="chat_aan" value="1" <?php checked( ls_op_optie( 'chat_aan' ) ); ?>> Chat tonen rechtsonder op de website</label></td></tr>
			<tr><th>Portaalpagina's</th><td>
				<?php
				wp_dropdown_pages( array( 'name' => 'pagina_portaal', 'selected' => (int) get_option( 'ls_op_pagina_portaal' ), 'show_option_none' => '— kies —' ) );
				echo ' <span class="description">Oefenportaal (met [ls_portaal])</span><br><br>';
				wp_dropdown_pages( array( 'name' => 'pagina_registreren', 'selected' => (int) get_option( 'ls_op_pagina_registreren' ), 'show_option_none' => '— kies —' ) );
				echo ' <span class="description">Account aanmaken (met [ls_registreren])</span>';
				?>
			</td></tr>
		</table>
		<?php submit_button( 'Opslaan', 'primary', 'submit', false ); ?>
	</form>
	<div class="ls-op-box">
		<h2>Beveiliging</h2>
		<ul class="ul-disc">
			<li>Zet tweestapsverificatie aan voor elk praktijkaccount (plugin <strong>Two Factor</strong>, daarna per gebruiker in Profiel).</li>
			<li>Patiënten komen nooit in wp-admin; ze zien alleen hun eigen oefeningen.</li>
			<li>Bewaar geen medische gegevens in dossiers of oefeningen: gebruik voornaam en initiaal.</li>
		</ul>
	</div>
	</div>
	<?php
}

add_action(
	'admin_post_ls_op_instellingen',
	function () {
		check_admin_referer( 'ls_op_instellingen' );
		if ( ! current_user_can( 'ls_instellingen' ) ) {
			wp_die( 'Geen toegang.' );
		}
		update_option(
			'ls_op_instellingen',
			array(
				'ontvanger'       => sanitize_email( wp_unslash( $_POST['ontvanger'] ?? '' ) ),
				'antwoordtermijn' => sanitize_text_field( wp_unslash( $_POST['antwoordtermijn'] ?? '' ) ),
				'telefoon'        => sanitize_text_field( wp_unslash( $_POST['telefoon'] ?? '' ) ),
				'chat_aan'        => empty( $_POST['chat_aan'] ) ? 0 : 1,
			)
		);
		update_option( 'ls_op_pagina_portaal', absint( $_POST['pagina_portaal'] ?? 0 ) );
		update_option( 'ls_op_pagina_registreren', absint( $_POST['pagina_registreren'] ?? 0 ) );
		ls_op_log( 'Instellingen van het portaal gewijzigd' );
		ls_op_terug( array( 'page' => 'ls-instellingen' ), 'Instellingen opgeslagen.' );
	}
);
