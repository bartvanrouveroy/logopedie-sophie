<?php
/**
 * Het oefenportaal op de website: [ls_portaal] en [ls_registreren].
 *
 * @package sophie-oefenportaal
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'ls-op', LS_OP_URL . 'assets/portaal.css', array(), LS_OP_VERSION );
	}
);

/**
 * Fout of melding voor deze aanvraag.
 *
 * @param string|null $tekst Zet een tekst; null = lezen.
 * @return string
 */
function ls_op_melding( $tekst = null ) {
	static $m = '';
	if ( null !== $tekst ) {
		$m = $tekst;
	}
	return $m;
}

/* ====================================================================
 * Formulieren verwerken (voor er iets op het scherm komt)
 * ==================================================================== */
add_action(
	'template_redirect',
	function () {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['ls_op_actie'] ) ) {
			return;
		}
		$actie = sanitize_key( wp_unslash( $_POST['ls_op_actie'] ) );
		if ( ! isset( $_POST['_ls_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_ls_nonce'] ), 'ls_op_' . $actie ) ) {
			ls_op_melding( 'De pagina was verlopen. Probeer het nog eens.' );
			return;
		}
		$terug = wp_get_referer() ?: ls_op_pagina_url( 'portaal' );

		if ( 'klaar' === $actie && is_user_logged_in() ) {
			$pid = absint( $_POST['pid'] ?? 0 );
			$oef = absint( $_POST['oef'] ?? 0 );
			if ( in_array( $pid, array_map( 'intval', (array) get_user_meta( get_current_user_id(), '_ls_dossiers', true ) ), true ) ) {
				$open = wp_list_pluck( ls_op_taken( $pid ), 'oef' );
				if ( in_array( $oef, array_map( 'intval', $open ), true ) ) {
					ls_op_markeer( $pid, $oef, ! empty( $_POST['gedaan'] ) );
				}
			}
			wp_safe_redirect( $terug . '#oefeningen' );
			exit;
		}

		if ( 'code' === $actie && is_user_logged_in() ) {
			$code = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
			if ( ls_op_code_gebruiken( $code, get_current_user_id() ) ) {
				wp_safe_redirect( add_query_arg( 'ls_ok', 'code', ls_op_pagina_url( 'portaal' ) ) );
				exit;
			}
			ls_op_melding( 'Deze code klopt niet of werd al gebruikt. Een code ziet eruit als LS-1234.' );
			return;
		}

		if ( 'registreer' === $actie && ! is_user_logged_in() ) {
			if ( ! empty( $_POST['website'] ) ) { // Honeypot.
				return;
			}
			$code  = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
			$naam  = sanitize_text_field( wp_unslash( $_POST['naam'] ?? '' ) );
			$mail  = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
			$pw    = (string) wp_unslash( $_POST['wachtwoord'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$ok    = ! empty( $_POST['toestemming'] );
			$fout  = '';
			if ( ! ls_op_code_zoeken( $code ) ) {
				$fout = 'Deze code klopt niet of werd al gebruikt. Een code ziet eruit als LS-1234. Je krijgt ze van Sophie.';
			} elseif ( strlen( $naam ) < 2 ) {
				$fout = 'Vul je naam in.';
			} elseif ( ! is_email( $mail ) ) {
				$fout = 'Dat e-mailadres lijkt niet te kloppen.';
			} elseif ( email_exists( $mail ) ) {
				$fout = 'Met dit e-mailadres bestaat al een account. Log in en vul de code in bij "Nog een kind toevoegen".';
			} elseif ( strlen( $pw ) < 10 ) {
				$fout = 'Kies een wachtwoord van minstens 10 tekens.';
			} elseif ( ! $ok ) {
				$fout = 'Geef toestemming om de oefenresultaten met de logopedist te delen.';
			}
			if ( $fout ) {
				ls_op_melding( $fout );
				return;
			}
			$login = sanitize_user( current( explode( '@', $mail ) ), true );
			while ( username_exists( $login ) ) {
				$login .= wp_rand( 1, 9 );
			}
			$uid = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_email'   => $mail,
					'user_pass'    => $pw,
					'display_name' => $naam,
					'first_name'   => current( explode( ' ', $naam ) ),
					'role'         => 'sophie_patient',
				)
			);
			if ( is_wp_error( $uid ) ) {
				ls_op_melding( 'Het account kon niet aangemaakt worden. Probeer het later opnieuw of bel de praktijk.' );
				return;
			}
			update_user_meta( $uid, '_ls_toestemming', current_time( 'mysql' ) );
			ls_op_log( 'Account aangemaakt door ' . $naam, $uid );
			ls_op_code_gebruiken( $code, $uid );
			wp_set_current_user( $uid );
			wp_set_auth_cookie( $uid, true );
			wp_safe_redirect( add_query_arg( 'ls_ok', 'welkom', ls_op_pagina_url( 'portaal' ) ) );
			exit;
		}
	}
);

