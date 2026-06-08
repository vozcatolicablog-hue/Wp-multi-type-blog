# Análisis Exhaustivo: WP Multi-Post Type Blog Block for Elementor
**Versión analizada:** 1.5.0  
**Fecha de análisis:** 2026-06-08  
**Archivos revisados:** 6 archivos PHP, 1 JS, 1 CSS, 1 README

---

## Índice

1. [Errores Críticos](#1-errores-críticos)
2. [Errores Menores / Bugs Potenciales](#2-errores-menores--bugs-potenciales)
3. [Problemas de Seguridad](#3-problemas-de-seguridad)
4. [Código Huérfano y Elementos Sin Uso](#4-código-huérfano-y-elementos-sin-uso)
5. [Optimizaciones de Rendimiento](#5-optimizaciones-de-rendimiento)
6. [Mejoras de Arquitectura](#6-mejoras-de-arquitectura)
7. [Mejoras de UX / Frontend](#7-mejoras-de-ux--frontend)
8. [Inconsistencias y Deuda Técnica](#8-inconsistencias-y-deuda-técnica)
9. [Tabla Resumen de Hallazgos](#9-tabla-resumen-de-hallazgos)

---

## ~~1. Errores Críticos~~

### ~~1.1 — N-consultas extra por cada post type en el widget Archive (O(n) queries)~~

**Archivo:** `widgets/class-blog-archive-widget.php` — Líneas 69–83

```php
// PROBLEMA: Se ejecuta una WP_Query completa por cada post type configurado
foreach ( $post_types as $pt ) {
    $pt_query_args = wp_multipost_blog_build_query_args( $settings, 1 );
    $pt_query_args['posts_per_page'] = 1;
    $pt_query_args['offset'] = 0;
    // ...
    $pt_query = new \WP_Query( $pt_query_args );
    if ( $pt_query->have_posts() ) {
        $active_post_types[] = $pt;
    }
}
```

**Impacto:** Si el usuario configura 5 post types, se disparan **5 consultas adicionales a la base de datos** solo para saber cuáles tienen posts. Esto ocurre en cada carga de página que tenga el widget Archive.

**Corrección sugerida:** Usar `wp_count_posts()` o una sola consulta con `post_type => $post_types` y `fields => 'post_type'` agrupando por tipo:

```php
// Alternativa más eficiente: un solo query SQL con GROUP BY
global $wpdb;
$placeholders = implode(',', array_fill(0, count($post_types), '%s'));
$active_post_types = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT DISTINCT post_type FROM {$wpdb->posts}
         WHERE post_type IN ($placeholders) AND post_status = 'publish'",
        ...$post_types
    )
);
```

---

### ~~1.2 — `wp_reset_postdata()` llamado cuando no hay posts (innecesario y mal posicionado)~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Línea 1064  
**Archivo:** `widgets/class-blog-archive-widget.php` — Línea 58

```php
if ( ! $query->have_posts() ) {
    echo '<div ...>' . esc_html__(...) . '</div>';
    wp_reset_postdata(); // ← ERROR: No se hizo the_post(), esto es innecesario aquí
    return;
}
```

**Impacto:** `wp_reset_postdata()` restaura `$post` global a su valor anterior. Pero si nunca se llamó `the_post()` dentro de ese `WP_Query`, llamarlo es un no-op engañoso. En WordPress, la convención correcta es llamarlo **solo después de** haber usado `the_post()` dentro de un loop. No genera un fallo real, pero puede enmascarar bugs de contexto si el código evoluciona.

**Corrección:** Eliminar `wp_reset_postdata()` del bloque `! have_posts()`:

```php
if ( ! $query->have_posts() ) {
    echo '<div ...>' . esc_html__(...) . '</div>';
    return; // Sin wp_reset_postdata() porque nunca se llamó the_post()
}
```

---

### ~~1.3 — Fallo silencioso del handler AJAX cuando Elementor no está disponible (orden de verificaciones)~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Líneas 356–363

```php
if ( $query->have_posts() ) {
    if ( ! class_exists( 'Elementor\\Widget_Base' ) ) {
        wp_send_json_error( 'Elementor no está disponible.', 500 );
    }
    // Include widget file to access static render helpers.
    require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-blog-posts-widget.php';
```

**Problema:** La verificación de `class_exists('Elementor\\Widget_Base')` y el `require_once` de la clase del widget se realizan **dentro del loop** de comprobación de posts. Si Elementor no está disponible, el handler responde un error 500 *después* de ya haber ejecutado la query de base de datos completa. La verificación debería ir **antes** de ejecutar cualquier query.

**Corrección sugerida:** Mover las verificaciones de dependencias al inicio del handler, antes de la query.

---

## ~~2. Errores Menores / Bugs Potenciales~~

### ~~2.1 — `currentPage = 0` en reset del filtro rompe la lógica de paginación~~

**Archivo:** `assets/js/blog-posts-widget.js` — Líneas 88–89

```javascript
// Reset pagination state
currentPage = 0;
maxPages = 1;

$list.fadeOut(200, function() {
    $list.empty().show();
    loadMorePosts(true); // reset load
});
```

Dentro de `loadMorePosts(isReset)`:
```javascript
var nextPage = isReset ? 1 : (currentPage + 1);
```

Y en el callback de `success`:
```javascript
if (isReset) {
    currentPage = 0; // ← se resetea a 0 OTRA VEZ
}
if (response.data.html) {
    // ...
    currentPage = nextPage; // nextPage fue 1, así que currentPage = 1 ✓
```

**Análisis:** El flujo es inconsistente. Cuando `isReset=true`, `currentPage` se pone a `0` antes de llamar a `loadMorePosts`, luego dentro del success se vuelve a poner a `0`, y finalmente se sobreescribe con `nextPage=1`. El doble reset es redundante y confuso. Si el AJAX falla, `currentPage` queda en `0`, lo que causará que el próximo click intente cargar `page=1` en lugar de volver al estado correcto.

### ~~2.2 — `$btn` y `$trigger` fuera de scope en `loadMorePosts`~~

**Archivo:** `assets/js/blog-posts-widget.js` — Líneas 106–110

```javascript
function loadMorePosts(isReset) {
    // ...
    if (pagination === 'load_more' && $btn) {       // $btn solo existe si pagination === 'load_more'
        $btn.addClass('is-loading');
    } else if (pagination === 'infinite' && $trigger) { // $trigger solo existe si pagination === 'infinite'
```

**Problema:** Las variables `$btn` y `$trigger` son declaradas en bloques `if` separados:
```javascript
if (pagination === 'load_more') {
    var $btn = $widget.find('...');
}
if (pagination === 'infinite') {
    var $trigger = $widget.find('...');
}
```

En JavaScript con `var`, el hoisting garantiza que existan pero tendrán valor `undefined` en el scope de `loadMorePosts` si el modo de paginación no coincide. La condición `&& $btn` no es suficiente para detectar `undefined` correctamente en jQuery, porque `$btn` en ese caso sería `undefined`, no un objeto jQuery vacío. Aunque el `if` con `&&` evita el crash gracias a la evaluación lazy, el patrón es frágil.

### ~~2.3 — `found_posts` no se incluye en la respuesta AJAX cuando no hay posts~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Líneas 377–381

```php
} else {
    wp_send_json_success( array(
        'html'      => '',
        'max_pages' => 0,
        // ← FALTA 'found_posts' => 0
    ) );
}
```

El bloque de éxito con posts **sí** incluye `found_posts` (línea 374), pero el bloque sin posts no. Esto puede causar errores de JavaScript si el consumidor intenta acceder a `response.data.found_posts` en ambos casos.

### ~~2.4 — `is_enabled()` con lógica invertida potencialmente confusa~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Líneas 809–811

```php
private static function is_enabled( $settings, $key ) {
    return empty( $settings[ $key ] ) || 'yes' === $settings[ $key ];
}
```

**Problema de lógica:** `empty($settings[$key])` devuelve `true` cuando la clave no existe O cuando el valor es falsy (cadena vacía, null, 0, false). Esto significa que si un setting no está definido en el array `$settings`, `is_enabled()` devuelve `true` (habilitado por defecto). Aunque esto es intencional para el fallback de "mostrar si no configurado", puede causar elementos visibles no deseados en el AJAX context donde `$settings` viene sanitizado y podría tener claves ausentes. Se debería documentar explícitamente este comportamiento o usar una función más clara:

```php
// Más explícito y menos propenso a errores:
private static function is_enabled( $settings, $key ) {
    return ! isset( $settings[ $key ] ) || 'yes' === $settings[ $key ];
}
```

### ~~2.5 — Paginación numérica renderiza HTML pero también se oculta con `style="display:none"`~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Líneas 1111–1124  
**Archivo:** `widgets/class-blog-archive-widget.php` — Líneas 137–150

```php
<div class="...numbers-pagination" style="<?php echo ( 'numbers' === $pagination && $max_pages > 1 ) ? '' : 'display: none;'; ?>">
    <?php
    if ( 'numbers' === $pagination && $max_pages > 1 ) {
        echo paginate_links( ... );
    }
    ?>
</div>
```

Se evalúa la condición **dos veces**. El `div` se oculta con CSS inline cuando no aplica, y además el `paginate_links()` no se ejecuta dentro. El `div` vacío con `display:none` es HTML innecesario en el DOM. Sería más limpio no renderizar el div completo si no aplica.

---

## ~~3. Problemas de Seguridad~~

### ~~3.1 — Settings expuestos en `data-settings` del HTML (Information Disclosure leve)~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Línea 1075  
**Archivo:** `widgets/class-blog-archive-widget.php` — Línea 88

```php
data-settings="<?php echo esc_attr( wp_json_encode( $settings ) ); ?>"
```

El array completo de `$settings` sanitizados se expone en el HTML del frontend como atributo `data-*`. Esto incluye valores como `archive_author_id`, `current_post_id`, `exclude_ids`, y la lista completa de post types y términos. Aunque la firma HMAC evita que sean manipulados, **la exposición pública de la configuración interna del widget** puede revelar información sobre la estructura del sitio (IDs internos, taxonomías, autores).

**Recomendación:** Exponer solo los datos mínimos necesarios para el JS (pagination mode, widget_id, max_pages, current_page) y manejar el resto exclusivamente en el servidor.

### ~~3.2 — `wp_hash()` como firma HMAC: uso correcto pero no resistente a timing attacks si no usa `hash_equals`~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Línea 342

```php
if ( empty( $signature ) || ! hash_equals( wp_multipost_blog_sign_settings( $settings ), $signature ) ) {
```

✅ **Esto está bien**: ya usa `hash_equals()` que es comparación en tiempo constante. No hay timing attack.  
⚠️ **Observación:** `wp_hash()` usa `NONCE_KEY` + `NONCE_SALT` de WordPress, lo que es apropiado para un contexto de sesión/request. Sin embargo, si las salts cambian (ej. en un reset de seguridad), todas las firmas activas en páginas cacheadas se invalidan silenciosamente, causando errores 403 en usuarios que tienen páginas cacheadas. Esto debería documentarse.

### ~~3.3 — XSS potencial en texto de respuesta AJAX en JavaScript~~

**Archivo:** `assets/js/blog-posts-widget.js` — Línea 160

```javascript
var noPostsText = settings.no_posts_text || 'No se encontraron publicaciones.';
$list.html('<div class="premium-blog-no-posts">' + noPostsText + '</div>');
```

`settings.no_posts_text` viene de `settings` que fue parseado del atributo `data-settings` del HTML. Si bien `wp_multipost_blog_sanitize_settings()` no incluye `no_posts_text` en los campos que procesa, este campo **no existe** en `$settings` sanitizados. Por lo tanto `settings.no_posts_text` siempre será `undefined` y el fallback hardcodeado siempre se usará. No es un XSS real porque el fallback es seguro, pero el patrón `$list.html(variable)` aplicado a datos del servidor sin escape es una práctica peligrosa que debería usar `.text()` o al menos `$('<div>').addClass('...').text(noPostsText)`.

---

## ~~4. Código Huérfano y Elementos Sin Uso~~

### ~~4.1 — `found_posts` en la respuesta AJAX nunca es consumido por el JavaScript~~

**Archivo PHP:** `wp-multi-post-type-blog-block.php` — Línea 374
```php
'found_posts' => $query->found_posts,
```

**Archivo JS:** `assets/js/blog-posts-widget.js` — No hay ninguna referencia a `response.data.found_posts`.

El campo `found_posts` se envía en la respuesta JSON de éxito pero **ninguna parte del JavaScript lo lee o utiliza**. Es código muerto en el lado del servidor.

### ~~4.2 — Variable `$trigger` referenciada antes de estar definida en `loadMorePosts`~~

**Archivo:** `assets/js/blog-posts-widget.js`

```javascript
var observer;  // declarada en scope de initWidget

if (pagination === 'infinite') {
    var $trigger = $widget.find('.premium-blog-widget__infinite-trigger');
    // ...
}
// ...
function loadMorePosts(isReset) {
    // ...
    } else if (pagination === 'infinite' && $trigger) { // $trigger puede ser undefined si pagination != 'infinite'
```

`$trigger` solo se declara si `pagination === 'infinite'`. Si por algún motivo el código llegara a `loadMorePosts` con `pagination !== 'infinite'`, `$trigger` sería `undefined`. El `&&` previene el crash pero la variable huérfana en ese contexto es confusa.

### ~~4.3 — Estilos CSS sin uso: `.badge` como selector global~~

**Archivo:** `assets/css/blog-posts-widget.css` — Líneas 726–734 (Elementor control) vs líneas 203–219 (CSS)

El control de Elementor define:
```php
'selectors' => [
    '{{WRAPPER}} .badge' => 'background-color: {{VALUE}};',
],
```

El selector `.badge` es genérico y puede colisionar con clases de otros plugins (Bootstrap, JNews, etc.) que también usan `.badge`. Debería ser `.list-post-item__badge` para ser específico al plugin.

### ~~4.4 — `walkthrough.md` incluido en el plugin pero sin propósito funcional~~

**Archivo:** `walkthrough.md` (6,382 bytes)

Este archivo es documentación de desarrollo interna. No tiene impacto en el funcionamiento del plugin, pero se distribuirá en el ZIP del plugin con cada instalación, incrementando el tamaño del paquete innecesariamente. Debería excluirse del ZIP de distribución (incluirse en `.gitignore` de distribución o en `.distignore`).

### ~~4.5 — `enqueue_editor_assets()` duplica el registro del estilo CSS~~

**Archivo:** `includes/class-elementor-addon.php` — Líneas 89–96

```php
public function enqueue_editor_assets() {
    wp_enqueue_style(
        'wp-multipost-blog-widget-css',
        WP_MULTIPOST_BLOG_URL . 'assets/css/blog-posts-widget.css',
        array(),
        WP_MULTIPOST_BLOG_VERSION
    );
}
```

El método `enqueue_assets()` (líneas 59–65) ya registra este mismo handle con `wp_register_style()`. El método `enqueue_editor_assets()` hace un `wp_enqueue_style()` con los mismos parámetros completos en lugar de simplemente hacer `wp_enqueue_style('wp-multipost-blog-widget-css')` aprovechando el handle ya registrado. Si los parámetros del archivo cambian, hay que actualizar en dos lugares.

---

## ~~5. Optimizaciones de Rendimiento~~

### ~~5.1 — Múltiples `get_post_meta()` por post para las vistas (hasta 4 llamadas a BD)~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Líneas 783–799

```php
public static function get_views_count( $post_id ) {
    $views = get_post_meta( $post_id, 'jeg_views', true );      // 1ª consulta
    if ( ! $views ) {
        $views = get_post_meta( $post_id, 'jnews_views', true ); // 2ª consulta
    }
    if ( ! $views ) {
        $views = get_post_meta( $post_id, 'post_views_count', true ); // 3ª consulta
    }
    if ( ! $views && function_exists( 'jnews_get_views' ) ) {
        $views = jnews_get_views( $post_id );                   // 4ª llamada (posible consulta)
    }
    return $views ? number_format_i18n( intval( $views ) ) : '0';
}
```

Con una lista de 10 posts y `show_views = 'yes'`, esto puede disparar hasta **40 llamadas adicionales a base de datos**. WordPress almacena todos los post metas en caché al ejecutar el loop principal (con `update_post_meta_cache`), por lo que las llamadas a `get_post_meta()` posteriores deberían ser hits de caché si la query principal usa el `WP_Query` estándar. Sin embargo, en el contexto AJAX, la caché de post meta **no está pre-cargada** porque se hace `the_post()` manualmente, por lo que cada `get_post_meta()` es una consulta real.

**Corrección:** Usar `update_postmeta_cache( $post_ids )` con todos los IDs antes del loop en el AJAX handler.

### ~~5.2 — `get_all_taxonomy_terms()` ejecuta N queries en el editor de Elementor~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Líneas 139–163

```php
private function get_all_taxonomy_terms() {
    $taxonomies = get_taxonomies( ... );
    foreach ( $taxonomies as $taxonomy ) {
        $terms = get_terms( array(
            'taxonomy' => $taxonomy->name,
            // ...
        ) );
    }
}
```

Este método se llama en `register_controls()` que Elementor invoca **en cada carga del editor**. Si el sitio tiene 10 taxonomías, se ejecutan 10 consultas a BD para cargar hasta `TERMS_PER_TAXONOMY_LIMIT = 250` términos cada una (potencialmente 2,500 términos en memoria). Esto ralentiza el editor significativamente en sitios grandes.

**Corrección:** Agregar transient caching:

```php
private function get_all_taxonomy_terms() {
    $cache_key = 'wpmb_all_taxonomy_terms_' . md5( wp_json_encode( get_taxonomies( ['public' => true] ) ) );
    $cached = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }
    // ... lógica actual ...
    set_transient( $cache_key, $options, HOUR_IN_SECONDS );
    return $options;
}
```

### ~~5.3 — `get_all_post_types()` y `get_all_authors()` sin caché en el editor~~

Similar al punto 5.2, `get_all_post_types()` y `get_all_authors()` se llaman en `register_controls()`. `get_all_authors()` con `has_published_posts => true` ejecuta una query de usuarios en BD sin caché.

### ~~5.4 — Animación jQuery con `step` y `transform` es costosa~~

**Archivo:** `assets/js/blog-posts-widget.js` — Líneas 142–153

```javascript
$newElements.each(function(index, el) {
    $(el).delay(index * 120).animate({
        opacity: 1
    }, {
        duration: 500,
        step: function(now, fx) {
            if (fx.prop === 'opacity') {
                $(el).css('transform', 'translateY(' + (15 - now * 15) + 'px)');
            }
        }
    });
});
```

El callback `step` de jQuery `.animate()` se ejecuta en **cada frame del timer** (aprox. 13ms / frame = ~77 veces por segundo por elemento). Con un batch de 10 nuevos elementos, esto genera 770 llamadas JS/segundo durante 500ms + delays. Esto fuerza repaints continuos en el browser.

**Corrección recomendada:** Usar CSS transitions con clases:

```javascript
$newElements.css({ opacity: 0, transform: 'translateY(15px)' });
$list.append($newElements);
$newElements.each(function(index, el) {
    setTimeout(function() {
        $(el).css({ transition: 'opacity 0.5s ease, transform 0.5s ease', opacity: 1, transform: 'translateY(0)' });
    }, index * 120);
});
```

### ~~5.5 — `placeholder.png` pesa 633 KB~~

**Archivo:** `assets/images/placeholder.png` — 633,946 bytes

Una imagen placeholder de 634 KB es innecesariamente pesada. Un placeholder típico de 1200×630 px bien optimizado no debería superar 20–40 KB. Además, se carga siempre que un post no tenga imagen destacada, lo que puede ocurrir masivamente en blogs con muchos posts sin imagen.

**Recomendación:** Comprimir la imagen con herramientas como `squoosh`, convertir a WebP, y/o generar el placeholder mediante SVG inline o CSS en lugar de una imagen PNG.

---

## ~~6. Mejoras de Arquitectura~~

### ~~6.1 — Funciones globales en el namespace raíz (riesgo de colisiones)~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Líneas 59–321

Todas las funciones helper están declaradas en el namespace global de PHP:
- `wp_multipost_blog_sanitize_array()`
- `wp_multipost_blog_sanitize_switch()`
- `wp_multipost_blog_validate_post_types()`
- `wp_multipost_blog_build_tax_query()`
- `wp_multipost_blog_allowed_orderby()`
- `wp_multipost_blog_sanitize_settings()`
- `wp_multipost_blog_sign_settings()`
- `wp_multipost_blog_build_query_args()`
- `wp_multipost_blog_get_max_pages()`
- `wp_multipost_blog_load_more_handler()`

Aunque el prefijo `wp_multipost_blog_` reduce el riesgo de colisiones, el patrón correcto en PHP moderno es encapsular estas funciones en una clase estática o en el namespace ya declarado `WpMultiPostTypeBlog\`:

```php
namespace WpMultiPostTypeBlog;

class Query_Builder {
    public static function sanitize_settings( $settings ) { ... }
    public static function build_query_args( $settings, $paged ) { ... }
    // ...
}
```

### ~~6.2 — Lógica de render duplicada entre `Blog_Posts_Widget` y `Blog_Archive_Widget`~~

**Archivos:** `widgets/class-blog-posts-widget.php` y `widgets/class-blog-archive-widget.php`

El método `render()` de `Blog_Archive_Widget` duplica casi íntegramente el de `Blog_Posts_Widget`. Las únicas diferencias son:
1. El filtro por autor en archives (líneas 45–47)
2. El bloque de filtros de post type (líneas 67–108)
3. El class CSS adicional `premium-blog-archive-widget`

Toda la lógica compartida de construcción del HTML (pagination divs, container div, loop de posts) está **copiada y pegada**. Esto viola el principio DRY (Don't Repeat Yourself) y significa que cualquier bug o mejora en el render de `Blog_Posts_Widget::render()` debe replicarse manualmente en `Blog_Archive_Widget::render()`.

**Recomendación:** Refactorizar el render en un método protegido base `render_widget_content()` que ambas clases compartan, pasando opciones por parámetro o método de extensión.

### ~~6.3 — `require_once` del widget dentro del AJAX handler acoplado al filesystem~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Línea 362

```php
require_once WP_MULTIPOST_BLOG_PATH . 'widgets/class-blog-posts-widget.php';
```

Este `require_once` dentro del handler AJAX asume que el archivo del widget **no fue cargado previamente** por Elementor. Esto podría ser correcto en una petición AJAX pura (sin Elementor), pero si Elementor carga el widget antes del AJAX handler (por ejemplo en un contexto REST), podría haber doble inclusión (aunque `require_once` lo previene). El problema real es el **acoplamiento fuerte**: el handler AJAX conoce la ruta física del archivo del widget para obtener acceso a métodos estáticos de renderizado.

**Mejor enfoque:** Registrar el autoloading correctamente o declarar la función de renderizado de HTML en un archivo separado no acoplado a Elementor.

### ~~6.4 — Sin archivo `.distignore` o herramienta de build~~

El plugin no tiene:
- `.distignore` para excluir archivos de desarrollo del ZIP de distribución
- `composer.json` para autoloading
- Scripts de build (Makefile, Grunt, Webpack)

Esto significa que `walkthrough.md`, `README.md`, y otros archivos de desarrollo se incluyen en el plugin que los usuarios instalan.

---

## ~~7. Mejoras de UX / Frontend~~

### ~~7.1 — Sin indicador visual de "no más posts" después de cargar todo con Load More~~

**Archivo:** `assets/js/blog-posts-widget.js` — `updateLoaderVisibility()`

Cuando se llegan a cargar todos los posts (currentPage >= maxPages), el botón "Cargar Más" simplemente desaparece con un fadeOut. No hay ningún mensaje que comunique al usuario que ya no hay más contenido. Una buena UX muestra un mensaje tipo "Has llegado al final" o "No hay más publicaciones".

### ~~7.2 — Infinite scroll sin debounce puede disparar múltiples requests simultáneos~~

**Archivo:** `assets/js/blog-posts-widget.js` — Líneas 58–69

El `IntersectionObserver` tiene `rootMargin: '100px 0px 300px 0px'`, lo que significa que el trigger dispara 300px **antes** de que el elemento sea visible. En conexiones lentas, esto puede causar que el mismo elemento dispare múltiples intersecciones mientras se carga, aunque `isLoading` previene peticiones paralelas. El `threshold: 0.1` combinado con el `rootMargin` extendido puede ser agresivo.

### ~~7.3 — El filtro de post type tabs no resetea la paginación numérica~~

**Archivo:** `assets/js/blog-posts-widget.js` — Filtros (líneas 76–95)

Los tabs de filtro de post type envían un AJAX request y reemplazan el contenido de `$list`, pero la paginación **numérica** (`numbers-pagination`) no es actualizada por JavaScript. Si el usuario filtra y luego ve la paginación numérica, los números siguen reflejando el conteo original sin filtro. (Nota: la paginación AJAX sí se actualiza, solo la numérica queda inconsistente).

### ~~7.4 — `outline: none` en el botón Load More elimina accesibilidad de teclado~~

**Archivo:** `assets/css/blog-posts-widget.css` — Línea 354

```css
.wp-multipost-blog-load-more-btn {
    /* ... */
    outline: none; /* ← Elimina el foco visible para usuarios de teclado */
}
```

Y en los filter tabs (línea 559):
```css
.premium-blog-archive__filters .filter-tab {
    outline: none;
}
```

Eliminar `outline` sin proveer un `focus-visible` alternativo es una violación de las pautas WCAG 2.1 (criterio 2.4.7 - Focus Visible). Los usuarios que navegan con teclado pierden el indicador de foco.

**Corrección:**
```css
.wp-multipost-blog-load-more-btn:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}
```

### ~~7.5 — Imagen de la tarjeta destacada sin `srcset` ni `sizes` para responsive~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Línea 889

```php
<img class="featured-post__image" src="<?php echo esc_url( $thumb_url ); ?>" alt="..." loading="lazy" />
```

Se usa `get_the_post_thumbnail_url()` que devuelve solo una URL. Para aprovechar imágenes responsive, debería usarse `get_the_post_thumbnail()` o construir `srcset` manualmente con `wp_get_attachment_image_srcset()`.

### ~~7.6 — La imagen destacada tiene `loading="lazy"` pero está above-the-fold~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Línea 889

El post destacado es el elemento principal del widget, típicamente visible al cargar la página (above-the-fold). Usar `loading="lazy"` en la imagen principal puede retrasar su carga, afectando el LCP (Largest Contentful Paint) de Google Core Web Vitals. La imagen del featured post debería usar `loading="eager"` o no especificar el atributo (comportamiento por defecto es eager).

---

## ~~8. Inconsistencias y Deuda Técnica~~

### ~~8.1 — Versión del plugin desincronizada entre header y constante~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Líneas 5 y 16

```php
 * Version: 1.5.0              // ← En el header de comentarios
define( 'WP_MULTIPOST_BLOG_VERSION', '1.5.0' ); // ← En la constante
```

Actualmente están sincronizadas, pero no hay mecanismo que lo garantice. En el futuro, un desarrollador podría actualizar uno y olvidarse del otro. El README documenta versiones 1.0.0 y 1.1.0 sin mencionar 1.5.0, lo que indica que el changelog está **desactualizado**.

### ~~8.2 — Textos hardcodeados en español mezclados con `__()` / `esc_html__()`~~

**Archivo:** `widgets/class-blog-posts-widget.php` — Líneas 917, 1005

```php
POR <?php echo esc_html( $author_name ); ?>
```
```php
<?php echo ( 'compact' === $layout_type ) ? '' : esc_html__( 'POR ', 'wp-multi-post-type-blog' ); ?>
```

El texto "POR" (prefijo de autor) aparece en el HTML directamente como texto hardcodeado en la línea 917 (sin función de traducción), mientras que en la línea 1005 sí usa `esc_html__()`. Inconsistencia en la internacionalización.

**Archivo:** `assets/js/blog-posts-widget.js` — Línea 159

```javascript
var noPostsText = settings.no_posts_text || 'No se encontraron publicaciones.';
```

Texto en español hardcodeado en JavaScript, no traducible mediante WordPress i18n.

### ~~8.3 — `Requires Plugins: elementor` en el header pero sin `Version:` de Elementor requerida~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Línea 8

```php
 * Requires Plugins: elementor
```

WordPress 6.5+ soporta el header `Requires Plugins`, pero no se especifica una versión mínima de Elementor. Dado que el plugin usa `Elementor\Widgets_Manager` y `elementor/widgets/register` (que son de Elementor 3.x+), sería mejor agregar:

```php
 * Requires at least: 6.0
 * Requires PHP: 7.4
```

### ~~8.4 — CSS con `!important` excesivo en el layout compact (potencial conflicto de especificidad)~~

**Archivo:** `assets/css/blog-posts-widget.css` — Líneas 576–659

El bloque de estilos para `.premium-blog-widget--layout-compact` contiene **13 declaraciones con `!important`**. Esto indica que los estilos base no tienen la especificidad correcta para ser sobreescritos sin `!important`. Una buena arquitectura CSS no debería requerir `!important` para overrides de variantes del mismo componente.

### ~~8.5 — Color hardcodeado `#004b87` en el CSS sin variable~~

**Archivo:** `assets/css/blog-posts-widget.css` — Línea 612

```css
.premium-blog-widget--layout-compact .list-post-item__title a {
    color: #004b87; /* Beautiful blue color from user screenshot */
}
```

Este color está hardcodeado con un comentario que hace referencia a "user screenshot", lo que indica que fue ajustado manualmente para un cliente específico y **no pertenece al plugin genérico**. No respeta las variables CSS de Elementor (`--e-global-color-*`) como sí lo hacen otros selectores del mismo archivo.

### ~~8.6 — `wp_unslash()` aplicado a un array pero solo se usa en la comparación de tipo~~

**Archivo:** `wp-multi-post-type-blog-block.php` — Líneas 333–338

```php
$settings_raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';

if ( empty( $settings_raw ) || ! is_array( $settings_raw ) ) {
    wp_send_json_error( 'Configuración inválida.', 400 );
}
```

`wp_unslash()` está documentado para ser usado en strings, no en arrays (aunque funcionalmente puede aplicarse a arrays de strings en PHP). Sin embargo, la combinación `wp_unslash( $_POST['settings'] )` en un array anidado puede no eliminar correctamente los slashes de valores profundamente anidados. La función `wp_magic_quotes()` de WordPress ya maneja esto en el nivel de la request, por lo que `wp_unslash()` sobre el array completo es lo correcto, pero el código podría ser más explícito al respecto con un comentario.

---

## 9. Tabla Resumen de Hallazgos

| # | Categoría | Descripción | Archivo | Severidad | Prioridad |
|---|-----------|-------------|---------|-----------|-----------|
~~| 1.1 | Error Crítico | N-queries extra por post type en Archive widget | `class-blog-archive-widget.php:69` | 🔴 Alta | 🔴 Urgente |~~
~~| 1.2 | Error Menor | `wp_reset_postdata()` fuera de loop con `the_post()` | `class-blog-posts-widget.php:1064` | 🟡 Media | 🟡 Normal |~~
~~| 1.3 | Error Crítico | Verificaciones de dependencias dentro del loop en AJAX | `wp-multi-post-type-blog-block.php:356` | 🔴 Alta | 🔴 Urgente |~~
~~| 2.1 | Bug | `currentPage=0` en reset doble es inconsistente | `blog-posts-widget.js:88` | 🟡 Media | 🟡 Normal |~~
~~| 2.2 | Bug | `$btn` / `$trigger` fuera de scope en `loadMorePosts` | `blog-posts-widget.js:106` | 🟡 Media | 🟡 Normal |~~
~~| 2.3 | Bug | `found_posts` ausente en respuesta AJAX sin posts | `wp-multi-post-type-blog-block.php:377` | 🟢 Baja | 🟢 Mejora |~~
~~| 2.4 | Bug | `is_enabled()` usa `empty()` en lugar de `isset()` | `class-blog-posts-widget.php:810` | 🟡 Media | 🟡 Normal |~~
~~| 2.5 | Bug | Doble evaluación de condición en paginación numérica | `class-blog-posts-widget.php:1111` | 🟢 Baja | 🟢 Mejora |~~
~~| 3.1 | Seguridad | Settings completos expuestos en `data-settings` HTML | `class-blog-posts-widget.php:1075` | 🟡 Media | 🟡 Normal |~~
~~| 3.2 | Seguridad | Firma HMAC se invalida si cambian las salts de WP | `wp-multi-post-type-blog-block.php:342` | 🟢 Baja | 🟢 Documentar |~~
~~| 3.3 | Seguridad | Uso de `.html()` con dato de servidor en JS | `blog-posts-widget.js:160` | 🟡 Media | 🟡 Normal |~~
~~| 4.1 | Huérfano | `found_posts` enviado pero nunca leído en JS | `wp-multi-post-type-blog-block.php:374` | 🟢 Baja | 🟢 Limpiar |~~
~~| 4.2 | Huérfano | `$trigger` referenciada sin estar definida en scope | `blog-posts-widget.js` | 🟢 Baja | 🟢 Limpiar |~~
~~| 4.3 | Huérfano | Selector `.badge` genérico colisiona con otros plugins | `blog-posts-widget.css:732` | 🟡 Media | 🟡 Normal |~~
~~| 4.4 | Huérfano | `walkthrough.md` se distribuye en el ZIP del plugin | `walkthrough.md` | 🟢 Baja | 🟢 Limpiar |~~
~~| 4.5 | Huérfano | `enqueue_editor_assets()` no reutiliza handle registrado | `class-elementor-addon.php:89` | 🟢 Baja | 🟢 Limpiar |~~
~~| 5.1 | Rendimiento | Hasta 4 queries de post_meta por post para views (AJAX) | `class-blog-posts-widget.php:783` | 🔴 Alta | 🔴 Urgente |~~
~~| 5.2 | Rendimiento | N queries de taxonomía sin caché en editor Elementor | `class-blog-posts-widget.php:139` | 🔴 Alta | 🔴 Urgente |~~
~~| 5.3 | Rendimiento | `get_all_authors()` sin caché en el editor | `class-blog-posts-widget.php:121` | 🟡 Media | 🟡 Normal |~~
~~| 5.4 | Rendimiento | Animación jQuery con `step` callback fuerza repaints | `blog-posts-widget.js:142` | 🟡 Media | 🟡 Normal |~~
~~| 5.5 | Rendimiento | Placeholder PNG pesa 634 KB | `assets/images/placeholder.png` | 🔴 Alta | 🔴 Urgente |~~
~~| 6.1 | Arquitectura | Funciones helper en namespace global PHP | `wp-multi-post-type-blog-block.php:59` | 🟡 Media | 🟡 Normal |~~
~~| 6.2 | Arquitectura | Lógica de render duplicada entre los dos widgets | `class-blog-archive-widget.php:37` | 🟡 Media | 🟡 Normal |~~
~~| 6.3 | Arquitectura | `require_once` del widget dentro del handler AJAX | `wp-multi-post-type-blog-block.php:362` | 🟡 Media | 🟡 Normal |~~
~~| 6.4 | Arquitectura | Sin `.distignore` ni herramienta de build | — | 🟢 Baja | 🟢 Mejora |~~
~~| 7.1 | UX | Sin mensaje "no más posts" al terminar Load More | `blog-posts-widget.js` | 🟡 Media | 🟡 Normal |~~
~~| 7.2 | UX | Infinite scroll puede disparar múltiples requests | `blog-posts-widget.js:58` | 🟢 Baja | 🟢 Mejora |~~
~~| 7.3 | UX | Tabs de filtro no actualizan paginación numérica | `blog-posts-widget.js:76` | 🟡 Media | 🟡 Normal |~~
~~| 7.4 | UX/A11y | `outline:none` en botones viola WCAG 2.4.7 | `blog-posts-widget.css:354` | 🟡 Media | 🟡 Normal |~~
~~| 7.5 | UX | Imágenes sin `srcset`/`sizes` para responsive | `class-blog-posts-widget.php:889` | 🟡 Media | 🟡 Normal |~~
~~| 7.6 | UX/SEO | `loading="lazy"` en imagen destacada above-the-fold | `class-blog-posts-widget.php:889` | 🟡 Media | 🟡 Normal |~~
~~| 8.1 | Deuda técnica | Changelog desactualizado (1.1.0 pero plugin es 1.5.0) | `README.md` | 🟢 Baja | 🟢 Documentar |~~
~~| 8.2 | Deuda técnica | "POR" hardcodeado sin función de traducción en línea 917 | `class-blog-posts-widget.php:917` | 🟢 Baja | 🟢 Limpiar |~~
~~| 8.3 | Deuda técnica | Header sin versión mínima de PHP/WP/Elementor | `wp-multi-post-type-blog-block.php:8` | 🟢 Baja | 🟢 Documentar |~~
~~| 8.4 | Deuda técnica | Exceso de `!important` en CSS compact layout | `blog-posts-widget.css:576` | 🟡 Media | 🟡 Normal |~~
~~| 8.5 | Deuda técnica | Color `#004b87` hardcodeado para cliente específico | `blog-posts-widget.css:612` | 🟡 Media | 🟡 Normal |~~
~~| 8.6 | Deuda técnica | `wp_unslash()` sobre array sin comentario explicativo | `wp-multi-post-type-blog-block.php:333` | 🟢 Baja | 🟢 Documentar |~~

---

## Leyenda de Severidad

| Símbolo | Nivel | Descripción |
|---------|-------|-------------|
| 🔴 Alta | Crítico/Urgente | Impacto en rendimiento, seguridad o funcionalidad correcta |
| 🟡 Media | Normal | Afecta calidad del código, UX o mantenibilidad |
| 🟢 Baja | Mejora | Buenas prácticas, limpieza, documentación |

---

*Análisis generado por revisión estática exhaustiva — WP Multi-Post Type Blog Block v1.5.0*
