<?php
namespace WpMultiPostTypeBlog;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings screen where a fallback image/icon is assigned to each post type.
 *
 * Used when a post has no featured image. Lives outside the Elementor widget so the
 * lookup also works from the AJAX handler and does not depend on Elementor loading.
 */
class Admin_Settings {

	/**
	 * Settings group name.
	 */
	const OPTION_GROUP = 'wpmb_settings';

	/**
	 * Option storing the post type => attachment ID map.
	 */
	const OPTION_NAME = 'wpmb_post_type_images';

	/**
	 * Option storing the post type => custom field name(s) map.
	 */
	const OPTION_META = 'wpmb_post_type_image_meta';

	/**
	 * Settings page slug.
	 */
	const MENU_SLUG = 'wpmb-settings';

	/**
	 * Instance of this class.
	 *
	 * @var Admin_Settings
	 */
	private static $instance = null;

	/**
	 * Hook suffix returned by add_options_page().
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Get class instance.
	 *
	 * @return Admin_Settings
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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Public post types that can be assigned a fallback image.
	 *
	 * @return array Post type objects keyed by name.
	 */
	public static function get_supported_post_types() {
		$post_types = get_post_types(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'objects'
		);
		unset( $post_types['attachment'] );

		return $post_types;
	}

	/**
	 * Every configured fallback image, keyed by post type.
	 *
	 * @return array
	 */
	public static function get_images() {
		$images = get_option( self::OPTION_NAME, array() );

		return is_array( $images ) ? $images : array();
	}

	/**
	 * Fallback attachment ID for a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return int Attachment ID, or 0 when none is configured.
	 */
	public static function get_image_id( $post_type ) {
		$images = self::get_images();

		return isset( $images[ $post_type ] ) ? absint( $images[ $post_type ] ) : 0;
	}

	/**
	 * Custom fields that are known to hold the "real" featured image of a post type.
	 *
	 * Some post types never populate _thumbnail_id and keep their image in a meta of
	 * their own, so has_post_thumbnail() reports nothing and every entry falls back to
	 * the post type logo. These defaults make those types work without configuration.
	 *
	 * @return array Post type => ordered list of meta keys.
	 */
	public static function default_meta_keys() {
		$defaults = array(
			// Catálogo Ediciones Voz Católica: portada del libro.
			'vcec_book'     => array( '_vcec_cover_image_id', '_vcec_cover_image' ),
			// Simple Download Monitor: campo ACF propio, y la miniatura nativa del plugin.
			'sdm_downloads' => array( 'Imagen-destacada-descargas', 'sdm_upload_thumbnail' ),
		);

		return apply_filters( 'wpmb_default_image_meta_keys', $defaults );
	}

