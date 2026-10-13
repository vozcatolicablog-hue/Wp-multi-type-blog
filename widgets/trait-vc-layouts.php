<?php
/**
 * Diseños del sistema de diseño de Voz Católica para los widgets de entradas.
 *
 * @package WpMultiPostTypeBlog
 */

namespace WpMultiPostTypeBlog\Widgets;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Renderizadores «Sistema VC» (layouts vc_lista, vc_tarjetas y vc_ranking).
 *
 * Reproducen el marcado de los mockups aprobados (propuesta-home-v4 y propuesta-autor). Viven
 * en un trait y no en la clase del widget para que el widget de archivo y el manejador AJAX
 * los compartan sin duplicar código, y para no engordar más una clase que ya es larga.
 *
 * Todo el texto visible va en «tú» y en minúscula de oración, como pide el sistema.
 */
trait Vc_Layouts {

	/**
	 * Palabras por minuto para estimar el tiempo de lectura.
	 */
	public static $vc_words_per_minute = 200;

	/* ---------------------------------------------------------------------
	 * Controles de Elementor
	 * ------------------------------------------------------------------ */

	/**
	 * Valores del control «Layout Type» que pertenecen al sistema.
	 *
	 * @return array
	 */
	protected static function vc_layout_options() {
		return array(
			'vc_lista'    => esc_html__( 'Sistema VC · Lista con miniatura', 'wp-multi-post-type-blog' ),
			'vc_tarjetas' => esc_html__( 'Sistema VC · Tarjetas y lista', 'wp-multi-post-type-blog' ),
			'vc_ranking'  => esc_html__( 'Sistema VC · Ranking numerado', 'wp-multi-post-type-blog' ),
		);
	}

