# Cómo leer las vistas de los posts en vozcatolica.com

> Actualizado el 26 de agosto de 2026, cuando el sitio dejó de depender de
> JNews View Counter. La versión anterior de este documento describía JNews
> como el contador del sitio; eso ya no es cierto.

## Contexto

El contador de visitas es **Voz Católica Analytics** (`voz-catolica-analytics`),
que reemplazó a JNews View Counter. El cambio no fue cosmético: JNews contaba
desde `jnews_do_first_load_action`, un hook del **tema** JNews. Al migrar a
Hello Elementor ese hook desaparece y el contador deja de sumar sin emitir
ningún error, congelando los rankings en la fecha del cambio de tema.

Las operaciones de servidor se hacen con la habilidad MCP `novamira/execute-php`.
**WP-CLI no funciona en este hosting** (`proc_open` y `exec` están deshabilitados
en PHP): no lo intentes, usá siempre `execute-php`.

## Dónde viven las vistas ahora

### `wp_vca_views_daily` — serie temporal

Una fila por (contenido, día). Es la **fuente de verdad**.

| Columna | Tipo | Significado |
|---|---|---|
| `object_id` | bigint | ID del post |
| `day` | date | Fecha |
| `views` | int | Vistas de ese contenido ese día |

Unas 224.000 filas, ~21 MB, con datos desde el 2022-12-29.

### `wp_vca_views_totals` — total acumulado

Una fila por contenido, con índice por `views`. Es una tabla **derivada**:
existe para que ordenar un ranking histórico no obligue a agregar la serie
completa. Si por un fallo divergiera de la serie, se repara desde la serie con
`VCA\Schema::reconcile()`, nunca al revés.

### `wp_vca_view_dedupe` y `wp_vca_hit_stats`

Huellas efímeras de deduplicación y métricas de operación. No contienen vistas.

## Cómo leerlas

Usá siempre la API del plugin, no SQL directo:

```php
VCA\Query::views( $post_id, 'all' );   // total histórico
VCA\Query::views( $post_id, 30 );      // últimos 30 días
VCA\Query::totals_for( $post_ids );    // varios de una vez, UNA consulta
VCA\Query::most_read( [ 'range' => 30, 'limit' => 10 ] );
VCA\Query::trending( [ 'range' => 7, 'min_views' => 20 ] );
```

`totals_for()` es la que hay que usar al renderizar listados: leer post por post
agrega una consulta por fila.

## Las tablas viejas de JNews

`wp_popularpostsdata` y `wp_popularpostssummary` **siguen existiendo** y
contienen el histórico original. Se conservan a propósito como red de seguridad
de la migración.

- **No las borres todavía.** Son el único camino de vuelta si apareciera un
  problema con los datos migrados.
- **No las uses como fuente.** Están congeladas: JNews ya no cuenta bajo el tema
  nuevo. Leer de ahí devuelve números viejos que parecen actuales.
- Cuando haya pasado tiempo suficiente y exista un backup verificado, se pueden
  borrar para recuperar unos 211 MB.

## Verificación

```php
VCA\Importer::verify();      // compara origen y destino de la migración
VCA\Schema::reconcile( true ); // divergencias entre serie y totales, sin corregir
```

La pantalla **Analíticas → Estado** muestra lo mismo en la interfaz, más el
reparto de peticiones por motivo (contadas, repetidas, bots) y los errores
recientes.