	/**
	 * Every configured custom field, keyed by post type.
	 *
	 * @return array
	 */
	public static function get_meta_settings() {
		$meta = get_option( self::OPTION_META, array() );

		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Meta keys to read the image from for a post type, in priority order.
	 *
	 * An explicit setting always wins over the built-in defaults; a single dash
	 * disables the lookup for that post type.
	 *
	 * @param string $post_type Post type name.
	 * @return array List of meta keys, empty when the lookup is disabled.
	 */
	public static function get_meta_keys( $post_type ) {
		$meta      = self::get_meta_settings();
		$raw       = isset( $meta[ $post_type ] ) ? trim( (string) $meta[ $post_type ] ) : '';
		$defaults  = self::default_meta_keys();

		if ( '-' === $raw ) {
			return array();
		}

		if ( '' === $raw ) {
			return isset( $defaults[ $post_type ] ) ? (array) $defaults[ $post_type ] : array();
		}

		return self::split_meta_keys( $raw );
	}

	/**
	 * Split a comma separated list of meta keys into a clean array.
	 *
	 * Meta keys are not restricted to sanitize_key()'s alphabet: the ones created by
	 * ACF keep their capitals and dashes, so only whitespace and empties are dropped.
	 *
	 * @param string $raw Raw field value.
	 * @return array
	 */
	private static function split_meta_keys( $raw ) {
		$keys = array_map( 'trim', explode( ',', $raw ) );
		$keys = array_filter(
			$keys,
			static function ( $key ) {
				return '' !== $key && strlen( $key ) <= 255;
			}
		);

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Register the settings page under the Settings menu.
	 */
	public function add_settings_page() {
		$this->hook_suffix = add_options_page(
			esc_html__( 'Multi-Post Blog', 'wp-multi-post-type-blog' ),
			esc_html__( 'Multi-Post Blog', 'wp-multi-post-type-blog' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option and its sanitizer.
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPTION_META,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_meta' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Keep only known post types with a non-empty field list.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array
	 */
	public function sanitize_meta( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = array_keys( self::get_supported_post_types() );
		$clean   = array();

		foreach ( $value as $post_type => $keys ) {
			$post_type = sanitize_key( $post_type );
			if ( ! in_array( $post_type, $allowed, true ) || ! is_scalar( $keys ) ) {
				continue;
			}

			$keys = trim( sanitize_text_field( (string) $keys ) );
			if ( '-' === $keys ) {
				$clean[ $post_type ] = '-';
				continue;
			}

			$keys = implode( ', ', self::split_meta_keys( $keys ) );
			if ( '' !== $keys ) {
				$clean[ $post_type ] = $keys;
			}
		}

		return $clean;
	}

	/**
	 * Keep only known post types pointing at real attachments.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = array_keys( self::get_supported_post_types() );
		$clean   = array();

		foreach ( $value as $post_type => $attachment_id ) {
			$post_type = sanitize_key( $post_type );
			if ( ! in_array( $post_type, $allowed, true ) ) {
				continue;
			}

			$attachment_id = is_scalar( $attachment_id ) ? absint( $attachment_id ) : 0;
			if ( $attachment_id && 'attachment' === get_post_type( $attachment_id ) ) {
				$clean[ $post_type ] = $attachment_id;
			}
		}

		return $clean;
	}

	/**
	 * Enqueue the media library and the picker script on this screen only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( empty( $this->hook_suffix ) || $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'wp-multipost-blog-admin-js',
			WP_MULTIPOST_BLOG_URL . 'assets/js/admin-settings.js',
			array( 'jquery' ),
			WP_MULTIPOST_BLOG_VERSION,
			true
		);

		wp_localize_script(
			'wp-multipost-blog-admin-js',
			'wpMultipostBlogAdmin',
			array(
				'frameTitle'  => esc_html__( 'Seleccionar imagen de respaldo', 'wp-multi-post-type-blog' ),
				'frameButton' => esc_html__( 'Usar esta imagen', 'wp-multi-post-type-blog' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$images     = self::get_images();
		$meta       = self::get_meta_settings();
		$defaults   = self::default_meta_keys();
		$post_types = self::get_supported_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Multi-Post Blog', 'wp-multi-post-type-blog' ); ?></h1>

			<p class="description" style="max-width: 52em;">
				<?php esc_html_e( 'Para cada tipo de contenido, el widget busca la imagen en este orden: imagen destacada de WordPress, campo personalizado, imagen de respaldo. Si no encuentra ninguna, oculta el área de imagen o muestra el placeholder genérico, según el control "Sin imagen destacada" de cada widget.', 'wp-multi-post-type-blog' ); ?>
			</p>

			<style>
				.wpmb-settings-table { max-width: 1080px; margin-top: 16px; }
				.wpmb-settings-table td { vertical-align: top; padding-top: 14px; padding-bottom: 14px; }
				.wpmb-image-field { display: flex; align-items: center; gap: 12px; }
				.wpmb-image-preview {
					width: 60px;
					height: 60px;
					object-fit: contain;
					background: #f6f7f7;
					border: 1px solid #dcdcde;
					border-radius: 4px;
					padding: 4px;
				}
				.wpmb-meta-field input { width: 100%; max-width: 320px; }
				.wpmb-meta-hint { display: block; margin-top: 4px; color: #646970; font-size: 12px; }
			</style>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<table class="widefat striped wpmb-settings-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Tipo de contenido', 'wp-multi-post-type-blog' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Campo personalizado con la imagen', 'wp-multi-post-type-blog' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Imagen de respaldo', 'wp-multi-post-type-blog' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $post_types as $post_type ) : ?>
							<?php
							$attachment_id = isset( $images[ $post_type->name ] ) ? absint( $images[ $post_type->name ] ) : 0;
							$preview_url   = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
							$field_name    = self::OPTION_NAME . '[' . $post_type->name . ']';
							$meta_name     = self::OPTION_META . '[' . $post_type->name . ']';
							$meta_value    = isset( $meta[ $post_type->name ] ) ? (string) $meta[ $post_type->name ] : '';
							$meta_default  = isset( $defaults[ $post_type->name ] ) ? implode( ', ', (array) $defaults[ $post_type->name ] ) : '';
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $post_type->label ); ?></strong><br />
									<code><?php echo esc_html( $post_type->name ); ?></code>
								</td>
								<td class="wpmb-meta-field">
									<input
										type="text"
										name="<?php echo esc_attr( $meta_name ); ?>"
										value="<?php echo esc_attr( $meta_value ); ?>"
										placeholder="<?php echo esc_attr( '' !== $meta_default ? $meta_default : __( 'Ninguno', 'wp-multi-post-type-blog' ) ); ?>" />
									<span class="wpmb-meta-hint">
										<?php if ( '' !== $meta_default ) : ?>
											<?php
											printf(
												/* translators: %s: comma separated list of meta keys. */
												esc_html__( 'Por defecto: %s. Escribí otros nombres separados por coma para reemplazarlos, o un guion para no usar ninguno.', 'wp-multi-post-type-blog' ),
												'<code>' . esc_html( $meta_default ) . '</code>'
											);
											?>
										<?php else : ?>
											<?php esc_html_e( 'Nombre del campo personalizado que guarda la imagen (ID de adjunto o URL). Podés indicar varios separados por coma: se usa el primero con valor.', 'wp-multi-post-type-blog' ); ?>
										<?php endif; ?>
									</span>
								</td>
								<td>
									<div class="wpmb-image-field">
										<img
											class="wpmb-image-preview"
											src="<?php echo esc_url( $preview_url ); ?>"
											alt=""
											<?php echo $preview_url ? '' : 'style="display:none;"'; ?> />

										<input
											type="hidden"
											class="wpmb-image-id"
											name="<?php echo esc_attr( $field_name ); ?>"
											value="<?php echo esc_attr( $attachment_id ); ?>" />

										<button type="button" class="button wpmb-select-image">
											<?php esc_html_e( 'Seleccionar imagen', 'wp-multi-post-type-blog' ); ?>
										</button>

										<button type="button" class="button-link wpmb-remove-image" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>>
											<?php esc_html_e( 'Quitar', 'wp-multi-post-type-blog' ); ?>
										</button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
