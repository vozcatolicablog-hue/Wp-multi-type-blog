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
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
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
	 * Retrieve the list of authors/users.
	 *
	 * @return array Authors key-value pairs.
	 */
	private function get_all_authors() {
		$users = get_users( array(
			'role__in' => array( 'Administrator', 'Editor', 'Author', 'Contributor' ),
			'fields'   => array( 'ID', 'display_name' ),
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
	public static function render_featured_post( $post ) {
		$post_id      = $post->ID;
		$title        = get_the_title( $post_id );
		$permalink    = get_permalink( $post_id );
		$thumb_url    = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( ! $thumb_url ) {
			$thumb_url = esc_url( WP_MULTIPOST_BLOG_URL . 'assets/images/placeholder.png' );
		}
		
		$primary_cat  = self::get_primary_category( $post_id, $post->post_type );
		$views_count  = self::get_views_count( $post_id );
		$author_name  = get_the_author_meta( 'display_name', $post->post_author );
		$post_date    = get_the_date( '', $post_id );

		ob_start();
		?>
		<article class="featured-post">
			<div class="featured-post__image-wrapper">
				<a href="<?php echo esc_url( $permalink ); ?>">
					<img class="featured-post__image" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
				</a>
			</div>
			<div class="featured-post__card">
				<?php if ( $primary_cat ) : ?>
					<span class="featured-post__badge">
						<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
					</span>
				<?php endif; ?>
				
				<h2 class="featured-post__title">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
				</h2>
				
				<div class="featured-post__meta">
					<span class="featured-post__meta-item featured-post__meta-author">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
						POR <?php echo esc_html( $author_name ); ?>
					</span>
					<span class="featured-post__meta-item featured-post__meta-date">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
						<?php echo esc_html( $post_date ); ?>
					</span>
					<span class="featured-post__meta-item featured-post__meta-views">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
						<?php echo esc_html( $views_count ); ?>
					</span>
				</div>
				
				<div class="featured-post__button-container">
					<a href="<?php echo esc_url( $permalink ); ?>" class="featured-post__button">
						<?php esc_html_e( 'LEER MÁS', 'wp-multi-post-type-blog' ); ?>
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
	public static function render_list_post( $post ) {
		$post_id      = $post->ID;
		$title        = get_the_title( $post_id );
		$permalink    = get_permalink( $post_id );
		$thumb_url    = get_the_post_thumbnail_url( $post_id, 'medium_large' );
		if ( ! $thumb_url ) {
			$thumb_url = esc_url( WP_MULTIPOST_BLOG_URL . 'assets/images/placeholder.png' );
		}

		$primary_cat  = self::get_primary_category( $post_id, $post->post_type );
		$views_count  = self::get_views_count( $post_id );
		$author_name  = get_the_author_meta( 'display_name', $post->post_author );
		$post_date    = get_the_date( '', $post_id );
		
		// Truncate excerpt without breaking multibyte characters.
		$excerpt = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), 30, '...' );

		ob_start();
		?>
		<article class="list-post-item">
			<div class="list-post-item__image-wrapper">
				<a href="<?php echo esc_url( $permalink ); ?>">
					<img class="list-post-item__image" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" />
				</a>
				<?php if ( $primary_cat ) : ?>
					<span class="list-post-item__badge badge">
						<a href="<?php echo esc_url( $primary_cat['link'] ); ?>"><?php echo esc_html( $primary_cat['name'] ); ?></a>
					</span>
				<?php endif; ?>
			</div>
			<div class="list-post-item__content">
				<h3 class="list-post-item__title">
					<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
				</h3>
				
				<div class="list-post-item__meta">
					<span class="list-post-item__meta-item list-post-item__meta-author">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
						POR <?php echo esc_html( $author_name ); ?>
					</span>
					<span class="list-post-item__meta-item list-post-item__meta-date">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
						<?php echo esc_html( $post_date ); ?>
					</span>
					<span class="list-post-item__meta-item list-post-item__meta-views">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
						<?php echo esc_html( $views_count ); ?>
					</span>
				</div>
				
				<?php if ( ! empty( $excerpt ) ) : ?>
					<p class="list-post-item__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
				
				<div class="list-post-item__button-container">
					<a href="<?php echo esc_url( $permalink ); ?>" class="list-post-item__read-more">
						<?php esc_html_e( 'LEER MÁS', 'wp-multi-post-type-blog' ); ?>
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

		// Get current page
		if ( get_query_var( 'paged' ) ) {
			$paged = get_query_var( 'paged' );
		} elseif ( get_query_var( 'page' ) ) {
			$paged = get_query_var( 'page' );
		} else {
			$paged = 1;
		}

		// Extract and validate settings.
		$post_types     = ! empty( $settings['post_types'] ) ? array_map( 'sanitize_key', (array) $settings['post_types'] ) : [ 'post' ];
		$post_types     = wp_multipost_blog_validate_post_types( $post_types );
		$authors        = ! empty( $settings['authors'] ) ? array_values( array_filter( array_map( 'absint', (array) $settings['authors'] ) ) ) : [];
		$terms          = ! empty( $settings['terms'] ) ? array_map( 'sanitize_text_field', (array) $settings['terms'] ) : [];
		$posts_per_page = ! empty( $settings['posts_per_page'] ) ? intval( $settings['posts_per_page'] ) : 5;
		$posts_per_page = max( 1, min( 100, $posts_per_page ) );
		$orderby        = ! empty( $settings['orderby'] ) ? sanitize_key( $settings['orderby'] ) : 'date';
		$order          = ! empty( $settings['order'] ) ? strtoupper( sanitize_key( $settings['order'] ) ) : 'DESC';
		$pagination     = ! empty( $settings['pagination'] ) ? sanitize_key( $settings['pagination'] ) : 'none';

		if ( ! in_array( $orderby, array( 'date', 'title', 'rand', 'comment_count', 'menu_order' ), true ) ) {
			$orderby = 'date';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		if ( ! in_array( $pagination, array( 'none', 'numbers', 'load_more', 'infinite' ), true ) ) {
			$pagination = 'none';
		}

		// Build WP_Query
		$query_args = array(
			'post_type'      => $post_types,
			'posts_per_page' => $posts_per_page,
			'paged'          => $paged,
			'post_status'    => 'publish',
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( ! empty( $authors ) ) {
			$query_args['author__in'] = array_map( 'intval', $authors );
		}

		if ( ! empty( $terms ) ) {
			$tax_query = wp_multipost_blog_build_tax_query( $terms );
			if ( ! empty( $tax_query ) ) {
				$query_args['tax_query'] = $tax_query;
			}
		}

		$query = new \WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			echo '<div class="premium-blog-no-posts">' . esc_html__( 'No se encontraron publicaciones.', 'wp-multi-post-type-blog' ) . '</div>';
			return;
		}

		// Prepare settings to pass to AJAX javascript (sanitized)
		$ajax_settings = array(
			'post_types'     => $post_types,
			'authors'        => $authors,
			'terms'          => $terms,
			'orderby'        => $orderby,
			'order'          => $order,
			'posts_per_page' => $posts_per_page,
		);

		$widget_id = $this->get_id();
		?>
		<div id="wp-multipost-blog-<?php echo esc_attr( $widget_id ); ?>" 
			class="premium-blog-widget"
			data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
			data-settings="<?php echo esc_attr( wp_json_encode( $ajax_settings ) ); ?>"
			data-pagination="<?php echo esc_attr( $pagination ); ?>"
			data-max-pages="<?php echo esc_attr( $query->max_num_pages ); ?>"
			data-current-page="<?php echo esc_attr( $paged ); ?>">
			
			<div class="premium-blog-widget__container">
				<?php
				$count = 0;
				while ( $query->have_posts() ) {
					$query->the_post();
					$count++;

					// Only display the featured post at the top on the very first page
					if ( 1 === $paged && 1 === $count ) {
						echo self::render_featured_post( get_post() );
						// Open list container
						echo '<div class="premium-blog-widget__list list-posts">';
					} else {
						// If page 1, we already opened list-posts. If page > 1, make sure it's open if it's the first post.
						if ( $paged > 1 && 1 === $count ) {
							echo '<div class="premium-blog-widget__list list-posts">';
						}
						echo self::render_list_post( get_post() );
					}
				}
				// Close list container
				echo '</div>'; 
				?>
			</div>

			<?php
			// Standard numerical pagination
			if ( 'numbers' === $pagination && $query->max_num_pages > 1 ) {
				echo '<div class="premium-blog-widget__pagination numbers-pagination">';
				echo paginate_links( array(
					'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
					'format'    => '?paged=%#%',
					'current'   => max( 1, $paged ),
					'total'     => $query->max_num_pages,
					'prev_text' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Anterior',
					'next_text' => 'Siguiente <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
				) );
				echo '</div>';
			}

			// AJAX Load More Button
			if ( 'load_more' === $pagination && $query->max_num_pages > 1 && $paged < $query->max_num_pages ) {
				$btn_text = ! empty( $settings['load_more_text'] ) ? $settings['load_more_text'] : esc_html__( 'Cargar Más', 'wp-multi-post-type-blog' );
				?>
				<div class="premium-blog-widget__pagination-ajax ajax-pagination">
					<button class="wp-multipost-blog-load-more-btn" aria-label="<?php echo esc_attr( $btn_text ); ?>">
						<span class="btn-text"><?php echo esc_html( $btn_text ); ?></span>
						<span class="btn-spinner"></span>
					</button>
				</div>
				<?php
			}

			// Infinite Scroll Loader
			if ( 'infinite' === $pagination && $query->max_num_pages > 1 && $paged < $query->max_num_pages ) {
				?>
				<div class="premium-blog-widget__infinite-trigger infinite-pagination">
					<div class="infinite-loader-spinner"></div>
				</div>
				<?php
			}
			?>
		</div>
		<?php
		wp_reset_postdata();
	}
}
