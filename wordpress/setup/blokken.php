<?php
/**
 * Kleine hulpfuncties die geldige blokcode (Gutenberg) maken voor de setup.
 *
 * @package logopedie-sophie-setup
 */

defined( 'ABSPATH' ) || exit;

/**
 * Blokcommentaar met attributen.
 *
 * @param string $name  Bloknaam zonder "core/".
 * @param array  $attrs Attributen.
 * @return string
 */
function lsb_open( $name, $attrs = array() ) {
	return '<!-- wp:' . $name . ( $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '' ) . ' -->';
}

/**
 * Klassen uit attributen (kleur, lettergrootte, className).
 *
 * @param array $a Attributen.
 * @return string[]
 */
function lsb_classes( $a ) {
	$c = array();
	if ( ! empty( $a['align'] ) ) {
		$c[] = 'align' . $a['align'];
	}
	if ( ! empty( $a['className'] ) ) {
		$c[] = $a['className'];
	}
	if ( ! empty( $a['textColor'] ) ) {
		$c[] = 'has-' . $a['textColor'] . '-color';
		$c[] = 'has-text-color';
	}
	if ( ! empty( $a['backgroundColor'] ) ) {
		$c[] = 'has-' . $a['backgroundColor'] . '-background-color';
		$c[] = 'has-background';
	}
	if ( ! empty( $a['fontSize'] ) ) {
		$c[] = 'has-' . $a['fontSize'] . '-font-size';
	}
	return $c;
}

/**
 * Paragraaf.
 *
 * @param string $html  Inhoud.
 * @param array  $attrs Attributen.
 * @return string
 */
function lsb_p( $html, $attrs = array() ) {
	$c = lsb_classes( $attrs );
	return lsb_open( 'paragraph', $attrs ) . "\n<p" . ( $c ? ' class="' . implode( ' ', $c ) . '"' : '' ) . '>' . $html . "</p>\n<!-- /wp:paragraph -->";
}

/**
 * Titel.
 *
 * @param int    $level Niveau.
 * @param string $html  Inhoud.
 * @param array  $attrs Attributen.
 * @return string
 */
function lsb_h( $level, $html, $attrs = array() ) {
	if ( 2 !== $level ) {
		$attrs = array_merge( array( 'level' => $level ), $attrs );
	}
	$c = array_merge( array( 'wp-block-heading' ), lsb_classes( $attrs ) );
	return lsb_open( 'heading', $attrs ) . "\n<h{$level} class=\"" . implode( ' ', $c ) . "\">{$html}</h{$level}>\n<!-- /wp:heading -->";
}

/**
 * Knoppen. Elke knop: array( tekst, url, stijl ).
 *
 * @param array $knoppen Knoppen.
 * @param array $attrs   Attributen van de groep.
 * @return string
 */
function lsb_buttons( $knoppen, $attrs = array() ) {
	$out = array();
	foreach ( $knoppen as $k ) {
		$a   = ! empty( $k[2] ) ? array( 'className' => 'is-style-' . $k[2] ) : array();
		$cls = 'wp-block-button' . ( ! empty( $k[2] ) ? ' is-style-' . $k[2] : '' );
		$out[] = lsb_open( 'button', $a ) . "\n<div class=\"{$cls}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $k[1] ) . '">' . $k[0] . "</a></div>\n<!-- /wp:button -->";
	}
	$c = array_merge( array( 'wp-block-buttons' ), lsb_classes( $attrs ) );
	return lsb_open( 'buttons', $attrs ) . "\n<div class=\"" . implode( ' ', $c ) . '">' . implode( "\n\n", $out ) . "</div>\n<!-- /wp:buttons -->";
}

/**
 * Lijst.
 *
 * @param string[] $items   Items (HTML).
 * @param array    $attrs   Attributen.
 * @return string
 */
function lsb_list( $items, $attrs = array() ) {
	$tag = ! empty( $attrs['ordered'] ) ? 'ol' : 'ul';
	$c   = array_merge( array( 'wp-block-list' ), lsb_classes( $attrs ) );
	$li  = array_map( fn( $i ) => "<!-- wp:list-item -->\n<li>{$i}</li>\n<!-- /wp:list-item -->", $items );
	return lsb_open( 'list', $attrs ) . "\n<{$tag} class=\"" . implode( ' ', $c ) . '">' . implode( "\n\n", $li ) . "</{$tag}>\n<!-- /wp:list -->";
}

/**
 * Afbeelding uit de mediabibliotheek.
 *
 * @param int    $id    Bijlage.
 * @param string $alt   Alternatieve tekst.
 * @param array  $attrs Extra attributen.
 * @return string
 */
