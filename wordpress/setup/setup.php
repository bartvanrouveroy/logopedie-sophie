<?php
/**
 * Logopedie Sophie – zet een volledige voorbeeldsite klaar in een verse WordPress.
 *
 * Gebruik:
 *   wp eval-file setup.php                     (WP-CLI)
 *   require '/pad/naar/setup.php';             (WordPress Playground, stap runPHP)
 *
 * Vereist: thema "logopedie-sophie", plugins "woocommerce" en "sophie-oefenportaal" actief.
 * Het script maakt pagina's, menu's, artikels, webshop, oefeningen en voorbeeldaccounts.
 * Draai het één keer op een lege site. Op de echte site gebruik je LS_DEMO = false
 * (dan worden geen voorbeeldpatiënten of -accounts aangemaakt).
 *
 * @package logopedie-sophie-setup
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'LS_DEMO' ) ) {
	define( 'LS_DEMO', true );
}
$ls_dir = __DIR__;
require_once $ls_dir . '/blokken.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/post.php';

if ( get_option( 'ls_setup_klaar' ) ) {
	echo "Setup werd al uitgevoerd.\n";
	return;
}

/* ====================================================================
 * 1. Algemene instellingen
 * ==================================================================== */
update_option( 'blogname', 'Logopedie Sophie' );
update_option( 'blogdescription', 'Erkend logopediste in Hasselt (Runkst)' );
update_option( 'timezone_string', 'Europe/Brussels' );
update_option( 'date_format', 'j F Y' );
update_option( 'time_format', 'H:i' );
update_option( 'start_of_week', 1 );
update_option( 'default_comment_status', 'closed' );
update_option( 'default_ping_status', 'closed' );
update_option( 'users_can_register', 0 );
update_option( 'admin_email', 'info@logopediesophie.be' );
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
$wp_rewrite->init();
if ( ! get_current_user_id() ) {
	$ls_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( $ls_admins ? (int) $ls_admins[0] : 1 );
}

// Standaardinhoud van WordPress weg.
foreach ( array( 'hello-world' => 'post', 'sample-page' => 'page', 'refund_returns' => 'page' ) as $slug => $type ) {
	$p = get_page_by_path( $slug, OBJECT, $type );
	if ( $p ) {
		wp_delete_post( $p->ID, true );
	}
}
wp_delete_comment( 1, true );

/* ====================================================================
 * 2. Afbeeldingen in de mediabibliotheek
 * ==================================================================== */
/**
 * Bestand uit de setupmap in de mediabibliotheek zetten.
 *
 * @param string $file Bestandsnaam.
 * @param string $title Titel.
 * @return int
 */
function ls_setup_media( $file, $title ) {
	$src = __DIR__ . '/images/' . $file;
	$up  = wp_upload_dir();
	$dst = trailingslashit( $up['path'] ) . $file;
	copy( $src, $dst );
	$type = wp_check_filetype( $file, array( 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg' ) );
	$id   = wp_insert_attachment(
		array(
			'post_mime_type' => $type['type'],
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$dst
	);
	if ( 'image/svg+xml' !== $type['type'] ) {
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $dst ) );
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	return $id;
}
$img = array(
	'logo'   => ls_setup_media( 'logo.png', 'Logo Logopedie Sophie' ),
	'welkom' => ls_setup_media( 'welkom.png', 'Getekende kat met de tekst: heet je welkom' ),
	'banner' => ls_setup_media( 'banner.svg', 'Getekende logopediste in een zetel oefent klanken met een meisje' ),
);
set_theme_mod( 'custom_logo', $img['logo'] );
update_option( 'site_logo', $img['logo'] );
update_option( 'site_icon', $img['logo'] );

/* ====================================================================
 * 3. Pagina's (eerst leeg, zodat links naar elkaar kunnen verwijzen)
 * ==================================================================== */
$paginas = array(
	'home'          => array( 'Home', 0 ),
	'over-sophie'   => array( 'Over Sophie', 0 ),
	'werkwijze-en-tarieven' => array( 'Werkwijze en tarieven', 0 ),
	'hulpvragen'    => array( 'Hulpvragen', 0 ),
	'dyslexie'      => array( 'Dyslexie', 'hulpvragen' ),
	'dysorthografie' => array( 'Dysorthografie', 'hulpvragen' ),
	'dyscalculie'   => array( 'Dyscalculie', 'hulpvragen' ),
	'taalontwikkeling' => array( 'Taalontwikkeling', 'hulpvragen' ),
	'omft'          => array( 'Oro-myofunctionele therapie', 'hulpvragen' ),
	'afasie'        => array( 'Afasie', 'hulpvragen' ),
	'blind-leren-typen' => array( 'Blind leren typen', 'hulpvragen' ),
	'oefenportaal'  => array( 'Oefenportaal', 0 ),
	'account-aanmaken' => array( 'Account aanmaken', 'oefenportaal' ),
	'contact'       => array( 'Afspraak maken', 0 ),
	'privacybeleid' => array( 'Privacybeleid', 0 ),
	'algemene-voorwaarden' => array( 'Algemene voorwaarden', 0 ),
);
$pid = array();
foreach ( $paginas as $slug => $info ) {
	$bestaand = get_page_by_path( 'privacybeleid' === $slug ? 'privacy-policy' : $slug );
	if ( 'privacybeleid' === $slug && $bestaand ) {
		wp_update_post( array( 'ID' => $bestaand->ID, 'post_name' => 'privacybeleid', 'post_title' => 'Privacybeleid', 'post_status' => 'publish' ) );
		$pid[ $slug ] = $bestaand->ID;
		continue;
	}
	$pid[ $slug ] = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $info[0],
			'post_name'   => $slug,
			'post_parent' => $info[1] ? $pid[ $info[1] ] : 0,
			'menu_order'  => count( $pid ),
		)
	);
}
update_option( 'wp_page_for_privacy_policy', $pid['privacybeleid'] );
$url = fn( $slug ) => get_permalink( $pid[ $slug ] );

/* ====================================================================
 * 4. WooCommerce: instellingen, categorieën en producten
 * ==================================================================== */
