<?php
/**
 * Plugin Name: WP Multi-Post Type Blog Block for Elementor
 * Description: Un bloque personalizado de Elementor que permite mostrar posts de múltiples post types con filtros de taxonomía, autores, paginación avanzada (AJAX Cargar Más, Scroll Infinito) y un diseño premium mobile-friendly.
 * Version: 1.5.0
 * Author: Voz Catolica
 * Text Domain: wp-multi-post-type-blog
 * Requires Plugins: elementor
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

namespace WpMultiPostTypeBlog;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'WP_MULTIPOST_BLOG_VERSION', '1.5.0' );
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
	load_plugin_textdomain( 'wp-multi-post-type-blog', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\wp_multipost_blog_missing_elementor_notice' );
		return;
	}

	// Include the addon class.
	require_once WP_MULTIPOST_BLOG_PATH . 'includes/class-elementor-addon.php';
	\WpMultiPostTypeBlog\Elementor_Addon::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\wp_multipost_blog_init' );

/**
 * Utility class containing sanitization and query-building helpers.
 */
class Utils {

	/**
	 * Sanitize an array input.
	 *
	 * @param mixed    $value    Raw value.
	 * @param callable $callback Sanitizer callback.
	 * @return array
	 */
	public static function sanitize_array( $value, $callback ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_filter( array_map( $callback, $value ) ) );
	}

	/**
	 * Sanitize an Elementor switcher value.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param string $default  Default value.
	 * @return string
	 */
	public static function sanitize_switch( $settings, $key, $default = 'yes' ) {
		if ( ! array_key_exists( $key, $settings ) ) {
			return $default;
		}

		return 'yes' === $settings[ $key ] ? 'yes' : 'no';
	}

	/**
	 * Validate post types against public post types.
	 *
	 * @param array $post_types Raw post type names.
	 * @return array
	 */
	public static function validate_post_types( $post_types ) {
		$public_post_types = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'names'
		);
		unset( $public_post_types['attachment'] );

		$post_types = array_intersect( $post_types, array_values( $public_post_types ) );

		return ! empty( $post_types ) ? array_values( $post_types ) : array( 'post' );
	}

	/**
	 * Build a sanitized tax query from taxonomy:term-slug keys.
	 *
	 * @param array  $terms    Raw taxonomy term keys.
	 * @param string $relation Tax query relation.
	 * @return array
	 */
	public static function build_tax_query( $terms, $relation = 'AND' ) {
		$grouped_terms = array();
		$relation      = 'OR' === strtoupper( $relation ) ? 'OR' : 'AND';

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

		$tax_query = array( 'relation' => $relation );
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
	 * Return the allowed query orderby values.
	 *
	 * @return array
	 */
	public static function allowed_orderby() {
		return array( 'date', 'title', 'rand', 'comment_count', 'menu_order' );
	}

	/**
	 * Sanitize widget settings for rendering and AJAX.
	 *
	 * @param array $settings Raw widget settings.
	 * @return array
	 */
	public static function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$post_types     = ! empty( $settings['post_types'] ) ? self::sanitize_array( (array) $settings['post_types'], 'sanitize_key' ) : array( 'post' );
		$post_types     = self::validate_post_types( $post_types );
		$authors        = ! empty( $settings['authors'] ) ? self::sanitize_array( (array) $settings['authors'], 'absint' ) : array();
		$terms          = ! empty( $settings['terms'] ) ? self::sanitize_array( (array) $settings['terms'], 'sanitize_text_field' ) : array();
		$orderby        = ! empty( $settings['orderby'] ) ? sanitize_key( $settings['orderby'] ) : 'date';
		$order          = ! empty( $settings['order'] ) ? strtoupper( sanitize_key( $settings['order'] ) ) : 'DESC';
		$pagination     = ! empty( $settings['pagination'] ) ? sanitize_key( $settings['pagination'] ) : 'none';
		$tax_relation   = ! empty( $settings['tax_relation'] ) ? strtoupper( sanitize_key( $settings['tax_relation'] ) ) : 'AND';
		$posts_per_page = ! empty( $settings['posts_per_page'] ) ? intval( $settings['posts_per_page'] ) : 5;
		$offset         = ! empty( $settings['offset'] ) ? intval( $settings['offset'] ) : 0;
		if ( ! empty( $settings['exclude_ids'] ) && is_array( $settings['exclude_ids'] ) ) {
			$exclude_ids = self::sanitize_array( $settings['exclude_ids'], 'absint' );
		} else {
			$exclude_ids = ! empty( $settings['exclude_ids'] ) ? self::sanitize_array( preg_split( '/[\s,]+/', (string) $settings['exclude_ids'] ), 'absint' ) : array();
		}

		$current_post_id = ! empty( $settings['current_post_id'] ) ? absint( $settings['current_post_id'] ) : 0;
		$archive_author_id = ! empty( $settings['archive_author_id'] ) ? absint( $settings['archive_author_id'] ) : 0;

		$layout_type = ! empty( $settings['layout_type'] ) ? sanitize_key( $settings['layout_type'] ) : 'classic';
		if ( ! in_array( $layout_type, array( 'classic', 'compact' ), true ) ) {
			$layout_type = 'classic';
		}

		if ( ! in_array( $orderby, self::allowed_orderby(), true ) ) {
			$orderby = 'date';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		if ( ! in_array( $pagination, array( 'none', 'numbers', 'load_more', 'infinite' ), true ) ) {
			$pagination = 'none';
		}

		if ( ! in_array( $tax_relation, array( 'AND', 'OR' ), true ) ) {
			$tax_relation = 'AND';
		}

		$posts_per_page = max( 1, min( 100, $posts_per_page ) );
		$offset         = max( 0, min( 500, $offset ) );
		$exclude_ids    = array_values( array_unique( array_filter( $exclude_ids ) ) );

		if ( 'yes' === self::sanitize_switch( $settings, 'exclude_current_post', 'yes' ) && $current_post_id ) {
			$exclude_ids[] = $current_post_id;
			$exclude_ids   = array_values( array_unique( $exclude_ids ) );
		}

		return array(
			'post_types'           => $post_types,
			'authors'              => $authors,
			'terms'                => $terms,
			'tax_relation'         => $tax_relation,
			'orderby'              => $orderby,
			'order'                => $order,
			'posts_per_page'       => $posts_per_page,
			'offset'               => $offset,
			'exclude_ids'          => $exclude_ids,
			'current_post_id'      => $current_post_id,
			'exclude_current_post' => self::sanitize_switch( $settings, 'exclude_current_post', 'yes' ),
			'show_featured'        => self::sanitize_switch( $settings, 'show_featured', 'yes' ),
			'show_author'          => self::sanitize_switch( $settings, 'show_author', 'yes' ),
			'show_date'            => self::sanitize_switch( $settings, 'show_date', 'yes' ),
			'show_views'           => self::sanitize_switch( $settings, 'show_views', 'yes' ),
			'show_excerpt'         => self::sanitize_switch( $settings, 'show_excerpt', 'yes' ),
			'show_category'        => self::sanitize_switch( $settings, 'show_category', 'yes' ),
			'excerpt_words'        => ! empty( $settings['excerpt_words'] ) ? max( 0, min( 80, intval( $settings['excerpt_words'] ) ) ) : 30,
			'read_more_text'       => ! empty( $settings['read_more_text'] ) ? sanitize_text_field( $settings['read_more_text'] ) : __( 'LEER MÁS', 'wp-multi-post-type-blog' ),
			'load_more_text'       => ! empty( $settings['load_more_text'] ) ? sanitize_text_field( $settings['load_more_text'] ) : __( 'Cargar Más', 'wp-multi-post-type-blog' ),
			'featured_image_size'  => ! empty( $settings['featured_image_size'] ) ? sanitize_key( $settings['featured_image_size'] ) : 'full',
			'list_image_size'      => ! empty( $settings['list_image_size'] ) ? sanitize_key( $settings['list_image_size'] ) : 'medium_large',
			'columns'              => ! empty( $settings['columns'] ) ? max( 1, min( 3, intval( $settings['columns'] ) ) ) : 1,
			'pagination'           => $pagination,
			'archive_author_id'    => $archive_author_id,
			'layout_type'          => $layout_type,
		);
	}

	/**
	 * Create a signed payload for AJAX requests.
	 *
	 * @param array $settings Sanitized settings.
	 * @return string
	 */
	public static function sign_settings( $settings ) {
		return wp_hash( wp_json_encode( $settings ) );
	}

	/**
	 * Build WP_Query args from sanitized settings.
	 *
	 * @param array $settings Sanitized settings.
	 * @param int   $paged    Page number.
	 * @return array
	 */
	public static function build_query_args( $settings, $paged = 1 ) {
		$paged       = max( 1, intval( $paged ) );
		$base_offset = ! empty( $settings['offset'] ) ? intval( $settings['offset'] ) : 0;
		$query_args = array(
			'post_type'      => $settings['post_types'],
			'posts_per_page' => $settings['posts_per_page'],
			'post_status'    => 'publish',
			'orderby'        => $settings['orderby'],
			'order'          => $settings['order'],
		);

		if ( $base_offset > 0 ) {
			$query_args['offset'] = $base_offset + ( ( $paged - 1 ) * $settings['posts_per_page'] );
		} else {
			$query_args['paged'] = $paged;
		}

		if ( ! empty( $settings['authors'] ) ) {
			$query_args['author__in'] = $settings['authors'];
		}

		if ( ! empty( $settings['archive_author_id'] ) ) {
			$query_args['author'] = $settings['archive_author_id'];
		}

		if ( ! empty( $settings['exclude_ids'] ) ) {
			$query_args['post__not_in'] = $settings['exclude_ids'];
		}

		if ( ! empty( $settings['terms'] ) ) {
			$tax_query = self::build_tax_query( $settings['terms'], $settings['tax_relation'] );
			if ( ! empty( $tax_query ) ) {
				$query_args['tax_query'] = $tax_query;
			}
		}

		return $query_args;
	}

	/**
	 * Calculate max pages when a manual offset is used.
	 *
	 * @param \WP_Query $query    Query object.
	 * @param array     $settings Sanitized settings.
	 * @return int
	 */
	public static function get_max_pages( $query, $settings ) {
		if ( empty( $settings['offset'] ) ) {
			return intval( $query->max_num_pages );
		}

		$available_posts = max( 0, intval( $query->found_posts ) - intval( $settings['offset'] ) );

		return (int) ceil( $available_posts / max( 1, intval( $settings['posts_per_page'] ) ) );
	}
}

