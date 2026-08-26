<?php
/**
 * Plugin Name: WP Multi-Post Type Blog Block for Elementor
 * Description: Un bloque personalizado de Elementor que permite mostrar posts de múltiples post types con filtros de taxonomía, autores, paginación avanzada (AJAX Cargar Más, Scroll Infinito) y un diseño premium mobile-friendly.
 * Version: 2.8.1
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
define( 'WP_MULTIPOST_BLOG_VERSION', '2.8.1' );
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

	// Loaded before the Elementor check: the fallback image lookup is used by the AJAX
	// handler and the settings screen should exist even if Elementor is missing.
	require_once WP_MULTIPOST_BLOG_PATH . 'includes/class-views-source.php';
	// Antes de la comprobación de Elementor: el filtro que ordena por vistas
	// tiene que estar enganchado también para el manejador AJAX de «cargar
	// más», que arma su propia WP_Query sin pasar por el widget.
	Views_Source::register();

	require_once WP_MULTIPOST_BLOG_PATH . 'includes/class-admin-settings.php';
	if ( is_admin() ) {
		Admin_Settings::get_instance();
	}

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

		// Drop nested arrays/objects before handing values to scalar sanitizers.
		$value = array_filter( $value, 'is_scalar' );

		return array_values( array_filter( array_map( $callback, $value ) ) );
	}

	/**
	 * Read a scalar setting, discarding arrays/objects coming from a tampered payload.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param mixed  $default  Value returned when the key is missing or not scalar.
	 * @return mixed
	 */
	public static function scalar_value( $settings, $key, $default = '' ) {
		if ( ! isset( $settings[ $key ] ) || ! is_scalar( $settings[ $key ] ) ) {
			return $default;
		}

		return $settings[ $key ];
	}

	/**
	 * Read a text setting, falling back to a default when empty.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param string $default  Fallback value.
	 * @return string
	 */
	public static function text_or_default( $settings, $key, $default ) {
		$value = sanitize_text_field( (string) self::scalar_value( $settings, $key, '' ) );

		return '' !== $value ? $value : $default;
	}

	/**
	 * Read a key-like setting, falling back to a default when empty.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param string $default  Fallback value.
	 * @return string
	 */
	public static function key_or_default( $settings, $key, $default ) {
		$value = sanitize_key( (string) self::scalar_value( $settings, $key, '' ) );

		return '' !== $value ? $value : $default;
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
		return array_merge(
			array( 'date', 'title', 'rand', 'comment_count', 'menu_order' ),
			Views_Source::order_modes()
		);
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
		$orderby        = sanitize_key( self::scalar_value( $settings, 'orderby', 'date' ) );
		$order          = strtoupper( sanitize_key( self::scalar_value( $settings, 'order', 'DESC' ) ) );
		$pagination     = sanitize_key( self::scalar_value( $settings, 'pagination', 'none' ) );
		$tax_relation   = strtoupper( sanitize_key( self::scalar_value( $settings, 'tax_relation', 'AND' ) ) );
		$posts_per_page = intval( self::scalar_value( $settings, 'posts_per_page', 5 ) );
		$posts_per_page = $posts_per_page > 0 ? $posts_per_page : 5;
		$offset         = intval( self::scalar_value( $settings, 'offset', 0 ) );
		if ( ! empty( $settings['exclude_ids'] ) && is_array( $settings['exclude_ids'] ) ) {
			$exclude_ids = self::sanitize_array( $settings['exclude_ids'], 'absint' );
		} else {
			$exclude_ids_raw = (string) self::scalar_value( $settings, 'exclude_ids', '' );
			$exclude_ids     = '' !== $exclude_ids_raw ? self::sanitize_array( preg_split( '/[\s,]+/', $exclude_ids_raw ), 'absint' ) : array();
		}

		// Ventana y piso del ordenamiento por vistas. Se acotan acá y no en el
		// filtro SQL porque estos valores viajan firmados hasta el AJAX de
		// «cargar más», donde ya no hay controles de Elementor que los limiten.
		$views_range = absint( self::scalar_value( $settings, 'views_range', 30 ) );
		$views_range = max( 1, min( 3653, $views_range ? $views_range : 30 ) );
		$views_min   = absint( self::scalar_value( $settings, 'views_min', 20 ) );
		$views_min   = min( 100000, $views_min );

		$current_post_id = absint( self::scalar_value( $settings, 'current_post_id', 0 ) );
		$archive_author_id = absint( self::scalar_value( $settings, 'archive_author_id', 0 ) );

		$layout_type = sanitize_key( self::scalar_value( $settings, 'layout_type', 'classic' ) );
		if ( ! in_array( $layout_type, array( 'classic', 'compact' ), true ) ) {
			$layout_type = 'classic';
		}

		// Last step of the image chain, reached only when the post has no featured image
		// and its post type has no fallback image configured.
		$image_fallback = sanitize_key( self::scalar_value( $settings, 'image_fallback', 'hide' ) );
		if ( ! in_array( $image_fallback, array( 'hide', 'placeholder' ), true ) ) {
			$image_fallback = 'hide';
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
			'views_range'          => $views_range,
			'views_min'            => $views_min,
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
			'excerpt_words'        => max( 0, min( 80, intval( self::scalar_value( $settings, 'excerpt_words', 30 ) ) ) ),
			'read_more_text'       => self::text_or_default( $settings, 'read_more_text', __( 'LEER MÁS', 'wp-multi-post-type-blog' ) ),
			'load_more_text'       => self::text_or_default( $settings, 'load_more_text', __( 'Cargar Más', 'wp-multi-post-type-blog' ) ),
			'featured_image_size'  => self::key_or_default( $settings, 'featured_image_size', 'full' ),
			'list_image_size'      => self::key_or_default( $settings, 'list_image_size', 'medium_large' ),
			'featured_title_tag'   => self::heading_tag( $settings, 'featured_title_tag', 'h2' ),
			'list_title_tag'       => self::heading_tag( $settings, 'list_title_tag', 'h3' ),
			'hide_featured_duplicates' => self::sanitize_switch( $settings, 'hide_featured_duplicates', 'yes' ),
			'category_level'       => self::category_level( $settings ),
			'columns'              => max( 1, min( 3, intval( self::scalar_value( $settings, 'columns', 1 ) ) ) ),
			'pagination'           => $pagination,
			'archive_author_id'    => $archive_author_id,
			'layout_type'          => $layout_type,
			'image_fallback'       => $image_fallback,
		);
	}

	/**
	 * Parse a comma separated list of user IDs.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @return array
	 */
	public static function user_id_list( $settings, $key ) {
		$raw = (string) self::scalar_value( $settings, $key, '' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		return array_values( array_unique( self::sanitize_array( preg_split( '/[\s,]+/', $raw ), 'absint' ) ) );
	}

	/**
	 * Sanitize the settings of the author list widget.
	 *
	 * @param array $settings Raw widget settings.
	 * @return array
	 */
	public static function sanitize_author_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();

		$post_types = ! empty( $settings['post_types'] ) ? self::sanitize_array( (array) $settings['post_types'], 'sanitize_key' ) : array( 'post' );
		$post_types = self::validate_post_types( $post_types );

		$roles     = ! empty( $settings['roles'] ) ? self::sanitize_array( (array) $settings['roles'], 'sanitize_key' ) : array();
		$known     = array_keys( wp_roles()->get_names() );
		$roles     = array_values( array_intersect( $roles, $known ) );

		$orderby = sanitize_key( self::scalar_value( $settings, 'orderby', 'recent_activity' ) );
		if ( ! in_array( $orderby, array( 'recent_activity', 'post_count', 'name', 'random' ), true ) ) {
			$orderby = 'recent_activity';
		}

		$order = strtoupper( sanitize_key( self::scalar_value( $settings, 'order', 'DESC' ) ) );

		$layout = sanitize_key( self::scalar_value( $settings, 'layout', 'list' ) );
		if ( ! in_array( $layout, array( 'list', 'cards', 'compact' ), true ) ) {
			$layout = 'list';
		}

		return array(
			'layout'          => $layout,
			'post_types'      => $post_types,
			'roles'           => $roles,
			'number'          => max( 1, min( 100, intval( self::scalar_value( $settings, 'number', 8 ) ) ) ),
			'min_posts'       => max( 1, min( 500, intval( self::scalar_value( $settings, 'min_posts', 1 ) ) ) ),
			'orderby'         => $orderby,
			'order'           => 'ASC' === $order ? 'ASC' : 'DESC',
			'include_users'   => self::user_id_list( $settings, 'include_users' ),
			'exclude_users'   => self::user_id_list( $settings, 'exclude_users' ),
			'show_avatar'     => self::sanitize_switch( $settings, 'show_avatar', 'yes' ),
			'avatar_size'     => max( 16, min( 300, intval( self::scalar_value( $settings, 'avatar_size', 56 ) ) ) ),
			'show_name'       => self::sanitize_switch( $settings, 'show_name', 'yes' ),
			'link_to'         => self::key_or_default( $settings, 'link_to', 'author_page' ),
			'show_last_post'  => self::sanitize_switch( $settings, 'show_last_post', 'yes' ),
			'show_post_date'  => self::sanitize_switch( $settings, 'show_post_date', 'no' ),
			'show_post_count' => self::sanitize_switch( $settings, 'show_post_count', 'no' ),
			'show_bio'        => self::sanitize_switch( $settings, 'show_bio', 'no' ),
			'bio_length'      => max( 20, min( 600, intval( self::scalar_value( $settings, 'bio_length', 120 ) ) ) ),
			'show_dividers'   => self::sanitize_switch( $settings, 'show_dividers', 'yes' ),
		);
	}

	/**
	 * Which level of a hierarchical taxonomy the category badge should show.
	 *
	 * @param array $settings Sanitized settings.
	 * @return string 'top' or 'deepest'.
	 */
	public static function category_level( $settings ) {
		$level = sanitize_key( self::scalar_value( $settings, 'category_level', 'top' ) );

		return in_array( $level, array( 'top', 'deepest' ), true ) ? $level : 'top';
	}

	/**
	 * Posts already rendered as the featured item, for the current request.
	 *
	 * @var array
	 */
	private static $featured_ids = array();

	/**
	 * Record a post as having been rendered in the featured slot.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function register_featured_id( $post_id ) {
		$post_id = absint( $post_id );

		if ( $post_id && ! in_array( $post_id, self::$featured_ids, true ) ) {
			self::$featured_ids[] = $post_id;
		}
	}

	/**
	 * Posts already used as the featured item earlier in the page.
	 *
	 * @return array
	 */
	public static function rendered_featured_ids() {
		return self::$featured_ids;
	}

	/**
	 * Exclude posts already featured by a widget rendered earlier in the page.
	 *
	 * Elementor renders widgets in document order, so by the time a widget builds
	 * its query every widget above it has already registered its featured post.
	 *
	 * The IDs are folded into 'exclude_ids' rather than applied at query time
	 * because the settings array is what gets signed and handed to the browser:
	 * doing it here is what makes the exclusion survive into the "load more"
	 * AJAX request, which runs with an empty registry.
	 *
	 * @param array $settings Sanitized settings.
	 * @return array Settings with the exclusions merged in.
	 */
	public static function apply_featured_exclusions( $settings ) {
		if ( empty( $settings['hide_featured_duplicates'] ) || 'yes' !== $settings['hide_featured_duplicates'] ) {
			return $settings;
		}

		$already = self::rendered_featured_ids();
		if ( empty( $already ) ) {
			return $settings;
		}

		$exclude             = isset( $settings['exclude_ids'] ) && is_array( $settings['exclude_ids'] ) ? $settings['exclude_ids'] : array();
		$settings['exclude_ids'] = array_values( array_unique( array_merge( $exclude, $already ) ) );

		return $settings;
	}

	/**
	 * Heading levels a post title is allowed to use.
	 *
	 * The tag is interpolated straight into the markup, so the whitelist is what
	 * keeps a tampered AJAX payload from injecting an arbitrary element.
	 *
	 * @return array
	 */
	public static function allowed_heading_tags() {
		return array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
	}

	/**
	 * Read a heading level setting, falling back to the default when unknown.
	 *
	 * @param array  $settings Raw settings.
	 * @param string $key      Setting key.
	 * @param string $default  Fallback tag.
	 * @return string
	 */
	public static function heading_tag( $settings, $key, $default ) {
		$tag = strtolower( sanitize_key( self::scalar_value( $settings, $key, $default ) ) );

		return in_array( $tag, self::allowed_heading_tags(), true ) ? $tag : $default;
	}

	/**
	 * Option name holding the incremental cache version.
	 */
	const CACHE_VERSION_OPTION = 'wpmb_cache_version';

	/**
	 * Get the current cache version used to namespace every plugin transient.
	 *
	 * @return int
	 */
	public static function cache_version() {
		$version = (int) get_option( self::CACHE_VERSION_OPTION, 0 );

		if ( $version < 1 ) {
			$version = 1;
			update_option( self::CACHE_VERSION_OPTION, $version, true );
		}

		return $version;
	}

	/**
	 * Build a versioned transient key.
	 *
	 * @param string $suffix Key suffix.
	 * @return string
	 */
	public static function cache_key( $suffix ) {
		return 'wpmb_' . self::cache_version() . '_' . $suffix;
	}

	/**
	 * Whether the cache was already invalidated during this request.
	 *
	 * @var bool
	 */
	private static $cache_flushed = false;

	/**
	 * Invalidate every plugin transient by bumping the cache version.
	 *
	 * Stale entries expire on their own, so no table-wide DELETE is needed and the
	 * invalidation also works on installs with a persistent object cache. A single
	 * bump per request is enough, which keeps bulk imports cheap.
	 */
	public static function flush_cache() {
		if ( self::$cache_flushed ) {
			return;
		}

		self::$cache_flushed = true;
		update_option( self::CACHE_VERSION_OPTION, self::cache_version() + 1, true );
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

		// An author archive pins the query to that author; combining it with the manual
		// author filter would AND both clauses and could yield an always-empty result.
		if ( ! empty( $settings['archive_author_id'] ) ) {
			$query_args['author'] = $settings['archive_author_id'];
		} elseif ( ! empty( $settings['authors'] ) ) {
			$query_args['author__in'] = $settings['authors'];
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

		// Último paso a propósito: el orden por vistas puede fijar post__in
		// (tendencias) y necesita ver los IDs ya excluidos y los tipos de
		// contenido definitivos.
		$query_args = Views_Source::apply_order( $query_args, $settings );

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
		// 5.1: Pre-cache postmeta to prevent extra queries in AJAX loops
		$post_ids = wp_list_pluck( $query->posts, 'ID' );
		if ( ! empty( $post_ids ) ) {
			update_postmeta_cache( $post_ids );

			// Views live in their own tables, so they need a separate batch query.
			if ( 'yes' === $settings['show_views'] ) {
				// El alcance del número mostrado sigue al del ordenamiento.
				\WpMultiPostTypeBlog\Views_Source::set_display_range( $settings );
				\WpMultiPostTypeBlog\Widgets\Blog_Posts_Widget::prime_views_cache( $query->posts );
			}
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
 * Invalidate every plugin transient.
 */
function wp_multipost_blog_clear_all_transients() {
	Utils::flush_cache();
}

/**
 * Invalidate caches on save, ignoring revisions, autosaves and auto-drafts.
 *
 * Note: this guard is only for 'save_post'. On 'deleted_post' the row is already gone,
 * so get_post() would return null and the flush must run unconditionally.
 *
 * @param int $post_id Post ID.
 */
function wp_multipost_blog_clear_transients_on_save( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$post = get_post( $post_id );
	if ( ! $post || 'auto-draft' === $post->post_status ) {
		return;
	}

	Utils::flush_cache();
}
add_action( 'save_post', __NAMESPACE__ . '\wp_multipost_blog_clear_transients_on_save' );
add_action( 'deleted_post', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'created_term', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'edited_term', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'delete_term', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'profile_update', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'user_register', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
add_action( 'delete_user', __NAMESPACE__ . '\wp_multipost_blog_clear_all_transients' );