$shop_url = home_url( '/webshop/' );
$cat_ids  = array();
if ( class_exists( 'WooCommerce' ) ) {
	if ( class_exists( 'WC_Install' ) ) {
		WC_Install::create_pages();
	}
	$opts = array(
		'woocommerce_store_address'          => 'Paters Adonsstraat 25',
		'woocommerce_store_city'             => 'Hasselt',
		'woocommerce_store_postcode'         => '3500',
		'woocommerce_default_country'        => 'BE',
		'woocommerce_allowed_countries'      => 'specific',
		'woocommerce_specific_allowed_countries' => array( 'BE', 'NL', 'LU' ),
		'woocommerce_ship_to_countries'      => '',
		'woocommerce_currency'               => 'EUR',
		'woocommerce_currency_pos'           => 'left_space',
		'woocommerce_price_thousand_sep'     => '.',
		'woocommerce_price_decimal_sep'      => ',',
		'woocommerce_price_num_decimals'     => 2,
		'woocommerce_weight_unit'            => 'g',
		'woocommerce_dimension_unit'         => 'cm',
		'woocommerce_calc_taxes'             => 'no',
		'woocommerce_enable_guest_checkout'  => 'yes',
		'woocommerce_enable_checkout_login_reminder' => 'yes',
		'woocommerce_enable_reviews'         => 'no',
		'woocommerce_coming_soon'            => 'no',
		'woocommerce_store_pages_only'       => 'no',
		'woocommerce_onboarding_profile'     => array( 'skipped' => true ),
		'woocommerce_task_list_hidden'       => 'yes',
		'woocommerce_show_marketplace_suggestions' => 'no',
		'woocommerce_allow_tracking'         => 'no',
		'woocommerce_demo_store'             => LS_DEMO ? 'yes' : 'no',
		'woocommerce_demo_store_notice'      => 'Voorbeeldwebshop: prijzen zijn voorbeelden en bestellingen worden niet verwerkt.',
		'woocommerce_email_from_name'        => 'Logopedie Sophie',
		'woocommerce_email_from_address'     => 'info@logopediesophie.be',
	);
	foreach ( $opts as $k => $v ) {
		update_option( $k, $v );
	}
	// Webshop-pagina: "Webshop" in plaats van "Shop".
	$shop_id = wc_get_page_id( 'shop' );
	if ( $shop_id > 0 ) {
		wp_update_post( array( 'ID' => $shop_id, 'post_title' => 'Webshop', 'post_name' => 'webshop' ) );
	}
	foreach ( array( 'cart' => array( 'Winkelmand', 'winkelmand' ), 'checkout' => array( 'Afrekenen', 'afrekenen' ), 'myaccount' => array( 'Mijn bestellingen', 'mijn-bestellingen' ) ) as $wc => $t ) {
		$id = wc_get_page_id( $wc );
		if ( $id > 0 ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $t[0], 'post_name' => $t[1] ) );
		}
	}
	update_option( 'woocommerce_terms_page_id', $pid['algemene-voorwaarden'] );
	$refund = get_page_by_path( 'refund_returns' );
	if ( $refund ) {
		wp_delete_post( $refund->ID, true );
	}

	// Betaalmethodes voor de demo (op de echte site: Mollie).
	update_option(
		'woocommerce_bacs_settings',
		array(
			'enabled'      => 'yes',
			'title'        => 'Overschrijving',
			'description'  => 'Je krijgt de rekeninggegevens in de bevestigingsmail. We verzenden zodra de betaling binnen is.',
			'instructions' => 'Schrijf het bedrag over met je bestelnummer als mededeling.',
		)
	);
	update_option(
		'woocommerce_cod_settings',
		array(
			'enabled'            => 'yes',
			'title'              => 'Betalen bij afhalen in de praktijk',
			'description'        => 'Betaal met Payconiq of cash wanneer je je bestelling afhaalt in Runkst.',
			'instructions'       => 'Je krijgt een bericht zodra je bestelling klaarligt.',
			'enable_for_methods' => array(),
			'enable_for_virtual' => 'no',
		)
	);

	// Verzending: België en Nederland, plus afhalen.
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( 'België' );
	$zone->set_zone_order( 1 );
	$zone->add_location( 'BE', 'country' );
	$zone->save();
	$i = $zone->add_shipping_method( 'flat_rate' );
	update_option( 'woocommerce_flat_rate_' . $i . '_settings', array( 'title' => 'Verzending met bpost', 'tax_status' => 'none', 'cost' => '4.95' ) );
	$i = $zone->add_shipping_method( 'free_shipping' );
	update_option( 'woocommerce_free_shipping_' . $i . '_settings', array( 'title' => 'Gratis verzending vanaf € 50', 'requires' => 'min_amount', 'min_amount' => '50' ) );
	$i = $zone->add_shipping_method( 'local_pickup' );
	update_option( 'woocommerce_local_pickup_' . $i . '_settings', array( 'title' => 'Afhalen in de praktijk (Runkst)', 'tax_status' => 'none', 'cost' => '0' ) );
	$zone2 = new WC_Shipping_Zone();
	$zone2->set_zone_name( 'Nederland en Luxemburg' );
	$zone2->set_zone_order( 2 );
	$zone2->add_location( 'NL', 'country' );
	$zone2->add_location( 'LU', 'country' );
	$zone2->save();
	$i = $zone2->add_shipping_method( 'flat_rate' );
	update_option( 'woocommerce_flat_rate_' . $i . '_settings', array( 'title' => 'Verzending', 'tax_status' => 'none', 'cost' => '8.95' ) );

	// Categorieën.
	$cats = array(
		'logo'     => array( 'Logopediemateriaal', 'Articulatie, taal en mondmotoriek.' ),
		'oefen'    => array( 'Oefenmateriaal', 'Werkboekjes en bundels om thuis te oefenen.' ),
		'spel'     => array( 'Educatief speelgoed', 'Spellen en puzzels die leren leuk maken.' ),
		'schrijf'  => array( 'Schrijfwaren', 'Potloden, pengrepen en schriften.' ),
		'motoriek' => array( 'Fijne motoriek', 'Knijpen, rijgen en kneden.' ),
		'bundel'   => array( 'Boekjes en bundels van Sophie', 'Door Sophie gemaakt, op bestelling gedrukt en thuis geleverd.' ),
	);
	$volgorde = 0;
	foreach ( $cats as $k => $c ) {
		$t              = wp_insert_term( $c[0], 'product_cat', array( 'description' => $c[1] ) );
		$cat_ids[ $k ] = is_wp_error( $t ) ? (int) $t->get_error_data() : $t['term_id'];
		update_term_meta( $cat_ids[ $k ], 'order', $volgorde++ );
	}
	$uncat = get_term_by( 'slug', 'uncategorized', 'product_cat' );
	if ( $uncat ) {
		wp_update_term( $uncat->term_id, 'product_cat', array( 'name' => 'Overige', 'slug' => 'overige' ) );
	}

	$pod = '<p class="ls-pod-note">Gedrukt op bestelling: dit boekje wordt voor jou gedrukt en rechtstreeks thuis geleverd. Reken op 5 tot 8 werkdagen.</p>';
	$producten = array(
		array( 'spiegel', 'logo', 'Articulatiespiegel met handvat', 'Onbreekbare spiegel van 20 cm. Je kind ziet zelf hoe de mond een klank vormt.', '14.95' ),
		array( 'blaas', 'logo', 'Blaasoefeningen-set', 'Fluitjes, pluimpjes en rietjes om de mondspieren speels te trainen.', '9.95' ),
		array( 'kauw', 'logo', 'Kauwtube voor mondmotoriek', 'Medisch siliconen, in twee hardheden. Voor oefeningen bij oro-myofunctionele therapie.', '12.50' ),
		array( 'platen', 'logo', 'Vertelplaten: dagelijkse routines', '24 platen op A4 om woordenschat en zinsbouw te oefenen.', '24.95', 'Nieuw' ),
		array( 'tafels', 'oefen', 'Rekenbundel tafels 1 tot 10', 'Flitskaarten en werkbladen om de tafels stap voor stap in te oefenen.', '11.95' ),
		array( 'memory', 'spel', 'Memory tweelingklanken', '64 kaarten met ei, ij, au, ou en ui. Vanaf 6 jaar.', '16.95' ),
		array( 'lotto', 'spel', 'Lottospel voorzetsels', 'Zes borden, voor 2 tot 6 spelers. Leert op, onder, naast en achter.', '19.95', 'Nieuw' ),
		array( 'rijm', 'spel', 'Rijmpuzzels', '30 tweedelige puzzels voor kleuters.', '13.95' ),
		array( 'zand', 'spel', 'Zandloper 1, 3 en 5 minuten', 'Set van drie. Handig om korte oefenmomenten af te bakenen.', '8.95' ),
		array( 'potlood', 'schrijf', 'Driekantig potlood, dik', 'Set van 6, HB. Geeft vanzelf een goede pengreep.', '6.95' ),
		array( 'greep', 'schrijf', 'Pengreep links en rechts', 'Set van 4 maten.', '7.95' ),
		array( 'schrift', 'schrijf', 'Schrijfschrift met hulplijnen', 'A5, met drie lijnhoogtes.', '3.95' ),
		array( 'lineaal', 'schrijf', 'Leeslineaal met venster', 'Gekleurd filter, set van 3. Helpt om de regel te volgen.', '9.95', 'Populair' ),
		array( 'kneed', 'motoriek', 'Therapiekneedgum', 'Vier weerstanden om de handspieren te versterken.', '11.95' ),
		array( 'rijg', 'motoriek', 'Rijgkaarten met veters', '12 kaarten.', '10.95' ),
		array( 'pincet', 'motoriek', 'Pincetspel met balletjes', 'Kleuren sorteren met een pincet.', '14.95' ),
		array( 'sz', 'bundel', 'Klankkaarten /s/ en /z/', '120 kaarten om de s en de z te oefenen, door Sophie gemaakt.' . $pod, '15.95', 'Door Sophie' ),
		array( 'eiij', 'bundel', 'Spellingbundel ei/ij en au/ou', '48 werkbladen, door Sophie gemaakt.' . $pod, '12.95', 'Door Sophie' ),
		array( 'herfst', 'bundel', 'Herfstbundel taal en lezen', '32 pagina\'s met lees- en taalopdrachten in herfstsfeer.' . $pod, '9.95', 'Door Sophie' ),
		array( 'beloning', 'bundel', 'Beloningskaarten', 'Zes thema\'s om oefenen te belonen.' . $pod, '6.95', 'Door Sophie' ),
	);
	foreach ( $producten as $p ) {
		$prod = new WC_Product_Simple();
		$prod->set_name( $p[2] );
		$prod->set_slug( sanitize_title( $p[2] ) );
		$prod->set_regular_price( $p[4] );
		$prod->set_short_description( '<p>' . wp_strip_all_tags( preg_replace( '/<p class="ls-pod-note">.*<\/p>/', '', $p[3] ) ) . '</p>' );
		$prod->set_description( '<p>' . $p[3] . '</p>' );
		$prod->set_category_ids( array( $cat_ids[ $p[1] ] ) );
		$prod->set_sku( 'LS-' . strtoupper( $p[0] ) );
		$prod->set_image_id( ls_setup_media( 'prod-' . $p[0] . '.png', $p[2] ) );
		$prod->set_featured( ! empty( $p[5] ) );
		$prod->set_manage_stock( 'bundel' !== $p[1] );
		if ( 'bundel' !== $p[1] ) {
			$prod->set_stock_quantity( 10 );
		}
		$prod->save();
		if ( 'bundel' === $p[1] ) {
			update_post_meta( $prod->get_id(), '_ls_pod', 1 );
		}
	}

	// AVI-leesboekjes: variabel product (niveau × uitvoering), print-on-demand.
	$niveaus = array( 'Start', 'M3', 'E3', 'M4', 'E4', 'M5', 'E5', 'M6', 'E6', 'M7', 'E7', 'Plus' );
	$uitv    = array( 'Pakket van 5 boekjes', 'Boekje 1', 'Boekje 2', 'Boekje 3', 'Boekje 4', 'Boekje 5' );
	$a1      = new WC_Product_Attribute();
	$a1->set_name( 'AVI-niveau' );
	$a1->set_options( $niveaus );
	$a1->set_visible( true );
	$a1->set_variation( true );
	$a2 = new WC_Product_Attribute();
	$a2->set_name( 'Uitvoering' );
	$a2->set_options( $uitv );
	$a2->set_visible( true );
	$a2->set_variation( true );
	$avi = new WC_Product_Variable();
	$avi->set_name( 'AVI-leesboekjes, Start tot Plus' );
	$avi->set_slug( 'avi-leesboekjes' );
	$avi->set_short_description( '<p>Sophie\'s eigen leesboekjes op 12 AVI-niveaus, van Start tot Plus. Per pakket van 5 of los. Gedrukt op bestelling en thuis geleverd.</p>' );
	$avi->set_description(
		'<p>Korte, grappige verhalen op het juiste leesniveau, door Sophie geschreven voor de kinderen in haar praktijk. Elk niveau heeft 5 boekjes. Kies een pakket of één boekje.</p>'
		. '<ul><li>A5-formaat, geniet, met kleurenomslag</li><li>AVI Start tot AVI Plus, van begin 1e leerjaar tot vanaf het 6e leerjaar</li><li>Ook te gebruiken in het oefenportaal</li></ul>'
		. '<p class="ls-pod-note">Gedrukt op bestelling: de boekjes worden voor jou gedrukt en rechtstreeks thuis geleverd. Reken op 5 tot 8 werkdagen. Geen downloads.</p>'
		. '<p><strong>Welk niveau?</strong> Start: begin 1e leerjaar · M3: midden 1e leerjaar · E3: eind 1e leerjaar · M4: midden 2e leerjaar · E4: eind 2e leerjaar · M5: midden 3e leerjaar · E5: eind 3e leerjaar · M6: midden 4e leerjaar · E6: eind 4e leerjaar · M7: midden 5e leerjaar · E7: eind 5e leerjaar · Plus: vanaf 6e leerjaar.</p>'
	);
	$avi->set_attributes( array( $a1, $a2 ) );
	$avi->set_category_ids( array( $cat_ids['bundel'] ) );
	$avi->set_sku( 'LS-AVI' );
	$avi->set_featured( true );
	$avi->set_image_id( ls_setup_media( 'prod-avi.png', 'AVI-leesboekjes van Sophie' ) );
	$avi->set_default_attributes( array( 'avi-niveau' => 'M3', 'uitvoering' => 'Pakket van 5 boekjes' ) );
	$avi_id = $avi->save();
	update_post_meta( $avi_id, '_ls_pod', 1 );
	foreach ( $niveaus as $n ) {
		foreach ( $uitv as $u ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $avi_id );
			$v->set_attributes( array( 'avi-niveau' => $n, 'uitvoering' => $u ) );
			$v->set_regular_price( 0 === strpos( $u, 'Pakket' ) ? '19.95' : '4.95' );
			$v->set_sku( 'LS-AVI-' . strtoupper( $n ) . '-' . ( 0 === strpos( $u, 'Pakket' ) ? 'P5' : 'B' . substr( $u, -1 ) ) );
			$v->set_manage_stock( false );
			$v->save();
		}
	}
	WC_Product_Variable::sync( $avi_id );
}

