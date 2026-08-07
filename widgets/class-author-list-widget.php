<?php
namespace WpMultiPostTypeBlog\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Premium Author List Elementor Widget.
 *
 * Lists authors with their most recent entry. Unlike the usual author-avatar
 * widgets it resolves "most recent" across every post type selected, so blogs,
 * downloads and catalogue entries all count as activity.
 */
class Author_List_Widget extends Widget_Base {

	/**
	 * Ceiling on how many authors a single widget may render.
	 *
	 * The query is driven from the posts table, so the candidate set is already
	 * small, but a runaway "number" setting should not be able to fan out into
	 * hundreds of avatar lookups.
	 */
	const MAX_AUTHORS = 100;

	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'wp_multi_post_type_author_list';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Premium Author List', 'wp-multi-post-type-blog' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-person';
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
	 * Selectable post types.
	 *
	 * @return array
	 */
	private function get_post_type_options() {
		$post_types = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'objects'
		);
		unset( $post_types['attachment'] );

		$options = array();
		foreach ( $post_types as $post_type ) {
			$options[ $post_type->name ] = $post_type->label;
		}

		return $options;
	}

	/**
	 * Selectable roles.
	 *
	 * @return array
	 */
	private function get_role_options() {
		$roles = wp_roles()->get_names();

		return array_map( 'translate_user_role', $roles );
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {
		$this->register_query_controls();
		$this->register_display_controls();
		$this->register_style_controls();
	}

	/**
	 * Which authors to list.
	 */
	private function register_query_controls() {
		$this->start_controls_section(
			'section_query',
			[
				'label' => esc_html__( 'Autores', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'post_types',
			[
				'label'       => esc_html__( 'Contar entradas de', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => [ 'post' ],
				'options'     => $this->get_post_type_options(),
				'label_block' => true,
				'description' => esc_html__( 'Tipos de contenido que cuentan como actividad al buscar la última entrada de cada autor.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'roles',
			[
				'label'       => esc_html__( 'Roles a mostrar', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => [ 'administrator', 'editor', 'author' ],
				'options'     => $this->get_role_options(),
				'label_block' => true,
				'description' => esc_html__( 'Vacío incluye cualquier rol. Solo aparecen los usuarios que además tengan entradas publicadas.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'number',
			[
				'label'   => esc_html__( 'Cantidad máxima', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 8,
				'min'     => 1,
				'max'     => self::MAX_AUTHORS,
			]
		);

		$this->add_control(
			'min_posts',
			[
				'label'       => esc_html__( 'Mínimo de entradas', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 1,
				'min'         => 1,
				'max'         => 500,
				'description' => esc_html__( 'Descarta a los autores con menos entradas publicadas que este número.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'orderby',
			[
				'label'   => esc_html__( 'Ordenar por', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'recent_activity',
				'options' => [
					'recent_activity' => esc_html__( 'Actividad reciente', 'wp-multi-post-type-blog' ),
					'post_count'      => esc_html__( 'Cantidad de entradas', 'wp-multi-post-type-blog' ),
					'name'            => esc_html__( 'Nombre', 'wp-multi-post-type-blog' ),
					'random'          => esc_html__( 'Aleatorio', 'wp-multi-post-type-blog' ),
				],
			]
		);

		$this->add_control(
			'order',
			[
				'label'     => esc_html__( 'Dirección', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'DESC',
				'options'   => [
					'DESC' => esc_html__( 'Descendente', 'wp-multi-post-type-blog' ),
					'ASC'  => esc_html__( 'Ascendente', 'wp-multi-post-type-blog' ),
				],
				'condition' => [
					'orderby!' => 'random',
				],
			]
		);

		$this->add_control(
			'include_users',
			[
				'label'       => esc_html__( 'Solo estos usuarios', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'placeholder' => '18, 25, 3869',
				'description' => esc_html__( 'IDs separados por coma. Si se completa, se ignoran los filtros de rol y solo se muestran estos.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'exclude_users',
			[
				'label'       => esc_html__( 'Ocultar usuarios', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'placeholder' => '1, 22, 24',
				'description' => esc_html__( 'IDs separados por coma. Se descartan antes que la lista blanca.', 'wp-multi-post-type-blog' ),
			]
		);

		$this->end_controls_section();
	}

	/**
	 * What to show for each author.
	 */
	private function register_display_controls() {
		$this->start_controls_section(
			'section_display',
			[
				'label' => esc_html__( 'Mostrar', 'wp-multi-post-type-blog' ),
			]
		);

		$this->add_control(
			'layout',
			[
				'label'   => esc_html__( 'Plantilla', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'list',
				'options' => [
					'list'    => esc_html__( 'Lista', 'wp-multi-post-type-blog' ),
					'cards'   => esc_html__( 'Tarjetas', 'wp-multi-post-type-blog' ),
					'compact' => esc_html__( 'Compacto', 'wp-multi-post-type-blog' ),
				],
			]
		);

		$this->add_responsive_control(
			'columns',
			[
				'label'     => esc_html__( 'Columnas', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'   => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				],
				'selectors' => [
					'{{WRAPPER}} .premium-author-list--cards' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				],
				'condition' => [
					'layout' => 'cards',
				],
			]
		);

		$this->add_control(
			'show_avatar',
			[
				'label'        => esc_html__( 'Avatar', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'avatar_size',
			[
				'label'     => esc_html__( 'Tamaño del avatar (px)', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 56,
				'min'       => 16,
				'max'       => 300,
				'condition' => [
					'show_avatar' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_name',
			[
				'label'        => esc_html__( 'Nombre', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'link_to',
			[
				'label'   => esc_html__( 'Enlazar el nombre a', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'author_page',
				'options' => [
					'author_page' => esc_html__( 'Página del autor', 'wp-multi-post-type-blog' ),
					'last_post'   => esc_html__( 'Su última entrada', 'wp-multi-post-type-blog' ),
					'none'        => esc_html__( 'Sin enlace', 'wp-multi-post-type-blog' ),
				],
			]
		);

		$this->add_control(
			'show_last_post',
			[
				'label'        => esc_html__( 'Última entrada', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->add_control(
			'show_post_date',
			[
				'label'        => esc_html__( 'Fecha de la última entrada', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'condition'    => [
					'show_last_post' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_post_count',
			[
				'label'        => esc_html__( 'Cantidad de entradas', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->add_control(
			'show_bio',
			[
				'label'        => esc_html__( 'Biografía', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			]
		);

		$this->add_control(
			'bio_length',
			[
				'label'     => esc_html__( 'Recortar la biografía a', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 120,
				'min'       => 20,
				'max'       => 600,
				'condition' => [
					'show_bio' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_dividers',
			[
				'label'        => esc_html__( 'Líneas divisorias', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Colours and typography.
	 */
	private function register_style_controls() {
		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Estilo', 'wp-multi-post-type-blog' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'avatar_radius',
			[
				'label'      => esc_html__( 'Redondeo del avatar', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ '%', 'px' ],
				'default'    => [
					'unit' => '%',
					'size' => 50,
				],
				'range'      => [
					'%'  => [ 'min' => 0, 'max' => 50 ],
					'px' => [ 'min' => 0, 'max' => 60 ],
				],
				'selectors'  => [
					'{{WRAPPER}} .author-item__avatar img' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'gap',
			[
				'label'      => esc_html__( 'Separación entre autores', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'default'    => [
					'unit' => 'px',
					'size' => 16,
				],
				'range'      => [
					'px' => [ 'min' => 0, 'max' => 60 ],
				],
				'selectors'  => [
					'{{WRAPPER}} .premium-author-list' => '--wpmb-author-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'divider_color',
			[
				'label'     => esc_html__( 'Color de la línea', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .premium-author-list' => '--wpmb-author-divider: {{VALUE}};',
				],
				'condition' => [
					'show_dividers' => 'yes',
				],
			]
		);

		$this->add_control(
			'name_color',
			[
				'label'     => esc_html__( 'Color del nombre', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .author-item__name, {{WRAPPER}} .author-item__name a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'name_hover_color',
			[
				'label'     => esc_html__( 'Color del nombre al pasar', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .author-item__name a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .author-item__name',
			]
		);

		$this->add_control(
			'post_color',
			[
				'label'     => esc_html__( 'Color de la entrada', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .author-item__post a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'post_hover_color',
			[
				'label'     => esc_html__( 'Color de la entrada al pasar', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .author-item__post a:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name'     => 'post_typography',
				'selector' => '{{WRAPPER}} .author-item__post',
			]
		);

		$this->add_control(
			'meta_color',
			[
				'label'     => esc_html__( 'Color de los metadatos', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => [
					'{{WRAPPER}} .author-item__meta, {{WRAPPER}} .author-item__bio' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget.
	 */
	protected function render() {
		$settings = \WpMultiPostTypeBlog\Utils::sanitize_author_settings( $this->get_settings_for_display() );
		$authors  = self::get_authors( $settings );

		if ( empty( $authors ) ) {
			echo '<div class="premium-blog-no-posts">' . esc_html__( 'No se encontraron autores.', 'wp-multi-post-type-blog' ) . '</div>';
			return;
		}

		$classes = 'premium-author-list premium-author-list--' . $settings['layout'];

		// Las tarjetas ya se separan por su propio borde: la línea divisoria solo
		// tiene sentido en las plantillas que apilan filas.
		if ( 'yes' !== $settings['show_dividers'] || 'cards' === $settings['layout'] ) {
			$classes .= ' premium-author-list--no-dividers';
		}
		?>
		<ul class="<?php echo esc_attr( $classes ); ?>">
			<?php foreach ( $authors as $author ) : ?>
				<?php $this->render_author( $author, $settings ); ?>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render a single author row.
	 *
	 * @param array $author   Author data from get_authors().
	 * @param array $settings Sanitized settings.
	 */
	private function render_author( $author, $settings ) {
		$author_url = get_author_posts_url( $author['id'] );
		$post_url   = $author['post_id'] ? get_permalink( $author['post_id'] ) : '';

		// 'last_post' silently falls back to the author archive when the author has
		// no readable entry, so the name never renders as a dead link.
		$name_url = '';
		if ( 'author_page' === $settings['link_to'] ) {
			$name_url = $author_url;
		} elseif ( 'last_post' === $settings['link_to'] ) {
			$name_url = $post_url ? $post_url : $author_url;
		}
		?>
		<li class="author-item">
			<?php if ( 'yes' === $settings['show_avatar'] ) : ?>
				<a class="author-item__avatar" href="<?php echo esc_url( $author_url ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo get_avatar( $author['id'], $settings['avatar_size'], '', $author['name'] ); ?>
				</a>
			<?php endif; ?>

			<div class="author-item__content">
				<?php if ( 'yes' === $settings['show_name'] ) : ?>
					<h3 class="author-item__name">
						<?php if ( $name_url ) : ?>
							<a href="<?php echo esc_url( $name_url ); ?>"><?php echo esc_html( $author['name'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $author['name'] ); ?>
						<?php endif; ?>
					</h3>
				<?php endif; ?>

				<?php if ( 'yes' === $settings['show_bio'] && '' !== $author['bio'] ) : ?>
					<p class="author-item__bio">
						<?php echo esc_html( wp_html_excerpt( $author['bio'], $settings['bio_length'], '…' ) ); ?>
					</p>
				<?php endif; ?>

				<?php if ( 'yes' === $settings['show_last_post'] && $post_url ) : ?>
					<div class="author-item__post">
						<a href="<?php echo esc_url( $post_url ); ?>"><?php echo esc_html( $author['post_title'] ); ?></a>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $this->build_meta( $author, $settings ) ) : ?>
					<div class="author-item__meta"><?php echo esc_html( $this->build_meta( $author, $settings ) ); ?></div>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	/**
	 * Build the small meta line under an author.
	 *
	 * @param array $author   Author data.
	 * @param array $settings Sanitized settings.
	 * @return string
	 */
	private function build_meta( $author, $settings ) {
		$parts = array();

		if ( 'yes' === $settings['show_post_date'] && $author['post_date'] ) {
			$parts[] = date_i18n( get_option( 'date_format' ), strtotime( $author['post_date'] ) );
		}

		if ( 'yes' === $settings['show_post_count'] ) {
			$parts[] = sprintf(
				/* translators: %s: formatted number of posts. */
				_n( '%s entrada', '%s entradas', $author['post_count'], 'wp-multi-post-type-blog' ),
				number_format_i18n( $author['post_count'] )
			);
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Resolve the authors to list, with their latest entry.
	 *
	 * The set is built from the posts table rather than from the user table: the
	 * site has thousands of subscribers and customers, and only a few dozen have
	 * ever published. Grouping the posts first keeps the candidate list to the
	 * people who can actually appear, so the role check runs over tens of users
	 * instead of thousands.
	 *
	 * @param array $settings Sanitized settings.
	 * @return array List of author rows.
	 */
	protected static function get_authors( $settings ) {
		$cache_key = \WpMultiPostTypeBlog\Utils::cache_key( 'authors_' . md5( wp_json_encode( $settings ) ) );

		// Random order must not be frozen by the cache.
		if ( 'random' !== $settings['orderby'] ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		global $wpdb;

		$post_types   = $settings['post_types'];
		$placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		// One grouped pass gives both the entry count and the last activity date.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated from the post type count.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_author, COUNT(*) AS post_count, MAX(post_date) AS last_date
				 FROM {$wpdb->posts}
				 WHERE post_status = 'publish' AND post_type IN ( {$placeholders} )
				 GROUP BY post_author",
				$post_types
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$candidates = array();
		foreach ( $rows as $row ) {
			$id = absint( $row['post_author'] );
			if ( ! $id || (int) $row['post_count'] < $settings['min_posts'] ) {
				continue;
			}
			$candidates[ $id ] = array(
				'id'         => $id,
				'post_count' => (int) $row['post_count'],
				'last_date'  => $row['last_date'],
			);
		}

		$candidates = self::filter_by_audience( $candidates, $settings );
		if ( empty( $candidates ) ) {
			return array();
		}

		$candidates = self::sort_candidates( $candidates, $settings );
		$candidates = array_slice( $candidates, 0, $settings['number'], true );

		return self::hydrate( $candidates, $settings, $cache_key );
	}

	/**
	 * Apply the include list, the exclude list and the role filter.
	 *
	 * @param array $candidates Authors keyed by ID.
	 * @param array $settings   Sanitized settings.
	 * @return array
	 */
	private static function filter_by_audience( $candidates, $settings ) {
		if ( ! empty( $settings['exclude_users'] ) ) {
			foreach ( $settings['exclude_users'] as $id ) {
				unset( $candidates[ $id ] );
			}
		}

		// An explicit include list is a deliberate hand-picked set, so it wins over
		// the role filter instead of being intersected with it.
		if ( ! empty( $settings['include_users'] ) ) {
			return array_intersect_key( $candidates, array_flip( $settings['include_users'] ) );
		}

		if ( empty( $settings['roles'] ) ) {
			return $candidates;
		}

		$allowed = get_users(
			array(
				'include'  => array_keys( $candidates ),
				'role__in' => $settings['roles'],
				'fields'   => 'ID',
				'number'   => -1,
			)
		);

		return array_intersect_key( $candidates, array_flip( array_map( 'absint', $allowed ) ) );
	}

	/**
	 * Order the candidate list.
	 *
	 * @param array $candidates Authors keyed by ID.
	 * @param array $settings   Sanitized settings.
	 * @return array
	 */
	private static function sort_candidates( $candidates, $settings ) {
		if ( 'random' === $settings['orderby'] ) {
			$keys = array_keys( $candidates );
			shuffle( $keys );

			$shuffled = array();
			foreach ( $keys as $key ) {
				$shuffled[ $key ] = $candidates[ $key ];
			}

			return $shuffled;
		}

		if ( 'name' === $settings['orderby'] ) {
			foreach ( $candidates as $id => $data ) {
				$candidates[ $id ]['name'] = get_the_author_meta( 'display_name', $id );
			}
		}

		$orderby = $settings['orderby'];
		uasort(
			$candidates,
			static function ( $a, $b ) use ( $orderby ) {
				if ( 'post_count' === $orderby ) {
					return $a['post_count'] <=> $b['post_count'];
				}

				if ( 'name' === $orderby ) {
					return strcasecmp( $a['name'], $b['name'] );
				}

				return strcmp( $a['last_date'], $b['last_date'] );
			}
		);

		return 'DESC' === $settings['order'] ? array_reverse( $candidates, true ) : $candidates;
	}

	/**
	 * Attach the display name, bio and latest entry to the selected authors.
	 *
	 * @param array  $candidates Authors keyed by ID, already sliced.
	 * @param array  $settings   Sanitized settings.
	 * @param string $cache_key  Transient key.
	 * @return array
	 */
	private static function hydrate( $candidates, $settings, $cache_key ) {
		global $wpdb;

		$ids          = array_keys( $candidates );
		$post_types   = $settings['post_types'];
		$type_holders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$id_holders   = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// Join the grouped maximum back onto the table to get the actual entry.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated from the argument counts.
		$latest = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_author, p.post_title, p.post_date
				 FROM {$wpdb->posts} p
				 INNER JOIN (
					SELECT post_author, MAX(post_date) AS md
					FROM {$wpdb->posts}
					WHERE post_status = 'publish'
					  AND post_type IN ( {$type_holders} )
					  AND post_author IN ( {$id_holders} )
					GROUP BY post_author
				 ) latest ON p.post_author = latest.post_author AND p.post_date = latest.md
				 WHERE p.post_status = 'publish' AND p.post_type IN ( {$type_holders} )",
				array_merge( $post_types, $ids, $post_types )
			),
			ARRAY_A
		);

		$by_author = array();
		foreach ( (array) $latest as $row ) {
			// Two entries can share a timestamp to the second; the first one wins.
			$author = absint( $row['post_author'] );
			if ( ! isset( $by_author[ $author ] ) ) {
				$by_author[ $author ] = $row;
			}
		}

		$out = array();
		foreach ( $candidates as $id => $data ) {
			$post = isset( $by_author[ $id ] ) ? $by_author[ $id ] : null;

			$out[] = array(
				'id'         => $id,
				'name'       => get_the_author_meta( 'display_name', $id ),
				'bio'        => (string) get_the_author_meta( 'description', $id ),
				'post_count' => $data['post_count'],
				'post_id'    => $post ? absint( $post['ID'] ) : 0,
				'post_title' => $post ? $post['post_title'] : '',
				'post_date'  => $post ? $post['post_date'] : '',
			);
		}

		if ( 'random' !== $settings['orderby'] ) {
			set_transient( $cache_key, $out, 15 * MINUTE_IN_SECONDS );
		}

		return $out;
	}
}
