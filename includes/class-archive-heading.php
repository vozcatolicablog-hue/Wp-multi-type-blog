<?php
/**
 * Encabezado de los archivos (categoría, etiqueta, taxonomía, búsqueda) con el sistema de
 * diseño: migas, antetítulo con el tipo de archivo, título, descripción o total, y las
 * subcategorías como chips.
 *
 * Shortcode: [wpmb_archivo_cabecera]. Va en la plantilla de archivo de Elementor, encima del
 * widget «Premium Multi-Post Archive» con un layout «Sistema VC».
 *
 * @package WpMultiPostTypeBlog
 */

namespace WpMultiPostTypeBlog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Archive_Heading {

	public static function register() {
		add_shortcode( 'wpmb_archivo_cabecera', array( __CLASS__, 'render' ) );
	}

	/**
	 * @return string
	 */
	public static function render() {
		global $wp_query;

		$total  = (int) $wp_query->found_posts;
		$migas  = array( '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Inicio', 'wp-multi-post-type-blog' ) . '</a>' );
		$ante   = __( 'Archivo', 'wp-multi-post-type-blog' );
		$titulo = '';
		$bajada = '';
		$hijos  = array();

		if ( is_search() ) {
			$ante   = __( 'Búsqueda', 'wp-multi-post-type-blog' );
			$titulo = '«' . get_search_query( false ) . '»';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$tax    = get_taxonomy( $term->taxonomy );
				$ante   = is_category() ? __( 'Categoría', 'wp-multi-post-type-blog' ) : ( is_tag() ? __( 'Etiqueta', 'wp-multi-post-type-blog' ) : $tax->labels->singular_name );
				$titulo = $term->name;
				$bajada = trim( wp_strip_all_tags( term_description( $term ) ) );

				foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $padre_id ) {
					$padre = get_term( $padre_id, $term->taxonomy );
					if ( $padre instanceof \WP_Term ) {
						$migas[] = '<a href="' . esc_url( get_term_link( $padre ) ) . '">' . esc_html( $padre->name ) . '</a>';
					}
				}

				if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
					$hijos = get_terms(
						array(
							'taxonomy'   => $term->taxonomy,
							'parent'     => $term->term_id,
							'hide_empty' => true,
							'orderby'    => 'count',
							'order'      => 'DESC',
							'number'     => 12,
						)
					);
					$hijos = is_wp_error( $hijos ) ? array() : $hijos;
				}
			}
		} else {
			$titulo = wp_strip_all_tags( get_the_archive_title() );
		}

		$migas[] = '<span aria-current="page">' . esc_html( $titulo ) . '</span>';

		/* translators: %s: cantidad de publicaciones. */
		$cuenta = sprintf( _n( '%s publicación', '%s publicaciones', $total, 'wp-multi-post-type-blog' ), number_format_i18n( $total ) );

		$html  = '<div class="wpmb-arch">';
		$html .= '<nav class="wpmb-arch__migas" aria-label="' . esc_attr__( 'Migas de pan', 'wp-multi-post-type-blog' ) . '">' . implode( '<span aria-hidden="true">/</span>', $migas ) . '</nav>';
		$html .= '<p class="wpmb-arch__ante">' . esc_html( $ante ) . '</p>';
		$html .= '<h1 class="wpmb-arch__tit">' . esc_html( $titulo ) . '</h1>';
		if ( '' !== $bajada ) {
			$html .= '<p class="wpmb-arch__lead">' . esc_html( wp_trim_words( $bajada, 45 ) ) . '</p>';
		}
		$html .= '<p class="wpmb-arch__cuenta">' . esc_html( $cuenta ) . '</p>';

		// En los resultados, el buscador con lo buscado, para afinar sin volver atrás. Conserva
		// el tipo de contenido (el buscador de /publicaciones/ busca solo entradas).
		if ( is_search() ) {
			// Se toma de la URL: en una búsqueda la variable de consulta puede llegar como lista
			// (otros plugins la amplían), y convertida a texto daba «array».
			$tipo  = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tipo  = post_type_exists( $tipo ) ? $tipo : '';
			$html .= '<form class="wpmb-arch__busca" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">'
				. '<label class="wpmb-arch__busca-l" for="wpmb-arch-q">' . esc_html__( 'Buscar otra vez', 'wp-multi-post-type-blog' ) . '</label>'
				. '<div class="wpmb-arch__busca-fila"><input id="wpmb-arch-q" type="search" name="s" value="' . esc_attr( get_search_query( false ) ) . '" placeholder="' . esc_attr__( 'Título, autor o tema', 'wp-multi-post-type-blog' ) . '" autocomplete="off" required>'
				. ( '' !== $tipo && 'any' !== $tipo ? '<input type="hidden" name="post_type" value="' . esc_attr( $tipo ) . '">' : '' )
				. '<button type="submit">' . esc_html__( 'Buscar', 'wp-multi-post-type-blog' ) . '</button></div></form>';
		}

		if ( $hijos ) {
			$html .= '<ul class="wpmb-arch__hijos" aria-label="' . esc_attr__( 'Subcategorías', 'wp-multi-post-type-blog' ) . '">';
			foreach ( $hijos as $h ) {
				$html .= '<li><a href="' . esc_url( get_term_link( $h ) ) . '">' . esc_html( $h->name ) . '</a></li>';
			}
			$html .= '</ul>';
		}

		return $html . '</div>';
	}
}
