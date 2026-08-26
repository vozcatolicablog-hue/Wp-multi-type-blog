<?php
/**
 * Widget de encabezado de sección.
 *
 * @package WpMultiPostTypeBlog
 */

namespace WpMultiPostTypeBlog\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reemplazo del encabezado de sección del tema JNews (`jeg_block_heading_6`).
 *
 * Reproduce el mismo objeto: un título con ícono, una línea gris al pie y un
 * segmento de color en el extremo izquierdo de esa línea.
 *
 * Dos cosas NO se copian tal cual, a propósito:
 *
 * 1. Las clases `jeg_*`. Heredarlas dejaría el encabezado atado a la hoja de
 *    estilos del tema, que es de lo que se está saliendo: al cambiar de tema
 *    el título quedaría sin borde, sin acento y con otra tipografía.
 *
 * 2. El ícono. JNews lo dibuja con FontAwesome 4 (`fa fa-comments`), una
 *    fuente que carga el tema y que desaparece con él. Acá se usa el control de
 *    íconos de Elementor, que trae su propia biblioteca: los mismos glifos,
 *    servidos por algo que va a seguir estando.
 */
class Section_Heading_Widget extends Widget_Base {

	public function get_name() {
		return 'wp_multipost_section_heading';
	}

	public function get_title() {
		return esc_html__( 'Encabezado de sección', 'wp-multi-post-type-blog' );
	}

	public function get_icon() {
		return 'eicon-t-letter';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'encabezado', 'titulo', 'seccion', 'heading', 'jnews' );
	}

	public function get_style_depends() {
		return array( 'wp-multipost-blog-widget-css' );
	}

	protected function register_controls() {

		/* ---------------- Contenido ---------------- */

		$this->start_controls_section(
			'seccion_contenido',
			array( 'label' => esc_html__( 'Contenido', 'wp-multi-post-type-blog' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'       => esc_html__( 'Título', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Título de sección', 'wp-multi-post-type-blog' ),
				'placeholder' => esc_html__( 'Últimos comentarios', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_control(
			'icon',
			array(
				'label'   => esc_html__( 'Ícono', 'wp-multi-post-type-blog' ),
				'type'    => Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-comments',
					'library' => 'fa-solid',
				),
			)
		);

		$this->add_control(
			'html_tag',
			array(
				'label'       => esc_html__( 'Etiqueta HTML', 'wp-multi-post-type-blog' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'h3',
				'options'     => array(
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'div' => 'div',
				),
				'description' => esc_html__( 'Un encabezado decorativo dentro de una página que ya tiene su H1 suele ir en H3.', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => esc_html__( 'Alineación', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::CHOOSE,
				'default'   => 'left',
				'options'   => array(
					'left'   => array(
						'title' => esc_html__( 'Izquierda', 'wp-multi-post-type-blog' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'wp-multi-post-type-blog' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Derecha', 'wp-multi-post-type-blog' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'prefix_class' => 'wpmptb-align-',
			)
		);

		$this->end_controls_section();

		/* ---------------- Estilo del título ---------------- */

		$this->start_controls_section(
			'seccion_titulo',
			array(
				'label' => esc_html__( 'Título', 'wp-multi-post-type-blog' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'title_color',
			array(
				'label'     => esc_html__( 'Color', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#083572',
				'selectors' => array(
					'{{WRAPPER}} .wpmptb-heading__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'selector' => '{{WRAPPER}} .wpmptb-heading__title',
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => esc_html__( 'Color del ícono', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpmptb-heading__icon' => 'color: {{VALUE}};',
				),
				'description' => esc_html__( 'Vacío = el mismo color del título.', 'wp-multi-post-type-blog' ),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Tamaño del ícono', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 8, 'max' => 60 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .wpmptb-heading__icon' => 'font-size: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'icon_gap',
			array(
				'label'      => esc_html__( 'Separación del ícono', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 8 ),
				'selectors'  => array(
					'{{WRAPPER}} .wpmptb-heading__icon' => 'margin-right: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ---------------- Estilo de la línea ---------------- */

		$this->start_controls_section(
			'seccion_linea',
			array(
				'label' => esc_html__( 'Línea', 'wp-multi-post-type-blog' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'line_color',
			array(
				'label'     => esc_html__( 'Color de la línea', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#eeeeee',
				'selectors' => array(
					'{{WRAPPER}} .wpmptb-heading' => 'border-bottom-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'accent_color',
			array(
				'label'     => esc_html__( 'Color del acento', 'wp-multi-post-type-blog' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#0091FC',
				'selectors' => array(
					'{{WRAPPER}} .wpmptb-heading::after' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'accent_width',
			array(
				'label'      => esc_html__( 'Ancho del acento', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 300 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 30 ),
				'selectors'  => array(
					'{{WRAPPER}} .wpmptb-heading::after' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'line_thickness',
			array(
				'label'      => esc_html__( 'Grosor', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 12 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 2 ),
				'selectors'  => array(
					// El acento se apoya sobre el borde: su desplazamiento tiene
					// que seguir al grosor o queda flotando por encima.
					'{{WRAPPER}} .wpmptb-heading'        => 'border-bottom-width: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpmptb-heading::after'  => 'height: {{SIZE}}{{UNIT}}; bottom: -{{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'title_gap',
			array(
				'label'      => esc_html__( 'Espacio entre título y línea', 'wp-multi-post-type-blog' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array(
					'{{WRAPPER}} .wpmptb-heading__title' => 'padding-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$title    = trim( (string) ( $settings['title'] ?? '' ) );

		if ( '' === $title ) {
			return;
		}

		$allowed = array( 'h2', 'h3', 'h4', 'h5', 'div' );
		$tag     = in_array( $settings['html_tag'] ?? 'h3', $allowed, true ) ? $settings['html_tag'] : 'h3';

		$align  = in_array( $settings['align'] ?? 'left', array( 'left', 'center', 'right' ), true )
			? $settings['align']
			: 'left';
		$classes = 'wpmptb-heading wpmptb-heading--' . $align;

		printf( '<div class="%s">', esc_attr( $classes ) );
		printf( '<%s class="wpmptb-heading__title">', esc_attr( $tag ) );

		if ( ! empty( $settings['icon']['value'] ) ) {
			// El ícono es decorativo: el título ya dice de qué se trata, y
			// anunciarlo sería ruido para quien usa un lector de pantalla.
			echo '<span class="wpmptb-heading__icon" aria-hidden="true">';
			\Elementor\Icons_Manager::render_icon( $settings['icon'], array( 'aria-hidden' => 'true' ) );
			echo '</span>';
		}

		echo '<span class="wpmptb-heading__text">' . esc_html( $title ) . '</span>';

		printf( '</%s>', esc_attr( $tag ) );
		echo '</div>';
	}
}