/* ====================================================================
 * [ls_portaal]
 * ==================================================================== */
add_shortcode( 'ls_portaal', 'ls_op_portaal_html' );

/**
 * HTML van het portaal.
 *
 * @return string
 */
function ls_op_portaal_html() {
	if ( is_admin() || wp_is_json_request() ) {
		return '<p>[Oefenportaal]</p>';
	}
	ob_start();
	echo '<div class="ls-op">';
	$melding = ls_op_melding();

	if ( ! is_user_logged_in() ) {
		ls_op_login_html( $melding );
	} elseif ( ls_op_is_personeel() || current_user_can( 'manage_options' ) ) {
		$u = wp_get_current_user();
		?>
		<div class="ls-op-card ls-op-staff">
			<span class="ls-op-badge-role"><?php echo esc_html( ls_op_rol_label( $u ) ); ?></span>
			<h2>Hallo <?php echo esc_html( $u->display_name ); ?></h2>
			<p>Je bent ingelogd met een praktijkaccount. Patiënten, oefeningen en uitnodigingscodes beheer je in het beheer van het oefenportaal.</p>
			<p><a class="ls-op-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=ls-portaal' ) ); ?>">Naar het beheer</a> <a class="ls-op-btn ghost" href="<?php echo esc_url( wp_logout_url( ls_op_pagina_url( 'portaal' ) ) ); ?>">Uitloggen</a></p>
		</div>
		<?php
	} else {
		ls_op_patient_html( $melding );
	}
	echo '</div>';
	return ob_get_clean();
}

/**
 * Inlogscherm.
 *
 * @param string $melding Foutmelding.
 */
function ls_op_login_html( $melding ) {
	$reg = ls_op_pagina_url( 'registreren' );
	?>
	<div class="ls-op-split">
		<div class="ls-op-card">
			<h2>Inloggen</h2>
			<p class="ls-op-muted">Voor patiënten, ouders en de logopedisten van de praktijk.</p>
			<?php if ( $melding ) : ?><p class="ls-op-error" role="alert"><?php echo esc_html( $melding ); ?></p><?php endif; ?>
			<?php
			wp_login_form(
				array(
					'redirect'       => ls_op_pagina_url( 'portaal' ),
					'label_username' => 'E-mailadres of gebruikersnaam',
					'label_password' => 'Wachtwoord',
					'label_remember' => 'Ingelogd blijven op dit toestel',
					'label_log_in'   => 'Inloggen',
				)
			);
			?>
			<p class="ls-op-small"><a href="<?php echo esc_url( wp_lostpassword_url( ls_op_pagina_url( 'portaal' ) ) ); ?>">Wachtwoord vergeten?</a></p>
			<?php ls_op_demo_knoppen(); ?>
		</div>
		<div class="ls-op-card ls-op-soft">
			<h3>Nog geen account?</h3>
			<p>Na de start van de behandeling krijg je van Sophie een uitnodigingscode, bijvoorbeeld <strong>LS-1234</strong>. Daarmee maak je in een minuut een account.</p>
			<p><a class="ls-op-btn" href="<?php echo esc_url( $reg ); ?>">Account aanmaken</a></p>
			<p class="ls-op-small">Logopedisten van de praktijk loggen hier ook in en komen daarna in het beheer.</p>
		</div>
	</div>
	<?php
}

/**
 * Portaal voor een patiënt of ouder.
 *
 * @param string $melding Melding.
 */
