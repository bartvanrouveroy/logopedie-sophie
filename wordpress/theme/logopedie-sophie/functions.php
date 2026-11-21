<?php
/**
 * Logopedie Sophie – thema-functies.
 *
 * @package logopedie-sophie
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'responsive-embeds' );
		add_editor_style( 'style.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$ver = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'logopedie-sophie', get_stylesheet_uri(), array(), $ver );
	}
);

/**
 * Eigen stijlen voor blokken, kiesbaar in de editor (zijbalk › Stijlen).
 */
add_action(
	'init',
	function () {
		register_block_style( 'core/button', array( 'name' => 'sun', 'label' => 'Zon' ) );
		register_block_style( 'core/button', array( 'name' => 'chip', 'label' => 'Label (wit)' ) );
		register_block_style( 'core/button', array( 'name' => 'chip-dark', 'label' => 'Label (donker)' ) );
		register_block_style( 'core/list', array( 'name' => 'ls-checks', 'label' => 'Vinkjes' ) );
	}
);

/**
 * Menu's: de navigatieblokken in kop en voet krijgen het juiste menu.
 * De menu's zelf pas je aan in Weergave › Editor › Navigatie.
 */
add_filter(
	'render_block_data',
	function ( $block ) {
		if ( 'core/navigation' !== ( $block['blockName'] ?? '' ) || ! empty( $block['attrs']['ref'] ) ) {
			return $block;
		}
		$map = array(
			'ls-main-nav'   => 'ls_nav_main',
			'ls-footer-nav' => 'ls_nav_footer',
			'ls-legal-nav'  => 'ls_nav_legal',
		);
		$class = $block['attrs']['className'] ?? '';
		foreach ( $map as $needle => $option ) {
			if ( false !== strpos( $class, $needle ) ) {
				$id = (int) get_option( $option );
				if ( $id && get_post( $id ) ) {
					$block['attrs']['ref'] = $id;
				}
			}
		}
		return $block;
	}
);

/**
 * [ls_kruimel] – kruimelpad bovenaan een pagina: Home › Ouder › Pagina.
 */
add_shortcode(
	'ls_kruimel',
	function () {
		if ( ! is_singular() ) {
			return '';
		}
		$post  = get_post();
		$parts = array( '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>' );
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
			$parts[] = '<a href="' . esc_url( get_permalink( $ancestor ) ) . '">' . esc_html( get_the_title( $ancestor ) ) . '</a>';
		}
		$parts[] = '<span aria-current="page">' . esc_html( get_the_title( $post ) ) . '</span>';
		return '<nav class="ls-crumb" aria-label="Kruimelpad">' . implode( ' › ', $parts ) . '</nav>';
	}
);

/**
 * [ls_terug_naar_artikels] – link onder een artikel terug naar het overzicht.
 */
add_shortcode(
	'ls_terug_naar_artikels',
	function () {
		$page = get_page_by_path( 'hulpvragen' );
		$url  = $page ? get_permalink( $page ) . '#artikels' : home_url( '/' );
		return '<p><a class="ls-more" href="' . esc_url( $url ) . '">← Alle artikels voor ouders</a></p>';
	}
);

/**
 * SVG-tekeningen van het thema mogen in de mediabibliotheek staan (alleen voor beheerders).
 */
add_filter(
	'upload_mimes',
	function ( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}
);

/**
 * Categorieën als knoppen bovenaan de webshop (en de categoriepagina's).
 */
add_filter(
	'render_block_woocommerce/product-results-count',
	function ( $html ) {
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_category() ) ) {
			return $html;
		}
		$kleuren = array( '#BFD1DF', '#EAD6A8', '#F2C9A8', '#C9D8C0', '#DCD0E8', '#E3C99E' );
		$cats    = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'parent'     => 0,
				'orderby'    => 'meta_value_num',
				'meta_key'   => 'order', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);
		if ( is_wp_error( $cats ) || ! $cats ) {
			return $html;
		}
		$huidig = is_product_category() ? get_queried_object_id() : 0;
		$out    = '<nav class="ls-shop-cats" aria-label="Categorieën">';
		$out   .= '<a href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '"' . ( $huidig ? '' : ' aria-current="page"' ) . '><i style="background:#333"></i>Alles</a>';
		foreach ( $cats as $i => $c ) {
			$out .= '<a href="' . esc_url( get_term_link( $c ) ) . '"' . ( $huidig === $c->term_id ? ' aria-current="page"' : '' ) . '><i style="background:' . esc_attr( $kleuren[ $i % 6 ] ) . '"></i>' . esc_html( $c->name ) . '</a>';
		}
		return $out . '</nav>' . $html;
	}
);
