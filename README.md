# WP Multi-Post Type Blog Block for Elementor

Plugin de WordPress para Elementor que agrega un widget de blog capaz de mostrar publicaciones desde multiples post types en un mismo bloque.

Fue creado para cubrir el caso donde un modulo de blog solo permite consultar un post type a la vez, por ejemplo en sitios con entradas, noticias, eventos, columnas u otros CPTs que deben aparecer mezclados en una misma grilla/lista.

## Caracteristicas

- Widget personalizado para Elementor: **Premium Multi-Post Blog**.
- Seleccion multiple de post types publicos.
- Filtros por taxonomias, categorias, tags y terminos personalizados.
- Relacion de taxonomias configurable: `AND` u `OR`.
- Filtro por autores.
- Offset, exclusion por IDs y exclusion automatica del post actual.
- Ordenamiento por fecha, titulo, aleatorio, comentarios o menu order.
- Post destacado inicial con imagen grande y tarjeta superpuesta.
- Lista responsive de publicaciones secundarias.
- Layout de lista en 1, 2 o 3 columnas.
- Controles para mostrar u ocultar categoria, autor, fecha, vistas y extracto.
- Control del texto de "Leer mas", largo del extracto y tamanos de imagen.
- Paginacion por numeros, boton **Cargar Mas** via AJAX o scroll infinito.
- Imagen o icono de respaldo configurable por tipo de contenido desde **Ajustes → Multi-Post Blog**, con opción de ocultar el área de imagen cuando no hay ninguna.
- Carga de CSS/JS como dependencias del widget, no globalmente en todo el sitio.

## Requisitos

- WordPress.
- Elementor activo.
- PHP compatible con la version soportada por tu instalacion de WordPress.

## Instalacion

1. Descarga o clona este repositorio.
2. Comprime la carpeta del plugin en un archivo `.zip`.
3. En WordPress ve a **Plugins > Anadir nuevo > Subir plugin**.
4. Sube el `.zip`, instala y activa el plugin.
5. Edita una pagina con Elementor.
6. Busca el widget **Premium Multi-Post Blog** en la categoria **General**.
7. Arrastralo a la pagina y configura los filtros.

## Uso

Desde el panel del widget puedes configurar:

- **Post Types**: tipos de contenido publicos que quieres mezclar.
- **Filter by Taxonomy Terms**: terminos de categorias, tags o taxonomias personalizadas.
- **Taxonomy Relation**: exige todos los grupos de taxonomia seleccionados (`AND`) o cualquiera de ellos (`OR`).
- **Filter by Authors**: autores incluidos en la consulta.
- **Offset**: cantidad de resultados a saltar.
- **Exclude Post IDs**: IDs separados por coma que no deben aparecer.
- **Exclude Current Post**: evita repetir el post actual en paginas single.
- **Posts Count**: cantidad de publicaciones por pagina.
- **Order By / Order Direction**: criterio y direccion de orden.
- **Featured First Post**: activa o desactiva el post destacado.
- **Pagination Mode**: sin paginacion, numeros, AJAX con boton o scroll infinito.
- **Display Options**: visibilidad de categoria, autor, fecha, vistas y extracto.
- **List Columns**: 1, 2 o 3 columnas para la lista.

El primer post de la primera pagina se renderiza como destacado. El resto se muestra como lista de tarjetas.

## Estructura

```text
wp-multi-post-type-blog-block.php
includes/
  class-elementor-addon.php
  class-admin-settings.php
  class-views-source.php
widgets/
  class-blog-posts-widget.php
  class-blog-archive-widget.php
  class-author-list-widget.php
  class-section-heading-widget.php
  class-recent-comments-widget.php
  trait-vc-layouts.php
assets/
  css/blog-posts-widget.css
  css/sistema.css
  fonts/ (Cormorant Garamond 500/600, Inter variable)
  js/blog-posts-widget.js
  images/placeholder.jpg
walkthrough.md
```

## Seguridad y rendimiento

El endpoint AJAX valida nonce, firma de configuracion, post types publicos, taxonomias publicas, ordenamiento permitido y limite de publicaciones por pagina. La carga de terminos en Elementor esta limitada por taxonomia para evitar que el editor se vuelva pesado en sitios grandes.

La firma de configuracion evita que un visitante modifique manualmente los settings del widget en el navegador para forzar consultas distintas a las renderizadas por Elementor.

## Sistema de diseño de Voz Católica (3.0.0)

