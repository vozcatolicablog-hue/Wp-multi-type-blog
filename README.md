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
- Placeholder local para publicaciones sin imagen destacada.
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
widgets/
  class-blog-posts-widget.php
assets/
  css/blog-posts-widget.css
  js/blog-posts-widget.js
  images/placeholder.jpg
walkthrough.md
```

## Seguridad y rendimiento

El endpoint AJAX valida nonce, firma de configuracion, post types publicos, taxonomias publicas, ordenamiento permitido y limite de publicaciones por pagina. La carga de terminos en Elementor esta limitada por taxonomia para evitar que el editor se vuelva pesado en sitios grandes.

La firma de configuracion evita que un visitante modifique manualmente los settings del widget en el navegador para forzar consultas distintas a las renderizadas por Elementor.

## Changelog

### 1.5.0

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