/* ====================================================================
 * 5. Artikels voor ouders
 * ==================================================================== */
$noot     = lsb_p( '<em>Concepttekst, na te lezen door Sophie.</em>', array( 'className' => 'ls-concept' ) );
$artikels = array(
	array(
		'Taal',
		'Wanneer ga je met je kleuter naar de logopedist?',
		'Praat je kleuter minder dan leeftijdsgenootjes of is hij moeilijk te verstaan? Deze signalen zijn een reden om eens te laten kijken.',
		lsb_p( 'Elk kind ontwikkelt in zijn eigen tempo. Toch zijn er signalen waarbij het goed is om een logopedist te laten meekijken. Hoe vroeger je erbij bent, hoe makkelijker het vaak gaat.' )
		. lsb_h( 2, 'Signalen' )
		. lsb_list( array( 'Je kind zegt weinig woorden of maakt nauwelijks zinnetjes.', 'Vreemden verstaan je kind moeilijk.', 'Je kind begrijpt eenvoudige opdrachten niet goed.', 'De juf of het CLB maakt zich zorgen over de taal.' ), array( 'className' => 'is-style-ls-checks' ) )
		. lsb_h( 2, 'Wat kan je zelf doen?' )
		. lsb_p( 'Praat veel met je kind over wat jullie samen doen, lees elke dag voor en geef je kind de tijd om te antwoorden. Verbeter niet, maar herhaal de zin op de juiste manier.' )
		. lsb_p( 'Twijfel je? Bel gerust even. Samen bekijken we of een onderzoek zinvol is.' ),
	),
	array(
		'Lezen',
		'Dyslexie: wat kan je thuis doen?',
		'Lezen kost je kind veel moeite? Met korte, haalbare oefenmomenten en veel aanmoediging help je het meest.',
		lsb_p( 'Kinderen met dyslexie moeten harder werken om te lezen en te spellen. Thuis oefenen helpt, als het kort en leuk blijft.' )
		. lsb_h( 2, 'Vijf tips' )
		. lsb_list( array( 'Oefen elke dag kort, liever 10 minuten dan een uur.', 'Kies boekjes op het juiste AVI-niveau, niet te moeilijk.', 'Lees om beurten: jij een zin, je kind een zin.', 'Gebruik een leeslineaal om de regel te volgen.', 'Prijs de inzet, niet alleen het resultaat.' ), array( 'ordered' => true ) )
		. lsb_p( 'In het oefenportaal zet Sophie oefeningen klaar die aansluiten bij de therapie.' ),
	),
	array(
		'Praktisch',
		'Terugbetaling van logopedie: zo werkt het',
		'Met een voorschrift van de huisarts komt het ziekenfonds voor veel hulpvragen tussen. Zo verloopt dat.',
		lsb_p( 'Voor de meeste hulpvragen voorziet het RIZIV een tussenkomst via je ziekenfonds. Daarvoor zijn een paar stappen nodig.' )
		. lsb_list( array( 'Je huisarts schrijft een voorschrift voor een logopedisch onderzoek.', 'Sophie doet het onderzoek en schrijft een bilan.', 'Een arts of specialist keurt het bilan goed.', 'Het ziekenfonds keurt de behandeling goed; daarna start de terugbetaling.' ), array( 'ordered' => true ) )
		. lsb_p( 'Of jouw situatie in aanmerking komt, hangt af van de hulpvraag en soms van de leeftijd of testresultaten. [Voorwaarden aan te vullen door Sophie]' ),
	),
	array(
		'Lezen',
		'Wat betekenen de AVI-niveaus?',
		'AVI Start, M3, E4… Wat betekenen die letters en cijfers, en hoe kies je het juiste leesboekje?',
		lsb_p( 'AVI-niveaus geven aan hoe moeilijk een tekst is om technisch te lezen. De letter staat voor het moment in het schooljaar: M is midden, E is eind. Het cijfer staat voor de groep in Nederland; in Vlaanderen trek je er twee af voor het leerjaar.' )
		. lsb_list( array( 'AVI Start: begin 1e leerjaar', 'AVI M3 en E3: midden en eind 1e leerjaar', 'AVI M4 en E4: 2e leerjaar', 'AVI M5 tot E7: 3e tot 5e leerjaar', 'AVI Plus: vanaf het 6e leerjaar' ) )
		. lsb_p( 'Een goed boekje lees je kind vlot, met hooguit een paar fouten per bladzijde. Is het te moeilijk, ga dan een niveau terug.' ),
	),
	array(
		'Rekenen',
		'Tafels oefenen zonder tranen',
		'Tafels blijven moeilijk? Met spelletjes, ritme en kleine stapjes gaat het vlotter.',
		lsb_p( 'De tafels moeten op den duur automatisch gaan. Dat lukt het best met korte, regelmatige oefenmomenten.' )
		. lsb_list( array( 'Begin met de makkelijke tafels: 1, 2, 5 en 10.', 'Oefen één tafel tegelijk, met flitskaarten of een spelletje.', 'Gebruik ritme: tik of spring op de maat.', 'Herhaal oude tafels kort voor je een nieuwe start.' ), array( 'className' => 'is-style-ls-checks' ) ),
	),
	array(
		'Mond',
		'Duimzuigen en speen: wanneer stoppen?',
		'Een speen of duim geeft troost, maar te lang kan het de mond en de spraak beïnvloeden. Zo pak je het aan.',
		lsb_p( 'Duimzuigen en een speen zijn heel normaal bij jonge kinderen. Duurt het lang, dan kan het invloed hebben op de stand van de tanden, de tong en de uitspraak.' )
		. lsb_list( array( 'Bouw de speen af naar alleen slapen.', 'Maak er samen een afscheid van, bijvoorbeeld met de speenfee.', 'Beloon kleine stappen.', 'Merk je dat de tong tussen de tanden komt? Laat het dan nakijken.' ), array( 'className' => 'is-style-ls-checks' ) ),
	),
);
$cat_post_ids = array();
foreach ( $artikels as $n => $a ) {
	if ( ! isset( $cat_post_ids[ $a[0] ] ) ) {
		$t                     = wp_insert_term( $a[0], 'category' );
		$cat_post_ids[ $a[0] ] = is_wp_error( $t ) ? (int) $t->get_error_data() : $t['term_id'];
	}
	wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_title'    => $a[1],
			'post_excerpt'  => $a[2],
			'post_content'  => $a[3] . $noot,
			'post_category' => array( $cat_post_ids[ $a[0] ] ),
			'post_date'     => gmdate( 'Y-m-d H:i:s', time() - ( $n + 1 ) * 6 * DAY_IN_SECONDS ),
		)
	);
}
$uncat = get_category_by_slug( 'uncategorized' );
if ( $uncat ) {
	wp_update_term( $uncat->term_id, 'category', array( 'name' => 'Algemeen', 'slug' => 'algemeen' ) );
}

