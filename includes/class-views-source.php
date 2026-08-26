<?php
/**
 * Fuente de las cantidades de vistas.
 *
 * @package WpMultiPostTypeBlog
 */

namespace WpMultiPostTypeBlog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * De dónde salen las vistas que muestra el plugin.
 *
 * Hasta la 2.7 salían directamente de JNews View Counter: la tabla
 * `wp_popularpostsdata` y la función `jnews_get_views()`. Eso ataba este plugin
 * al tema JNews, porque aquel contador cuenta desde un hook del tema y deja de
 * sumar en cuanto el tema cambia. Peor todavía: la tabla sobrevive a la
 * desactivación del plugin, así que las cifras no darían error, se congelarían
 * en la fecha de la migración y nadie lo notaría hasta mucho después.
 *
 * Ahora la fuente preferida es Voz Católica Analytics. JNews se conserva como
 * respaldo para que el orden de la transición no importe: se puede desplegar
 * esta versión antes o después de desactivar el contador viejo, y en ambos
 * casos los números siguen apareciendo.
 */
class Views_Source {

	/**
	 * Marcador que viaja dentro de los argumentos de WP_Query para pedir un
	 * orden por vistas. Lo lee el filtro `posts_clauses`.
	 */
	const ORDER_ARG = 'wpmptb_views_order';

	/**
	 * Modos de ordenamiento que aporta el contador.
	 *
	 * @return array
	 */
	public static function order_modes() {
		return array( 'vca_views', 'vca_views_period', 'vca_trending' );
	}

	/**
	 * ¿Está disponible el contador nuevo?
	 *
	 * Se comprueban las funciones concretas que se van a usar, no la constante
	 * de versión: si el plugin está a medio cargar o alguien lo reemplaza por
	 * una versión vieja, esto lo detecta y cae en el respaldo en lugar de
	 * romperse con un error fatal.
	 *
	 * @return bool
	 */
	public static function analytics_available() {
		return class_exists( '\\VCA\\Query' )
			&& method_exists( '\\VCA\\Query', 'totals_for' )
			&& method_exists( '\\VCA\\Query', 'views' );
	}

	/**
	 * Totales históricos de varios posts, en una sola consulta.
	 *
	 * @param int[] $post_ids IDs.
	 * @return array<int,int> ID => vistas.
	 */
	public static function totals_for( $post_ids ) {
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );

		if ( empty( $post_ids ) ) {
			return array();
		}

		if ( self::analytics_available() ) {
			return \VCA\Query::totals_for( $post_ids );
		}