function lsb_img( $id, $alt, $attrs = array() ) {
	$attrs = array_merge( array( 'id' => (int) $id, 'sizeSlug' => 'full', 'linkDestination' => 'none' ), $attrs );
	$c     = array_merge( array( 'wp-block-image', 'size-full' ), lsb_classes( $attrs ) );
	return lsb_open( 'image', $attrs ) . "\n<figure class=\"" . implode( ' ', $c ) . '"><img src="' . esc_url( wp_get_attachment_url( $id ) ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . (int) $id . "\"/></figure>\n<!-- /wp:image -->";
}

/**
 * Groep.
 *
 * @param string $inner Inhoud.
 * @param array  $attrs Attributen (tagName, align, className, backgroundColor, textColor, layout).
 * @return string
 */
function lsb_group( $inner, $attrs = array() ) {
	$attrs = array_merge( array( 'layout' => array( 'type' => 'constrained' ) ), $attrs );
	$tag   = $attrs['tagName'] ?? 'div';
	$c     = array_merge( array( 'wp-block-group' ), lsb_classes( $attrs ) );
	$id    = ! empty( $attrs['anchor'] ) ? ' id="' . esc_attr( $attrs['anchor'] ) . '"' : '';
	return lsb_open( 'group', $attrs ) . "\n<{$tag}{$id} class=\"" . implode( ' ', $c ) . "\">{$inner}</{$tag}>\n<!-- /wp:group -->";
}

/**
 * Sectie over de volle breedte met inhoud van 1180px.
 *
 * @param string $inner Inhoud.
 * @param array  $attrs Extra attributen.
 * @return string
 */
function lsb_section( $inner, $attrs = array() ) {
	$cls = trim( 'ls-section ' . ( $attrs['className'] ?? '' ) );
	unset( $attrs['className'] );
	return lsb_group(
		$inner,
		array_merge(
			array(
				'tagName'   => 'section',
				'align'     => 'full',
				'className' => $cls,
				'layout'    => array( 'type' => 'constrained', 'contentSize' => '1180px' ),
			),
			$attrs
		)
	);
}

/**
 * Kolommen. Elke kolom: array( inhoud, breedte|null ).
 *
 * @param array $cols  Kolommen.
 * @param array $attrs Attributen.
 * @return string
 */
function lsb_cols( $cols, $attrs = array() ) {
	$va  = $attrs['verticalAlignment'] ?? '';
	$out = array();
	foreach ( $cols as $col ) {
		$a = array();
		$c = array( 'wp-block-column' );
		if ( $va ) {
			$a['verticalAlignment'] = $va;
			$c[]                    = 'is-vertically-aligned-' . $va;
		}
		if ( ! empty( $col[1] ) ) {
			$a['width'] = $col[1];
		}
		if ( ! empty( $col[2] ) ) {
			$a['className'] = $col[2];
			$c[]            = $col[2];
		}
		$style = ! empty( $col[1] ) ? ' style="flex-basis:' . $col[1] . '"' : '';
		$out[] = lsb_open( 'column', $a ) . "\n<div class=\"" . implode( ' ', $c ) . "\"{$style}>" . $col[0] . "</div>\n<!-- /wp:column -->";
	}
	$c = array_merge( array( 'wp-block-columns' ), lsb_classes( $attrs ) );
	if ( $va ) {
		$c[] = 'are-vertically-aligned-' . $va;
	}
	return lsb_open( 'columns', $attrs ) . "\n<div class=\"" . implode( ' ', $c ) . '">' . implode( "\n\n", $out ) . "</div>\n<!-- /wp:columns -->";
}

/**
 * Citaat.
 *
 * @param string[] $paragrafen Paragrafen.
 * @param array    $attrs      Attributen.
 * @return string
 */
function lsb_quote( $paragrafen, $attrs = array() ) {
	$c = array_merge( array( 'wp-block-quote' ), lsb_classes( $attrs ) );
	return lsb_open( 'quote', $attrs ) . "\n<blockquote class=\"" . implode( ' ', $c ) . '">' . implode( "\n\n", array_map( 'lsb_p', $paragrafen ) ) . "</blockquote>\n<!-- /wp:quote -->";
}

/**
 * Uitklapper (vraag en antwoord).
 *
 * @param string $vraag    Vraag.
 * @param string $antwoord Antwoord (HTML).
 * @return string
 */
function lsb_details( $vraag, $antwoord ) {
	return lsb_open( 'details' ) . "\n<details class=\"wp-block-details\"><summary>{$vraag}</summary>" . lsb_p( $antwoord ) . "</details>\n<!-- /wp:details -->";
}

/**
 * Tabel zonder kop.
 *
 * @param array $rows  Rijen (arrays met cellen).
 * @param array $attrs Attributen.
 * @return string
 */
function lsb_table( $rows, $attrs = array() ) {
	$attrs = array_merge( array( 'hasFixedLayout' => false ), $attrs );
	$c     = array_merge( array( 'wp-block-table' ), lsb_classes( $attrs ) );
	$tr    = array_map( fn( $r ) => '<tr>' . implode( '', array_map( fn( $td ) => '<td>' . $td . '</td>', $r ) ) . '</tr>', $rows );
	return lsb_open( 'table', $attrs ) . "\n<figure class=\"" . implode( ' ', $c ) . '"><table><tbody>' . implode( '', $tr ) . "</tbody></table></figure>\n<!-- /wp:table -->";
}

/**
 * Shortcode-blok.
 *
 * @param string $code Shortcode.
 * @return string
 */
function lsb_sc( $code ) {
	return "<!-- wp:shortcode -->\n{$code}\n<!-- /wp:shortcode -->";
}

/**
 * Lijst van artikels (Query Loop) in kaarten.
 *
 * @param int $aantal Aantal.
 * @return string
 */
function lsb_artikels( $aantal = 6 ) {
	return '<!-- wp:query {"queryId":7,"query":{"perPage":' . (int) $aantal . ',"postType":"post","order":"desc","orderBy":"date","inherit":false}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"className":"ls-card","backgroundColor":"surface","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group ls-card has-surface-background-color has-background"><!-- wp:post-terms {"term":"category","className":"ls-eyebrow"} /-->

<!-- wp:post-title {"isLink":true,"level":3,"className":"ls-card-title"} /-->

<!-- wp:post-excerpt {"excerptLength":18,"className":"ls-card-excerpt"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->';
}

/**
 * Lijst van producten (WooCommerce-blok) uit een categorie.
 *
 * @param int $cat_id Productcategorie.
 * @param int $aantal Aantal.
 * @return string
 */
function lsb_producten( $cat_id, $aantal = 4 ) {
	return lsb_sc( '[products limit="' . (int) $aantal . '" columns="' . (int) $aantal . '" category="' . esc_attr( get_term( $cat_id )->slug ) . '"]' );
}
