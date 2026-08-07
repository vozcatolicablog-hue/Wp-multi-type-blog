# Prompt: cómo leer las vistas de los posts en vozcatolica.com

> Copiá todo lo que está debajo de la línea y pegáselo a la IA que vaya a trabajar con los contadores de visitas.

---

## Contexto

Trabajás sobre el WordPress de vozcatolica.com. El contador de visitas es el plugin **JNews View Counter**, que internamente es un fork de *WordPress Popular Posts* y por eso usa tablas con nombre `popularposts`. Necesitás leer cantidades de vistas por post.

Las operaciones de servidor se hacen con la habilidad MCP `novamira/execute-php`. **WP-CLI no funciona en este hosting** (`proc_open`/`exec` están deshabilitados en PHP): no lo intentes, usá siempre `execute-php`.

## Regla crítica antes de tocar nada

**Nunca borres, renombres ni vacíes las tablas `wp_popularpostsdata` ni `wp_popularpostssummary`.** Parecen tablas huérfanas de un plugin desinstalado porque "WordPress Popular Posts" no figura en la lista de plugins activos, pero son el almacenamiento real y vivo de todas las visitas del sitio. Renombrarlas deja todos los contadores en cero de inmediato. Si alguna vez hay que reducir su tamaño, la única vía segura es podar filas antiguas de `wp_popularpostssummary` por `view_date`, nunca la tabla entera.

## Dónde viven las vistas

### `wp_popularpostsdata` — total acumulado, una fila por post

| Columna | Tipo | Significado |
|---|---|---|
| `postid` | bigint | ID del post |
| `day` | datetime | Primera visita registrada |
| `last_viewed` | datetime | Última visita |
| `pageviews` | bigint | **Total histórico de vistas** |

Aproximadamente 3.200 filas (solo posts que recibieron al menos una visita).

### `wp_popularpostssummary` — detalle por día, para rangos temporales

| Columna | Tipo | Significado |
|---|---|---|
| `ID` | bigint | Autoincremental |
| `postid` | bigint | ID del post |
| `pageviews` | bigint | Vistas de ese post en esa fecha |
| `view_date` | date | Fecha |
| `view_datetime` | datetime | Última visita de ese día |

Alrededor de 1,71 millones de filas, con datos desde el 2022-12-29. Es la tabla que se consulta para "lo más leído de los últimos 7 / 30 días". Pesa unos 207 MB.

## Forma recomendada de leer: la función del plugin

```php
jnews_get_views( $post_id, $range, $number_format );
```

- `$post_id` — ID del post.
- `$range` — uno de: `all`, `last24hours`, `daily`, `last7days`, `weekly`, `last30days`, `monthly`, `custom`. Si pasás `null` usa el default del plugin.
- `$number_format` — `true` devuelve el número formateado para mostrar (`1.234`); **pasá `false` si vas a hacer cálculos o comparaciones**, así obtenés el entero crudo.

```php
$total   = jnews_get_views( 77174, 'all', false );        // 224
$semana  = jnews_get_views( 77174, 'last7days', false );
$mes     = jnews_get_views( 77174, 'last30days', false );
```

Usala cuando necesites las vistas de uno o de pocos posts. Devuelve `0` si el post nunca fue visitado (no existe fila en la tabla).

## SQL directo: para rankings y procesamiento masivo

Para ordenar miles de posts, `jnews_get_views()` en un bucle es lentísimo. Consultá las tablas directamente.

**Total histórico de un post:**
```sql
SELECT pageviews FROM wp_popularpostsdata WHERE postid = 77174;
```

**Los 20 posts más leídos de la historia:**
```sql
SELECT p.ID, p.post_title, v.pageviews
FROM wp_popularpostsdata v
JOIN wp_posts p ON p.ID = v.postid
WHERE p.post_status = 'publish' AND p.post_type = 'post'
ORDER BY v.pageviews DESC
LIMIT 20;
```

**Los 20 más leídos de los últimos 30 días:**
```sql
SELECT p.ID, p.post_title, SUM(s.pageviews) AS vistas
FROM wp_popularpostssummary s
JOIN wp_posts p ON p.ID = s.postid
WHERE s.view_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  AND p.post_status = 'publish'
GROUP BY p.ID, p.post_title
ORDER BY vistas DESC
LIMIT 20;
```

**Posts publicados sin ninguna visita registrada** (no tienen fila en `data`):
```sql
SELECT p.ID, p.post_title
FROM wp_posts p
LEFT JOIN wp_popularpostsdata v ON v.postid = p.ID
WHERE p.post_type = 'post' AND p.post_status = 'publish' AND v.postid IS NULL;
```

Si devolvés resultados a través de MCP, **agregá o recortá dentro del propio PHP** (contá, sumá, limitá a una muestra): los listados completos superan el límite de tokens del cliente y la llamada falla.

## Trampa: no uses el postmeta `better-views-count`

Existe un postmeta llamado `better-views-count` que parece contener las vistas. **Está obsoleto y da resultados falsos.** Verificado: ningún plugin activo ni el tema lo escriben; solo 1.733 posts lo tienen y el más reciente es del 2026-04-22. Todo post posterior a esa fecha devuelve vacío, lo que hace parecer que las notas nuevas tienen cero visitas.