/* ====================================================================
 * 6. Inhoud van de pagina's
 * ==================================================================== */
$sticker = '<img src="' . esc_url( wp_get_attachment_url( $img['logo'] ) ) . '" alt="">Logopedie Sophie · Runkst, Hasselt';
$tel     = '<a href="tel:+32472220162">0472 22 01 62</a>';

$hulp_chips = lsb_buttons(
	array(
		array( 'Dyslexie', $url( 'dyslexie' ), 'chip' ),
		array( 'Dysorthografie', $url( 'dysorthografie' ), 'chip' ),
		array( 'Dyscalculie', $url( 'dyscalculie' ), 'chip' ),
		array( 'Taalontwikkeling', $url( 'taalontwikkeling' ), 'chip' ),
		array( 'Afasie', $url( 'afasie' ), 'chip' ),
		array( 'Oro-myofunctionele therapie', $url( 'omft' ), 'chip' ),
		array( 'Blind leren typen (Typ10)', $url( 'blind-leren-typen' ), 'chip-dark' ),
	),
	array( 'className' => 'ls-chips' )
);

$cta = lsb_section(
	lsb_cols(
		array(
			array( lsb_h( 2, 'Twijfel je of logopedie nodig is?' ) . lsb_p( 'Bel even naar <strong>' . $tel . '</strong>. Samen bekijken we het.', array( 'textColor' => 'muted' ) ), '70%' ),
			array( lsb_buttons( array( array( 'Afspraak maken', $url( 'contact' ) ) ), array( 'className' => 'ls-right' ) ), '30%' ),
		),
		array( 'verticalAlignment' => 'center' )
	),
	array( 'className' => 'ls-cta' )
);

$inhoud = array();

// --- Home ---
$inhoud['home'] =
	lsb_section(
		lsb_cols(
			array(
				array(
					lsb_p( $sticker, array( 'className' => 'ls-sticker' ) )
					. lsb_h( 1, 'Spelend leren <em>praten</em>, lezen en schrijven' )
					. lsb_p( 'Voor kinderen én volwassenen. Met geduld, een flinke dosis humor en een warme, huiselijke sfeer.', array( 'className' => 'ls-lead' ) )
					. lsb_buttons( array( array( 'Afspraak maken', $url( 'contact' ) ), array( 'Hoe werkt het?', $url( 'werkwijze-en-tarieven' ), 'outline' ) ) ),
					'45%',
				),
				array( lsb_img( $img['banner'], 'Getekende logopediste in een zetel oefent klanken met een meisje op een krukje, met letterblokken en spraakballonnen' ), '55%' ),
			),
			array( 'verticalAlignment' => 'center' )
		),
		array( 'className' => 'ls-banner' )
	)
	. lsb_section( lsb_quote( array( 'Ik ben er nog niet.<br>Maar ik ben dichterbij dan gisteren.' ), array( 'className' => 'ls-quote' ) ), array( 'className' => 'ls-pt-0' ) )
	. lsb_section(
		lsb_cols(
			array(
				array(
					lsb_p( 'Erkend logopediste in Hasselt', array( 'className' => 'ls-eyebrow' ) )
					. lsb_h( 2, 'Logopedie in Runkst, voor kinderen én volwassenen', array( 'className' => 'ls-h-plain' ) )
					. lsb_p( 'Hulp bij dyslexie, dyscalculie, taalontwikkeling, afasie en mondgewoonten. In een huiselijke praktijk waar je kind zich snel thuis voelt.', array( 'className' => 'ls-lead' ) )
					. lsb_buttons( array( array( 'Aanmelden', $url( 'contact' ) ), array( 'Hoe verloopt een traject?', $url( 'werkwijze-en-tarieven' ), 'outline' ) ) )
					. lsb_list( array( 'RIZIV-erkend', 'Beperkte wachttijd', 'Overleg met school en CLB' ), array( 'className' => 'ls-ticks' ) ),
					'60%',
				),
				array(
					lsb_group( lsb_h( 2, 'Welkom bij Sophie' ) . lsb_img( $img['welkom'], 'Getekende kat met de tekst: heet je welkom' ), array( 'className' => 'ls-welkom', 'layout' => array( 'type' => 'flex', 'flexWrap' => 'nowrap' ) ) )
					. lsb_p( 'Sophie werkt vanuit haar eigen huis, in een rustige en gemoedelijke sfeer waar kinderen zich snel op hun gemak voelen.', array( 'textColor' => 'muted' ) ),
					'40%',
				),
			),
			array( 'verticalAlignment' => 'bottom' )
		)
	)
	. lsb_section(
		lsb_cols(
			array(
				array( lsb_h( 2, 'Waarmee kan Sophie helpen?' ), '70%' ),
				array( lsb_p( '<a href="' . esc_url( $url( 'hulpvragen' ) ) . '">Alle hulpvragen →</a>', array( 'className' => 'ls-more ls-right' ) ), '30%' ),
			),
			array( 'verticalAlignment' => 'bottom' )
		) . $hulp_chips,
		array( 'className' => 'ls-band', 'backgroundColor' => 'surface' )
	)
	. lsb_section(
		lsb_cols(
			array(
				array(
					lsb_h( 2, 'Een praktijk aan huis' )
					. lsb_p( 'Sophie werkt vanuit haar eigen huis in Runkst. Geen wachtzaal vol formulieren, wel een warme, huiselijke plek, flexibele uren en altijd tijd voor een babbel.', array( 'textColor' => 'muted' ) )
					. lsb_p( '<a href="' . esc_url( $url( 'over-sophie' ) ) . '">Maak kennis met Sophie →</a>', array( 'className' => 'ls-more' ) ),
				),
				array(
					lsb_group(
						lsb_p( '<strong>In 4 stappen naar een behandeling</strong>', array( 'fontSize' => 'large' ) )
						. lsb_list( array( 'Voorschrift van je huisarts', 'Onderzoek en bilan bij Sophie', 'Goedkeuring door arts of specialist', 'Behandeling, met oefeningen voor thuis' ), array( 'ordered' => true, 'textColor' => 'muted' ) )
						. lsb_p( '<a href="' . esc_url( $url( 'werkwijze-en-tarieven' ) ) . '">Werkwijze en tarieven →</a>', array( 'className' => 'ls-more' ) ),
						array( 'className' => 'ls-card', 'backgroundColor' => 'surface' )
					),
				),
			),
			array( 'verticalAlignment' => 'center' )
		)
	)
	. lsb_section(
		lsb_cols(
			array(
				array(
					lsb_group(
						lsb_p( 'Webshop', array( 'className' => 'ls-eyebrow ls-eyebrow-sun' ) )
						. lsb_h( 2, 'Materiaal dat Sophie zelf gebruikt' )
						. lsb_p( 'Logopediemateriaal, oefenbundels, educatief speelgoed en schrijfwaren. Met Sophie\'s eigen AVI-leesboekjes van Start tot Plus, gedrukt en thuis geleverd.' )
						. lsb_p( '<a href="' . esc_url( $shop_url ) . '"><strong>Naar de webshop →</strong></a>' ),
						array( 'className' => 'ls-promo', 'backgroundColor' => 'deep', 'textColor' => 'base' )
					),
					'60%',
				),
				array(
					lsb_group(
						lsb_p( 'Voor patiënten', array( 'className' => 'ls-eyebrow ls-eyebrow-sun' ) )
						. lsb_h( 2, 'Oefenportaal en Oefenhoek' )
						. lsb_p( 'De oefeningen van Sophie, plus vrij oefenen in taal en rekenen van kleuter tot 6e leerjaar.' )
						. lsb_p( '<a href="' . esc_url( $url( 'oefenportaal' ) ) . '"><strong>Inloggen →</strong></a>' ),
						array( 'className' => 'ls-promo', 'backgroundColor' => 'accent', 'textColor' => 'base' )
					),
					'40%',
				),
			)
		),
		array( 'className' => 'ls-band', 'backgroundColor' => 'surface' )
	)
	. $cta;

