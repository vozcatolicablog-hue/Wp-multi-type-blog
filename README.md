# WP Multi-Post Type Blog Block for Elementor

Plugin de WordPress para Elementor que agrega un widget de blog capaz de mostrar publicaciones desde multiples post types en un mismo bloque.

Fue creado para cubrir el caso donde un modulo de blog solo permite consultar un post type a la vez, por ejemplo en sitios con entradas, noticias, eventos, columnas u otros CPTs que deben aparecer mezclados en una misma grilla/lista.

## Caracteristicas

- Widget personalizado para Elementor: **Premium Multi-Post Blog**.
- Seleccion multiple de post types publicos.
- Filtros por taxonomias, categorias, tags y terminos personalizados.
- Filtro por autores.
- Ordenamiento por fecha, titulo, aleatorio, comentarios o menu order.
- Post destacado inicial con imagen grande y tarjeta superpuesta.
- Lista responsive de publicaciones secundarias.
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
- **Filter by Authors**: autores incluidos en la consulta.
- **Posts Count**: cantidad de publicaciones por pagina.
- **Order By / Order Direction**: criterio y direccion de orden.
- **Pagination Mode**: sin paginacion, numeros, AJAX con boton o scroll infinito.

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
  images/placeholder.png
walkthrough.md
```

## Seguridad y rendimiento

El endpoint AJAX valida nonce, post types publicos, taxonomias publicas, ordenamiento permitido y limite de publicaciones por pagina. La carga de terminos en Elementor esta limitada por taxonomia para evitar que el editor se vuelva pesado en sitios grandes.

## Notas

- Si Elementor no esta activo, el plugin muestra un aviso en el administrador y no registra el widget.
- Los estilos estan pensados para un bloque visual premium y responsive; pueden requerir ajustes menores segun el tema activo.
- `walkthrough.md` contiene una explicacion tecnica mas detallada del desarrollo original.