/**
 * AJAX Handler for loading more posts.
 */
function wp_multipost_blog_load_more_handler() {
	// Check security nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), WP_MULTIPOST_BLOG_AJAX_NONCE ) ) {
		wp_send_json_error( 'Acceso no autorizado o token vencido.', 403 );
	}

	// 1.3: Verify Elementor dependency BEFORE database query
	if ( ! class_exists( 'Elementor\\Widget_Base' ) ) {
		wp_send_json_error( 'Elementor no está disponible.', 500 );
	}

	// Include widget file to access static render helpers.
	require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-blog-posts-widget.php';

	$paged        = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
	
	// Note: wp_unslash() recursively removes slashes on array data inputs like settings arrays.
	$settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
	$signature    = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';

	if ( empty( $settings_raw ) || ! is_array( $settings_raw ) ) {
		wp_send_json_error( 'Configuración inválida.', 400 );
	}

	$settings = Utils::sanitize_settings( $settings_raw );

	if ( empty( $signature ) || ! hash_equals( Utils::sign_settings( $settings ), $signature ) ) {
		wp_send_json_error( 'Configuración no autorizada.', 403 );
	}

	$filter_post_type = isset( $_POST['filter_post_type'] ) ? sanitize_key( wp_unslash( $_POST['filter_post_type'] ) ) : '';
	if ( ! empty( $filter_post_type ) && in_array( $filter_post_type, $settings['post_types'], true ) ) {
		$settings['post_types'] = array( $filter_post_type );
	}

	$query_args = Utils::build_query_args( $settings, $paged );
	$query = new \WP_Query( $query_args );
	$max_pages = Utils::get_max_pages( $query, $settings );
	$html      = '';

	if ( $query->have_posts() ) {
		// 5.1: Pre-cache postmeta (including views meta) to prevent extra queries in AJAX loops
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $post_ids ) ) {
			update_postmeta_cache( $post_ids );
		}

		while ( $query->have_posts() ) {
			$query->the_post();
			// Since this is loaded via AJAX (which is page > 1), we ONLY output standard list items.
			$html .= \WpMultiPostTypeBlog\Widgets\Blog_Posts_Widget::render_list_post( get_post(), $settings );
		}
		wp_reset_postdata();

		wp_send_json_success( array(
			'html'        => $html,
			'max_pages'   => $max_pages,
			'found_posts' => $query->found_posts,
		) );
	} else {
		wp_send_json_success( array(
			'html'        => '',
			'max_pages'   => 0,
			'found_posts' => 0, // 2.3: Return found_posts 0 when no posts
		) );
	}
}
add_action( 'wp_ajax_wp_multiblog_load_more', __NAMESPACE__ . '\wp_multipost_blog_load_more_handler' );
add_action( 'wp_ajax_nopriv_wp_multiblog_load_more', __NAMESPACE__ . '\wp_multipost_blog_load_more_handler' );

/**
 * Clear all cache transients starting with wpmb_.
 */
function wp_multipost_blog_clear_all_transients() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpmb_%' OR option_name LIKE '_transient_timeout_wpmb_%'" );
}
add_action( 'save_post', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'deleted_post', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'created_term', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'edited_term', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'delete_term', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'profile_update', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'user_register', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'delete_user', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