	/**
	 * Sección de controles propia de los diseños del sistema.
	 *
	 * @param array $term_options Opciones de términos (taxonomía:slug => etiqueta).
	 */
	protected function register_vc_controls( $term_options ) {
		$vc = array( 'layout_type' => array_keys( self::vc_layout_options() ) );

		$this->start_controls_section(
			'section_vc',
			array(
				'label'     => esc_html__( 'Sistema VC · Encabezado y textos', 'wp-multi-post-type-blog' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => $vc,
			)
		);

		$this->add_control(
			'vc_help',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Portada del mockup: un widget «Lista con miniatura» con 1 entrada y destacado (lo último), otro con el destacado apagado para «Más publicaciones» y un «Ranking numerado» ordenado por «Más leídos» al costado.', 'wp-multi-post-type-blog' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$this->add_control(
			'block_title',
			array(
				'label'       => esc_html__( 'Título del bloque', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'Más publicaciones', 'wp-multi-post-type-blog' ),
				'label_block' => true,
				'separator'   => 'before',
			)
		);

		$this->add_control(
			'block_title_tag',
			array(
				'label'   => esc_html__( 'Etiqueta del título del bloque', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'div' => 'div',
				),
			)
		);

		$this->add_control(
			'block_subtitle',
			array(
				'label'       => esc_html__( 'Bajada', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'Lo último que publicó en el blog.', 'wp-multi-post-type-blog' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'block_link_text',
			array(
				'label'       => esc_html__( 'Texto del enlace «ver todo»', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => esc_html__( 'Ver los {total} artículos', 'wp-multi-post-type-blog' ),
				'label_block' => true,
				'description' => esc_html__( '{total} se reemplaza por la cantidad de entradas que encuentra el widget.', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_control(
			'block_link_url',
			array(
				'label'       => esc_html__( 'Destino del enlace', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::URL,
				'dynamic'     => array( 'active' => true ),
				'placeholder' => home_url( '/blog/' ),
				'description' => esc_html__( 'Vacío en una página de autor = el archivo de ese autor.', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_control(
			'block_link_pos',
			array(
				'label'   => esc_html__( 'Dónde va el enlace', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'pie',
				'options' => array(
					'cabecera' => esc_html__( 'En la cabecera (enlace con flecha)', 'wp-multi-post-type-blog' ),
					'pie'      => esc_html__( 'Al pie (botón secundario)', 'wp-multi-post-type-blog' ),
					'ambos'    => esc_html__( 'En los dos lugares', 'wp-multi-post-type-blog' ),
				),
			)
		);

		$this->add_control(
			'chip_terms',
			array(
				'label'       => esc_html__( 'Chips de filtro', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => array(),
				'options'     => $term_options,
				'label_block' => true,
				'separator'   => 'before',
				'description' => esc_html__( 'Temas que el lector puede elegir sin recargar la página. Se suman con «Todo» al comienzo.', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_control(
			'chips_all_label',
			array(
				'label'   => esc_html__( 'Texto del chip «todo»', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Todo', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_control(
			'featured_eyebrow',
			array(
				'label'       => esc_html__( 'Antetítulo del destacado', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Lo último en el blog', 'wp-multi-post-type-blog' ),
				'label_block' => true,
				'separator'   => 'before',
				'condition'   => array(
					'layout_type'   => 'vc_lista',
					'show_featured' => 'yes',
				),
			)
		);

		$this->add_control(
			'vc_read_text',
			array(
				'label'     => esc_html__( 'Texto del botón del destacado', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Leer el artículo', 'wp-multi-post-type-blog' ),
				'condition' => array(
					'layout_type'   => 'vc_lista',
					'show_featured' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_avatar',
			array(
				'label'        => esc_html__( 'Foto del autor en la firma', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array(
					'layout_type'   => 'vc_lista',
					'show_featured' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_reading_time',
			array(
				'label'        => esc_html__( 'Tiempo de lectura en la firma', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'condition'    => array(
					'layout_type'   => 'vc_lista',
					'show_featured' => 'yes',
				),
			)
		);

		$this->add_control(
			'cards_count',
			array(
				'label'       => esc_html__( 'Entradas como tarjeta', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 3,
				'min'         => 1,
				'max'         => 6,
				'separator'   => 'before',
				'description' => esc_html__( 'La primera ocupa el ancho completo; el resto va de a dos. Las demás entradas se listan como renglones a la derecha.', 'wp-multi-post-type-blog' ),
				'condition'   => array( 'layout_type' => 'vc_tarjetas' ),
			)
		);

		$this->add_control(
			'list_heading',
			array(
				'label'     => esc_html__( 'Título de la lista', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Más artículos', 'wp-multi-post-type-blog' ),
				'condition' => array( 'layout_type' => 'vc_tarjetas' ),
			)
		);

		$this->end_controls_section();
	}

	/* ---------------------------------------------------------------------
	 * Datos
	 * ------------------------------------------------------------------ */

	/**
	 * Fecha en el formato del sistema.
	 *
	 * 'larga' omite el año cuando es el corriente («23 de septiembre»); 'completa' lo lleva
	 * siempre («19 de mayo de 2026»); 'corta' es la de las listas («2 jul 2026»).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $mode    larga|completa|corta.
	 * @return string
	 */
	public static function vc_date( $post_id, $mode = 'larga' ) {
		$timestamp = get_post_timestamp( $post_id );
		if ( ! $timestamp ) {
			return '';
		}

		if ( 'corta' === $mode ) {
			$format = 'j M Y';
		} elseif ( 'completa' === $mode || wp_date( 'Y', $timestamp ) !== wp_date( 'Y' ) ) {
			$format = 'j \d\e F \d\e Y';
		} else {
			$format = 'j \d\e F';
		}

		// Algunas traducciones abrevian el mes con mayúscula («5 Oct»); en castellano va en
		// minúscula, como en el resto del sitio.
		return self::vc_lower( wp_date( $format, $timestamp ) );
	}

	/**
	 * Minúsculas seguras para texto con tildes.
	 *
	 * @param string $text Texto.
	 * @return string
	 */
	public static function vc_lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Minutos de lectura estimados.
	 *
	 * @param \WP_Post $post Post.
	 * @return int
	 */
	public static function vc_reading_minutes( $post ) {
		$text  = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$count = is_array( $words ) ? count( $words ) : 0;

		/**
		 * Filtra los minutos de lectura que muestra la firma del destacado.
		 *
		 * @param int      $minutes Minutos estimados.
		 * @param \WP_Post $post    Post.
		 */
		return (int) apply_filters( 'wp_multipost_blog_reading_minutes', max( 1, (int) ceil( $count / self::$vc_words_per_minute ) ), $post );
	}

	/**
	 * Línea de categoría: el tema y, si no es una entrada, el tipo de contenido.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @return string Texto plano, '' si no hay nada que mostrar.
	 */
	protected static function vc_category_line( $post, $settings ) {
		$parts = array();

		if ( self::vc_enabled( $settings, 'show_category' ) ) {
			$cat = self::get_primary_category( $post->ID, $post->post_type, \WpMultiPostTypeBlog\Utils::category_level( $settings ) );
			if ( $cat ) {
				$parts[] = $cat['name'];
			}
		}

		if ( 'post' !== $post->post_type ) {
			$type = get_post_type_object( $post->post_type );
			if ( $type ) {
				$parts[] = $type->labels->singular_name;
			}
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Nombre del autor de una entrada.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	protected static function vc_author_name( $post ) {
		return (string) get_the_author_meta( 'display_name', $post->post_author );
	}

	/**
	 * Vistas para mostrar, '' cuando no hay o están apagadas.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @return string
	 */
	protected static function vc_views_text( $post, $settings ) {
		if ( ! self::vc_enabled( $settings, 'show_views' ) ) {
			return '';
		}

		$views = self::get_views_raw( $post->ID, $post->post_type );
		if ( $views <= 0 ) {
			return '';
		}

		/* translators: %s: formatted number of views. */
		return sprintf( _n( '%s lectura', '%s lecturas', $views, 'wp-multi-post-type-blog' ), number_format_i18n( $views ) );
	}

	/**
	 * Lectura de un interruptor saneado.
	 *
	 * @param array  $settings Ajustes saneados.
	 * @param string $key      Clave.
	 * @return bool
	 */
	protected static function vc_enabled( $settings, $key ) {
		return ! isset( $settings[ $key ] ) || 'yes' === $settings[ $key ];
	}

	/**
	 * Imagen de una entrada con la cadena de respaldo del plugin.
	 *
	 * Las imágenes van con alt vacío: siempre están junto al título, que ya es el texto del
	 * enlace, y repetirlo haría que el lector de pantalla lo anunciara dos veces.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @param string   $size     Tamaño registrado.
	 * @param string   $loading  lazy|eager.
	 * @return array|null { html, mod } o null si no hay imagen.
	 */
	protected static function vc_image( $post, $settings, $size, $loading = 'lazy' ) {
		$image = self::resolve_image( $post, $settings );
		$attrs = array(
			'loading' => $loading,
			'alt'     => '',
		);

		if ( 'eager' === $loading ) {
			$attrs['fetchpriority'] = 'high';
		}

		switch ( $image['type'] ) {
			case 'thumbnail':
				$html = get_the_post_thumbnail( $post->ID, $size, $attrs );
				break;
			case 'meta':
				$html = self::meta_image_html( $image, $size, '', $loading, '' );
				break;
			case 'icon':
				$html = wp_get_attachment_image( $image['id'], $size, false, $attrs );
				break;
			case 'placeholder':
				$html = sprintf(
					'<img src="%1$s" alt="" loading="%2$s" />',
					esc_url( WP_MULTIPOST_BLOG_URL . 'assets/images/placeholder.jpg' ),
					esc_attr( $loading )
				);
				break;
			default:
				return null;
		}

		if ( ! $html ) {
			return null;
		}

		$mod = '';
		if ( 'icon' === $image['type'] ) {
			$mod = ' wpmb-img--icono';
		} elseif ( 'meta' === $image['type'] && 'contain' === $image['fit'] ) {
			$mod = ' wpmb-img--tapa';
		}

		return array(
			'html' => $html,
			'mod'  => $mod,
		);
	}

	/* ---------------------------------------------------------------------
	 * Piezas
	 * ------------------------------------------------------------------ */

	/**
	 * Destacado partido: texto a la izquierda, foto 4:3 a la derecha.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @return string
	 */
	public static function render_vc_hero( $post, $settings ) {
		$permalink = get_permalink( $post );
		$title     = get_the_title( $post );
		$tag       = \WpMultiPostTypeBlog\Utils::heading_tag( $settings, 'featured_title_tag', 'h2' );
		$size      = ! empty( $settings['featured_image_size'] ) ? $settings['featured_image_size'] : 'full';
		$image     = self::vc_image( $post, $settings, $size, 'eager' );
		$eyebrow   = isset( $settings['featured_eyebrow'] ) ? $settings['featured_eyebrow'] : '';
		$button    = ! empty( $settings['vc_read_text'] ) ? $settings['vc_read_text'] : __( 'Leer el artículo', 'wp-multi-post-type-blog' );

		$excerpt = '';
		if ( self::vc_enabled( $settings, 'show_excerpt' ) ) {
			$words   = isset( $settings['excerpt_words'] ) ? (int) $settings['excerpt_words'] : 30;
			$excerpt = $words > 0 ? wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), $words, '…' ) : '';
		}

		// Segunda línea de la firma: «Tema · 25 de septiembre, 5 min».
		$sign_parts = array();
		$cat_line   = self::vc_category_line( $post, $settings );
		if ( '' !== $cat_line ) {
			$sign_parts[] = $cat_line;
		}
		$when = self::vc_enabled( $settings, 'show_date' ) ? self::vc_date( $post->ID, 'larga' ) : '';
		if ( self::vc_enabled( $settings, 'show_reading_time' ) ) {
			/* translators: %d: minutes. */
			$minutes = sprintf( __( '%d min', 'wp-multi-post-type-blog' ), self::vc_reading_minutes( $post ) );
			$when    = '' !== $when ? $when . ', ' . $minutes : $minutes;
		}
		if ( '' !== $when ) {
			$sign_parts[] = $when;
		}

		$show_author = self::vc_enabled( $settings, 'show_author' );
		$avatar      = ( $show_author && self::vc_enabled( $settings, 'show_avatar' ) )
			? get_avatar( $post->post_author, 72, '', '', array( 'class' => 'wpmb-firma__foto', 'loading' => 'eager' ) )
			: '';

		ob_start();
		?>
		<article class="wpmb-hero<?php echo $image ? '' : ' wpmb-hero--sin-imagen'; ?>">
			<div class="wpmb-hero__tx">
				<?php if ( '' !== $eyebrow ) : ?>
					<p class="wpmb-ante"><?php echo esc_html( $eyebrow ); ?></p>
				<?php endif; ?>
				<<?php echo $tag; ?> class="wpmb-hero__tit"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></<?php echo $tag; ?>>
				<?php if ( '' !== $excerpt ) : ?>
					<p class="wpmb-hero__sub"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>
				<div class="wpmb-hero__acc">
					<a class="wpmb-btn" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $button . ': ' . $title ); ?>"><?php echo esc_html( $button ); ?></a>
					<?php if ( $show_author || ! empty( $sign_parts ) ) : ?>
						<span class="wpmb-firma">
							<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() devuelve HTML ya escapado. ?>
							<span>
								<?php if ( $show_author ) : ?>
									<b><?php echo esc_html( self::vc_author_name( $post ) ); ?></b>
								<?php endif; ?>
								<?php echo esc_html( implode( ' · ', $sign_parts ) ); ?>
							</span>
						</span>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( $image ) : ?>
				<a class="wpmb-hero__foto<?php echo esc_attr( $image['mod'] ); ?>" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo $image['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de wp_get_attachment_image(). ?>
				</a>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Fila con miniatura 16:10 («Más publicaciones»).
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @return string
	 */
	public static function render_vc_row( $post, $settings ) {
		$permalink = get_permalink( $post );
		$tag       = \WpMultiPostTypeBlog\Utils::heading_tag( $settings, 'list_title_tag', 'h3' );
		$size      = ! empty( $settings['list_image_size'] ) ? $settings['list_image_size'] : 'medium_large';
		$image     = self::vc_image( $post, $settings, $size );
		$cat_line  = self::vc_category_line( $post, $settings );

		// «Beato Fulton Sheen, 23 de septiembre»
		$meta = array();
		if ( self::vc_enabled( $settings, 'show_author' ) ) {
			$meta[] = self::vc_author_name( $post );
		}
		if ( self::vc_enabled( $settings, 'show_date' ) ) {
			$meta[] = self::vc_date( $post->ID, 'larga' );
		}
		$meta  = implode( ', ', array_filter( $meta ) );
		$views = self::vc_views_text( $post, $settings );
		if ( '' !== $views ) {
			$meta = '' !== $meta ? $meta . ' · ' . $views : $views;
		}

		ob_start();
		?>
		<article class="wpmb-fila wpmb-item<?php echo $image ? '' : ' wpmb-fila--sin-imagen'; ?>">
			<?php if ( $image ) : ?>
				<span class="wpmb-fila__img<?php echo esc_attr( $image['mod'] ); ?>"><?php echo $image['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
			<span class="wpmb-fila__tx">
				<?php if ( '' !== $cat_line ) : ?>
					<span class="wpmb-cat"><?php echo esc_html( $cat_line ); ?></span>
				<?php endif; ?>
				<<?php echo $tag; ?> class="wpmb-fila__tit"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></<?php echo $tag; ?>>
				<?php if ( '' !== $meta ) : ?>
					<span class="wpmb-meta"><?php echo esc_html( $meta ); ?></span>
				<?php endif; ?>
			</span>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Tarjeta 16:9 de la página de autor; la primera, a todo el ancho y con extracto.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @param bool     $big      Tarjeta grande.
	 * @return string
	 */
	public static function render_vc_card( $post, $settings, $big = false ) {
		$permalink = get_permalink( $post );
		$tag       = \WpMultiPostTypeBlog\Utils::heading_tag( $settings, 'list_title_tag', 'h3' );
		$size      = $big
			? ( ! empty( $settings['featured_image_size'] ) ? $settings['featured_image_size'] : 'full' )
			: ( ! empty( $settings['list_image_size'] ) ? $settings['list_image_size'] : 'medium_large' );
		$image     = self::vc_image( $post, $settings, $size, $big ? 'eager' : 'lazy' );
		$cat_line  = self::vc_category_line( $post, $settings );
		$is_video  = has_post_format( 'video', $post );

		$excerpt = '';
		if ( $big && self::vc_enabled( $settings, 'show_excerpt' ) ) {
			$words   = isset( $settings['excerpt_words'] ) ? (int) $settings['excerpt_words'] : 30;
			$excerpt = $words > 0 ? wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), $words, '…' ) : '';
		}

		$meta = array();
		if ( self::vc_enabled( $settings, 'show_date' ) ) {
			$meta[] = self::vc_date( $post->ID, 'completa' );
		}
		$views = self::vc_views_text( $post, $settings );
		if ( '' !== $views ) {
			$meta[] = $views;
		}

		ob_start();
		?>
		<article class="wpmb-card<?php echo $big ? ' wpmb-card--grande' : ''; ?>">
			<?php if ( $image ) : ?>
				<span class="wpmb-card__foto<?php echo esc_attr( $image['mod'] ); ?>">
					<?php echo $image['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $is_video ) : ?>
						<span class="wpmb-card__tipo"><?php esc_html_e( '▶ Video', 'wp-multi-post-type-blog' ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<?php if ( '' !== $cat_line ) : ?>
				<span class="wpmb-cat"><?php echo esc_html( $cat_line ); ?></span>
			<?php endif; ?>
			<<?php echo $tag; ?> class="wpmb-card__tit"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></<?php echo $tag; ?>>
			<?php if ( '' !== $excerpt ) : ?>
				<p class="wpmb-card__ext"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $meta ) ) : ?>
				<span class="wpmb-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renglón de texto de la lista «Más artículos»: título y fecha corta.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @return string
	 */
	public static function render_vc_line( $post, $settings ) {
		$date = self::vc_enabled( $settings, 'show_date' ) ? self::vc_date( $post->ID, 'corta' ) : '';

		return sprintf(
			'<li class="wpmb-renglon wpmb-item"><a href="%1$s"><b>%2$s</b>%3$s</a></li>',
			esc_url( get_permalink( $post ) ),
			esc_html( get_the_title( $post ) ),
			'' !== $date ? '<small>' . esc_html( $date ) . '</small>' : ''
		);
	}

	/**
	 * Puesto del ranking: el número lo pone un contador CSS, así sigue correlativo cuando
	 * «cargar más» agrega puestos al final.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @return string
	 */
	public static function render_vc_rank( $post, $settings ) {
		$meta = array();
		if ( self::vc_enabled( $settings, 'show_author' ) ) {
			$meta[] = self::vc_author_name( $post );
		}
		if ( self::vc_enabled( $settings, 'show_date' ) ) {
			$meta[] = self::vc_date( $post->ID, 'larga' );
		}
		$views = self::vc_views_text( $post, $settings );
		if ( '' !== $views ) {
			$meta[] = $views;
		}

		return sprintf(
			'<li class="wpmb-puesto wpmb-item"><a href="%1$s"><span><b>%2$s</b>%3$s</span></a></li>',
			esc_url( get_permalink( $post ) ),
			esc_html( get_the_title( $post ) ),
			! empty( $meta ) ? '<small>' . esc_html( implode( ' · ', array_filter( $meta ) ) ) . '</small>' : ''
		);
	}

	/**
	 * Elemento de lista según el layout del sistema.
	 *
	 * @param \WP_Post $post     Post.
	 * @param array    $settings Ajustes saneados.
	 * @return string
	 */
	public static function render_vc_item( $post, $settings ) {
		switch ( $settings['layout_type'] ) {
			case 'vc_tarjetas':
				return self::render_vc_line( $post, $settings );
			case 'vc_ranking':
				return self::render_vc_rank( $post, $settings );
			default:
				return self::render_vc_row( $post, $settings );
		}
	}

	/* ---------------------------------------------------------------------
	 * Encabezado, chips y enlace «ver todo»
	 * ------------------------------------------------------------------ */

	/**
	 * Reúne lo que el encabezado necesita a partir de los ajustes crudos de Elementor.
	 *
	 * No viaja en la configuración firmada: solo se usa en el render inicial, nunca en AJAX.
	 *
	 * @param array $raw         Ajustes crudos (get_settings_for_display).
	 * @param array $settings    Ajustes saneados.
	 * @param int   $found_posts Entradas encontradas.
	 * @return array
	 */
	protected static function build_vc_header( $raw, $settings, $found_posts ) {
		$text = isset( $raw['block_link_text'] ) && is_scalar( $raw['block_link_text'] ) ? trim( sanitize_text_field( $raw['block_link_text'] ) ) : '';
		$text = str_replace( '{total}', number_format_i18n( max( 0, (int) $found_posts ) ), $text );

		$url      = '';
		$external = false;
		if ( ! empty( $raw['block_link_url'] ) && is_array( $raw['block_link_url'] ) && ! empty( $raw['block_link_url']['url'] ) ) {
			$url      = (string) $raw['block_link_url']['url'];
			$external = ! empty( $raw['block_link_url']['is_external'] );
		} elseif ( is_author() ) {
			$url = get_author_posts_url( get_queried_object_id() );
		}

		$tag = isset( $raw['block_title_tag'] ) && in_array( $raw['block_title_tag'], array( 'h2', 'h3', 'h4', 'div' ), true ) ? $raw['block_title_tag'] : 'h2';
		$pos = isset( $raw['block_link_pos'] ) && in_array( $raw['block_link_pos'], array( 'cabecera', 'pie', 'ambos' ), true ) ? $raw['block_link_pos'] : 'pie';

		$chips = array();
		foreach ( $settings['chip_terms'] as $key ) {
			list( $taxonomy, $slug ) = explode( ':', $key, 2 );
			$term                    = get_term_by( 'slug', sanitize_title( $slug ), sanitize_key( $taxonomy ) );
			if ( $term && ! is_wp_error( $term ) ) {
				$chips[ $key ] = $term->name;
			}
		}

		$all = isset( $raw['chips_all_label'] ) && is_scalar( $raw['chips_all_label'] ) ? trim( sanitize_text_field( $raw['chips_all_label'] ) ) : '';

		return array(
			'title'     => isset( $raw['block_title'] ) && is_scalar( $raw['block_title'] ) ? trim( sanitize_text_field( $raw['block_title'] ) ) : '',
			'tag'       => $tag,
			'subtitle'  => isset( $raw['block_subtitle'] ) && is_scalar( $raw['block_subtitle'] ) ? trim( sanitize_text_field( $raw['block_subtitle'] ) ) : '',
			'link_text' => ( '' !== $text && '' !== $url ) ? $text : '',
			'link_url'  => $url,
			'external'  => $external,
			'link_pos'  => $pos,
			'chips'     => $chips,
			'chips_all' => '' !== $all ? $all : __( 'Todo', 'wp-multi-post-type-blog' ),
		);
	}

	/**
	 * Encabezado del bloque: título, bajada, chips y/o enlace.
	 *
	 * Los chips se sientan a la derecha del título cuando la cabecera está libre (portada) y
	 * bajan a una fila propia cuando la ocupan la bajada o el enlace (página de autor).
	 *
	 * @param array $header   Datos de build_vc_header().
	 * @param array $settings Ajustes saneados.
	 */
	protected static function render_vc_header( $header, $settings ) {
		if ( empty( $header ) ) {
			return;
		}

		$link_in_head = '' !== $header['link_text'] && in_array( $header['link_pos'], array( 'cabecera', 'ambos' ), true );
		$chips_in_row = ! empty( $header['chips'] ) && ( $link_in_head || '' !== $header['subtitle'] );
		$has_head     = '' !== $header['title'] || $link_in_head || ( ! empty( $header['chips'] ) && ! $chips_in_row );

		if ( $has_head ) {
			$classes = 'wpmb-cab';
			if ( '' !== $header['subtitle'] ) {
				$classes .= ' wpmb-cab--con-bajada';
			}
			if ( 'vc_ranking' === $settings['layout_type'] ) {
				$classes .= ' wpmb-cab--ranking';
			}
			?>
			<div class="<?php echo esc_attr( $classes ); ?>">
				<?php if ( '' !== $header['title'] ) : ?>
					<div class="wpmb-cab__tx">
						<<?php echo $header['tag']; ?> class="wpmb-cab__tit"><?php echo esc_html( $header['title'] ); ?></<?php echo $header['tag']; ?>>
						<?php if ( '' !== $header['subtitle'] ) : ?>
							<p class="wpmb-cab__sub"><?php echo esc_html( $header['subtitle'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php
				if ( ! empty( $header['chips'] ) && ! $chips_in_row ) {
					self::render_vc_chips( $header, '' );
				}
				if ( $link_in_head ) {
					self::render_vc_link( $header, 'enlace' );
				}
				?>
			</div>
			<?php
		}

		if ( $chips_in_row ) {
			self::render_vc_chips( $header, ' wpmb-chips--fila' );
		}
	}

	/**
	 * Chips de filtro. Son botones: cambian el contenido sin navegar.
	 *
	 * @param array  $header Datos del encabezado.
	 * @param string $extra  Clases extra.
	 */
	protected static function render_vc_chips( $header, $extra ) {
		?>
		<div class="wpmb-chips wpmb-filtros<?php echo esc_attr( $extra ); ?>" role="group" aria-label="<?php esc_attr_e( 'Filtrar por tema', 'wp-multi-post-type-blog' ); ?>">
			<button type="button" class="wpmb-chip active" aria-pressed="true" data-term=""><?php echo esc_html( $header['chips_all'] ); ?></button>
			<?php foreach ( $header['chips'] as $key => $name ) : ?>
				<button type="button" class="wpmb-chip" aria-pressed="false" data-term="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $name ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Enlace «ver todo», como enlace con flecha o como botón secundario.
	 *
	 * @param array  $header Datos del encabezado.
	 * @param string $style  enlace|boton.
	 * @param bool   $small  Botón pequeño (el de la portada).
	 */
	protected static function render_vc_link( $header, $style, $small = false ) {
		$class = 'boton' === $style ? 'wpmb-btn wpmb-btn--sec' . ( $small ? ' wpmb-btn--sm' : '' ) : 'wpmb-enlace';
		$attrs = $header['external'] ? ' target="_blank" rel="noopener"' : '';

		printf(
			'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
			esc_attr( $class ),
			esc_url( $header['link_url'] ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- literal fijo.
			esc_html( $header['link_text'] )
		);
	}

	/**
	 * Enlace al pie, si corresponde.
	 *
	 * @param array $header Datos del encabezado.
	 * @param bool  $small  Botón pequeño.
	 */
	protected static function render_vc_foot_link( $header, $small = false ) {
		if ( empty( $header ) || '' === $header['link_text'] || ! in_array( $header['link_pos'], array( 'pie', 'ambos' ), true ) ) {
			return;
		}

		echo '<p class="wpmb-pie">';
		self::render_vc_link( $header, 'boton', $small );
		echo '</p>';
	}
}