// --- Over Sophie ---
$inhoud['over-sophie'] =
	lsb_section(
		lsb_cols(
			array(
				array(
					lsb_h( 2, 'Hallo, ik ben Sophie', array( 'className' => 'ls-h-plain' ) )
					. lsb_p( 'Mijn praktijk zit in mijn eigen huis in Runkst. Kinderen voelen zich hier snel thuis: geen kille behandelkamer, maar een gezellige plek waar we rustig de tijd nemen. Logopedie mag nooit aanvoelen als falen. Het is een stap vooruit.', array( 'className' => 'ls-lead' ) )
					. lsb_p( 'Voor ik mijn praktijk in Hasselt startte, deed ik al heel wat ervaring op in de sector. Die ervaring zet ik elke dag in, samen met wat ik leer uit bijscholingen.' )
					. lsb_p( 'Erkend logopediste · RIZIV-nr. [NUMMER] · [Lid VVL]', array( 'textColor' => 'muted', 'fontSize' => 'small' ) ),
					'58%',
				),
				array( lsb_group( lsb_p( '[Foto: Sophie in de praktijk]<br><em>Vervang dit blok door een foto: klik erop en kies Vervangen.</em>' ), array( 'className' => 'ls-photo-ph', 'backgroundColor' => 'mist' ) ), '42%' ),
			),
			array( 'verticalAlignment' => 'center' )
		)
	)
	. lsb_section(
		lsb_h( 2, 'Waarom kiezen voor Sophie?' )
		. lsb_cols(
			array(
				array( lsb_group( lsb_h( 3, 'Professioneel' ) . lsb_p( 'Altijd mee met nieuwe methodes, en in contact met school, huisarts, specialist en CLB.', array( 'textColor' => 'muted' ) ), array( 'className' => 'ls-card', 'backgroundColor' => 'base' ) ) ),
				array( lsb_group( lsb_h( 3, 'Huiselijk' ) . lsb_p( 'Een warme praktijk aan huis waar kinderen zich goed voelen.', array( 'textColor' => 'muted' ) ), array( 'className' => 'ls-card', 'backgroundColor' => 'base' ) ) ),
				array( lsb_group( lsb_h( 3, 'Ervaren' ) . lsb_p( 'Jarenlange ervaring in de sector, ingezet voor jouw behandeling.', array( 'textColor' => 'muted' ) ), array( 'className' => 'ls-card', 'backgroundColor' => 'base' ) ) ),
				array( lsb_group( lsb_h( 3, 'Flexibel' ) . lsb_p( 'Een solide planning met een vast ritme, en zo weinig mogelijk wachttijd.', array( 'textColor' => 'muted' ) ), array( 'className' => 'ls-card', 'backgroundColor' => 'base' ) ) ),
			)
		),
		array( 'className' => 'ls-band', 'backgroundColor' => 'surface' )
	)
	. lsb_section(
		lsb_h( 2, 'Recente bijscholingen' )
		. lsb_list( array( '[Bijscholing, organisator, jaar]', '[Bijscholing, organisator, jaar]', '[Bijscholing, organisator, jaar]' ), array( 'className' => 'is-style-ls-checks' ) )
	)
	. $cta;

// --- Werkwijze en tarieven ---
$stap = fn( $n, $t, $p ) => array( lsb_group( lsb_p( (string) $n, array( 'className' => 'ls-stepnum' ) ) . lsb_h( 3, $t ) . lsb_p( $p, array( 'textColor' => 'muted' ) ), array( 'className' => 'ls-card', 'backgroundColor' => 'surface' ) ) );
$inhoud['werkwijze-en-tarieven'] =
	lsb_section(
		lsb_p( 'Van eerste telefoontje tot behandeling, zonder verrassingen.', array( 'className' => 'ls-lead' ) )
		. lsb_cols(
			array(
				$stap( 1, 'Voorschrift van de huisarts', 'Vraag je huisarts een voorschrift voor een logopedisch onderzoek.' ),
				$stap( 2, 'Onderzoek en bilan', 'Sophie onderzoekt de hulpvraag en stelt een behandeling voor in een bilan.' ),
				$stap( 3, 'Goedkeuring', 'De huisarts of specialist keurt het bilan goed, zodat de terugbetaling kan starten.' ),
				$stap( 4, 'Behandeling', 'Vaste sessies in de praktijk. Tussen de sessies oefen je online in je eigen portaal.' ),
			),
			array( 'className' => 'ls-steps' )
		)
	)
	. lsb_section(
		lsb_cols(
			array(
				array(
					lsb_h( 2, 'Tarieven en terugbetaling' )
					. lsb_p( 'Voor de meeste hulpvragen voorziet het RIZIV een tussenkomst via je ziekenfonds. Of jouw situatie in aanmerking komt, bekijkt Sophie samen met jou na het onderzoek.', array( 'textColor' => 'muted' ) )
					. lsb_table(
						array(
							array( 'Onderzoek (bilan)', '[TARIEF]' ),
							array( 'Sessie 30 minuten', '[TARIEF]' ),
							array( 'Sessie 60 minuten', '[TARIEF]' ),
							array( 'Typ10-cursus (10 lessen)', '[TARIEF]' ),
							array( 'Online oefenportaal', 'Inbegrepen' ),
						),
						array( 'className' => 'ls-prices' )
					)
					. lsb_p( 'Annuleren kan kosteloos tot [24/48] uur vooraf. [Geconventioneerd / niet-geconventioneerd]', array( 'fontSize' => 'small', 'textColor' => 'muted' ) ),
					'55%',
				),
				array(
					lsb_h( 2, 'Veelgestelde vragen' )
					. lsb_details( 'Heb ik een voorschrift nodig?', 'Voor terugbetaling wel: je huisarts schrijft een voorschrift voor een logopedisch onderzoek.' )
					. lsb_details( 'Is er een wachtlijst?', 'Sophie houdt de wachttijd zo kort mogelijk. Bel voor de actuele situatie. [Actuele wachttijd]' )
					. lsb_details( 'Mag ik de sessie bijwonen?', 'Ja. Meekijken helpt om thuis op dezelfde manier verder te oefenen.' )
					. lsb_details( 'Hoe werkt het oefenportaal?', 'Na de start van de behandeling krijg je een uitnodigingscode om een account aan te maken. Sophie zet oefeningen klaar die passen bij de therapie, en in de Oefenhoek kan je vrij verder oefenen.' )
					. lsb_details( 'Werkt Sophie ook met volwassenen?', 'Ja, onder meer bij afasie na een beroerte of hersenletsel.' ),
					'45%',
				),
			)
		),
		array( 'className' => 'ls-band', 'backgroundColor' => 'surface' )
	)
	. $cta;

// --- Hulpvragen ---
$kaart = fn( $slug, $t, $p, $link ) => array( lsb_group( lsb_h( 3, $t ) . lsb_p( $p, array( 'textColor' => 'muted' ) ) . lsb_p( '<a href="' . esc_url( $url( $slug ) ) . '">' . $link . ' →</a>', array( 'className' => 'ls-more' ) ), array( 'className' => 'ls-card', 'backgroundColor' => 'surface' ) ) );
$inhoud['hulpvragen'] =
	lsb_section(
		lsb_p( 'Kies een hulpvraag voor signalen, aanpak en terugbetaling. Twijfel je welke past? Bel gerust.', array( 'className' => 'ls-lead' ) )
		. lsb_h( 3, 'Leren lezen, spellen en rekenen', array( 'className' => 'ls-eyebrow' ) )
		. lsb_cols(
			array(
				$kaart( 'dyslexie', 'Dyslexie', 'Moeizaam en traag lezen, ondanks oefenen.', 'Meer over dyslexie' ),
				$kaart( 'dysorthografie', 'Dysorthografie', 'Hardnekkige spellingfouten, ook bij gekende woorden.', 'Meer over dysorthografie' ),
				$kaart( 'dyscalculie', 'Dyscalculie', 'Rekenen, tafels en getalbegrip blijven moeilijk.', 'Meer over dyscalculie' ),
			)
		)
		. lsb_h( 3, 'Taal, spraak en mond', array( 'className' => 'ls-eyebrow' ) )
		. lsb_cols(
			array(
				$kaart( 'taalontwikkeling', 'Taalontwikkeling', 'Je kind praat later, minder of moeilijker verstaanbaar.', 'Meer over taalontwikkeling' ),
				$kaart( 'omft', 'Oro-myofunctionele therapie', 'Tongpersen, mondademen, duimzuigen, afwijkend slikken.', 'Meer over OMFT' ),
				$kaart( 'afasie', 'Afasie', 'Taalproblemen na een beroerte of hersenletsel.', 'Meer over afasie' ),
			)
		)
		. lsb_h( 3, 'Extra in de praktijk', array( 'className' => 'ls-eyebrow' ) )
		. lsb_cols(
			array(
				$kaart( 'blind-leren-typen', 'Blind leren typen', 'Typ10 in 10 lessen, ook therapeutisch bij leerstoornissen.', 'Bekijk de cursus' ),
				array( lsb_group( lsb_h( 3, 'Staat je vraag er niet bij?' ) . lsb_p( 'Neem contact op. Sophie verwijst door als een collega beter past.', array( 'textColor' => 'muted' ) ) . lsb_p( '<a href="' . esc_url( $url( 'contact' ) ) . '">Contact opnemen →</a>', array( 'className' => 'ls-more' ) ), array( 'className' => 'ls-card', 'backgroundColor' => 'mist' ) ) ),
				array( '' ),
			)
		)
	)
	. lsb_section(
		lsb_group( lsb_h( 2, 'Artikels voor ouders' ) . lsb_p( 'Praktische uitleg om thuis mee aan de slag te gaan.', array( 'textColor' => 'muted' ) ), array( 'className' => 'ls-anchor', 'anchor' => 'artikels' ) )
		. lsb_artikels( 6 ),
		array( 'className' => 'ls-band', 'backgroundColor' => 'surface' )
	)
	. $cta;

/**
 * Pagina voor één hulpvraag.
 *
 * @param string   $intro    Inleiding.
 * @param string[] $signalen Signalen.
 * @param string   $aanpak   Aanpak (HTML-paragrafen).
 * @param string   $terug    Terugbetaling.
 * @param array    $faq      Vragen.
 * @param string   $wat      Naam voor de oproep.
 * @param string   $contact  Contact-URL.
 * @param string   $shop     Webshop-URL.
 * @return string
 */
