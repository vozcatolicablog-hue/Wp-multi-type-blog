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
	 * Retrieve the list of active public post types.
	 *
	 * @return array Post types key-value pairs.
	 */
	private function get_all_post_types() {
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
	 * Retrieve the list of authors/users.
	 *
	 * @return array Authors key-value pairs.
	 */
	private function get_all_authors() {
		$users = get_users( array(
			'who'                 => 'authors',
			'has_published_posts' => true,
			'fields'              => array( 'ID', 'display_name' ),
		) );
		$options = array();
		foreach ( $users as $user ) {
			$options[ $user->ID ] = $user->display_name;
		}
		return $options;
	}

	/**
	 * Retrieve all public taxonomy terms.
	 *
	 * @return array Terms key-value pairs grouped by taxonomy.
	 */
	private function get_all_taxonomy_terms() {
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
				],
				'description' => esc_html__( 'Random ordering can be slower on large sites.', 'wp-multi-post-type-blog' ),
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
					'{{WRAPPER}} .badge' => 'background-color: {{VALUE}};',
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
					'{{WRAPPER}} .badge' => 'color: {{VALUE}};',
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
	 * Fallback method to get compatible view counts from JNews views meta or others.
	 *
	 * @param int $post_id Post ID.
	 * @return string Formatted views.
	 */
	public static function get_views_count( $post_id ) {
		// Try JNews popular view key
		$views = get_post_meta( $post_id, 'jeg_views', true );
		if ( ! $views ) {
			// Try JNews secondary key
			$views = get_post_meta( $post_id, 'jnews_views', true );
		}
		if ( ! $views ) {
			// Try standard plugin key
			$views = get_post_meta( $post_id, 'post_views_count', true );
		}

		// Check if JNews function exists
		if ( ! $views && function_exists( 'jnews_get_views' ) ) {
			$views = jnews_get_views( $post_id );
		}

		return $views ? number_format_i18n( intval( $views ) ) : '0';
	}

	/**
	 * Check whether a display option is enabled.
	 *
	 * @param array  $settings Sanitized render settings.
	 * @param string $key      Setting key.
	 * @return bool
	 */
	private static function is_enabled( $settings, $key ) {
		return empty( $settings[ $key ] ) || 'yes' === $settings[ $key ];
	}

	/**
	 * Get the first term of the first hierarchical taxonomy of a post (acting as a dynamic badge/category link).
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post Type.
	 * @return array|null Name and link of the term or null.
	 */
	public static function get_primary_category( $post_id, $post_type ) {
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
				$first_term = reset( $terms );
				$term_link  = get_term_link( $first_term );
				if ( is_wp_error( $term_link ) ) {
					return null;
				}

				return array(
					'name' => $first_term->name,
					'link' => $term_link,
				);
			}
		}

		return null;
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
		$thumb_url    = get_the_post_thumbnail_url( $post_id, $image_size );
		if ( ! $thumb_url ) {
			$thumb_url = esc_url( WP_MULTIPOST_BLOG_URL . 'assets/images/placeholder.png' );
		}
		
		$primary_cat  = self::get_primary_category( $post_id, $post->post_type );
		$views_count  = self::get_views_count( $post_id );
		$author_name  = get_the_author_meta( 'display_name', $post->post_author );
		$post_date    = get_the_date( '', $post_id );
		$button_text  = ! empty( $settings['read_more_text'] ) ? $settings['read_more_text'] : esc_html__( 'LEER MÁS', 'wp-multi-post-type-blog' );
		$show_meta    = self::is_enabled( $settings, 'show_author' ) || self::is_enabled( $settings, 'show_date' ) || self::is_enabled( $settings, 'show_views' );

		ob_start();
		?>
		<article class="featured-post">
			<div class="featured-post__image-wrapper">
				<a href="<?php echo esc_url( $permalink ); ?>">
					<img class="featured-post__image" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
				</a>
			</div>
			<div class="featured-post__card">
				<?php if ( $primary_cat && self::is_enabled( $settings, 'show_category' ) ) : ?>
					<span class="featured-post__badge">
						<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
					</span>
				<?php endif; ?>
				
				<h2 class="featured-post__title">
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
				</h2>
				
				<?php if ( $show_meta ) : ?>
					<div class="featured-post__meta">
						<?php if ( self::is_enabled( $settings, 'show_author' ) ) : ?>
							<span class="featured-post__meta-item featured-post__meta-author">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								POR <?php echo esc_html( $author_name ); ?>
							</span>
						<?php endif; ?>
						<?php if ( self::is_enabled( $settings, 'show_date' ) ) : ?>
							<span class="featured-post__meta-item featured-post__meta-date">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
								<?php echo esc_html( $post_date ); ?>
							</span>
						<?php endif; ?>
						<?php if ( self::is_enabled( $settings, 'show_views' ) ) : ?>
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
		$thumb_url    = get_the_post_thumbnail_url( $post_id, $image_size );
		if ( ! $thumb_url ) {
			$thumb_url = esc_url( WP_MULTIPOST_BLOG_URL . 'assets/images/placeholder.png' );
		}

		$primary_cat  = self::get_primary_category( $post_id, $post->post_type );
		$views_count  = self::get_views_count( $post_id );
		$author_name  = get_the_author_meta( 'display_name', $post->post_author );
		$post_date    = get_the_date( '', $post_id );
		$button_text  = ! empty( $settings['read_more_text'] ) ? $settings['read_more_text'] : esc_html__( 'LEER MÁS', 'wp-multi-post-type-blog' );
		$show_meta    = self::is_enabled( $settings, 'show_author' ) || self::is_enabled( $settings, 'show_date' ) || self::is_enabled( $settings, 'show_views' );
		
		// Truncate excerpt without breaking multibyte characters.
		$excerpt_words = isset( $settings['excerpt_words'] ) ? intval( $settings['excerpt_words'] ) : 30;
		$excerpt       = $excerpt_words > 0 ? wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), $excerpt_words, '...' ) : '';

		ob_start();
		?>
		<article class="list-post-item">
			<div class="list-post-item__image-wrapper">
				<a href="<?php echo esc_url( $permalink ); ?>">
					<img class="list-post-item__image" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
				</a>
				<?php if ( $primary_cat && self::is_enabled( $settings, 'show_category' ) ) : ?>
					<span class="list-post-item__badge badge">
						<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
					</span>
				<?php endif; ?>
			</div>
			<div class="list-post-item__content">
				<h3 class="list-post-item__title">
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
				</h3>
				
				<?php if ( $show_meta ) : ?>
					<div class="list-post-item__meta">
						<?php if ( self::is_enabled( $settings, 'show_author' ) ) : ?>
							<span class="list-post-item__meta-item list-post-item__meta-author">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
								POR <?php echo esc_html( $author_name ); ?>
							</span>
						<?php endif; ?>
						<?php if ( self::is_enabled( $settings, 'show_date' ) ) : ?>
							<span class="list-post-item__meta-item list-post-item__meta-date">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
								<?php echo esc_html( $post_date ); ?>
							</span>
						<?php endif; ?>
						<?php if ( self::is_enabled( $settings, 'show_views' ) ) : ?>
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
				
				<div class="list-post-item__button-container">
					<a href="<?php echo esc_url( $permalink ); ?>" class="list-post-item__read-more">
						<?php echo esc_html( $button_text ); ?>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="arrow-icon"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
					</a>
				</div>
			</div>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the widget content.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$widget_id = $this->get_id();
		$page_var  = 'wpmpb_' . $widget_id . '_page';

		$paged = isset( $_GET[ $page_var ] ) ? max( 1, absint( wp_unslash( $_GET[ $page_var ] ) ) ) : 1;

		$settings['current_post_id'] = get_queried_object_id();
		$settings = wp_multipost_blog_sanitize_settings( $settings );
		$pagination = $settings['pagination'];

		$query = new \WP_Query( wp_multipost_blog_build_query_args( $settings, $paged ) );
		$max_pages = wp_multipost_blog_get_max_pages( $query, $settings );

		if ( ! $query->have_posts() ) {
			echo '<div class="premium-blog-no-posts">' . esc_html__( 'No se encontraron publicaciones.', 'wp-multi-post-type-blog' ) . '</div>';
			wp_reset_postdata();
			return;
		}

		$settings_signature = wp_multipost_blog_sign_settings( $settings );
		$columns_class = 'premium-blog-widget--columns-' . intval( $settings['columns'] );
		?>
		<div id="wp-multipost-blog-<?php echo esc_attr( $widget_id ); ?>" 
			class="premium-blog-widget <?php echo esc_attr( $columns_class ); ?>"
			data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
			data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>"
			data-settings-signature="<?php echo esc_attr( $settings_signature ); ?>"
			data-pagination="<?php echo esc_attr( $pagination ); ?>"
			data-max-pages="<?php echo esc_attr( $max_pages ); ?>"
			data-current-page="<?php echo esc_attr( $paged ); ?>">
			
			<div class="premium-blog-widget__container">
				<?php
				$count     = 0;
				$list_open = false;
				while ( $query->have_posts() ) {
					$query->the_post();
					$count++;

					if ( 1 === $paged && 1 === $count && 'yes' === $settings['show_featured'] ) {
						echo self::render_featured_post( get_post(), $settings );
						continue;
					}

					if ( ! $list_open ) {
						echo '<div class="premium-blog-widget__list list-posts">';
						$list_open = true;
					}

					echo self::render_list_post( get_post(), $settings );
				}

				if ( $list_open ) {
					echo '</div>';
				}
				?>
			</div>

			<?php
			// Standard numerical pagination
			?>
			<div class="premium-blog-widget__pagination numbers-pagination" style="<?php echo ( 'numbers' === $pagination && $max_pages > 1 ) ? '' : 'display: none;'; ?>">
				<?php
				if ( 'numbers' === $pagination && $max_pages > 1 ) {
					echo paginate_links( array(
						'base'      => esc_url_raw( add_query_arg( $page_var, '%#%' ) ),
						'format'    => '',
						'current'   => max( 1, $paged ),
						'total'     => $max_pages,
						'prev_text' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Anterior',
						'next_text' => 'Siguiente <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
					) );
				}
				?>
			</div>

			<?php
			// AJAX Load More Button
			$btn_text = $settings['load_more_text'];
			?>
			<div class="premium-blog-widget__pagination-ajax ajax-pagination" style="<?php echo ( 'load_more' === $pagination && $max_pages > 1 && $paged < $max_pages ) ? '' : 'display: none;'; ?>">
				<button class="wp-multipost-blog-load-more-btn" aria-label="<?php echo esc_attr( $btn_text ); ?>">
					<span class="btn-text"><?php echo esc_html( $btn_text ); ?></span>
					<span class="btn-spinner"></span>
				</button>
			</div>

			<?php
			// Infinite Scroll Loader
			?>
			<div class="premium-blog-widget__infinite-trigger infinite-pagination" style="<?php echo ( 'infinite' === $pagination && $max_pages > 1 && $paged < $max_pages ) ? '' : 'display: none;'; ?>">
				<div class="infinite-loader-spinner"></div>
			</div>
			?>
		</div>
		<?php
		wp_reset_postdata();
	}
}
