<?php
namespace WpMultiPostTypeBlog\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Premium Multi-Post Blog Elementor Widget.
 */
class Blog_Posts_Widget extends Widget_Base {

	/**
	 * Maximum terms loaded per taxonomy in Elementor controls.
	 */
	const TERMS_PER_TAXONOMY_LIMIT = 250;

	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'wp_multi_post_type_blog_widget';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Premium Multi-Post Blog', 'wp-multi-post-type-blog' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * Retrieve the list of categories the widget belongs to.
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Retrieve widget style dependencies.
	 *
	 * @return array
	 */
	public function get_style_depends() {
		return [ 'wp-multipost-blog-widget-css' ];
	}

	/**
	 * Retrieve widget script dependencies.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return [ 'wp-multipost-blog-widget-js' ];
	}

	/**
	 * Retrieve the list of active public post types with transient caching.
	 *
	 * @return array Post types key-value pairs.
	 */
	private function get_all_post_types() {
		$cache_key = \WpMultiPostTypeBlog\Utils::cache_key( 'all_post_types' );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$post_types = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'objects'
		);
		$options = array();
		foreach ( $post_types as $post_type ) {
			// Skip attachments
			if ( 'attachment' === $post_type->name ) {
				continue;
			}
			$options[ $post_type->name ] = $post_type->label;
		}