function ls_setup_hulpvraag( $intro, $signalen, $aanpak, $terug, $faq, $wat, $contact, $shop ) {
	$f = '';
	foreach ( $faq as $q ) {
		$f .= lsb_details( $q, '[Antwoord door Sophie]' );
	}
	return lsb_section(
		lsb_cols(
			array(
				array(
					lsb_p( $intro, array( 'className' => 'ls-lead' ) )
					. lsb_h( 2, 'Signalen om op te letten' )
					. lsb_list( $signalen, array( 'className' => 'is-style-ls-checks' ) )
					. lsb_h( 2, 'Hoe pakt Sophie het aan?' )
					. $aanpak
					. lsb_h( 2, 'Terugbetaling' )
					. lsb_p( $terug )
					. ( $f ? lsb_h( 2, 'Vragen over ' . strtolower( $wat ) ) . $f : '' ),
					'64%',
				),
				array(
					lsb_group(
						lsb_h( 3, 'Vermoed je ' . strtolower( $wat ) . '?' )
						. lsb_p( 'Vraag een voorschrift bij je huisarts en meld je aan.', array( 'textColor' => 'muted' ) )
						. lsb_p( '<strong><a href="tel:+32472220162">0472 22 01 62</a></strong>' )
						. lsb_buttons( array( array( 'Aanmelden', $contact ) ) ),
						array( 'className' => 'ls-card ls-sticky-card', 'backgroundColor' => 'surface' )
					)
					. lsb_group(
						lsb_p( 'Webshop', array( 'className' => 'ls-eyebrow' ) )
						. lsb_p( '<strong>Materiaal dat Sophie zelf gebruikt</strong>' )
						. lsb_p( '<a href="' . esc_url( $shop ) . '">Naar de webshop →</a>', array( 'className' => 'ls-more' ) ),
						array( 'className' => 'ls-card', 'backgroundColor' => 'accent-soft' )
					),
					'36%',
				),
			)
		)
	);
}
$c = $url( 'contact' );
$inhoud['dyslexie']         = ls_setup_hulpvraag(
	'Leest je kind traag en met veel fouten, ook al wordt er thuis en op school flink geoefend? Sophie onderzoekt waar het lezen vastloopt en begeleidt je kind stap voor stap.',
	array( 'Lezen blijft moeizaam en spellend, ook na het 1e leerjaar.', 'Letters of klanken worden verwisseld, zoals b en d.', 'Je kind raadt woorden op basis van de eerste letter.', 'Lezen kost zoveel energie dat de inhoud niet blijft hangen.', 'Dyslexie komt al voor in de familie.' ),
	lsb_p( 'Na een grondig onderzoek volgt een behandeling op maat: klank-tekenkoppeling, technisch lezen en leesvlotheid, telkens met materiaal dat je kind leuk vindt. Sophie overlegt met de juf en het CLB, zodat school en thuis dezelfde aanpak volgen.' ) . lsb_p( 'Tussen de sessies oefent je kind verder in het oefenportaal, met AVI-verhalen op het juiste niveau.' ),
	'Voor dyslexie kan het RIZIV tussenkomen als aan bepaalde voorwaarden is voldaan. [Voorwaarden en leeftijdsgrenzen invullen door Sophie]. Na het onderzoek weet je meteen of jouw kind in aanmerking komt.',
	array( 'Vanaf welke leeftijd kan dyslexie vastgesteld worden?', 'Hoe lang duurt een behandeling?', 'Gaat dyslexie ooit over?' ),
	'Dyslexie',
	$c,
	$shop_url
);
$inhoud['dysorthografie']   = ls_setup_hulpvraag(
	'Maakt je kind veel spellingfouten, ook bij woorden die het al lang kent? Sophie zoekt uit waar het misloopt en werkt aan vaste strategieën.',
	array( 'Gekende woorden worden telkens anders geschreven.', 'Regels worden gekend maar niet toegepast.', 'Dictees lukken veel slechter dan de oefeningen.', 'Schrijven kost veel tijd en moeite.' ),
	lsb_p( 'Sophie werkt met duidelijke spellingregels, visuele steun en veel herhaling in korte stappen. In het oefenportaal staan spellingoefeningen klaar die aansluiten bij wat in de sessie geoefend werd.' ),
	'[Voorwaarden voor terugbetaling invullen door Sophie]',
	array( 'Wat is het verschil met dyslexie?', 'Hoe kan ik thuis helpen?' ),
	'Dysorthografie',
	$c,
	$shop_url
);
$inhoud['dyscalculie']      = ls_setup_hulpvraag(
	'Blijven rekenen, tafels en getalbegrip moeilijk, ondanks extra oefenen? Sophie brengt in kaart wat al lukt en bouwt daarop verder.',
	array( 'Je kind telt nog op de vingers waar klasgenootjes uit het hoofd rekenen.', 'Tafels worden telkens weer vergeten.', 'Klok kijken en met geld rekenen lukken moeilijk.', 'Rekenen geeft veel spanning of tranen.' ),
	lsb_p( 'De behandeling vertrekt van concreet materiaal en gaat stap voor stap naar uit het hoofd rekenen. Met spelletjes, flitskaarten en oefeningen in de Oefenhoek wordt het weer haalbaar.' ),
	'[Voorwaarden voor terugbetaling invullen door Sophie]',
	array( 'Vanaf wanneer spreek je van dyscalculie?', 'Hoe oefen ik de tafels thuis?' ),
	'Dyscalculie',
	$c,
	$shop_url
);
$inhoud['taalontwikkeling'] = ls_setup_hulpvraag(
	'Praat je kind later, minder of moeilijker verstaanbaar dan leeftijdsgenootjes? Hoe vroeger je erbij bent, hoe beter.',
	array( 'Je kind zegt weinig woorden of maakt korte zinnetjes.', 'Anderen verstaan je kind moeilijk.', 'Je kind begrijpt opdrachten niet goed.', 'Vertellen over wat er gebeurde lukt nog niet.' ),
	lsb_p( 'Spelenderwijs werkt Sophie aan woordenschat, zinsbouw en uitspraak. Ouders krijgen tips en oefeningen voor thuis, zodat taal deel wordt van elke dag.' ),
	'[Voorwaarden voor terugbetaling invullen door Sophie]',
	array( 'Wanneer moet mijn kind welke woorden kennen?', 'Helpt tweetaligheid of maakt het het moeilijker?' ),
	'Een taalprobleem',
	$c,
	$shop_url
);
$inhoud['omft']             = ls_setup_hulpvraag(
	'Duwt je kind de tong tegen of tussen de tanden, ademt het vaak door de mond of zuigt het nog op de duim? Met oro-myofunctionele therapie (OMFT) leert je kind de mond anders gebruiken.',
	array( 'De tong komt tussen de tanden bij spreken of slikken.', 'Je kind ademt vaak met de mond open.', 'Duimzuigen of een speen duurt lang.', 'De tandarts of orthodontist raadt OMFT aan.' ),
	lsb_p( 'Met korte, dagelijkse oefeningen trainen we de mond- en tongspieren. In het portaal staan oefenvideo\'s klaar, zodat je thuis ziet hoe het moet.' ),
	'[Voorwaarden voor terugbetaling invullen door Sophie]',
	array( 'Hoe lang duurt een OMFT-behandeling?', 'Werkt Sophie samen met de orthodontist?' ),
	'OMFT',
	$c,
	$shop_url
);
$inhoud['afasie']           = ls_setup_hulpvraag(
	'Na een beroerte of hersenletsel kan praten, begrijpen, lezen of schrijven moeilijk worden. Sophie begeleidt volwassenen en hun familie met geduld en op maat.',
	array( 'Woorden niet meer vinden.', 'Zinnen moeilijk begrijpen.', 'Lezen of schrijven lukt niet meer zoals vroeger.', 'Gesprekken kosten veel energie.' ),
	lsb_p( 'De therapie sluit aan bij het dagelijks leven: boodschappen, gesprekken, hobby\'s. Familie leert hoe ze het best communiceren. Tussen de sessies staan benoem- en leesoefeningen klaar in het portaal.' ),
	'[Voorwaarden voor terugbetaling invullen door Sophie]',
	array( 'Kan Sophie aan huis komen?', 'Hoe lang na een beroerte start je best?' ),
	'Afasie',
	$c,
	$shop_url
);
$inhoud['blind-leren-typen'] = ls_setup_hulpvraag(
	'Blind leren typen met Typ10 in 10 lessen. Handig voor elk kind, en een echte hulp bij dyslexie of dysorthografie: typen ontlast het schrijven.',
	array( 'Schrijven met de hand kost veel moeite.', 'Je kind gebruikt de laptop op school.', 'Je kind wil vlotter werken op de computer.' ),
	lsb_p( 'In 10 lessen leert je kind blind typen, met thuisoefeningen tussen de lessen. Ook therapeutisch inzetbaar bij leerstoornissen. [Data, groepsgrootte en tarief invullen]' ),
	'De Typ10-cursus wordt niet terugbetaald door het RIZIV. Sommige ziekenfondsen geven een tussenkomst. [Na te kijken door Sophie]',
	array(),
	'Interesse',
	$c,
	$shop_url
);

// --- Oefenportaal ---
$inhoud['oefenportaal'] = lsb_section(
	lsb_p( 'Hier vind je de oefeningen die Sophie voor jou of je kind klaarzette, plus de Oefenhoek om vrij te oefenen van kleuter tot 6e leerjaar.', array( 'className' => 'ls-lead' ) )
	. lsb_sc( '[ls_portaal]' )
);
$inhoud['account-aanmaken'] = lsb_section( lsb_sc( '[ls_registreren]' ) );

