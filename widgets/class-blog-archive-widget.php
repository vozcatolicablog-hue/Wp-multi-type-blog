<?php
namespace WpMultiPostTypeBlog\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Premium Multi-Post Archive Elementor Widget.
 */
class Blog_Archive_Widget extends Blog_Posts_Widget {

	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'wp_multi_post_type_archive_widget';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Premium Multi-Post Archive', 'wp-multi-post-type-blog' );
	}

	/**
	 * Render the widget content.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$raw       = $settings;
		$widget_id = $this->get_id();
		$page_var  = 'wpmpb_' . $widget_id . '_page';

		$paged = isset( $_GET[ $page_var ] ) ? max( 1, absint( wp_unslash( $_GET[ $page_var ] ) ) ) : 1;

		// Automatically filter by the currently viewed author on author archives.
		if ( is_author() ) {
			$settings['archive_author_id'] = get_queried_object_id();
		}

		// Lo mismo en los archivos de categoría, etiqueta o taxonomía: el término que se
		// está viendo reemplaza a los términos del widget. Sin esto, cada categoría
		// listaba todas las entradas del sitio.
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$settings['terms']        = array( $term->taxonomy . ':' . $term->slug );
				$settings['tax_relation'] = 'AND';
			}
		}

		// Resultados de búsqueda: el texto buscado.
		if ( is_search() ) {
			$settings['archive_search'] = get_search_query( false );
		}

		// Only singular views have a "current post". On archives get_queried_object_id()
		// returns a term or user ID, which would exclude an unrelated post by that ID.
		$settings['current_post_id'] = is_singular() ? get_queried_object_id() : 0;
		$settings = \WpMultiPostTypeBlog\Utils::sanitize_settings( $settings );
		$settings = \WpMultiPostTypeBlog\Utils::apply_featured_exclusions( $settings );

		$query = new \WP_Query( \WpMultiPostTypeBlog\Utils::build_query_args( $settings, $paged ) );
		$max_pages = \WpMultiPostTypeBlog\Utils::get_max_pages( $query, $settings );

		if ( ! $query->have_posts() ) {
			$empty_class = \WpMultiPostTypeBlog\Utils::is_vc_layout( $settings['layout_type'] ) ? 'premium-blog-no-posts wpmb-ds' : 'premium-blog-no-posts';
			echo '<div class="' . esc_attr( $empty_class ) . '">' . esc_html__( 'No se encontraron publicaciones.', 'wp-multi-post-type-blog' ) . '</div>';
			return;
		}

		// 5.1: Pre-cache postmeta before starting the loops
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $post_ids ) ) {
			update_postmeta_cache( $post_ids );

			// Views live in their own tables, so they need a separate batch query.
			if ( 'yes' === $settings['show_views'] ) {
				// El archivo siempre muestra totales históricos: no ordena por vistas.
				\WpMultiPostTypeBlog\Views_Source::set_display_range( array() );
				self::prime_views_cache( $query->posts );
			}
		}

		$settings_signature = \WpMultiPostTypeBlog\Utils::sign_settings( $settings );
		$columns_class = 'premium-blog-widget--columns-' . intval( $settings['columns'] );
		$layout_type_class = 'premium-blog-widget--layout-' . sanitize_html_class( $settings['layout_type'] );
		$post_types = $settings['post_types'];

		// 1.1: Dynamically check which post types have actual posts matching the query parameters with transient caching
		$cache_key = \WpMultiPostTypeBlog\Utils::cache_key( 'active_pt_' . md5( wp_json_encode( $settings ) ) );
		$active_post_types = get_transient( $cache_key );
		if ( false === $active_post_types ) {
			$active_post_types = array();
			foreach ( $post_types as $pt ) {
				$pt_query_args = \WpMultiPostTypeBlog\Utils::build_query_args( $settings, 1 );
				$pt_query_args['posts_per_page'] = 1;
				$pt_query_args['offset'] = 0;
				if ( isset( $pt_query_args['paged'] ) ) {
					unset( $pt_query_args['paged'] );
				}
				$pt_query_args['fields'] = 'ids';
				$pt_query_args['post_type'] = $pt;

				$pt_query = new \WP_Query( $pt_query_args );
				if ( $pt_query->have_posts() ) {
					$active_post_types[] = $pt;
				}
			}
			set_transient( $cache_key, $active_post_types, HOUR_IN_SECONDS );
		}

		// 6.2: Call base class HTML rendering helper to eliminate code duplication
		$header = \WpMultiPostTypeBlog\Utils::is_vc_layout( $settings['layout_type'] )
			? self::build_vc_header( $raw, $settings, $query->found_posts )
			: null;

		$this->render_widget_html( $widget_id, $settings, $query, $paged, $max_pages, $settings_signature, $columns_class, $layout_type_class, 'premium-blog-archive-widget', $active_post_types, $header );
	}
}