Si encontrás código o documentación que ordene posts por `better-views-count`, corregilo para que use `wp_popularpostsdata.pageviews`.

## Catálogo Ediciones Voz Católica: sistema aparte

Los libros (`vcec_book`, plugin `voz-catolica-editorial-catalog`) **no usan JNews View Counter**. Tienen su propio contador, independiente del anterior. No mezcles los dos sistemas ni busques libros en `wp_popularpostsdata`.

### Dónde viven las vistas de los libros

Tabla propia `wp_vcec_book_views`, una fila por libro y por día:

| Columna | Tipo | Significado |
|---|---|---|
| `book_id` | bigint | ID del libro |
| `day` | date | Día |
| `views` | int | Vistas de ese libro ese día |
| `updated_at` | datetime | Última escritura |

El total acumulado por libro se consolida en el postmeta **`_vcec_view_count`**, y la última visita en `_vcec_last_viewed`. Lo hace el cron `vcec_consolidate_views`, que corre **cada 10 minutos** y solo recalcula los libros con actividad desde `vcec_views_last_sync`. Consecuencia práctica: `_vcec_view_count` puede estar hasta 10 minutos desactualizado; si necesitás el dato al instante, sumá desde la tabla.

Se conservan **400 días** de historia diaria (poda automática diaria). El seguimiento arrancó el **2026-06-14**: no hay datos anteriores a esa fecha.

### Cómo consumirlas

**Total de un libro (lo más simple):**
```php
$vistas = (int) get_post_meta( $book_id, '_vcec_view_count', true );
```

**Totales del catálogo completo (métodos estáticos):**
```php
\VCEC\Views::grand_total();        // int: vistas de todo el catálogo
\VCEC\Views::daily_totals( 30 );   // array 'Y-m-d' => vistas, con los días sin datos en 0
```

**Los más vistos, por shortcode:**
```
[vcec_books orderby="views" limit="6" columns="3" order="DESC"]
```
El bloque Gutenberg "most viewed" no hace otra cosa que envolver ese shortcode.

**Por REST:**
```
GET /wp-json/vcec/v1/books?orderby=views
```
Cada libro del JSON incluye el campo `views`. También `GET /wp-json/vcec/v1/books/{id}`.

**Ranking por SQL, con rango de fechas** (lo único que da vistas por período):
```sql
SELECT p.ID, p.post_title, SUM(v.views) AS vistas
FROM wp_vcec_book_views v
JOIN wp_posts p ON p.ID = v.book_id
WHERE v.day >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY p.ID, p.post_title
ORDER BY vistas DESC;
```

**En el admin:** el listado de libros tiene una columna "Vistas" ordenable.

### Cómo se registran (para interpretar los números)

Por AJAX (`vcec_track_view`), desde `assets/js/views.js`, y **solo en la ficha individual de un libro** (`is_singular('vcec_book')`). Ver un libro en la grilla del catálogo no suma vista. Además:

- Se descartan bots por user-agent (`bot|crawl|slurp|spider|mediapartners`).
- Deduplicación de 30 minutos por visitante: localStorage en el cliente y un transient `vcec_v_{book_id}` en el servidor como red de seguridad.
- Con el ajuste `exclude_admin_tracking` en `1` (el default), **a los administradores no se les carga ni el script**: revisar fichas desde el panel no infla el ranking público.
- Todo el seguimiento se puede apagar con el ajuste `enable_view_tracking`.

### Descargas en el catálogo: no existen

**El catálogo de Ediciones no cuenta descargas.** No hay contador, ni tabla, ni postmeta, ni acción AJAX: la única acción de tracking registrada en todo el plugin es `vcec_track_view`.

Lo que sí tienen los libros son dos campos que son solo enlaces, sin medición alguna:

- `_vcec_sample_pdf` — PDF de muestra (lo tienen los 55 libros). Se renderiza como un `<iframe>` más un `<a target="_blank">`.
- `_vcec_ebook_url` — enlace al ebook, mostrado en la caja de compra.

Si hace falta medir esas descargas hay que implementarlo desde cero (una acción AJAX análoga a `vcec_track_view`, o eventos de clic hacia GA4). **No confundir con el plugin Simple Download Monitor**, que sí registra descargas en `wp_sdm_downloads` pero exclusivamente de su propio tipo de contenido `sdm_downloads` (210 ítems: estampas, PDF sueltos). Ese log no tiene ninguna relación con los libros del catálogo.

## Cómo se registran las vistas de los posts (para interpretar los números)

El conteo se dispara por AJAX desde el navegador, no en el render de PHP. Eso significa que:

- Funciona igual con la caché de página activa (WP Rocket) y detrás de Cloudflare.
- No cuenta bots ni peticiones server-side; un `wp_remote_get()` tuyo no infla el contador.
- Hay deduplicación por cookie e IP, y exclusión configurable por rol de usuario: las visitas de administradores logueados pueden no contarse.
- Como consecuencia, estos números son de visitas reales de lectores, pero **no son equivalentes a los de Google Analytics ni a los de Search Console** — no intentes cuadrarlos entre sí.