// --- Contact ---
$inhoud['contact'] = lsb_section(
	lsb_cols(
		array(
			array(
				lsb_p( 'Vul het formulier in of bel. Sophie neemt binnen [2 werkdagen] contact op.', array( 'className' => 'ls-lead' ) )
				. lsb_sc( '[ls_contactformulier]' ),
				'60%',
			),
			array(
				lsb_group(
					lsb_p( '<strong>Logopedie Sophie</strong><br>Paters Adonsstraat 25<br>3500 Hasselt (Runkst)' )
					. lsb_p( '<a href="tel:+32472220162">0472 22 01 62</a><br><a href="mailto:info@logopediesophie.be">info@logopediesophie.be</a>' )
					. lsb_p( '<a href="https://www.google.com/maps/search/?api=1&query=Paters+Adonsstraat+25+3500+Hasselt">Open in Google Maps →</a>', array( 'className' => 'ls-more' ) ),
					array( 'className' => 'ls-card', 'backgroundColor' => 'surface' )
				)
				. lsb_group( lsb_p( '<strong>Liever chatten?</strong> Klik rechtsonder op de chat. Je vraag komt meteen bij Sophie terecht.' ), array( 'className' => 'ls-card', 'backgroundColor' => 'accent-soft' ) ),
				'40%',
			),
		)
	)
);

// --- Juridisch ---
$inhoud['privacybeleid'] = lsb_section(
	lsb_p( '[Sjabloon – laat dit nakijken en vul aan. Op de echte site helpt de plugin Complianz hierbij.]', array( 'className' => 'ls-concept' ) )
	. lsb_h( 2, 'Wie zijn we?' ) . lsb_p( 'Logopedie Sophie, Sophie Houben, Paters Adonsstraat 25, 3500 Hasselt. Ondernemingsnummer [KBO]. Contact: info@logopediesophie.be.' )
	. lsb_h( 2, 'Welke gegevens verwerken we?' )
	. lsb_list( array( 'Aanvragen via het contactformulier en de chat: naam, telefoon, e-mail, hulpvraag en toelichting.', 'Accounts in het oefenportaal: naam, e-mail, gekoppeld dossier en wanneer er geoefend werd.', 'Bestellingen in de webshop: naam, adres, e-mail en bestelling.' ) )
	. lsb_h( 2, 'Waarom?' ) . lsb_p( 'Om contact op te nemen, de behandeling te ondersteunen en bestellingen te leveren. We gebruiken je gegevens niet voor reclame en verkopen ze nooit.' )
	. lsb_h( 2, 'Hoe lang?' ) . lsb_p( '[Bewaartermijnen invullen]' )
	. lsb_h( 2, 'Jouw rechten' ) . lsb_p( 'Je mag je gegevens inkijken, laten verbeteren of laten verwijderen. Mail naar info@logopediesophie.be. Klachten kan je richten aan de Gegevensbeschermingsautoriteit.' )
);
$inhoud['algemene-voorwaarden'] = lsb_section(
	lsb_p( '[Sjabloon – laat dit nakijken en vul aan.]', array( 'className' => 'ls-concept' ) )
	. lsb_h( 2, 'Webshop' ) . lsb_p( 'Verkoper: Logopedie Sophie, Paters Adonsstraat 25, 3500 Hasselt, [KBO], [btw-statuut].' )
	. lsb_h( 2, 'Gedrukt op bestelling' ) . lsb_p( 'Boekjes en bundels van Sophie worden pas gedrukt na je bestelling en rechtstreeks door de drukker geleverd. Reken op 5 tot 8 werkdagen. Omdat ze voor jou gemaakt worden, kan je ze niet ruilen, behalve bij een drukfout of beschadiging.' )
	. lsb_h( 2, 'Herroepingsrecht' ) . lsb_p( 'Voor ander materiaal heb je 14 dagen bedenktijd na ontvangst. [Verder aan te vullen]' )
	. lsb_h( 2, 'Verzending' ) . lsb_p( 'België: € 4,95, gratis vanaf € 50. Nederland en Luxemburg: € 8,95. Afhalen in de praktijk in Runkst is gratis.' )
);

foreach ( $inhoud as $slug => $html ) {
	wp_update_post( array( 'ID' => $pid[ $slug ], 'post_content' => $html ) );
}
foreach ( array( 'home', 'oefenportaal', 'account-aanmaken' ) as $slug ) {
	// Home en het portaal zonder grote paginakop.
	if ( 'home' === $slug ) {
		update_post_meta( $pid[ $slug ], '_wp_page_template', 'page-no-title' );
	}
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $pid['home'] );
update_option( 'ls_op_pagina_portaal', $pid['oefenportaal'] );
update_option( 'ls_op_pagina_registreren', $pid['account-aanmaken'] );

/* ====================================================================
 * 7. Menu's
 * ==================================================================== */
/**
 * Navigatielink naar een pagina.
 *
 * @param int    $id    Pagina.
 * @param string $label Tekst.
 * @param string $class Extra klasse.
 * @return string
 */