function ls_op_patient_html( $melding ) {
	$u        = wp_get_current_user();
	$dossiers = ls_op_dossiers_van_ouder( $u->ID );
	$ok       = sanitize_key( wp_unslash( $_GET['ls_ok'] ?? '' ) );
	?>
	<div class="ls-op-bar">
		<span>Ingelogd als <strong><?php echo esc_html( $u->display_name ); ?></strong></span>
		<a href="<?php echo esc_url( wp_logout_url( ls_op_pagina_url( 'portaal' ) ) ); ?>">Uitloggen</a>
	</div>
	<?php if ( 'welkom' === $ok ) : ?><p class="ls-op-ok" role="status">Welkom! Je account is klaar. Hieronder vind je de oefeningen van Sophie.</p><?php endif; ?>
	<?php if ( 'code' === $ok ) : ?><p class="ls-op-ok" role="status">Gelukt, de code is toegevoegd.</p><?php endif; ?>
	<?php if ( $melding ) : ?><p class="ls-op-error" role="alert"><?php echo esc_html( $melding ); ?></p><?php endif; ?>
	<?php
	if ( ! $dossiers ) {
		echo '<div class="ls-op-card"><h2>Bijna klaar</h2><p>Je account is nog niet gekoppeld aan een behandeling. Vul de code in die je van Sophie kreeg.</p>';
		ls_op_code_form();
		echo '</div>';
		return;
	}
	$sel = absint( $_GET['kind'] ?? 0 );
	$cur = $dossiers[0];
	foreach ( $dossiers as $d ) {
		if ( $d->ID === $sel ) {
			$cur = $d;
		}
	}
	$tab  = 'hoek' === ( $_GET['tab'] ?? '' ) ? 'hoek' : 'sophie';
	$base = remove_query_arg( array( 'ls_ok', 'tab', 'niveau' ) );

	if ( count( $dossiers ) > 1 ) {
		echo '<div class="ls-op-seg" role="tablist" aria-label="Kies wie oefent">';
		foreach ( $dossiers as $d ) {
			printf( '<a role="tab" aria-selected="%s" href="%s">%s</a>', $d->ID === $cur->ID ? 'true' : 'false', esc_url( add_query_arg( 'kind', $d->ID, $base ) ), esc_html( get_post_meta( $d->ID, '_ls_voornaam', true ) ?: $d->post_title ) );
		}
		echo '</div>';
	}
	$voornaam = get_post_meta( $cur->ID, '_ls_voornaam', true ) ?: $cur->post_title;
	?>
	<div class="ls-op-tabs" role="tablist">
		<a role="tab" aria-selected="<?php echo 'sophie' === $tab ? 'true' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( 'kind', $cur->ID, $base ) ); ?>">Oefeningen van Sophie</a>
		<a role="tab" aria-selected="<?php echo 'hoek' === $tab ? 'true' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( array( 'kind' => $cur->ID, 'tab' => 'hoek' ), $base ) ); ?>">Oefenhoek</a>
	</div>
	<?php
	if ( 'hoek' === $tab ) {
		ls_op_oefenhoek_html( $cur );
	} else {
		ls_op_taken_html( $cur, $voornaam );
	}
	?>
	<details class="ls-op-more">
		<summary>Nog een kind toevoegen?</summary>
		<p>Vul de nieuwe code van Sophie in. Daarna kan je wisselen tussen je kinderen.</p>
		<?php ls_op_code_form(); ?>
	</details>
	<?php
}

/**
 * Formulier om een code toe te voegen.
 */
function ls_op_code_form() {
	?>
	<form method="post" class="ls-op-inline">
		<?php wp_nonce_field( 'ls_op_code', '_ls_nonce' ); ?>
		<input type="hidden" name="ls_op_actie" value="code">
		<label class="screen-reader-text" for="ls-code">Uitnodigingscode</label>
		<input id="ls-code" name="code" class="ls-op-field" placeholder="LS-1234" required pattern="[Ll][Ss]-[0-9]{4}" autocomplete="off">
		<button class="ls-op-btn" type="submit">Code toevoegen</button>
	</form>
	<?php
}

/**
 * Week en opengezette oefeningen.
 *
 * @param WP_Post $d        Dossier.
 * @param string  $voornaam Naam.
 */
