<?php
namespace WpMultiPostTypeBlog\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Premium Multi-Post Archive Elementor Widget.
 */
class Blog_Archive_Widget extends Blog_Posts_Widget {

	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'wp_multi_post_type_archive_widget';
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'Premium Multi-Post Archive', 'wp-multi-post-type-blog' );
	}

	/**
	 * Render the widget content.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$widget_id = $this->get_id();
		$page_var  = 'wpmpb_' . $widget_id . '_page';

		$paged = isset( $_GET[ $page_var ] ) ? max( 1, absint( wp_unslash( $_GET[ $page_var ] ) ) ) : 1;

		// Automatically filter by the currently viewed author on author archives.
		if ( is_author() ) {
			$settings['archive_author_id'] = get_queried_object_id();
		}

		$settings['current_post_id'] = get_queried_object_id();
		$settings = wp_multipost_blog_sanitize_settings( $settings );
		$pagination = $settings['pagination'];

		$query = new \WP_Query( wp_multipost_blog_build_query_args( $settings, $paged ) );
		$max_pages = wp_multipost_blog_get_max_pages( $query, $settings );

		if ( ! $query->have_posts() ) {
			echo '<div class="premium-blog-no-posts">' . esc_html__( 'No se encontraron publicaciones.', 'wp-multi-post-type-blog' ) . '</div>';
			wp_reset_postdata();
			return;
		}

		$settings_signature = wp_multipost_blog_sign_settings( $settings );
		$columns_class = 'premium-blog-widget--columns-' . intval( $settings['columns'] );
		$layout_type_class = 'premium-blog-widget--layout-' . sanitize_html_class( $settings['layout_type'] );
		$post_types = $settings['post_types'];

		// Dynamically check which post types have actual posts matching the query parameters
		$active_post_types = array();
		foreach ( $post_types as $pt ) {
			$pt_query_args = wp_multipost_blog_build_query_args( $settings, 1 );
			$pt_query_args['posts_per_page'] = 1;
			$pt_query_args['offset'] = 0;
			if ( isset( $pt_query_args['paged'] ) ) {
				unset( $pt_query_args['paged'] );
			}
			$pt_query_args['fields'] = 'ids';
			$pt_query_args['post_type'] = $pt;

			$pt_query = new \WP_Query( $pt_query_args );
			if ( $pt_query->have_posts() ) {
				$active_post_types[] = $pt;
			}
		}
		?>
		<div id="wp-multipost-blog-<?php echo esc_attr( $widget_id ); ?>" 
			class="premium-blog-widget premium-blog-archive-widget <?php echo esc_attr( $columns_class ); ?> <?php echo esc_attr( $layout_type_class ); ?>"
			data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
			data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>"
			data-settings-signature="<?php echo esc_attr( $settings_signature ); ?>"
			data-pagination="<?php echo esc_attr( $pagination ); ?>"
			data-max-pages="<?php echo esc_attr( $max_pages ); ?>"
			data-current-page="<?php echo esc_attr( $paged ); ?>">
			
			<?php if ( count( $active_post_types ) > 1 ) : ?>
				<div class="premium-blog-archive__filters">
					<button class="filter-tab active" data-post-type="" aria-label="<?php esc_attr_e( 'Todos', 'wp-multi-post-type-blog' ); ?>">
						<?php esc_html_e( 'Todos', 'wp-multi-post-type-blog' ); ?>
					</button>
					<?php foreach ( $active_post_types as $pt ) : ?>
						<?php $pt_obj = get_post_type_object( $pt ); ?>
						<?php if ( $pt_obj ) : ?>
							<button class="filter-tab" data-post-type="<?php echo esc_attr( $pt ); ?>" aria-label="<?php echo esc_attr( $pt_obj->labels->name ); ?>">
								<?php echo esc_html( $pt_obj->labels->name ); ?>
							</button>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="premium-blog-widget__container">
				<?php
				$count     = 0;
				$list_open = false;
				while ( $query->have_posts() ) {
					$query->the_post();
					$count++;

					if ( 1 === $paged && 1 === $count && 'yes' === $settings['show_featured'] ) {
						echo self::render_featured_post( get_post(), $settings );
						continue;
					}

					if ( ! $list_open ) {
						echo '<div class="premium-blog-widget__list list-posts">';
						$list_open = true;
					}

					echo self::render_list_post( get_post(), $settings );
				}

				if ( $list_open ) {
					echo '</div>';
				}
				?>
			</div>

			<div class="premium-blog-widget__pagination numbers-pagination" style="<?php echo ( 'numbers' === $pagination && $max_pages > 1 ) ? '' : 'display: none;'; ?>">
				<?php
				if ( 'numbers' === $pagination && $max_pages > 1 ) {
					echo paginate_links( array(
						'base'      => esc_url_raw( add_query_arg( $page_var, '%#%' ) ),
						'format'    => '',
						'current'   => max( 1, $paged ),
						'total'     => $max_pages,
						'prev_text' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg> Anterior',
						'next_text' => 'Siguiente <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
					) );
				}
				?>
			</div>

			<?php $btn_text = $settings['load_more_text']; ?>
			<div class="premium-blog-widget__pagination-ajax ajax-pagination" style="<?php echo ( 'load_more' === $pagination && $max_pages > 1 && $paged < $max_pages ) ? '' : 'display: none;'; ?>">
				<button class="wp-multipost-blog-load-more-btn" aria-label="<?php echo esc_attr( $btn_text ); ?>">
					<span class="btn-text"><?php echo esc_html( $btn_text ); ?></span>
					<span class="btn-spinner"></span>
				</button>
			</div>

			<div class="premium-blog-widget__infinite-trigger infinite-pagination" style="<?php echo ( 'infinite' === $pagination && $max_pages > 1 && $paged < $max_pages ) ? '' : 'display: none;'; ?>">
				<div class="infinite-loader-spinner"></div>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}
}