Los widgets ofrecen diseños «Sistema VC» que reproducen los mockups aprobados de la portada (`propuesta-home-v4`) y de la página de autor (`propuesta-autor`). Usan Cormorant Garamond 500/600 e Inter autoalojadas y leen los colores del Kit (`--azul`, `--noche`, `--oro`, `--pergamino`, `--papel`, `--tinta`, `--linea`) con los valores del sistema como respaldo. Todo cuelga de `.wpmb-ds` en `assets/css/sistema.css`, que solo se carga donde hay un widget.

Cómo armar cada bloque del mockup:

| Bloque del mockup | Widget | Ajustes clave |
|---|---|---|
| «Lo último en el blog» (portada partida) | Premium Multi-Post Blog | Layout `Sistema VC · Lista con miniatura`, 1 entrada, destacado activo, etiqueta del título H1 si es la única de la página |
| «Más publicaciones» con chips | Premium Multi-Post Blog | Mismo layout, destacado apagado, «Evitar repetir destacados», chips de filtro, enlace al pie «Ver todo lo publicado» |
| «Lo más leído» | Premium Multi-Post Blog | Layout `Sistema VC · Ranking numerado`, orden «Más leídos», sin fecha |
| «Últimos comentarios» | Últimos comentarios (nuevo) | 3 comentarios; muestra solo el primer nombre |
| «Quienes escriben en Voz Católica» | Premium Author List | Layout `Sistema VC · Franja`, 5 autores, enlace «Ver todos los autores» |
| «Artículos» de la página de autor | Premium Multi-Post Archive | Layout `Sistema VC · Tarjetas y lista`, bajada, enlace «Ver los {total} artículos» en cabecera y pie |
| «Otros autores del blog» | Premium Author List | Layout `Sistema VC · Chips`, «Ocultar al autor de la página» |

Los fondos pergamino de las franjas (autores, biblioteca) son del contenedor de Elementor, no del widget.

## Changelog

### 3.0.3

- En los resultados de búsqueda, `[wpmb_archivo_cabecera]` repite el buscador con lo buscado, para afinar sin volver atrás; conserva `post_type`.

### 3.0.2

- El widget de archivo filtra por el término que se está viendo (categoría, etiqueta o taxonomía) y por el texto buscado. Antes solo reconocía el autor, así que cada categoría listaba todas las entradas del sitio.
- Shortcode `[wpmb_archivo_cabecera]`: migas, antetítulo (Categoría, Etiqueta, Búsqueda), título, descripción, total de publicaciones y subcategorías como chips.

### 3.0.1

- Diseño clásico: la miniatura de la lista es fluida (hasta 320 px, nunca más del 38 % de la fila). En el archivo de categoría y de autor, con barra lateral, la columna de entradas es estrecha y la miniatura fija de 320 px dejaba el texto en ~185 px, con títulos de 6 renglones.

### 3.0.0

- Base: la 2.9.0 que estaba en producción (orden por vistas con Voz Católica Analytics y widget «Encabezado de sección»), que no estaba en el repositorio.
- Tres layouts del sistema de diseño para los widgets de entradas: **Lista con miniatura** (destacado partido 50/50 con antetítulo, bajada, botón y firma con foto, tema, fecha y minutos de lectura; filas con miniatura 16:10), **Tarjetas y lista** (primera tarjeta a todo el ancho con extracto, el resto de a dos, y renglones «Más artículos» con fecha corta) y **Ranking numerado** (puestos en oro con contador CSS que sigue correlativo al cargar más).
- Encabezado de bloque: título, bajada, enlace «ver todo» en la cabecera o como botón al pie, con `{total}`.
- Chips de tema que filtran por AJAX sin recargar. Solo se aceptan los términos que el widget ofrece (viajan firmados); en tarjetas, el filtro vuelve a pintar las primeras entradas como tarjetas.
- Widget de autores: layouts **Franja** (tarjeta-enlace con la última entrada, desplazable en móvil) y **Chips**, encabezado propio y opción para ocultar al autor del archivo que se está viendo.
- Widget nuevo **Últimos comentarios**: cita recta entre «», recortada a tres renglones; primer nombre del lector; caché de 10 minutos que se invalida al aprobar o retirar un comentario.
- Los diseños clásico y compacto no cambian. Sus controles de estilo se ocultan en los layouts del sistema y Elementor deja de emitir su CSS ahí.

### 2.9.0

- Nuevo widget **Encabezado de sección**, reemplazo del `jeg_block_heading_6`
  del tema JNews: título con ícono, línea gris al pie y un segmento de color en
  el extremo izquierdo.
- No hereda las clases `jeg_*` ni la FontAwesome 4 del tema, que desaparecen
  con él: usa clases propias y el control de íconos de Elementor.