function ls_op_taken_html( $d, $voornaam ) {
	$taken  = ls_op_taken( $d->ID );
	$gedaan = ls_op_gedaan( $d->ID );
	$today  = current_time( 'Y-m-d' );
	$sessie = array_map( 'intval', (array) get_post_meta( $d->ID, '_ls_sessiedagen', true ) );
	$klaar  = 0;
	foreach ( $taken as $t ) {
		if ( in_array( $today, $gedaan[ $t['oef'] ] ?? array(), true ) ) {
			++$klaar;
		}
	}
	$now   = current_time( 'timestamp' );
	$start = strtotime( 'monday this week', $now );
	$namen = array( 1 => 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo' );
	?>
	<div class="ls-op-head">
		<div>
			<h2>Hallo <?php echo esc_html( $voornaam ); ?>!</h2>
			<p class="ls-op-muted">Dit zette Sophie klaar voor de dagen zonder therapie.</p>
		</div>
		<?php if ( $taken ) : ?>
		<div class="ls-op-progress" aria-label="Vandaag">
			<span><strong><?php echo (int) $klaar; ?> van <?php echo count( $taken ); ?></strong> vandaag klaar</span>
			<span class="ls-op-bar-track"><span style="width:<?php echo (int) round( $klaar / max( 1, count( $taken ) ) * 100 ); ?>%"></span></span>
		</div>
		<?php endif; ?>
	</div>
	<div class="ls-op-days" aria-label="Deze week">
		<?php
		for ( $i = 0; $i < 7; $i++ ) {
			$t   = strtotime( "+{$i} day", $start );
			$ymd = gmdate( 'Y-m-d', $t );
			$dow = $i + 1;
			$any = false;
			foreach ( $gedaan as $dates ) {
				if ( in_array( $ymd, (array) $dates, true ) ) {
					$any = true;
				}
			}
			if ( in_array( $dow, $sessie, true ) ) {
				$cls = 'session';
				$txt = 'Sessie bij Sophie';
			} elseif ( $ymd === $today ) {
				$cls = 'today';
				$txt = $any ? 'Vandaag ✓' : 'Vandaag';
			} elseif ( $any ) {
				$cls = 'done';
				$txt = 'Geoefend';
			} elseif ( 7 === $dow ) {
				$cls = 'rest';
				$txt = 'Rustdag';
			} else {
				$cls = $ymd < $today ? 'rest' : 'open';
				$txt = 'Oefendag';
			}
			if ( $ymd === $today && 'today' !== $cls ) {
				$cls .= ' is-today';
			}
			printf( '<div class="ls-op-day %s"><strong>%s %s</strong><span>%s</span></div>', esc_attr( $cls ), esc_html( $namen[ $dow ] ), esc_html( gmdate( 'j', $t ) ), esc_html( $txt ) );
		}
		?>
	</div>
	<div id="oefeningen" class="ls-op-tasks">
		<?php if ( ! $taken ) : ?>
			<div class="ls-op-card ls-op-soft"><p>Sophie zette nog geen oefeningen klaar. Kijk intussen eens in de <strong>Oefenhoek</strong>.</p></div>
		<?php endif; ?>
		<?php
		foreach ( $taken as $t ) :
			$o  = ls_op_oefening( $t['oef'] );
			$ok = in_array( $today, $gedaan[ $o['id'] ] ?? array(), true );
			?>
			<div class="ls-op-task<?php echo $ok ? ' ok' : ''; ?>">
				<span class="ls-op-badge" style="background:<?php echo esc_attr( $o['kleur'] ); ?>"><?php echo esc_html( $o['kort'] ); ?></span>
				<div class="ls-op-task-txt">
					<strong><?php echo esc_html( $o['titel'] ); ?></strong>
					<span class="ls-op-muted"><?php echo esc_html( $o['meta'] ); ?></span>
					<details>
						<summary>Start oefening</summary>
						<div class="ls-op-uitleg"><?php echo wp_kses_post( $o['uitleg'] ); ?>
							<?php if ( $o['link'] ) : ?><p><a class="ls-op-btn" href="<?php echo esc_url( $o['link'] ); ?>" target="_blank" rel="noopener">Open de oefening</a></p><?php endif; ?>
						</div>
					</details>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'ls_op_klaar', '_ls_nonce' ); ?>
					<input type="hidden" name="ls_op_actie" value="klaar">
					<input type="hidden" name="pid" value="<?php echo (int) $d->ID; ?>">
					<input type="hidden" name="oef" value="<?php echo (int) $o['id']; ?>">
					<input type="hidden" name="gedaan" value="<?php echo $ok ? '' : '1'; ?>">
					<button type="submit" class="ls-op-btn<?php echo $ok ? ' done' : ''; ?>"><?php echo $ok ? 'Klaar ✓' : 'Ik heb geoefend'; ?></button>
				</form>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * De Oefenhoek: vrij oefenen per leerjaar.
 *
 * @param WP_Post $d Dossier.
 */
function ls_op_oefenhoek_html( $d ) {
	$niveaus = ls_op_niveaus();
	$niveau  = sanitize_key( wp_unslash( $_GET['niveau'] ?? ( get_post_meta( $d->ID, '_ls_niveau', true ) ?: '2e' ) ) );
	if ( ! isset( $niveaus[ $niveau ] ) ) {
		$niveau = '2e';
	}
	$ids  = get_posts(
		array(
			'post_type'      => 'ls_oefening',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'meta_key'       => '_ls_oefenhoek',
			'meta_value'     => '1',
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$lijst = array();
	foreach ( $ids as $id ) {
		$o = ls_op_oefening( $id );
		if ( $o && ( ! $o['niveaus'] || in_array( $niveau, $o['niveaus'], true ) ) ) {
			$lijst[ $o['domein'] ?: 'Andere' ][] = $o;
		}
	}
	ksort( $lijst );
	?>
	<div class="ls-op-head"><div><h2>Oefenhoek</h2><p class="ls-op-muted">Vrij oefenen in taal en rekenen, van kleuter tot 6e leerjaar. Kies het leerjaar.</p></div></div>
	<div class="ls-op-levels">
		<?php foreach ( $niveaus as $slug => $label ) : ?>
			<a aria-pressed="<?php echo $slug === $niveau ? 'true' : 'false'; ?>" href="<?php echo esc_url( add_query_arg( array( 'kind' => $d->ID, 'tab' => 'hoek', 'niveau' => $slug ) ) ); ?>"><?php echo esc_html( str_replace( ' leerjaar', ' lj', $label ) ); ?></a>
		<?php endforeach; ?>
	</div>
	<?php if ( ! $lijst ) : ?>
		<div class="ls-op-card ls-op-soft"><p>Voor dit leerjaar staan er nog geen oefeningen in de Oefenhoek.</p></div>
	<?php endif; ?>
	<?php foreach ( $lijst as $domein => $oefs ) : ?>
		<h3 class="ls-op-domein"><?php echo esc_html( $domein ); ?></h3>
		<div class="ls-op-grid">
			<?php foreach ( $oefs as $o ) : ?>
				<details class="ls-op-topic">
					<summary><span class="ls-op-badge" style="background:<?php echo esc_attr( $o['kleur'] ); ?>"><?php echo esc_html( $o['kort'] ); ?></span><strong><?php echo esc_html( $o['titel'] ); ?></strong><span class="ls-op-muted"><?php echo esc_html( $o['meta'] ); ?></span></summary>
					<div class="ls-op-uitleg"><?php echo wp_kses_post( $o['uitleg'] ); ?>
						<?php if ( $o['link'] ) : ?><p><a class="ls-op-btn" href="<?php echo esc_url( $o['link'] ); ?>" target="_blank" rel="noopener">Open de oefening</a></p><?php endif; ?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
	<?php
}

/* ====================================================================
 * [ls_registreren]
 * ==================================================================== */
add_shortcode(
	'ls_registreren',
	function () {
		if ( is_admin() || wp_is_json_request() ) {
			return '<p>[Account aanmaken]</p>';
		}
		if ( is_user_logged_in() ) {
			return '<div class="ls-op"><div class="ls-op-card"><p>Je bent al ingelogd.</p><p><a class="ls-op-btn" href="' . esc_url( ls_op_pagina_url( 'portaal' ) ) . '">Naar het oefenportaal</a></p></div></div>';
		}
		$melding = ls_op_melding();
		$v       = fn( $k ) => esc_attr( sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) );
		ob_start();
		?>
		<div class="ls-op"><div class="ls-op-split">
			<form method="post" class="ls-op-card ls-op-form" novalidate>
				<h2>Account aanmaken</h2>
				<p class="ls-op-muted">Je hebt de uitnodigingscode nodig die je van Sophie kreeg.</p>
				<?php if ( $melding ) : ?><p class="ls-op-error" role="alert"><?php echo esc_html( $melding ); ?></p><?php endif; ?>
				<?php wp_nonce_field( 'ls_op_registreer', '_ls_nonce' ); ?>
				<input type="hidden" name="ls_op_actie" value="registreer">
				<p class="ls-op-hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></p>
				<label>Uitnodigingscode<input class="ls-op-field" name="code" placeholder="LS-1234" required value="<?php echo $v( 'code' ); // phpcs:ignore ?>" autocomplete="off"></label>
				<label>Jouw naam<input class="ls-op-field" name="naam" required autocomplete="name" value="<?php echo $v( 'naam' ); // phpcs:ignore ?>"></label>
				<label>E-mailadres<input class="ls-op-field" type="email" name="email" required autocomplete="email" value="<?php echo $v( 'email' ); // phpcs:ignore ?>"></label>
				<label>Wachtwoord <span class="ls-op-muted">(minstens 10 tekens)</span><input class="ls-op-field" type="password" name="wachtwoord" required minlength="10" autocomplete="new-password"></label>
				<label class="ls-op-check"><input type="checkbox" name="toestemming" value="1" <?php checked( ! empty( $_POST['toestemming'] ) ); ?>> Ik ga akkoord dat de oefenresultaten gedeeld worden met de logopedist, en met het privacybeleid.</label>
				<button class="ls-op-btn" type="submit">Account aanmaken</button>
				<p class="ls-op-small">Al een account? <a href="<?php echo esc_url( ls_op_pagina_url( 'portaal' ) ); ?>">Inloggen</a></p>
			</form>
			<div class="ls-op-card ls-op-soft">
				<h3>Hoe werkt het?</h3>
				<ol>
					<li>Sophie maakt een dossier en geeft je een code.</li>
					<li>Je maakt hier een account met die code.</li>
					<li>Op de dagen zonder therapie zie je welke oefeningen Sophie klaarzette.</li>
					<li>Sophie ziet wanneer er geoefend werd en past de oefeningen aan.</li>
				</ol>
				<p class="ls-op-small">We bewaren alleen wat nodig is om te oefenen. Medische gegevens horen hier niet thuis.</p>
			</div>
		</div></div>
		<?php
		return ob_get_clean();
	}
);

/* ====================================================================
 * Demo: inloggen met één klik als voorbeeldaccount (alleen als de demo-instelling aan staat)
 * ==================================================================== */

/**
 * Knoppen met de voorbeeldaccounts.
 */
function ls_op_demo_knoppen() {
	if ( ! get_option( 'ls_op_demo' ) ) {
		return;
	}
	$users = get_users( array( 'meta_key' => '_ls_demo', 'meta_value' => 1, 'orderby' => 'ID' ) );
	if ( ! $users ) {
		return;
	}
	echo '<div class="ls-op-demo"><p><strong>Voorbeeldsite:</strong> log in als een van deze voorbeeldaccounts.</p><ul>';
	foreach ( $users as $u ) {
		$link = wp_nonce_url( add_query_arg( 'ls_demo_login', $u->ID, ls_op_pagina_url( 'portaal' ) ), 'ls_demo_login' );
		$blok = get_user_meta( $u->ID, '_ls_geblokkeerd', true ) ? ' · geblokkeerd' : '';
		printf( '<li><a href="%s">%s</a> <span class="ls-op-muted">%s%s</span></li>', esc_url( $link ), esc_html( $u->display_name ), esc_html( ls_op_rol_label( $u ) ), esc_html( $blok ) );
	}
	echo '</ul></div>';
}

add_action(
	'template_redirect',
	function () {
		if ( empty( $_GET['ls_demo_login'] ) || ! get_option( 'ls_op_demo' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'ls_demo_login' ) ) {
			return;
		}
		$u = get_userdata( absint( $_GET['ls_demo_login'] ) );
		if ( ! $u || ! get_user_meta( $u->ID, '_ls_demo', true ) ) {
			return;
		}
		if ( get_user_meta( $u->ID, '_ls_geblokkeerd', true ) ) {
			ls_op_melding( 'Dit account is geblokkeerd. Neem contact op met de praktijk.' );
			return;
		}
		wp_set_current_user( $u->ID );
		wp_set_auth_cookie( $u->ID );
		do_action( 'wp_login', $u->user_login, $u );
		$to = apply_filters( 'login_redirect', ls_op_pagina_url( 'portaal' ), '', $u );
		wp_safe_redirect( $to );
		exit;
	},
	5
);