		return self::legacy_totals_for( $post_ids );
	}

	/**
	 * Vistas históricas de un post suelto.
	 *
	 * @param int $post_id ID.
	 * @return int
	 */
	public static function views_for_post( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return 0;
		}

		if ( self::analytics_available() ) {
			return (int) \VCA\Query::views( $post_id, 'all' );
		}

		return self::legacy_views_for_post( $post_id );
	}

	/* ---------------------------------------------------------------------
	 * Respaldo: JNews View Counter
	 * ------------------------------------------------------------------ */

	/**
	 * Nombre de la tabla de totales de JNews, o '' si no existe.
	 *
	 * JNews View Counter es un fork de WordPress Popular Posts, de ahí el
	 * nombre de la tabla.
	 *
	 * @return string
	 */
	private static function legacy_table() {
		static $table = null;

		if ( null !== $table ) {
			return $table;
		}

		global $wpdb;

		$candidate = $wpdb->prefix . 'popularpostsdata';
		$found     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $candidate ) ) );
		$table     = ( $found === $candidate ) ? $candidate : '';

		return $table;
	}

	/**
	 * Totales desde la tabla de JNews.
	 *
	 * @param int[] $post_ids IDs.
	 * @return array<int,int>
	 */
	private static function legacy_totals_for( $post_ids ) {
		$table = self::legacy_table();

		if ( '' === $table ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- el nombre de tabla sale de $wpdb->prefix y los marcadores se generan a partir del recuento de IDs.
				"SELECT postid, pageviews FROM {$table} WHERE postid IN ( {$placeholders} )",
				$post_ids
			)
		);

		$totals = array_fill_keys( $post_ids, 0 );

		foreach ( (array) $rows as $row ) {
			$totals[ absint( $row->postid ) ] = absint( $row->pageviews );
		}

		return $totals;
	}

	/**
	 * Vistas de un post por la vía de JNews.
	 *
	 * @param int $post_id ID.
	 * @return int
	 */
	private static function legacy_views_for_post( $post_id ) {
		if ( function_exists( 'jnews_get_views' ) ) {
			// Los dos argumentos importan: sin ellos la función devuelve el rango
			// por defecto del plugin y ya formateado como texto, que intval()
			// truncaría en el separador de miles.
			return (int) jnews_get_views( $post_id, 'all', false );
		}

		$totals = self::legacy_totals_for( array( $post_id ) );

		if ( isset( $totals[ $post_id ] ) && $totals[ $post_id ] > 0 ) {
			return (int) $totals[ $post_id ];
		}

		// Metas de contadores anteriores. 'better-views-count' se omite a
		// propósito: está desactualizada y devuelve vacío en los posts recientes.
		foreach ( array( 'jeg_views', 'jnews_views', 'post_views_count' ) as $meta_key ) {
			$views = get_post_meta( $post_id, $meta_key, true );

			if ( $views ) {
				return (int) $views;
			}
		}

		return 0;
	}

	/* ---------------------------------------------------------------------
	 * Ordenamiento por vistas
	 * ------------------------------------------------------------------ */

	public static function register() {
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 10, 2 );
	}

	/**
	 * Traduce un modo de orden por vistas a argumentos de WP_Query.
	 *
	 * @param array $query_args Argumentos ya armados.
	 * @param array $settings   Ajustes saneados del widget.
	 * @return array
	 */
	public static function apply_order( $query_args, $settings ) {
		$mode = isset( $settings['orderby'] ) ? $settings['orderby'] : '';

		if ( ! in_array( $mode, self::order_modes(), true ) ) {
			return $query_args;
		}

		// Sin el contador nuevo no hay con qué ordenar. Se cae a la fecha en
		// lugar de devolver un listado vacío o, peor, uno en orden arbitrario
		// que parezca intencional.
		if ( ! self::analytics_available() ) {
			$query_args['orderby'] = 'date';

			return $query_args;
		}

		$days = isset( $settings['views_range'] ) ? absint( $settings['views_range'] ) : 30;
		$days = max( 1, min( 3653, $days ) );

		if ( 'vca_trending' === $mode ) {
			return self::apply_trending( $query_args, $settings, $days );
		}

		$query_args[ self::ORDER_ARG ] = array(
			'mode' => $mode,
			'days' => $days,
		);

		// `orderby` se neutraliza porque el orden real lo impone el filtro
		// `posts_clauses`; dejarlo en su valor original haría que WordPress
		// agregara su propia cláusula ORDER BY delante de la nuestra.
		$query_args['orderby'] = 'none';

		return $query_args;
	}

	/**
	 * Tendencias: se resuelve con la consulta propia del contador y se fija el
	 * orden con `post__in`.
	 *
	 * No se hace con un JOIN como los otros modos porque «en tendencia» compara
	 * dos períodos y ya está implementado y probado del lado del contador. La
	 * contrapartida es que el resultado es un top acotado: sirve para un bloque
	 * de destacados, no para paginar un archivo completo.
	 *
	 * @param array $query_args Argumentos.
	 * @param array $settings   Ajustes.
	 * @param int   $days       Días del período.
	 * @return array
	 */
	private static function apply_trending( $query_args, $settings, $days ) {
		if ( ! method_exists( '\\VCA\\Query', 'trending' ) ) {
			$query_args['orderby'] = 'date';

			return $query_args;
		}

		$per_page = isset( $settings['posts_per_page'] ) ? absint( $settings['posts_per_page'] ) : 5;
		$offset   = isset( $settings['offset'] ) ? absint( $settings['offset'] ) : 0;

		$items = \VCA\Query::trending(
			array(
				'range'     => $days,
				// Se pide de más para que la exclusión de IDs y el desplazamiento
				// no dejen el bloque corto.
				'limit'     => min( 100, ( $per_page * 3 ) + $offset + 10 ),
				'min_views' => isset( $settings['views_min'] ) ? absint( $settings['views_min'] ) : 20,
				'post_type' => isset( $settings['post_types'] ) ? $settings['post_types'] : null,
			)
		);

		$ids = array_map( 'absint', wp_list_pluck( $items, 'object_id' ) );

		if ( ! empty( $settings['exclude_ids'] ) ) {
			$ids = array_values( array_diff( $ids, array_map( 'absint', $settings['exclude_ids'] ) ) );
		}

		if ( empty( $ids ) ) {
			// `post__in` vacío haría que WP_Query ignore el filtro y devuelva
			// todo el sitio, que es lo contrario de lo pedido.
			$query_args['post__in'] = array( 0 );

			return $query_args;
		}

		$query_args['post__in'] = $ids;
		$query_args['orderby']  = 'post__in';
		unset( $query_args['order'] );

		return $query_args;
	}

	/**
	 * Aplica el JOIN y el ORDER BY del orden por vistas.
	 *
	 * @param array     $clauses Cláusulas SQL.
	 * @param \WP_Query $query   Consulta.
	 * @return array
	 */
	public static function posts_clauses( $clauses, $query ) {
		$config = $query->get( self::ORDER_ARG );

		if ( empty( $config ) || ! is_array( $config ) ) {
			return $clauses;
		}

		global $wpdb;

		$order = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';

		if ( 'vca_views' === $config['mode'] ) {
			// Histórico: sale de la tabla de totales, que tiene una fila por
			// contenido y un índice por vistas.
			$table = $wpdb->prefix . 'vca_views_totals';

			$clauses['join']   .= " LEFT JOIN {$table} wpmptb_v ON wpmptb_v.object_id = {$wpdb->posts}.ID";
			$clauses['orderby'] = "COALESCE(wpmptb_v.views, 0) {$order}, {$wpdb->posts}.post_date DESC";

			return $clauses;
		}

		// Período: hay que sumar la serie diaria. El rango se acota en la
		// subconsulta, no después, para que el índice por fecha haga su trabajo
		// en lugar de agregar la tabla entera y descartar al final.
		$daily = $wpdb->prefix . 'vca_views_daily';
		$days  = max( 1, absint( $config['days'] ) );
		$to    = current_time( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', strtotime( $to ) - ( $days - 1 ) * DAY_IN_SECONDS );

		$subquery = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- el nombre de tabla sale de $wpdb->prefix.
			"( SELECT object_id, SUM(views) AS views FROM {$daily} WHERE day BETWEEN %s AND %s GROUP BY object_id )",
			$from,
			$to
		);

		$clauses['join']   .= " LEFT JOIN {$subquery} wpmptb_v ON wpmptb_v.object_id = {$wpdb->posts}.ID";
		$clauses['orderby'] = "COALESCE(wpmptb_v.views, 0) {$order}, {$wpdb->posts}.post_date DESC";

		return $clauses;
	}
}
