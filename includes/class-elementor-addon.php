<?php
namespace WpMultiPostTypeBlog;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Addon class to manage widget registration and asset loading.
 */
class Elementor_Addon {

	/**
	 * Instance of this class.
	 *
	 * @var Elementor_Addon
	 */
	private static $instance = null;

	/**
	 * Get class instance.
	 *
	 * @return Elementor_Addon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register widgets.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Register styles and scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// wp_enqueue_scripts does not run in wp-admin, so the handles must also be
		// registered before the Elementor editor tries to enqueue them.
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Register the custom widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widget manager.
	 */
	public function register_widgets( $widgets_manager ) {
		require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-blog-posts-widget.php';
		require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-blog-archive-widget.php';
		require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-author-list-widget.php';
		require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-section-heading-widget.php';
		require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-recent-comments-widget.php';
		$widgets_manager->register( new \WpMultiPostTypeBlog\Widgets\Blog_Posts_Widget() );
		$widgets_manager->register( new \WpMultiPostTypeBlog\Widgets\Blog_Archive_Widget() );
		$widgets_manager->register( new \WpMultiPostTypeBlog\Widgets\Author_List_Widget() );
		$widgets_manager->register( new \WpMultiPostTypeBlog\Widgets\Section_Heading_Widget() );
		$widgets_manager->register( new \WpMultiPostTypeBlog\Widgets\Recent_Comments_Widget() );
	}

	/**
	 * Register frontend CSS and Javascript assets.
	 */
	public function enqueue_assets() {
		// Runs on both the frontend and the editor hook; register only once.
		if ( wp_style_is( 'wp-multipost-blog-widget-css', 'registered' ) ) {
			return;
		}

		// La version sale de la fecha del archivo, no de la del plugin: editar el CSS
		// sin tocar la version dejaba a los navegadores sirviendo la hoja vieja.
		$css_rel = 'assets/css/blog-posts-widget.css';
		$css_ver = @filemtime( dirname( __DIR__ ) . '/' . $css_rel );
		wp_register_style( 'wp-multipost-blog-widget-css', WP_MULTIPOST_BLOG_URL . $css_rel, array(), $css_ver ? (string) $css_ver : WP_MULTIPOST_BLOG_VERSION );

		// Sistema de diseño de Voz Católica: tokens, fuentes autoalojadas y los diseños
		// «Sistema VC». Va aparte para que las páginas con los diseños clásicos no lo carguen.
		$ds_rel = 'assets/css/sistema.css';
		$ds_ver = @filemtime( dirname( __DIR__ ) . '/' . $ds_rel );
		wp_register_style( 'wp-multipost-blog-ds-css', WP_MULTIPOST_BLOG_URL . $ds_rel, array( 'wp-multipost-blog-widget-css' ), $ds_ver ? (string) $ds_ver : WP_MULTIPOST_BLOG_VERSION );

		wp_register_script(
			'wp-multipost-blog-widget-js',
			WP_MULTIPOST_BLOG_URL . 'assets/js/blog-posts-widget.js',
			array( 'jquery' ),
			WP_MULTIPOST_BLOG_VERSION,
			true
		);

		// Localize the script with ajax settings and nonce.
		wp_localize_script(
			'wp-multipost-blog-widget-js',
			'wpMultipostBlogAjax',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( WP_MULTIPOST_BLOG_AJAX_NONCE ),
				'no_posts_text' => esc_html__( 'No se encontraron publicaciones.', 'wp-multi-post-type-blog' ),
			)
		);
	}

	/**
	 * Enqueue assets in the Elementor Editor (for live previewing to work nicely).
	 */
	public function enqueue_editor_assets() {
		wp_enqueue_style( 'wp-multipost-blog-widget-css' );
		wp_enqueue_style( 'wp-multipost-blog-ds-css' );
	}
}