		set_transient( $cache_key, $options, HOUR_IN_SECONDS );
		return $options;
	}

	/**
	 * Retrieve selectable image sizes.
	 *
	 * @return array
	 */
	private function get_image_size_options() {
		$sizes = get_intermediate_image_sizes();
		$sizes[] = 'full';
		$options = array();

		foreach ( array_unique( $sizes ) as $size ) {
			$options[ $size ] = ucwords( str_replace( array( '-', '_' ), ' ', $size ) );
		}

		return $options;
	}

	/**
	 * Retrieve the selectable heading levels for post titles.
	 *
	 * @return array
	 */
	private function get_heading_tag_options() {
		$options = array();

		foreach ( \WpMultiPostTypeBlog\Utils::allowed_heading_tags() as $tag ) {
			$options[ $tag ] = strtoupper( $tag );
		}

		return $options;
	}

	/**
	 * Retrieve the list of authors/users with transient caching.
	 *
	 * @return array Authors key-value pairs.
	 */
	private function get_all_authors() {
		$cache_key = \WpMultiPostTypeBlog\Utils::cache_key( 'all_authors' );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// 'who' => 'authors' is deprecated since WP 5.9; 'capability' replaces it.
		$users = get_users( array(
			'capability'          => array( 'edit_posts' ),
			'has_published_posts' => true,
			'fields'              => array( 'ID', 'display_name' ),
		) );
		$options = array();
		foreach ( $users as $user ) {
			$options[ $user->ID ] = $user->display_name;
		}

		set_transient( $cache_key, $options, HOUR_IN_SECONDS );
		return $options;
	}

	/**
	 * Retrieve all public taxonomy terms with transient caching.
	 *
	 * @return array Terms key-value pairs grouped by taxonomy.
	 */
	private function get_all_taxonomy_terms() {
		$cache_key = \WpMultiPostTypeBlog\Utils::cache_key( 'all_taxonomy_terms' );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		$options = array();
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy->name,
				'hide_empty' => false,
				'number'     => self::TERMS_PER_TAXONOMY_LIMIT,
				'orderby'    => 'name',
				'order'      => 'ASC',
			) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$options[ $taxonomy->name . ':' . $term->slug ] = $taxonomy->label . ': ' . $term->name;
				}
			}
		}

		set_transient( $cache_key, $options, HOUR_IN_SECONDS );
		return $options;
	}

	/**
	 * Register the widget controls.
	 */
	protected function register_controls() {
		// --- CONTENT TAB ---
		$this->start_controls_section(
			'section_query',
			[
				'label' => esc_html__( 'Content Filter', 'wp-multi-post-type-blog' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'post_types',
			[
				'label'       => esc_html__( 'Post Types', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => [ 'post' ],
				'options'     => $this->get_all_post_types(),
				'label_block' => true,
			]
		);

		$this->add_control(
			'terms',
			[
				'label'       => esc_html__( 'Filter by Taxonomy Terms', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => [],
				'options'     => $this->get_all_taxonomy_terms(),
				'label_block' => true,
				'description' => esc_html__( 'Select categories, tags, or custom terms to filter the query.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'tax_relation',
			[
				'label'   => esc_html__( 'Taxonomy Relation', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'AND',
				'options' => [
					'AND' => esc_html__( 'Match all selected taxonomies', 'wp-multi-post-type-blog' ),
					'OR'  => esc_html__( 'Match any selected taxonomy', 'wp-multi-post-type-blog' ),
				],
			]
		);

		$this->add_control(
			'authors',
			[
				'label'       => esc_html__( 'Filter by Authors', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => [],
				'options'     => $this->get_all_authors(),
				'label_block' => true,
			]
		);

		$this->add_control(
			'offset',
			[
				'label'       => esc_html__( 'Offset', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 0,
				'min'         => 0,
				'max'         => 500,
				'step'        => 1,
				'description' => esc_html__( 'Skip this many posts before rendering results.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'exclude_ids',
			[
				'label'       => esc_html__( 'Exclude Post IDs', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'label_block' => true,
				'description' => esc_html__( 'Comma-separated post IDs to exclude.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'exclude_current_post',
			[
				'label'        => esc_html__( 'Exclude Current Post', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-multi-post-type-blog' ),
				'label_off'    => esc_html__( 'No', 'wp-multi-post-type-blog' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'posts_per_page',
			[
				'label'   => esc_html__( 'Posts Count', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 5,
				'min'     => 1,
				'max'     => 100,
				'step'    => 1,
			]
		);

		$this->add_control(
			'orderby',
			[
				'label'   => esc_html__( 'Order By', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => [
					'date'          => esc_html__( 'Date', 'wp-multi-post-type-blog' ),
					'title'         => esc_html__( 'Title', 'wp-multi-post-type-blog' ),
					'rand'          => esc_html__( 'Random', 'wp-multi-post-type-blog' ),
					'comment_count' => esc_html__( 'Popular (Comments)', 'wp-multi-post-type-blog' ),
					'menu_order'    => esc_html__( 'Menu Order', 'wp-multi-post-type-blog' ),
					'vca_views'           => esc_html__( 'Más leídos (histórico)', 'wp-multi-post-type-blog' ),
					'vca_views_period'    => esc_html__( 'Más leídos (del período)', 'wp-multi-post-type-blog' ),
					'vca_trending'        => esc_html__( 'En tendencia (crecimiento)', 'wp-multi-post-type-blog' ),
				],
				'description' => esc_html__( 'Random ordering can be slower on large sites.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'views_range',
			[
				'label'       => esc_html__( 'Período de las vistas (días)', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 30,
				'min'         => 1,
				'max'         => 3653,
				'condition'   => [ 'orderby' => [ 'vca_views_period', 'vca_trending' ] ],
				'description' => esc_html__( 'Ventana que se mide. Para tendencias se compara contra el período anterior de la misma duración.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'views_min',
			[
				'label'       => esc_html__( 'Mínimo de lecturas', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 20,
				'min'         => 0,
				'condition'   => [ 'orderby' => 'vca_trending' ],
				'description' => esc_html__( 'Evita que una nota que pasó de 1 a 20 lecturas encabece el ranking por haber crecido un 1900%.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'order',
			[
				'label'   => esc_html__( 'Order Direction', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'DESC',
				'options' => [
					'DESC' => esc_html__( 'Descending', 'wp-multi-post-type-blog' ),
					'ASC'  => esc_html__( 'Ascending', 'wp-multi-post-type-blog' ),
				],
			]
		);

		$this->add_control(
			'show_featured',
			[
				'label'        => esc_html__( 'Featured First Post', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'wp-multi-post-type-blog' ),
				'label_off'    => esc_html__( 'No', 'wp-multi-post-type-blog' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'pagination',
			[
				'label'   => esc_html__( 'Pagination Mode', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'none',
				'options' => [
					'none'      => esc_html__( 'None', 'wp-multi-post-type-blog' ),
					'numbers'   => esc_html__( 'Standard (Numbers)', 'wp-multi-post-type-blog' ),
					'load_more' => esc_html__( 'Load More Button (AJAX)', 'wp-multi-post-type-blog' ),
					'infinite'  => esc_html__( 'Infinite Scroll (AJAX)', 'wp-multi-post-type-blog' ),
				],
			]
		);

		$this->add_control(
			'load_more_text',
			[
				'label'     => esc_html__( 'Load More Button Text', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Cargar Más', 'wp-multi-post-type-blog' ),
				'condition' => [
					'pagination' => 'load_more',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_display',
			[
				'label' => esc_html__( 'Display Options', 'wp-multi-post-type-blog' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'layout_type',
			[
				'label'   => esc_html__( 'Layout Type', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'classic',
				'options' => [
					'classic' => esc_html__( 'Classic', 'wp-multi-post-type-blog' ),
					'compact' => esc_html__( 'Compact', 'wp-multi-post-type-blog' ),
				],
			]
		);

		foreach ( array(
			'show_category' => esc_html__( 'Show Category Badge', 'wp-multi-post-type-blog' ),
			'show_author'   => esc_html__( 'Show Author', 'wp-multi-post-type-blog' ),
			'show_date'     => esc_html__( 'Show Date', 'wp-multi-post-type-blog' ),
			'show_views'    => esc_html__( 'Show Views', 'wp-multi-post-type-blog' ),
			'show_excerpt'  => esc_html__( 'Show Excerpt', 'wp-multi-post-type-blog' ),
		) as $control_id => $label ) {
			$this->add_control(
				$control_id,
				[
					'label'        => $label,
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => esc_html__( 'Yes', 'wp-multi-post-type-blog' ),
					'label_off'    => esc_html__( 'No', 'wp-multi-post-type-blog' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				]
			);
		}

		$this->add_control(
			'excerpt_words',
			[
				'label'     => esc_html__( 'Excerpt Words', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 30,
				'min'       => 0,
				'max'       => 80,
				'step'      => 1,
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_control(
			'read_more_text',
			[
				'label'       => esc_html__( 'Read More Text', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'LEER MÁS', 'wp-multi-post-type-blog' ),
				'label_block' => true,
			]
		);

		$this->add_control(
			'featured_image_size',
			[
				'label'     => esc_html__( 'Featured Image Size', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'full',
				'options'   => $this->get_image_size_options(),
				'condition' => [
					'show_featured' => 'yes',
				],
			]
		);

		$this->add_control(
			'list_image_size',
			[
				'label'   => esc_html__( 'List Image Size', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'medium_large',
				'options' => $this->get_image_size_options(),
			]
		);

		$this->add_control(
			'image_fallback',
			[
				'label'       => esc_html__( 'Sin imagen destacada', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'hide',
				'options'     => [
					'hide'        => esc_html__( 'Ocultar el área de imagen', 'wp-multi-post-type-blog' ),
					'placeholder' => esc_html__( 'Mostrar placeholder genérico', 'wp-multi-post-type-blog' ),
				],
				'description' => sprintf(
					/* translators: %s: link to the plugin settings screen. */
					esc_html__( 'Solo se aplica si el tipo de contenido tampoco tiene campo personalizado ni imagen de respaldo configurados en %s.', 'wp-multi-post-type-blog' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=' . \WpMultiPostTypeBlog\Admin_Settings::MENU_SLUG ) ) . '" target="_blank">'
						. esc_html__( 'Ajustes → Multi-Post Blog', 'wp-multi-post-type-blog' ) . '</a>'
				),
			]
		);

		$this->add_control(
			'category_level',
			[
				'label'       => esc_html__( 'Categoría de la etiqueta', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'top',
				'options'     => [
					'top'     => esc_html__( 'La categoría superior', 'wp-multi-post-type-blog' ),
					'deepest' => esc_html__( 'La más específica', 'wp-multi-post-type-blog' ),
				],
				'description' => esc_html__( 'En una entrada archivada bajo "Niños > Devociones", la superior muestra Niños y la más específica muestra Devociones.', 'wp-multi-post-type-blog' ),
				'condition'   => [
					'show_category' => 'yes',
				],
			]
		);

		$this->add_control(
			'hide_featured_duplicates',
			[
				'label'        => esc_html__( 'Evitar repetir destacados', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Sí', 'wp-multi-post-type-blog' ),
				'label_off'    => esc_html__( 'No', 'wp-multi-post-type-blog' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'Oculta las entradas que ya aparecieron como destacado en un widget anterior de la misma página. Se aplica solo hacia abajo: el widget de más arriba conserva su destacado.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'featured_title_tag',
			[
				'label'       => esc_html__( 'Etiqueta del título destacado', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'h2',
				'options'     => $this->get_heading_tag_options(),
				'description' => esc_html__( 'Usá H1 solo si esta es la única entrada destacada de la página y no hay otro H1. Una página debe tener un H1 y uno solo.', 'wp-multi-post-type-blog' ),
				'condition'   => [
					'show_featured' => 'yes',
				],
			]
		);

		$this->add_control(
			'list_title_tag',
			[
				'label'   => esc_html__( 'Etiqueta del título de la lista', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h3',
				'options' => $this->get_heading_tag_options(),
			]
		);

		$this->add_control(
			'columns',
			[
				'label'   => esc_html__( 'List Columns', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '1',
				'options' => [
					'1' => esc_html__( 'One', 'wp-multi-post-type-blog' ),
					'2' => esc_html__( 'Two', 'wp-multi-post-type-blog' ),
					'3' => esc_html__( 'Three', 'wp-multi-post-type-blog' ),
				],
			]
		);

		$this->end_controls_section();

		// --- STYLE TAB ---

		// Featured Post Style Section
		$this->start_controls_section(
			'section_featured_style',
			[
				'label' => esc_html__( 'Featured Post Style', 'wp-multi-post-type-blog' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'featured_card_bg',
			[
				'label'     => esc_html__( 'Card Background (Glass)', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(255, 255, 255, 0.85)',
				'selectors' => [
					'{{WRAPPER}} .featured-post__card' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_card_blur',
			[
				'label'     => esc_html__( 'Backdrop Blur Strength', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'min'  => 0,
						'max'  => 30,
						'step' => 1,
					],
				],
				'default'   => [
					'unit' => 'px',
					'size' => 16,
				],
				'selectors' => [
					'{{WRAPPER}} .featured-post__card' => 'backdrop-filter: blur({{SIZE}}px); -webkit-backdrop-filter: blur({{SIZE}}px);',
				],
			]
		);

		$this->add_control(
			'featured_card_border_color',
			[
				'label'     => esc_html__( 'Card Border Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'rgba(255, 255, 255, 0.4)',
				'selectors' => [
					'{{WRAPPER}} .featured-post__card' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_title_color',
			[
				'label'     => esc_html__( 'Title Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0f172a',
				'selectors' => [
					'{{WRAPPER}} .featured-post__title a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_title_hover_color',
			[
				'label'     => esc_html__( 'Title Hover Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#2563eb',
				'selectors' => [
					'{{WRAPPER}} .featured-post__title a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_post_type_prefix_color',
			[
				'label'     => esc_html__( 'Post Type Prefix Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .featured-post__title .post-type-prefix' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_post_type_prefix_hover_color',
			[
				'label'     => esc_html__( 'Post Type Prefix Hover Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .featured-post__title a:hover .post-type-prefix' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_meta_color',
			[
				'label'     => esc_html__( 'Meta Text / Icon Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#64748b',
				'selectors' => [
					'{{WRAPPER}} .featured-post__meta-item'     => 'color: {{VALUE}};',
					'{{WRAPPER}} .featured-post__meta-item svg' => 'stroke: {{VALUE}}; fill: none;',
				],
			]
		);

		$this->add_control(
			'featured_button_text_color',
			[
				'label'     => esc_html__( 'Button Text Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0f172a',
				'selectors' => [
					'{{WRAPPER}} .featured-post__button' => 'color: {{VALUE}}; border-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_button_bg_hover',
			[
				'label'     => esc_html__( 'Button Hover Background', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0f172a',
				'selectors' => [
					'{{WRAPPER}} .featured-post__button:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'featured_button_text_hover',
			[
				'label'     => esc_html__( 'Button Hover Text Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .featured-post__button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		// List Posts Style Section
		$this->start_controls_section(
			'section_list_style',
			[
				'label' => esc_html__( 'List Posts Style', 'wp-multi-post-type-blog' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'list_items_gap',
			[
				'label'      => esc_html__( 'Post Separation (Gap)', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem' ],
				'range'      => [
					'px' => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'default'    => [
					'unit' => 'px',
					'size' => 32,
				],
				'selectors'  => [
					'{{WRAPPER}} .premium-blog-widget__list' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'list_card_bg',
			[
				'label'     => esc_html__( 'Item Background', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => 'transparent',
				'selectors' => [
					'{{WRAPPER}} .list-post-item' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_title_color',
			[
				'label'     => esc_html__( 'Title Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0f172a',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__title a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_title_hover_color',
			[
				'label'     => esc_html__( 'Title Hover Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#2563eb',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__title a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_post_type_prefix_color',
			[
				'label'     => esc_html__( 'Post Type Prefix Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__title .post-type-prefix' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_post_type_prefix_hover_color',
			[
				'label'     => esc_html__( 'Post Type Prefix Hover Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__title a:hover .post-type-prefix' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_meta_color',
			[
				'label'     => esc_html__( 'Meta Text / Icon Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#64748b',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__meta-item'     => 'color: {{VALUE}};',
					'{{WRAPPER}} .list-post-item__meta-item svg' => 'stroke: {{VALUE}}; fill: none;',
				],
			]
		);

		$this->add_control(
			'list_excerpt_color',
			[
				'label'     => esc_html__( 'Excerpt Text Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#475569',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__excerpt' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_badge_bg',
			[
				'label'     => esc_html__( 'Badge Background Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#f1f5f9',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__badge' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_badge_text_color',
			[
				'label'     => esc_html__( 'Badge Text Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#64748b',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__badge' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_read_more_color',
			[
				'label'     => esc_html__( 'Read More Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#2563eb',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__read-more' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'list_read_more_hover_color',
			[
				'label'     => esc_html__( 'Read More Hover Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1d4ed8',
				'selectors' => [
					'{{WRAPPER}} .list-post-item__read-more:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Post type of the Ediciones Voz Catolica catalog, which uses its own view counter.
	 */
	const CATALOG_BOOK_POST_TYPE = 'vcec_book';

	/**
	 * Postmeta where the catalog consolidates each book's view total.
	 */
	const CATALOG_VIEWS_META = '_vcec_view_count';

	/**
	 * Raw view totals for the current request, keyed by post ID.
	 *
	 * @var array
	 */
	private static $views_cache = array();

	/**
	 * Resolved name of the view counter totals table, '' when missing, null when unresolved.
	 *
	 * @var string|null
	 */
	private static $views_table = null;

	/**
	 * Preload view totals for a set of posts with a single query.
	 *
	 * Las vistas no viven en postmeta, así que update_postmeta_cache() no las
	 * cubre y leerlas post por post agregaría una consulta por fila renderizada.
	 *
	 * De dónde salen los números lo decide Views_Source: el contador nuevo si
	 * está disponible, JNews como respaldo. Este widget ya no sabe nada de
	 * ninguno de los dos.
	 *
	 * Los libros del catálogo se saltean: usan un contador aparte que consolida
	 * en postmeta, que update_postmeta_cache() ya precalentó.
	 *
	 * @param array $posts Post objects or post IDs.
	 */
	/**
	 * Vacía la caché de vistas.
	 *
	 * La llama Views_Source al cambiar el alcance de los números: lo que estaba
	 * cacheado corresponde a otro período y volvería a servirse tal cual.
	 */
	public static function reset_views_cache() {
		self::$views_cache = array();
	}

	public static function prime_views_cache( $posts ) {
		$post_ids = array();

		foreach ( (array) $posts as $post ) {
			$post = get_post( $post );
			if ( ! $post || self::CATALOG_BOOK_POST_TYPE === $post->post_type ) {
				continue;
			}
			$post_ids[] = (int) $post->ID;
		}

		$post_ids = array_values( array_unique( array_filter( $post_ids ) ) );
		$post_ids = array_values( array_diff( $post_ids, array_keys( self::$views_cache ) ) );

		if ( empty( $post_ids ) ) {
			return;
		}

		// Un post sin fila simplemente nunca fue visitado.
		foreach ( $post_ids as $post_id ) {
			self::$views_cache[ $post_id ] = 0;
		}

		foreach ( \WpMultiPostTypeBlog\Views_Source::totals_for( $post_ids ) as $post_id => $views ) {
			self::$views_cache[ (int) $post_id ] = (int) $views;
		}
	}

	/**
	 * Read the view total for a single post when the batch query is unavailable.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private static function get_views_fallback( $post_id ) {
		return \WpMultiPostTypeBlog\Views_Source::views_for_post( $post_id );
	}

	/**
	 * Get the raw view count for a post.
	 *
	 * Two counters coexist on the site: catalog books track their own views and
	 * consolidate them into postmeta, everything else goes through JNews View Counter.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type, resolved from the ID when omitted.
	 * @return int
	 */
	public static function get_views_raw( $post_id, $post_type = '' ) {
		$post_id   = absint( $post_id );
		$post_type = $post_type ? $post_type : get_post_type( $post_id );

		if ( self::CATALOG_BOOK_POST_TYPE === $post_type ) {
			// Consolidated by a cron every 10 minutes, so it can lag slightly behind
			// the daily table. Close enough for a listing, and it costs no extra query
			// because update_postmeta_cache() already warmed it.
			$views = (int) get_post_meta( $post_id, self::CATALOG_VIEWS_META, true );
		} else {
			if ( ! isset( self::$views_cache[ $post_id ] ) ) {
				self::prime_views_cache( array( $post_id ) );
			}

			$views = isset( self::$views_cache[ $post_id ] )
				? (int) self::$views_cache[ $post_id ]
				: self::get_views_fallback( $post_id );
		}

		/**
		 * Filter the raw view count before it is formatted for display.
		 *
		 * Returning 0 hides the views item entirely.
		 *
		 * @param int $views   View count.
		 * @param int $post_id Post ID.
		 */
		return (int) apply_filters( 'wp_multipost_blog_views_count', $views, $post_id );
	}

	/**
	 * Get the formatted view count for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type, resolved from the ID when omitted.
	 * @return string Formatted views.
	 */
	public static function get_views_count( $post_id, $post_type = '' ) {
		return number_format_i18n( self::get_views_raw( $post_id, $post_type ) );
	}

	/**
	 * Resolve which image to render for a post.
	 *
	 * Fallback chain: featured image -> custom field configured for the post type ->
	 * image configured for the post type in Settings -> Multi-Post Blog -> generic
	 * placeholder or no image at all, depending on the widget's 'image_fallback' control.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param array    $settings Sanitized render settings.
	 * @return array {
	 *     @type string $type One of 'thumbnail', 'meta', 'icon', 'placeholder', 'none'.
	 *     @type int    $id   Attachment ID, meaningful for 'icon' and 'meta'.
	 *     @type string $url  Image URL, only used by 'meta' when the file is not in the media library.
	 *     @type string $fit  'cover' or 'contain', only meaningful for 'meta'.
	 * }
	 */
	protected static function resolve_image( $post, $settings ) {
		if ( has_post_thumbnail( $post->ID ) ) {
			return array(
				'type' => 'thumbnail',
				'id'   => 0,
				'url'  => '',
				'fit'  => 'cover',
			);
		}

		$meta_image = self::resolve_meta_image( $post );
		if ( $meta_image ) {
			return $meta_image;
		}

		$fallback_id = \WpMultiPostTypeBlog\Admin_Settings::get_image_id( $post->post_type );
		if ( $fallback_id ) {
			return array(
				'type' => 'icon',
				'id'   => $fallback_id,
				'url'  => '',
				'fit'  => 'contain',
			);
		}

		$mode = ! empty( $settings['image_fallback'] ) ? $settings['image_fallback'] : 'hide';

		return array(
			'type' => ( 'placeholder' === $mode ) ? 'placeholder' : 'none',
			'id'   => 0,
			'url'  => '',
			'fit'  => 'cover',
		);
	}

	/**
	 * Look for the post image in the custom fields configured for its post type.
	 *
	 * Post types like the Ediciones catalogue or Simple Download Monitor never write
	 * _thumbnail_id and keep the cover in a meta of their own, so without this step
	 * every entry falls back to the post type logo.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array|null Image descriptor, or null when no field holds a usable image.
	 */
	protected static function resolve_meta_image( $post ) {
		$keys = \WpMultiPostTypeBlog\Admin_Settings::get_meta_keys( $post->post_type );

		foreach ( $keys as $key ) {
			$source = self::parse_image_meta( get_post_meta( $post->ID, $key, true ) );
			if ( ! $source ) {
				continue;
			}

			return array(
				'type' => 'meta',
				'id'   => $source['id'],
				'url'  => $source['url'],
				'fit'  => self::image_fit( $source['id'] ),
			);
		}

		return null;
	}

	/**
	 * Normalize whatever a custom field stores into an attachment ID and/or a URL.
	 *
	 * Handles the formats these fields show up in: a bare attachment ID, a URL, an ACF
	 * image array, and lists where only the first entry is relevant.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array|null { @type int $id, @type string $url }, or null when unusable.
	 */
	protected static function parse_image_meta( $value ) {
		if ( is_array( $value ) ) {
			// ACF returns the whole attachment when the field is set to "image array".
			if ( isset( $value['ID'] ) || isset( $value['id'] ) ) {
				$value = isset( $value['ID'] ) ? $value['ID'] : $value['id'];
			} elseif ( isset( $value['url'] ) ) {
				$value = $value['url'];
			} else {
				$value = reset( $value );
			}
		}

		if ( ! is_scalar( $value ) ) {
			return null;
		}

		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}

		// Gallery-style fields keep a comma separated list; the first image is the cover.
		if ( false !== strpos( $value, ',' ) && ! preg_match( '#^https?://#i', $value ) ) {
			$value = trim( strtok( $value, ',' ) );
		}

		if ( ctype_digit( $value ) ) {
			$id = absint( $value );

			return ( $id && 'attachment' === get_post_type( $id ) )
				? array(
					'id'  => $id,
					'url' => '',
				)
				: null;
		}

		if ( ! preg_match( '#^https?://#i', $value ) ) {
			return null;
		}

		return array(
			'id'  => self::attachment_id_from_url( $value ),
			'url' => $value,
		);
	}

	/**
	 * Attachment ID behind a URL, cached so a listing does not run one query per card.
	 *
	 * @param string $url Image URL.
	 * @return int Attachment ID, 0 when the file is not in the media library.
	 */
	protected static function attachment_id_from_url( $url ) {
		$cache_key = 'wpmb_att_' . md5( $url );
		$cached    = wp_cache_get( $cache_key, 'wpmb' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$id = (int) attachment_url_to_postid( $url );
		wp_cache_set( $cache_key, $id, 'wpmb', HOUR_IN_SECONDS );

		return $id;
	}

	/**
	 * Decide whether an image should be cropped or shown whole.
	 *
	 * Book covers and download sheets are portrait: cropping them to the landscape
	 * frame of a card cuts off the title, so they are contained instead. Landscape
	 * images keep the usual full-bleed crop.
	 *
	 * @param int $attachment_id Attachment ID, 0 when unknown.
	 * @return string 'cover' or 'contain'.
	 */
	protected static function image_fit( $attachment_id ) {
		if ( ! $attachment_id ) {
			// Dimensions unknown: these fields are almost always used for portrait art.
			return 'contain';
		}

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return 'contain';
		}

		// A small margin keeps roughly square images (1:1 logos) out of the crop path.
		return ( $meta['height'] > $meta['width'] * 1.05 ) ? 'contain' : 'cover';
	}

	/**
	 * Render the <img> for an image coming from a custom field.
	 *
	 * @param array  $image   Image descriptor from resolve_image().
	 * @param string $size    Registered image size.
	 * @param string $class   CSS class for the image.
	 * @param string $loading Value for the loading attribute.
	 * @param string $alt     Alternative text.
	 * @return string HTML.
	 */
	protected static function meta_image_html( $image, $size, $class, $loading, $alt ) {
		if ( ! empty( $image['id'] ) ) {
			return wp_get_attachment_image(
				$image['id'],
				$size,
				false,
				array(
					'class'   => $class,
					'loading' => $loading,
					'alt'     => $alt,
				)
			);
		}

		// The file is not in the media library, so there is no srcset to build.
		return sprintf(
			'<img class="%1$s" src="%2$s" alt="%3$s" loading="%4$s" />',
			esc_attr( $class ),
			esc_url( $image['url'] ),
			esc_attr( $alt ),
			esc_attr( $loading )
		);
	}

	/**
	 * Extra CSS class for the image wrapper, so the markup stays readable.
	 *
	 * @param array  $image Image descriptor from resolve_image().
	 * @param string $base  Base wrapper class.
	 * @return string Class attribute suffix, empty when the default treatment applies.
	 */
	protected static function image_wrapper_modifier( $image, $base ) {
		if ( 'icon' === $image['type'] ) {
			return ' ' . $base . '--icon';
		}

		if ( 'meta' === $image['type'] && 'contain' === $image['fit'] ) {
			return ' ' . $base . '--cover';
		}

		return '';
	}

	/**
	 * Check whether a display option is enabled with proper isset checks.
	 *
	 * @param array  $settings Sanitized render settings.
	 * @param string $key      Setting key.
	 * @return bool
	 */
	private static function is_enabled( $settings, $key ) {
		return ! isset( $settings[ $key ] ) || 'yes' === $settings[ $key ];
	}

	/**
	 * Get the first term of the first hierarchical taxonomy of a post (acting as a dynamic badge/category link).
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post Type.
	 * @return array|null Name and link of the term or null.
	 */
	public static function get_primary_category( $post_id, $post_type, $level = 'top' ) {
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		$category_taxonomy = '';

		// Search for standard category first
		if ( isset( $taxonomies['category'] ) ) {
			$category_taxonomy = 'category';
		} else {
			// Find the first hierarchical taxonomy (e.g. portfolio_category, event_cat, etc.)
			foreach ( $taxonomies as $tax ) {
				if ( $tax->hierarchical ) {
					$category_taxonomy = $tax->name;
					break;
				}
			}
		}

		// Fallback to the first available taxonomy if no hierarchical taxonomies exist
		if ( empty( $category_taxonomy ) && ! empty( $taxonomies ) ) {
			$first_tax = reset( $taxonomies );
			$category_taxonomy = $first_tax->name;
		}

		if ( ! empty( $category_taxonomy ) ) {
			$terms = get_the_terms( $post_id, $category_taxonomy );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term = reset( $terms );

				if ( 'top' === $level ) {
					$term = self::root_term( $term, $category_taxonomy );
				}

				$term_link = get_term_link( $term );
				if ( is_wp_error( $term_link ) ) {
					return null;
				}

				return array(
					'name' => $term->name,
					'link' => $term_link,
				);
			}
		}

		return null;
	}

	/**
	 * Climb a term up to the root of its branch.
	 *
	 * get_the_terms() returns terms ordered by name, not by depth, so a post filed
	 * under "Niños > Devociones" hands back the child first purely because of the
	 * alphabet. Walking up to the outermost ancestor gives the section the reader
	 * recognises instead of the narrowest label.
	 *
	 * @param \WP_Term $term     Term to start from.
	 * @param string   $taxonomy Taxonomy name.
	 * @return \WP_Term The topmost ancestor, or the same term when it is already a root.
	 */
	protected static function root_term( $term, $taxonomy ) {
		if ( empty( $term->parent ) ) {
			return $term;
		}

		// Ordered from the closest parent to the outermost ancestor.
		$ancestors = get_ancestors( $term->term_id, $taxonomy, 'taxonomy' );
		if ( empty( $ancestors ) ) {
			return $term;
		}

		$root = get_term( (int) end( $ancestors ), $taxonomy );

		return ( $root && ! is_wp_error( $root ) ) ? $root : $term;
	}

	/**
	 * Render the Featured Post HTML.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_featured_post( $post, $settings = array() ) {
		$post_id      = $post->ID;
		$title        = get_the_title( $post_id );
		$permalink    = get_permalink( $post_id );
		$image_size   = ! empty( $settings['featured_image_size'] ) ? $settings['featured_image_size'] : 'full';
		// Re-sanitized here because render_* is also reached from the AJAX handler.
		$title_tag    = \WpMultiPostTypeBlog\Utils::heading_tag( $settings, 'featured_title_tag', 'h2' );

		$primary_cat  = self::get_primary_category( $post_id, $post->post_type, \WpMultiPostTypeBlog\Utils::category_level( $settings ) );
		$views_raw    = self::get_views_raw( $post_id, $post->post_type );
		$views_count  = number_format_i18n( $views_raw );
		$author_name  = get_the_author_meta( 'display_name', $post->post_author );
		$post_date    = get_the_date( '', $post_id );
		$button_text  = ! empty( $settings['read_more_text'] ) ? $settings['read_more_text'] : esc_html__( 'LEER MÁS', 'wp-multi-post-type-blog' );

		// A zero view count says nothing useful, so the item is dropped rather than shown as "0".
		$show_views   = self::is_enabled( $settings, 'show_views' ) && $views_raw > 0;
		$show_meta    = self::is_enabled( $settings, 'show_author' ) || self::is_enabled( $settings, 'show_date' ) || $show_views;

		$image     = self::resolve_image( $post, $settings );
		$has_image = ( 'none' !== $image['type'] );

		ob_start();
		?>
		<article class="featured-post<?php echo $has_image ? '' : ' featured-post--no-image'; ?>">
			<?php if ( $has_image ) : ?>
				<div class="featured-post__image-wrapper<?php echo esc_attr( self::image_wrapper_modifier( $image, 'featured-post__image-wrapper' ) ); ?>">
					<a href="<?php echo esc_url( $permalink ); ?>">
						<?php if ( 'thumbnail' === $image['type'] ) : ?>
							<?php echo get_the_post_thumbnail( $post_id, $image_size, array( 'class' => 'featured-post__image', 'loading' => 'eager' ) ); ?>
						<?php elseif ( 'meta' === $image['type'] ) : ?>
							<?php echo self::meta_image_html( $image, $image_size, 'featured-post__image', 'eager', $title ); ?>
						<?php elseif ( 'icon' === $image['type'] ) : ?>
							<?php echo wp_get_attachment_image( $image['id'], $image_size, false, array( 'class' => 'featured-post__image', 'loading' => 'eager', 'alt' => '' ) ); ?>
						<?php else : ?>
							<img class="featured-post__image" src="<?php echo esc_url( WP_MULTIPOST_BLOG_URL . 'assets/images/placeholder.jpg' ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="eager" />
						<?php endif; ?>
					</a>
				</div>
			<?php endif; ?>
			<div class="featured-post__card">
				<?php if ( $primary_cat && self::is_enabled( $settings, 'show_category' ) ) : ?>
					<span class="featured-post__badge">
						<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
					</span>
				<?php endif; ?>
				
				<<?php echo $title_tag; ?> class="featured-post__title">
					<a href="<?php echo esc_url( $permalink ); ?>">
						<?php if ( 'post' !== $post->post_type ) : ?>
							<?php
							$post_type_obj = get_post_type_object( $post->post_type );
							$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
							?>
							<span class="post-type-prefix"><?php echo esc_html( $post_type_label ); ?>:</span>
						<?php endif; ?>
						<?php echo esc_html( $title ); ?>
					</a>
				</<?php echo $title_tag; ?>>
				
				<?php if ( $show_meta ) : ?>
					<div class="featured-post__meta">
						<?php if ( self::is_enabled( $settings, 'show_author' ) ) : ?>
							<span class="featured-post__meta-item featured-post__meta-author">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								<?php echo esc_html__( 'POR ', 'wp-multi-post-type-blog' ); ?><?php echo esc_html( $author_name ); ?>
							</span>
						<?php endif; ?>
						<?php if ( self::is_enabled( $settings, 'show_date' ) ) : ?>
							<span class="featured-post__meta-item featured-post__meta-date">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
								<?php echo esc_html( $post_date ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $show_views ) : ?>
							<span class="featured-post__meta-item featured-post__meta-views">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<?php echo esc_html( $views_count ); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				
				<div class="featured-post__button-container">
					<a href="<?php echo esc_url( $permalink ); ?>" class="featured-post__button">
						<?php echo esc_html( $button_text ); ?>
					</a>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a List Post Item HTML.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public static function render_list_post( $post, $settings = array() ) {
		$post_id      = $post->ID;
		$title        = get_the_title( $post_id );
		$permalink    = get_permalink( $post_id );
		$image_size   = ! empty( $settings['list_image_size'] ) ? $settings['list_image_size'] : 'medium_large';
		// Re-sanitized here because render_* is also reached from the AJAX handler.
		$title_tag    = \WpMultiPostTypeBlog\Utils::heading_tag( $settings, 'list_title_tag', 'h3' );

		$primary_cat  = self::get_primary_category( $post_id, $post->post_type, \WpMultiPostTypeBlog\Utils::category_level( $settings ) );
		$views_raw    = self::get_views_raw( $post_id, $post->post_type );
		$views_count  = number_format_i18n( $views_raw );
		$author_name  = get_the_author_meta( 'display_name', $post->post_author );
		$post_date    = get_the_date( '', $post_id );
		$button_text  = ! empty( $settings['read_more_text'] ) ? $settings['read_more_text'] : esc_html__( 'LEER MÁS', 'wp-multi-post-type-blog' );
		$layout_type  = ! empty( $settings['layout_type'] ) ? $settings['layout_type'] : 'classic';

		// The compact layout hides the excerpt and the read more link, so skip building them.
		$is_compact = ( 'compact' === $layout_type );

		// A zero view count says nothing useful, so the item is dropped rather than shown as "0".
		$show_views    = self::is_enabled( $settings, 'show_views' ) && $views_raw > 0;
		$show_cat_meta = $is_compact && $primary_cat && self::is_enabled( $settings, 'show_category' );
		$show_meta     = self::is_enabled( $settings, 'show_author' ) || self::is_enabled( $settings, 'show_date' ) || $show_views || $show_cat_meta;

		// Truncate excerpt without breaking multibyte characters.
		$excerpt_words = isset( $settings['excerpt_words'] ) ? intval( $settings['excerpt_words'] ) : 30;
		$excerpt       = ( ! $is_compact && $excerpt_words > 0 ) ? wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), $excerpt_words, '...' ) : '';

		$image     = self::resolve_image( $post, $settings );
		$has_image = ( 'none' !== $image['type'] );
		$show_badge = ( ! $is_compact && $primary_cat && self::is_enabled( $settings, 'show_category' ) );

		ob_start();
		?>
		<article class="list-post-item<?php echo $has_image ? '' : ' list-post-item--no-image'; ?>">
			<?php if ( $has_image ) : ?>
				<div class="list-post-item__image-wrapper<?php echo esc_attr( self::image_wrapper_modifier( $image, 'list-post-item__image-wrapper' ) ); ?>">
					<a href="<?php echo esc_url( $permalink ); ?>">
						<?php if ( 'thumbnail' === $image['type'] ) : ?>
							<?php echo get_the_post_thumbnail( $post_id, $image_size, array( 'class' => 'list-post-item__image', 'loading' => 'lazy' ) ); ?>
						<?php elseif ( 'meta' === $image['type'] ) : ?>
							<?php echo self::meta_image_html( $image, $image_size, 'list-post-item__image', 'lazy', $title ); ?>
						<?php elseif ( 'icon' === $image['type'] ) : ?>
							<?php echo wp_get_attachment_image( $image['id'], $image_size, false, array( 'class' => 'list-post-item__image', 'loading' => 'lazy', 'alt' => '' ) ); ?>
						<?php else : ?>
							<img class="list-post-item__image" src="<?php echo esc_url( WP_MULTIPOST_BLOG_URL . 'assets/images/placeholder.jpg' ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
						<?php endif; ?>
					</a>
					<?php if ( $show_badge ) : ?>
						<span class="list-post-item__badge">
							<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="list-post-item__content">
				<?php if ( $show_badge && ! $has_image ) : ?>
					<?php /* No image wrapper to overlay the badge on, so render it inline. */ ?>
					<span class="list-post-item__badge list-post-item__badge--inline">
						<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
					</span>
				<?php endif; ?>
				<<?php echo $title_tag; ?> class="list-post-item__title">
					<a href="<?php echo esc_url( $permalink ); ?>">
						<?php if ( 'post' !== $post->post_type ) : ?>
							<?php
							$post_type_obj = get_post_type_object( $post->post_type );
							$post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
							?>
							<span class="post-type-prefix"><?php echo esc_html( $post_type_label ); ?>:</span>
						<?php endif; ?>
						<?php echo esc_html( $title ); ?>
					</a>
				</<?php echo $title_tag; ?>>
				
				<?php if ( $show_meta ) : ?>
					<div class="list-post-item__meta">
						<?php if ( self::is_enabled( $settings, 'show_author' ) ) : ?>
							<span class="list-post-item__meta-item list-post-item__meta-author">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								<?php echo $is_compact ? '' : esc_html__( 'POR ', 'wp-multi-post-type-blog' ); ?><?php echo esc_html( $author_name ); ?>
							</span>
						<?php endif; ?>
						<?php if ( self::is_enabled( $settings, 'show_date' ) ) : ?>
							<span class="list-post-item__meta-item list-post-item__meta-date">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
								<?php echo esc_html( $post_date ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $show_cat_meta ) : ?>
							<span class="list-post-item__meta-item list-post-item__meta-category">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
								<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
							</span>
						<?php endif; ?>
						<?php if ( $show_views ) : ?>
							<span class="list-post-item__meta-item list-post-item__meta-views">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
								<?php echo esc_html( $views_count ); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				
				<?php if ( self::is_enabled( $settings, 'show_excerpt' ) && ! empty( $excerpt ) ) : ?>
					<p class="list-post-item__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
				
				<?php if ( ! $is_compact ) : ?>
					<div class="list-post-item__button-container">
						<a href="<?php echo esc_url( $permalink ); ?>" class="list-post-item__read-more">
							<?php echo esc_html( $button_text ); ?>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="arrow-icon"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the widget layout HTML (shared between Blog_Posts_Widget and Blog_Archive_Widget).
	 */
	protected function render_widget_html( $widget_id, $settings, $query, $paged, $max_pages, $settings_signature, $columns_class, $layout_type_class, $extra_classes = '', $active_post_types = array() ) {
		$pagination = $settings['pagination'];
		?>
		<div id="wp-multipost-blog-<?php echo esc_attr( $widget_id ); ?>" 
			class="premium-blog-widget <?php echo esc_attr( $extra_classes ); ?> <?php echo esc_attr( $columns_class ); ?> <?php echo esc_attr( $layout_type_class ); ?>"
			data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
			data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>"
			data-settings-signature="<?php echo esc_attr( $settings_signature ); ?>"
			data-pagination="<?php echo esc_attr( $pagination ); ?>"
			data-max-pages="<?php echo esc_attr( $max_pages ); ?>"
			data-current-page="<?php echo esc_attr( $paged ); ?>">
			
			<?php if ( ! empty( $active_post_types ) && count( $active_post_types ) > 1 ) : ?>
				<div class="premium-blog-archive__filters">
					<button class="filter-tab active" data-post-type="" aria-label="<?php esc_attr_e( 'Todos', 'wp-multi-post-type-blog' ); ?>">
						<?php esc_html_e( 'Todos', 'wp-multi-post-type-blog' ); ?>
					</button>
					<?php foreach ( $active_post_types as $pt ) : ?>
						<?php $pt_obj = get_post_type_object( $pt ); ?>
						<?php if ( $pt_obj ) : ?>
							<button class="filter-tab" data-post-type="<?php echo esc_attr( $pt ); ?>" aria-label="<?php echo esc_attr( $pt_obj->labels->name ); ?>">
								<?php echo esc_html( $pt_obj->labels->name ); ?>
							</button>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="premium-blog-widget__container">
				<?php
				$count         = 0;
				$featured_html = '';
				$list_html     = '';
				while ( $query->have_posts() ) {
					$query->the_post();
					$count++;

					if ( 1 === $paged && 1 === $count && 'yes' === $settings['show_featured'] ) {
						// Registrado aquí, antes de que Elementor pase al siguiente widget,
						// para que los de más abajo puedan excluirlo de su consulta.
						\WpMultiPostTypeBlog\Utils::register_featured_id( get_the_ID() );
						$featured_html = self::render_featured_post( get_post(), $settings );
						continue;
					}

					$list_html .= self::render_list_post( get_post(), $settings );
				}

				// The list container is always rendered, even when empty, so AJAX pagination
				// and the archive filter tabs always have a target to append into.
				echo $featured_html;
				echo '<div class="premium-blog-widget__list list-posts">' . $list_html . '</div>';
				?>
			</div>

			<?php if ( 'numbers' === $pagination && $max_pages > 1 ) : ?>
				<div class="premium-blog-widget__pagination numbers-pagination">
					<?php
					$page_var = 'wpmpb_' . $widget_id . '_page';
					echo paginate_links( array(
						'base'      => esc_url_raw( add_query_arg( $page_var, '%#%' ) ),
						'format'    => '',
						'current'   => max( 1, $paged ),
						'total'     => $max_pages,
						'prev_text' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Anterior',
						'next_text' => 'Siguiente <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
					) );
					?>
				</div>
			<?php endif; ?>

			<?php $btn_text = $settings['load_more_text']; ?>
			<div class="premium-blog-widget__pagination-ajax ajax-pagination" style="<?php echo ( 'load_more' === $pagination && $max_pages > 1 && $paged < $max_pages ) ? '' : 'display: none;'; ?>">
				<button class="wp-multipost-blog-load-more-btn" aria-label="<?php echo esc_attr( $btn_text ); ?>">
					<span class="btn-text"><?php echo esc_html( $btn_text ); ?></span>
					<span class="btn-spinner"></span>
				</button>
			</div>

			<div class="premium-blog-widget__infinite-trigger infinite-pagination" style="<?php echo ( 'infinite' === $pagination && $max_pages > 1 && $paged < $max_pages ) ? '' : 'display: none;'; ?>">
				<div class="infinite-loader-spinner"></div>
			</div>

			<div class="premium-blog-widget__no-more" style="display: none; text-align: center; margin-top: 30px; font-weight: 600; color: #64748b;">
				<?php esc_html_e( 'Has llegado al final.', 'wp-multi-post-type-blog' ); ?>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render the widget content.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$widget_id = $this->get_id();
		$page_var  = 'wpmpb_' . $widget_id . '_page';

		$paged = isset( $_GET[ $page_var ] ) ? max( 1, absint( wp_unslash( $_GET[ $page_var ] ) ) ) : 1;

		// Only singular views have a "current post". On archives get_queried_object_id()
		// returns a term or user ID, which would exclude an unrelated post by that ID.
		$settings['current_post_id'] = is_singular() ? get_queried_object_id() : 0;
		$settings = \WpMultiPostTypeBlog\Utils::sanitize_settings( $settings );
		$settings = \WpMultiPostTypeBlog\Utils::apply_featured_exclusions( $settings );

		$query = new \WP_Query( \WpMultiPostTypeBlog\Utils::build_query_args( $settings, $paged ) );
		$max_pages = \WpMultiPostTypeBlog\Utils::get_max_pages( $query, $settings );

		if ( ! $query->have_posts() ) {
			echo '<div class="premium-blog-no-posts">' . esc_html__( 'No se encontraron publicaciones.', 'wp-multi-post-type-blog' ) . '</div>';
			return;
		}

		// 5.1: Pre-cache postmeta before starting loop
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $post_ids ) ) {
			update_postmeta_cache( $post_ids );

			// Views live in their own tables, so they need a separate batch query.
			if ( 'yes' === $settings['show_views'] ) {
				// El alcance del número mostrado sigue al del ordenamiento.
				\WpMultiPostTypeBlog\Views_Source::set_display_range( $settings );
				self::prime_views_cache( $query->posts );
			}
		}

		$settings_signature = \WpMultiPostTypeBlog\Utils::sign_settings( $settings );
		$columns_class = 'premium-blog-widget--columns-' . intval( $settings['columns'] );
		$layout_type_class = 'premium-blog-widget--layout-' . sanitize_html_class( $settings['layout_type'] );

		$this->render_widget_html( $widget_id, $settings, $query, $paged, $max_pages, $settings_signature, $columns_class, $layout_type_class );
	}
}