- Los valores por defecto salen de medir el encabezado original ya renderizado,
  no de sus ajustes guardados: declaraban 18 px y peso 600, pero en pantalla la
  hoja del tema imponía 16 px y 700.

### 2.8.1

- El número de lecturas que se muestra sigue al criterio de ordenamiento. Un
  bloque de «lo más leído esta semana» mostraba el total histórico junto a un
  orden semanal: el segundo de la lista exhibía 17.364 lecturas y el primero
  446, y el orden parecía roto.

### 2.8.0

- Las vistas ya no se leen de JNews View Counter sino de **Voz Católica
  Analytics**. JNews queda como respaldo automático, así que esta versión se
  puede desplegar antes o después de desactivar el contador viejo.
- Nuevos ordenamientos: **Más leídos (histórico)**, **Más leídos (del período)**
  y **En tendencia (crecimiento)**. Hasta ahora el único orden por popularidad
  era por cantidad de comentarios.
- Nuevos controles «Período de las vistas» y «Mínimo de lecturas», visibles
  sólo cuando el orden elegido los usa.
- El acceso a las vistas se centraliza en `includes/class-views-source.php`; los
  widgets ya no saben de qué contador salen los números.
- `prompt-vistas.md` reescrito: describía JNews como el contador del sitio.

### 2.7.0

- Tres plantillas para el widget de autores, seleccionables desde el editor:
  - **Lista** (por defecto): avatar a la izquierda, nombre, última entrada y metadatos. Es el diseño de referencia.
  - **Tarjetas**: grilla con avatar centrado, la cantidad de entradas como píldora arriba del nombre y el título recortado a dos líneas para que las tarjetas no se desalineen. Pensada para una página de autores.
  - **Compacto**: avatar chico y título en una sola línea con puntos suspensivos. Pensada para barra lateral.
- Control responsive de columnas para la plantilla de tarjetas, con 3 / 2 / 1 por defecto en escritorio, tableta y móvil.
- Las líneas divisorias se desactivan solas en la plantilla de tarjetas, que ya se separan por su propio borde.
- Los colores salen de variables CSS para que los controles de estilo de Elementor los pisen sin pelear con la especificidad.

### 2.6.0

- Nuevo widget **Premium Author List**: lista autores con su última entrada, avatar, biografía recortada y cantidad de entradas.
- A diferencia de los widgets de avatares habituales, la "última entrada" se resuelve sobre **todos los tipos de contenido seleccionados**, así que descargas, libros del catálogo y documentos cuentan como actividad igual que las entradas del blog.
- Filtros: roles, lista blanca y lista negra de IDs de usuario, mínimo de entradas publicadas y cantidad máxima. La lista blanca gana sobre el filtro de roles, por ser una selección deliberada.
- Orden por actividad reciente, cantidad de entradas, nombre o aleatorio, ascendente o descendente.
- Rendimiento: el conjunto de candidatos se arma desde la tabla de entradas y no desde la de usuarios. El sitio tiene miles de suscriptores y clientes pero solo unas decenas han publicado, así que agrupar primero por autor deja la comprobación de roles corriendo sobre decenas de usuarios en lugar de miles. Son dos consultas en total, con caché de 15 minutos.
- El orden aleatorio no se cachea, para que no quede congelado.

### 2.5.0

- Nuevo control "Categoría de la etiqueta", con dos opciones: la categoría superior (por defecto) o la más específica.
- Corrección de fondo: la etiqueta no mostraba la categoría "de abajo" por diseño, sino porque `get_the_terms()` devuelve los términos ordenados por nombre y no por profundidad. En una entrada archivada bajo "Niños > Devociones" ganaba Devociones sólo por el alfabeto, y el resultado dependía de cómo se llamara cada categoría.
- Con la opción por defecto, el término elegido sube hasta la raíz de su rama, así que la etiqueta muestra la sección que el lector reconoce en vez de la subcategoría más angosta.
- Para conservar el comportamiento anterior, elegir "La más específica" en el widget.

### 2.4.0

- Nuevo: control "Evitar repetir destacados", activado por defecto. Una entrada que ya salió como destacada en un widget anterior de la misma página queda excluida de los widgets siguientes.
- Se aplica solo hacia abajo, en orden de documento: el widget de más arriba conserva su destacado y son los de abajo los que ceden. Elementor renderiza en ese orden, así que cuando un widget arma su consulta los de arriba ya registraron su destacado.
- Las exclusiones se pliegan dentro de `exclude_ids` en lugar de aplicarse al construir la consulta. Eso es lo que hace que sobrevivan a la paginación AJAX de "Cargar más", que corre en otra petición con el registro vacío: los IDs viajan dentro de los ajustes firmados que ya se entregan al navegador.
- Solo se registran las entradas del hueco destacado, no las de la lista.