function ls_setup_navlink( $id, $label, $class = '' ) {
	$a = array(
		'label' => $label,
		'type'  => 'page',
		'id'    => (int) $id,
		'url'   => get_permalink( $id ),
		'kind'  => 'post-type',
	);
	if ( $class ) {
		$a['className'] = $class;
	}
	return '<!-- wp:navigation-link ' . wp_json_encode( $a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
}
$shop_page = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
$hoofdmenu = ls_setup_navlink( $pid['home'], 'Home' )
	. ls_setup_navlink( $pid['over-sophie'], 'Over Sophie' )
	. ls_setup_navlink( $pid['werkwijze-en-tarieven'], 'Werkwijze' )
	. ls_setup_navlink( $pid['hulpvragen'], 'Hulpvragen' )
	. '<!-- wp:navigation-submenu ' . wp_json_encode( array( 'label' => 'Oefenportaal', 'type' => 'page', 'id' => $pid['oefenportaal'], 'url' => get_permalink( $pid['oefenportaal'] ), 'kind' => 'post-type' ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' -->'
	. ls_setup_navlink( $pid['oefenportaal'], 'Inloggen' )
	. ls_setup_navlink( $pid['account-aanmaken'], 'Account aanmaken' )
	. '<!-- /wp:navigation-submenu -->'
	. ( $shop_page > 0 ? ls_setup_navlink( $shop_page, 'Webshop' ) : '' )
	. ls_setup_navlink( $pid['contact'], 'Afspraak maken', 'ls-nav-btn' );
$voetmenu = ls_setup_navlink( $pid['hulpvragen'], 'Hulpvragen' )
	. ls_setup_navlink( $pid['werkwijze-en-tarieven'], 'Werkwijze en tarieven' )
	. ( $shop_page > 0 ? ls_setup_navlink( $shop_page, 'Webshop' ) : '' )
	. ls_setup_navlink( $pid['oefenportaal'], 'Oefenportaal' )
	. ls_setup_navlink( $pid['contact'], 'Contact' );
$juridisch = ls_setup_navlink( $pid['privacybeleid'], 'Privacybeleid' )
	. ls_setup_navlink( $pid['algemene-voorwaarden'], 'Algemene voorwaarden' );
foreach ( array( 'ls_nav_main' => array( 'Hoofdmenu', $hoofdmenu ), 'ls_nav_footer' => array( 'Voetmenu', $voetmenu ), 'ls_nav_legal' => array( 'Juridisch', $juridisch ) ) as $opt => $m ) {
	$id = wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => $m[0], 'post_content' => $m[1] ) );
	update_option( $opt, $id );
}

/* ====================================================================
 * 8. Oefenportaal: domeinen, oefeningen, voorbeeldaccounts
 * ==================================================================== */
$dom = array();
foreach ( array( 'Lezen', 'Spelling', 'Taal', 'Rekenen', 'Klok en kalender', 'Mond (OMFT)', 'Afasie', 'Klanken en rijmen' ) as $d ) {
	$t          = wp_insert_term( $d, 'ls_domein' );
	$dom[ $d ] = is_wp_error( $t ) ? (int) $t->get_error_data() : $t['term_id'];
}
$oefeningen = array(
	// slug, titel, domein, kort, kleur, info, duur, oefenhoek, niveaus, uitleg.
	array( 'lezen', 'Verhaal lezen: De verloren pet', 'Lezen', 'Lezen', 'blauw', 'AVI M3 · hardop lezen', '10 min', 1, array( '1e', '2e' ), 'Lees het verhaal hardop voor aan iemand thuis. Wijs met je vinger of met de leeslineaal mee. Lees moeilijke woorden een tweede keer.' ),
	array( 'lezen2', 'Verhaal lezen: Het bos in', 'Lezen', 'Lezen', 'blauw', 'AVI E3 · hardop lezen', '10 min', 1, array( '2e' ), 'Lees het verhaal hardop. Vertel daarna in drie zinnen wat er gebeurde.' ),
	array( 'eiij', 'Memory met ei- en ij-woorden', 'Spelling', 'ei/ij', 'zon', 'Spelling', '8 min', 1, array( '2e', '3e' ), 'Draai telkens twee kaartjes om. Zeg het woord en zeg of het met ei of ij geschreven wordt.' ),
	array( 'dt', 'Werkwoorden met d of t', 'Spelling', 'd/t', 'zon', 'Spelling', '10 min', 1, array( '4e', '5e', '6e' ), 'Gebruik de ezelsbrug: vervang het werkwoord door "werken". Hoor je een t? Schrijf dan een t.' ),
	array( 'tafel3', 'Flitskaarten tafel van 3', 'Rekenen', '×3', 'groen', 'Rekenen', '5 min', 1, array( '2e', '3e' ), 'Iemand toont een kaart, jij zegt zo snel mogelijk het antwoord. Leg de moeilijke kaarten apart en oefen ze nog eens.' ),
	array( 'splitsen', 'Splitsen tot 10', 'Rekenen', '+−', 'groen', 'Rekenen', '8 min', 1, array( 'k', '1e' ), 'Neem 10 blokjes. Verdeel ze in twee hoopjes en zeg de splitsing: 10 is 6 en 4.' ),
	array( 'klok', 'Klok kijken: halve uren', 'Klok en kalender', '◷', 'lila', 'Oefenhoek', '8 min', 1, array( '2e', '3e' ), 'Zet de wijzers van een speelgoedklok op een half uur. Zeg hoe laat het is.' ),
	array( 'maanden', 'Dagen en maanden op volgorde', 'Klok en kalender', 'Mrt', 'zand', 'Oefenhoek', '5 min', 1, array( '1e', '2e' ), 'Leg de kaartjes van de maanden op de juiste volgorde. Welke maand is je verjaardag?' ),
	array( 'rijm', 'Rijmen met plaatjes', 'Klanken en rijmen', '♪', 'zon', 'Auditief', '5 min', 1, array( 'k', '1e' ), 'Zoek telkens twee plaatjes die rijmen: muis en huis, boot en groot.' ),
	array( 'woorden', 'Woordenschat: in de keuken', 'Taal', 'Taal', 'perzik', 'Woordenschat', '10 min', 1, array( 'k', '1e', '2e' ), 'Benoem tien dingen in de keuken. Maak met drie woorden een zin.' ),
	array( 'omft', 'Tongpunt-oefeningen', 'Mond (OMFT)', 'Mond', 'perzik', 'OMFT', '5 min', 0, array(), 'Doe de oefeningen voor de spiegel: tongpunt op de plek achter de boventanden, tien tellen houden, vijf keer.' ),
	array( 'benoem', 'Benoemen: keukenvoorwerpen', 'Afasie', 'Taal', 'blauw', 'Afasie', '10 min', 0, array(), 'Benoem de voorwerpen op de foto\'s. Lukt een woord niet? Zeg waarvoor je het gebruikt.' ),
);
$oef = array();
foreach ( $oefeningen as $o ) {
	$id = wp_insert_post( array( 'post_type' => 'ls_oefening', 'post_status' => 'publish', 'post_title' => $o[1], 'post_content' => lsb_p( $o[9] ) ) );
	wp_set_object_terms( $id, array( $dom[ $o[2] ] ), 'ls_domein' );
	update_post_meta( $id, '_ls_kort', $o[3] );
	update_post_meta( $id, '_ls_kleur', $o[4] );
	update_post_meta( $id, '_ls_info', $o[5] );
	update_post_meta( $id, '_ls_duur', $o[6] );
	update_post_meta( $id, '_ls_oefenhoek', $o[7] );
	update_post_meta( $id, '_ls_niveaus', $o[8] );
	update_post_meta( $id, '_ls_gelogd', 1 );
	$oef[ $o[0] ] = $id;
}

update_option(
	'ls_op_instellingen',
	array(
		'ontvanger'       => 'info@logopediesophie.be',
		'antwoordtermijn' => '2 werkdagen',
		'telefoon'        => '0472 22 01 62',
		'chat_aan'        => 1,
	)
);

if ( LS_DEMO ) {
	update_option( 'ls_op_demo', 1 );
	/**
	 * Voorbeeldaccount maken (met een willekeurig wachtwoord; inloggen gaat via de demoknoppen).
	 *
	 * @param string $login Login.
	 * @param string $naam  Naam.
	 * @param string $mail  E-mail.
	 * @param string $rol   Rol.
	 * @return int
	 */
	function ls_setup_user( $login, $naam, $mail, $rol ) {
		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $mail,
				'user_pass'    => wp_generate_password( 20 ),
				'display_name' => $naam,
				'first_name'   => current( explode( ' ', $naam ) ),
				'role'         => $rol,
			)
		);
		update_user_meta( $id, '_ls_demo', 1 );
		return $id;
	}
	$sophie = ls_setup_user( 'sophie', 'Sophie Houben', 'sophie@logopediesophie.be', 'sophie_beheerder' );
	$lies   = ls_setup_user( 'lies', 'Lies V. (voorbeeld)', 'lies@logopediesophie.be', 'sophie_logopedist' );
	$an     = ls_setup_user( 'an.peeters', 'An Peeters', 'an.peeters@voorbeeld.be', 'sophie_patient' );
	$karim  = ls_setup_user( 'karim.b', 'Karim B.', 'karim.b@voorbeeld.be', 'sophie_patient' );
	$els    = ls_setup_user( 'els.v', 'Els V.', 'els.v@voorbeeld.be', 'sophie_patient' );
	update_user_meta( $els, '_ls_geblokkeerd', 1 );

	$dossier = function ( $naam, $voornaam, $focus, $dagen, $niveau, $owner, $taken ) use ( $oef ) {
		$id = wp_insert_post( array( 'post_type' => 'ls_patient', 'post_status' => 'private', 'post_title' => $naam ) );
		update_post_meta( $id, '_ls_voornaam', $voornaam );
		update_post_meta( $id, '_ls_focus', $focus );
		update_post_meta( $id, '_ls_sessiedagen', $dagen );
		update_post_meta( $id, '_ls_niveau', $niveau );
		update_post_meta( $id, '_ls_owner', $owner );
		$t = array();
		foreach ( $taken as $slug ) {
			$t[] = array( 'oef' => $oef[ $slug ], 'sinds' => current_time( 'Y-m-d' ), 'door' => $owner );
		}
		update_post_meta( $id, '_ls_taken', $t );
		return $id;
	};
	$lotte = $dossier( 'Lotte P.', 'Lotte', 'Dyslexie · AVI M3', array( 2, 4 ), '2e', $sophie, array( 'lezen', 'eiij', 'tafel3' ) );
	$jonas = $dossier( 'Jonas P.', 'Jonas', 'OMFT', array( 1 ), '1e', $sophie, array( 'omft', 'rijm' ) );
	$emma  = $dossier( 'Emma V.', 'Emma', 'Taalontwikkeling', array( 3 ), 'k', $lies, array( 'woorden', 'rijm' ) );
	$mehdi = $dossier( 'Mehdi B.', 'Mehdi', 'Afasie', array( 2, 5 ), '6e', $lies, array( 'benoem' ) );
	$noor  = $dossier( 'Noor D.', 'Noor', 'Dyscalculie', array( 4 ), '3e', $sophie, array( 'tafel3', 'klok' ) );

	// Ouders koppelen (zoals na het gebruik van een code).
	ls_op_koppel_ouder( $lotte, $an );
	ls_op_koppel_ouder( $jonas, $an );
	ls_op_koppel_ouder( $mehdi, $karim );
	ls_op_koppel_ouder( $emma, $els );

	// Wat er deze week al geoefend werd.
	$dag = fn( $n ) => gmdate( 'Y-m-d', strtotime( "-{$n} day", current_time( 'timestamp' ) ) );
	update_post_meta( $lotte, '_ls_gedaan', array( $oef['lezen'] => array( $dag( 2 ), $dag( 1 ) ), $oef['eiij'] => array( $dag( 2 ) ) ) );
	update_post_meta( $jonas, '_ls_gedaan', array( $oef['omft'] => array( $dag( 3 ), $dag( 2 ), $dag( 1 ) ) ) );
	update_post_meta( $mehdi, '_ls_gedaan', array( $oef['benoem'] => array( $dag( 1 ) ) ) );

	// Open uitnodigingscode om registreren te testen.
	add_post_meta( $noor, '_ls_code', 'LS-2026' );

	// Logboek.
	ls_op_log( 'Dossier aangemaakt voor Lotte P.', $sophie );
	ls_op_log( 'Oefening "Flitskaarten tafel van 3" opengezet voor Lotte P.', $sophie );
	ls_op_log( 'Uitnodigingscode LS-2026 aangemaakt voor Noor D.', $sophie );
	ls_op_log( 'Oefening "Benoemen: keukenvoorwerpen" opengezet voor Mehdi B.', $lies );
	ls_op_log( 'Account van Els V. geblokkeerd', $sophie );

	// Twee voorbeeldaanvragen.
	foreach ( array(
		array( 'Katrien M.', 'Mijn kind', '1e–2e leerjaar', 'Lezen of spelling (dyslexie)', 'De juf merkt dat lezen moeizaam gaat.', '0470 12 34 56', 'katrien@voorbeeld.be', 'Bellen', 'chat' ),
		array( 'Jan D.', 'Een familielid', '', 'Afasie', 'Mijn vader had een beroerte en zoekt therapie aan huis.', '0480 98 76 54', 'jan@voorbeeld.be', 'Maakt niet uit', 'contactformulier' ),
	) as $a ) {
		$id = wp_insert_post( array( 'post_type' => 'ls_aanvraag', 'post_status' => 'private', 'post_title' => $a[0] . ' – ' . $a[3] ) );
		foreach ( array( 'naam', 'voor', 'leeftijd', 'hulp', 'toel', 'tel', 'mail', 'pref', 'bron' ) as $i => $k ) {
			update_post_meta( $id, '_ls_' . $k, $a[ $i ] );
		}
		update_post_meta( $id, '_ls_status', 'nieuw' );
		update_post_meta( $id, '_ls_gemaild', 0 );
	}
}

flush_rewrite_rules( false );
update_option( 'ls_setup_klaar', current_time( 'mysql' ) );
echo "Logopedie Sophie: setup klaar.\n";
