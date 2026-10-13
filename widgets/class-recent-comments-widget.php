<?php
/**
 * Widget de últimos comentarios.
 *
 * @package WpMultiPostTypeBlog
 */

namespace WpMultiPostTypeBlog\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * «Últimos comentarios» del sistema de diseño (columna lateral de la portada).
 *
 * Cada comentario es un enlace a su ancla en la entrada: la cita, recta y entre «», recortada a
 * tres renglones; debajo, quién lo escribió, en qué entrada y cuándo.
 *
 * Del autor del comentario se muestra solo el primer nombre: la portada es pública y quien
 * comentó una entrada no eligió aparecer con nombre y apellido en la página de inicio.
 */
class Recent_Comments_Widget extends Widget_Base {

	public function get_name() {
		return 'wp_multipost_recent_comments';
	}

	public function get_title() {
		return esc_html__( 'Últimos comentarios', 'wp-multi-post-type-blog' );
	}

	public function get_icon() {
		return 'eicon-comments';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'comentarios', 'comments', 'recientes', 'voz catolica' );
	}

	public function get_style_depends() {
		return array( 'wp-multipost-blog-widget-css', 'wp-multipost-blog-ds-css' );
	}

	/**
	 * Tipos de contenido públicos.
	 *
	 * @return array
	 */
	private function get_post_type_options() {
		$options = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}
			$options[ $post_type->name ] = $post_type->label;
		}

		return $options;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array( 'label' => esc_html__( 'Comentarios', 'wp-multi-post-type-blog' ) )
		);

		$this->add_control(
			'block_title',
			array(
				'label'       => esc_html__( 'Título del bloque', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Últimos comentarios', 'wp-multi-post-type-blog' ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'block_title_tag',
			array(
				'label'   => esc_html__( 'Etiqueta del título', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'h2',
				'options' => array(
					'h2'  => 'H2',
					'h3'  => 'H3',
					'div' => 'div',
				),
			)
		);

		$this->add_control(
			'number',
			array(
				'label'   => esc_html__( 'Cantidad', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 3,
				'min'     => 1,
				'max'     => 20,
			)
		);

		$this->add_control(
			'post_types',
			array(
				'label'       => esc_html__( 'Comentarios de', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => array( 'post' ),
				'options'     => $this->get_post_type_options(),
				'label_block' => true,
			)
		);

		$this->add_control(
			'words',
			array(
				'label'       => esc_html__( 'Recortar a (palabras)', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 40,
				'min'         => 5,
				'max'         => 120,
				'description' => esc_html__( 'Además, la cita se corta visualmente a tres renglones.', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_control(
			'exclude_staff',
			array(
				'label'        => esc_html__( 'Ocultar respuestas del equipo', 'wp-multi-post-type-blog' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => esc_html__( 'Deja fuera los comentarios de usuarios que pueden editar entradas: el bloque muestra la voz de los lectores.', 'wp-multi-post-type-blog' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Ajustes saneados.
	 *
	 * @param array $raw Ajustes crudos.
	 * @return array
	 */
	private static function sanitize( $raw ) {
		$utils      = '\\WpMultiPostTypeBlog\\Utils';
		$post_types = ! empty( $raw['post_types'] ) ? $utils::sanitize_array( (array) $raw['post_types'], 'sanitize_key' ) : array( 'post' );
		$post_types = array_values( array_intersect( $post_types, array_keys( get_post_types( array( 'public' => true ) ) ) ) );

		return array(
			'number'        => max( 1, min( 20, intval( $utils::scalar_value( $raw, 'number', 3 ) ) ) ),
			'post_types'    => ! empty( $post_types ) ? $post_types : array( 'post' ),
			'words'         => max( 5, min( 120, intval( $utils::scalar_value( $raw, 'words', 40 ) ) ) ),
			'exclude_staff' => $utils::sanitize_switch( $raw, 'exclude_staff', 'yes' ),
		);
	}

	/**
	 * Comentarios aprobados más recientes, ya listos para pintar.
	 *
	 * Se cachean unos minutos: get_comments() con JOIN a posts es barato, pero esto va en la
	 * portada, que es la página más visitada. Aprobar un comentario invalida la caché.
	 *
	 * @param array $settings Ajustes saneados.
	 * @return array
	 */
	private static function fetch_comments( $settings ) {
		$cache_key = \WpMultiPostTypeBlog\Utils::cache_key( 'comments_' . md5( wp_json_encode( $settings ) ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$args = array(
			'status'      => 'approve',
			'type'        => 'comment',
			'post_status' => 'publish',
			'post_type'   => $settings['post_types'],
			'orderby'     => 'comment_date_gmt',
			'order'       => 'DESC',
			// Se piden de más por si hay que descartar respuestas del equipo.
			'number'      => $settings['number'] * 4,
		);

		$items = array();
		foreach ( get_comments( $args ) as $comment ) {
			if ( count( $items ) >= $settings['number'] ) {
				break;
			}

			if ( 'yes' === $settings['exclude_staff'] && $comment->user_id && user_can( (int) $comment->user_id, 'edit_posts' ) ) {
				continue;
			}

			$text = trim( wp_strip_all_tags( $comment->comment_content ) );
			if ( '' === $text ) {
				continue;
			}

			$name  = trim( (string) $comment->comment_author );
			$parts = preg_split( '/\s+/u', $name );
			$first = ! empty( $parts[0] ) ? $parts[0] : __( 'Un lector', 'wp-multi-post-type-blog' );

			$items[] = array(
				'text'  => wp_trim_words( $text, $settings['words'], '…' ),
				'name'  => $first,
				'post'  => get_the_title( $comment->comment_post_ID ),
				'url'   => get_comment_link( $comment ),
				// «24 sep»: el mes abreviado va en minúscula aunque la traducción lo capitalice.
				'date'  => function_exists( 'mb_strtolower' )
					? mb_strtolower( wp_date( 'j M', strtotime( $comment->comment_date_gmt . ' GMT' ) ), 'UTF-8' )
					: strtolower( wp_date( 'j M', strtotime( $comment->comment_date_gmt . ' GMT' ) ) ),
			);
		}

		set_transient( $cache_key, $items, 10 * MINUTE_IN_SECONDS );

		return $items;
	}

	protected function render() {
		$raw      = $this->get_settings_for_display();
		$settings = self::sanitize( $raw );
		$items    = self::fetch_comments( $settings );
		$title    = isset( $raw['block_title'] ) && is_scalar( $raw['block_title'] ) ? trim( sanitize_text_field( $raw['block_title'] ) ) : '';
		$tag      = isset( $raw['block_title_tag'] ) && in_array( $raw['block_title_tag'], array( 'h2', 'h3', 'div' ), true ) ? $raw['block_title_tag'] : 'h2';

		if ( empty( $items ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="wpmb-ds premium-blog-no-posts">' . esc_html__( 'Todavía no hay comentarios aprobados para mostrar.', 'wp-multi-post-type-blog' ) . '</div>';
			}
			return;
		}
		?>
		<div class="wpmb-ds wpmb-comentarios-bloque">
			<?php if ( '' !== $title ) : ?>
				<div class="wpmb-cab wpmb-cab--compacta">
					<<?php echo $tag; ?> class="wpmb-cab__tit"><?php echo esc_html( $title ); ?></<?php echo $tag; ?>>
				</div>
			<?php endif; ?>
			<div class="wpmb-comentarios">
				<?php foreach ( $items as $item ) : ?>
					<a class="wpmb-comentario" href="<?php echo esc_url( $item['url'] ); ?>">
						<q><?php echo esc_html( $item['text'] ); ?></q>
						<span class="wpmb-quien">
							<?php
							printf(
								/* translators: 1: commenter first name, 2: post title, 3: date. */
								esc_html__( '%1$s en %2$s, %3$s', 'wp-multi-post-type-blog' ),
								'<b>' . esc_html( $item['name'] ) . '</b>',
								'<em>' . esc_html( $item['post'] ) . '</em>',
								esc_html( $item['date'] )
							);
							?>
						</span>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