### 2.3.0

- Nuevo: controles "Etiqueta del título destacado" y "Etiqueta del título de la lista" en el widget, con H1 a H6 disponibles. Los valores por defecto son los de siempre — H2 para el destacado y H3 para los de la lista — así que nada cambia hasta que se toquen.
- Motivo: los niveles estaban fijos en el código y no había forma de que una página tuviera su H1. Ahora el destacado puede ascender a H1 desde el editor, sin tocar archivos.
- El CSS del plugin usa solo clases, nunca selectores de elemento, por lo que cambiar el nivel altera la semántica sin modificar el aspecto.
- El nivel se valida contra una lista blanca de h1 a h6 y se vuelve a sanear dentro del render, porque a esos métodos también se llega desde el manejador AJAX de "Cargar más".

### 2.2.0

- Corrección: los tipos de contenido que guardan su imagen en un campo personalizado en vez de en la imagen destacada de WordPress mostraban siempre el logo de respaldo. Afectaba a los libros del catálogo (`vcec_book`, portada en `_vcec_cover_image_id`) y a las descargas (`sdm_downloads`, campo ACF `Imagen-destacada-descargas`): ninguno de los 264 items publicados tiene `_thumbnail_id`, así que la cadena de respaldo saltaba directo al logo.
- Nuevo paso en la cadena de imagen: imagen destacada → **campo personalizado** → icono del tipo de contenido → ocultar o placeholder.
- Nueva columna "Campo personalizado con la imagen" en **Ajustes → Multi-Post Blog**. Acepta varios nombres separados por coma (se usa el primero con valor) y un guion para desactivar la búsqueda. Vacío usa los valores por defecto de cada tipo de contenido.
- Valores por defecto incorporados, filtrables con `wpmb_default_image_meta_keys`: `vcec_book` → `_vcec_cover_image_id, _vcec_cover_image`; `sdm_downloads` → `Imagen-destacada-descargas, sdm_upload_thumbnail`.
- El campo puede guardar un ID de adjunto, una URL, un array de ACF o una lista separada por comas: se normalizan todos. Si es un ID se usa `wp_get_attachment_image()` con srcset; si es una URL externa se resuelve el adjunto una sola vez y se cachea en object cache.
- Las imágenes verticales (portadas de libro, fichas de descarga) se muestran enteras y centradas sobre un fondo neutro, con sombra, en lugar de recortarse al marco apaisado de la tarjeta, que cortaba el título. Las apaisadas conservan el recorte a sangre habitual. La decisión se toma por post según las dimensiones reales del adjunto.
- El fondo de esas portadas se puede cambiar con la variable CSS `--wpmb-cover-bg`.

### 2.1.0

- Corrección: el contador de vistas leía de fuentes equivocadas y mostraba 0. Ahora lee el total histórico directamente de la tabla del contador (`{prefijo}popularpostsdata`), con una sola consulta por listado en lugar de una por post.
- Corrección: los libros del catálogo (`vcec_book`) usan un contador propio y no tienen fila en `popularpostsdata`, por lo que habrían mostrado 0. Sus vistas se leen del postmeta `_vcec_view_count`, sin consultas extra.
- Corrección: la llamada de respaldo a `jnews_get_views()` no pasaba el rango ni el formato, por lo que devolvía el rango por defecto del plugin como cadena ya formateada, que `intval()` truncaba en el separador de miles (`1.234` → `1`). Ahora se piden explícitamente el total histórico y el entero crudo.
- El contador de vistas se oculta cuando el valor es 0, en lugar de mostrar un "0" que no aporta nada. Si además autor y fecha están desactivados, la fila de metadatos completa desaparece.
- Nuevo filtro `wp_multipost_blog_views_count` para sobrescribir el número de vistas antes de formatearlo; devolver 0 oculta el elemento.
- Nuevo: pantalla **Ajustes → Multi-Post Blog** para asignar una imagen o icono de respaldo a cada tipo de contenido, con selector de la biblioteca de medios.
- Nuevo: cadena de respaldo de imagen — imagen destacada → icono del tipo de contenido → ocultar el área de imagen.
- Nuevo: control "Sin imagen destacada" en el widget para elegir el último paso de la cadena: ocultar el área de imagen (por defecto) o mostrar el placeholder genérico.
- Los items sin imagen pasan a texto a ancho completo, y el badge de categoría se renderiza inline al no haber miniatura sobre la que superponerse.
- El post destacado sin imagen deja de posicionar su tarjeta en absoluto y se renderiza como tarjeta normal.
- Las imágenes de respaldo se muestran contenidas y centradas sobre fondo neutro, sin recorte ni zoom al pasar el cursor, porque suelen ser iconos y no fotografías.

