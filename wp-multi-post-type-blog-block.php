<?php
/**
 * Plugin Name: WP Multi-Post Type Blog Block for Elementor
 * Description: Un bloque personalizado de Elementor que permite mostrar posts de múltiples post types con filtros de taxonomía, autores, paginación avanzada (AJAX Cargar Más, Scroll Infinito) y un diseño premium mobile-friendly.
 * Version: 1.0.0
 * Author: Voz Catolica
 * Text Domain: wp-multi-post-type-blog
 * Requires Plugins: elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'WP_MULTIPOST_BLOG_VERSION', '1.0.0' );
define( 'WP_MULTIPOST_BLOG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_MULTIPOST_BLOG_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_MULTIPOST_BLOG_AJAX_NONCE', 'wp_multipost_blog_ajax_nonce' );

/**
 * Show an admin notice when Elementor is not active.
 */
function wp_multipost_blog_missing_elementor_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning is-dismissible">
		<p><?php esc_html_e( 'WP Multi-Post Type Blog Block requiere Elementor activo para registrar su widget.', 'wp-multi-post-type-blog' ); ?></p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function wp_multipost_blog_init() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'wp_multipost_blog_missing_elementor_notice' );
		return;
	}

	// Include the addon class.
	require_once WP_MULTIPOST_BLOG_PATH . 'includes/class-elementor-addon.php';
	\WpMultiPostTypeBlog\Elementor_Addon::get_instance();
}
add_action( 'plugins_loaded', 'wp_multipost_blog_init' );

/**
 * Sanitize an array input.
 *
 * @param mixed    $value    Raw value.
 * @param callable $callback Sanitizer callback.
 * @return array
 */
function wp_multipost_blog_sanitize_array( $value, $callback ) {
	if ( ! is_array( $value ) ) {
		return array();
	}

	return array_values( array_filter( array_map( $callback, $value ) ) );
}

/**
 * Validate post types against public post types.
 *
 * @param array $post_types Raw post type names.
 * @return array
 */
function wp_multipost_blog_validate_post_types( $post_types ) {
	$public_post_types = get_post_types( array( 'public' => true ), 'names' );
	unset( $public_post_types['attachment'] );

	$post_types = array_intersect( $post_types, array_values( $public_post_types ) );

	return ! empty( $post_types ) ? array_values( $post_types ) : array( 'post' );
}

/**
 * Build a sanitized tax query from taxonomy:term-slug keys.
 *
 * @param array $terms Raw taxonomy term keys.
 * @return array
 */
function wp_multipost_blog_build_tax_query( $terms ) {
	$grouped_terms = array();

	foreach ( $terms as $term_str ) {
		if ( false === strpos( $term_str, ':' ) ) {
			continue;
		}

		list( $taxonomy, $slug ) = explode( ':', $term_str, 2 );
		$taxonomy = sanitize_key( $taxonomy );
		$slug     = sanitize_title( $slug );

		if ( empty( $taxonomy ) || empty( $slug ) || ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		$taxonomy_object = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_object || ! $taxonomy_object->public ) {
			continue;
		}

		$grouped_terms[ $taxonomy ][] = $slug;
	}

	if ( empty( $grouped_terms ) ) {
		return array();
	}

	$tax_query = array( 'relation' => 'AND' );
	foreach ( $grouped_terms as $taxonomy => $slugs ) {
		$tax_query[] = array(
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array_unique( $slugs ),
			'operator' => 'IN',
		);
	}

	return $tax_query;
}

/**
 * AJAX Handler for loading more posts.
 */
function wp_multipost_blog_load_more_handler() {
	// Check security nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), WP_MULTIPOST_BLOG_AJAX_NONCE ) ) {
		wp_send_json_error( 'Acceso no autorizado o token vencido.', 403 );
	}

	$paged        = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
	$settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';

	if ( empty( $settings_raw ) || ! is_array( $settings_raw ) ) {
		wp_send_json_error( 'Configuración inválida.', 400 );
	}

	// Sanitize and extract query settings.
	$post_types     = isset( $settings_raw['post_types'] ) ? wp_multipost_blog_sanitize_array( $settings_raw['post_types'], 'sanitize_key' ) : array( 'post' );
	$post_types     = wp_multipost_blog_validate_post_types( $post_types );
	$authors        = isset( $settings_raw['authors'] ) ? wp_multipost_blog_sanitize_array( $settings_raw['authors'], 'absint' ) : array();
	$terms          = isset( $settings_raw['terms'] ) ? wp_multipost_blog_sanitize_array( $settings_raw['terms'], 'sanitize_text_field' ) : array();
	$orderby        = isset( $settings_raw['orderby'] ) ? sanitize_key( $settings_raw['orderby'] ) : 'date';
	$order          = isset( $settings_raw['order'] ) ? strtoupper( sanitize_key( $settings_raw['order'] ) ) : 'DESC';
	$posts_per_page = isset( $settings_raw['posts_per_page'] ) ? intval( $settings_raw['posts_per_page'] ) : 5;
	$exclude_ids    = isset( $settings_raw['exclude_ids'] ) ? wp_multipost_blog_sanitize_array( $settings_raw['exclude_ids'], 'absint' ) : array();

	$allowed_orderby = array( 'date', 'title', 'rand', 'comment_count', 'menu_order' );
	if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
		$orderby = 'date';
	}

	if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
		$order = 'DESC';
	}

	$posts_per_page = max( 1, min( 100, $posts_per_page ) );

	// Build WP_Query args.
	$query_args = array(
		'post_type'      => $post_types,
		'posts_per_page' => $posts_per_page,
		'paged'          => $paged,
		'post_status'    => 'publish',
		'orderby'        => $orderby,
		'order'          => $order,
	);

	if ( ! empty( $authors ) ) {
		$query_args['author__in'] = $authors;
	}

	if ( ! empty( $exclude_ids ) ) {
		$query_args['post__not_in'] = $exclude_ids;
	}

	if ( ! empty( $terms ) ) {
		$tax_query = wp_multipost_blog_build_tax_query( $terms );
		if ( ! empty( $tax_query ) ) {
			$query_args['tax_query'] = $tax_query;
		}
	}

	$query = new WP_Query( $query_args );
	$html = '';

	if ( $query->have_posts() ) {
		if ( ! class_exists( 'Elementor\\Widget_Base' ) ) {
			wp_send_json_error( 'Elementor no está disponible.', 500 );
		}

		// Include widget file to access static render helpers.
		require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-blog-posts-widget.php';

		while ( $query->have_posts() ) {
			$query->the_post();
			// Since this is loaded via AJAX (which is page > 1), we ONLY output standard list items.
			$html .= \WpMultiPostTypeBlog\Widgets\Blog_Posts_Widget::render_list_post( get_post() );
		}
		wp_reset_postdata();

		wp_send_json_success( array(
			'html'        => $html,
			'max_pages'   => $query->max_num_pages,
			'found_posts' => $query->found_posts,
		) );
	} else {
		wp_send_json_success( array(
			'html'      => '',
			'max_pages' => 0,
		) );
	}
}
add_action( 'wp_ajax_wp_multiblog_load_more', 'wp_multipost_blog_load_more_handler' );
add_action( 'wp_ajax_nopriv_wp_multiblog_load_more', 'wp_multipost_blog_load_more_handler' );