### 2.0.1

- Corrección: `exclude_current_post` excluía el post equivocado en archivos, porque `get_queried_object_id()` devuelve un ID de término o de usuario fuera de vistas singulares.
- Corrección: al cambiar de pestaña en el widget Archive quedaba visible el post destacado de la consulta sin filtrar.
- Corrección: el contenedor `.premium-blog-widget__list` ahora se renderiza siempre, incluso vacío, para que la paginación AJAX y los filtros tengan siempre un destino donde insertar.
- Corrección: se eliminó el uso del argumento `who => authors` en `get_users()`, deprecado desde WordPress 5.9.
- Corrección: un archivo de autor ya no se combina con el filtro manual de autores, combinación que podía producir resultados siempre vacíos.
- Corrección: los assets ahora se registran también en el hook del editor de Elementor, donde `wp_enqueue_scripts` no se ejecuta.
- Rendimiento: la invalidación de caché usa una versión incremental en lugar de un `DELETE` directo sobre la tabla de opciones, lo que además funciona con object cache persistente.
- Rendimiento: la invalidación ignora revisiones, autoguardados y borradores automáticos, y se ejecuta una sola vez por petición.
- Rendimiento: el layout Compact ya no genera el extracto ni el enlace "Leer más" que el CSS ocultaba.
- Robustez: `sanitize_settings()` fuerza valores escalares antes de pasarlos a los sanitizadores.
- El control "Excerpt Words" en 0 ahora sí oculta el extracto, en lugar de revertir silenciosamente a 30 palabras.

### 2.0.0

- Optimización drástica de rendimiento mediante el almacenamiento en caché de taxonomías, autores y tipos de entrada en transients de WordPress en el panel de Elementor.
- Solución de rendimiento O(N) en la validación de tipos de entrada activos en el widget Archive mediante caché transients.
- Pre-carga del caché de postmeta (`update_postmeta_cache`) antes de renderizar bucles de posts para evitar múltiples consultas SQL de vistas por post.
- Reemplazo de animaciones jQuery personalizadas basadas en timers por transiciones CSS aceleradas por hardware.
- Conversión y compresión de imagen placeholder de 633 KB PNG a 25 KB JPEG de alta calidad.
- Incorporación de soporte nativo para imágenes adaptables (`srcset` y `sizes`) a través de la función estándar de WordPress `get_the_post_thumbnail`.
- Refactorización de código duplicado unificando el renderizado de HTML en un método compartido, lo que respeta el principio DRY.
- Organización del código global en el espacio de nombres `WpMultiPostTypeBlog` y encapsulado en la clase `Utils`.
- Añadido indicador visual "Has llegado al final" cuando las paginaciones AJAX completan la carga total.
- Corrección de accesibilidad mediante el diseño de indicadores de foco `:focus-visible` para navegación con teclado (cumplimiento de pautas WCAG 2.4.7).
- Corrección de seguridad contra inyección de código XSS en mensajes de error/vacíos en frontend.

### 1.1.0

- Firma de configuracion en AJAX para evitar consultas manipuladas desde el navegador.
- Query builder centralizado para que la carga inicial y AJAX usen las mismas reglas.
- Paginacion numerica por instancia de widget para evitar conflictos cuando hay mas de un widget en una pagina.
- Relacion de taxonomias configurable con `AND` u `OR`.
- Offset, exclusion por IDs y exclusion automatica del post actual.
- Controles para activar/desactivar post destacado, categoria, autor, fecha, vistas y extracto.
- Control del texto de "Leer mas", largo del extracto y tamanos de imagen.
- Layout de lista en 1, 2 o 3 columnas.
- Mejor seleccion de post types publicamente consultables y autores con publicaciones.
- Carga de textdomain para traducciones.

### 1.0.0

- Widget inicial para Elementor.
- Query multipost type con filtros por taxonomia y autores.
- Paginacion numerica, AJAX e infinite scroll.

## Notas

- Si Elementor no esta activo, el plugin muestra un aviso en el administrador y no registra el widget.
- Los estilos estan pensados para un bloque visual premium y responsive; pueden requerir ajustes menores segun el tema activo.
- `walkthrough.md` contiene una explicacion tecnica mas detallada del desarrollo original.
