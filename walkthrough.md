# Walkthrough - Multi-Post Type Blog Block for Elementor

We have successfully created a custom WordPress plugin that registers a modern, premium Elementor widget. This widget allows querying and filtering multiple post types (addressing the current limitation of JNews Module 12 which only supports single post types) and features a fully responsive, premium visual redesign.

---

## What Was Created

We structured the plugin as a standalone directory containing the following files:

1. **[wp-multi-post-type-blog-block.php](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/wp-multi-post-type-blog-block.php)**: Main plugin file that registers AJAX hooks for paginating posts asynchronously.
2. **[includes/class-elementor-addon.php](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/includes/class-elementor-addon.php)**: Addon manager that registers the custom widget and enqueues JS/CSS files.
3. **[widgets/class-blog-posts-widget.php](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/widgets/class-blog-posts-widget.php)**: The Elementor Widget class containing content filters (multi-post types, taxonomies, authors, pagination modes) and design controls.
4. **[assets/css/blog-posts-widget.css](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/assets/css/blog-posts-widget.css)**: CSS styles implementing glassmorphism, responsive grids, stacked mobile cards, and hover scaling animations.
5. **[assets/js/blog-posts-widget.js](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/assets/js/blog-posts-widget.js)**: AJAX logic for dynamic pagination (Load More and Infinite Scroll) with sequential entry transitions.
6. **[assets/images/placeholder.png](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/assets/images/placeholder.png)**: An elegant, minimalist placeholder image for posts without featured images.

---

## Key Features & Code Details

### 1. Multi-Post Type and Taxonomy Queries
In `widgets/class-blog-posts-widget.php`, we built a unified query parser that allows selecting multiple post types and custom taxonomies:
- Post types are queried dynamically using `get_post_types()`.
- Taxonomies (categories, tags, custom taxonomies) are gathered and parsed as `taxonomy:slug` keys, converting them dynamically into WordPress `tax_query` arrays.
- Filter by Authors using `get_users()` mapping.

### 2. Premium Design & Glassmorphism
The design is completely revised for premium aesthetics:
- **Featured Post Overlay**: The floating content card sits on top of the featured image with high-end glassmorphism styling (`backdrop-filter: blur(16px)` and translucent borders).
- **Smooth Image Zoom**: Images scale up smoothly on hover using `scale(1.04)` transitions.
- **List Post Cards**: Hovering on items triggers subtle card translates (`translateY(-3px)`) and shadows.
- **Read More Animation**: Text links with SVG arrows that shift on hover.
- **Mobile Stacked Layouts**: Media queries stack horizontal lists into vertical grids below `768px`.

### 3. AJAX Pagination & Sequential Animations
In `assets/js/blog-posts-widget.js`, we handle dynamic loading:
- **Load More**: Clicks query subsequent pages via `wp-admin/admin-ajax.php` and append them.
- **Infinite Scroll**: Utilizes `IntersectionObserver` to trigger loading automatically as the user scrolls.
- **Staggered entry**: Newly loaded cards animate in sequentially with a staggered delay for premium micro-interaction:
  ```javascript
  $newElements.each(function(index, el) {
      $(el).delay(index * 120).animate({ opacity: 1 }, {
          duration: 500,
          step: function(now, fx) {
              $(el).css('transform', 'translateY(' + (15 - now * 15) + 'px)');
          }
      });
  });
  ```

---

## Installation Guide

To install the plugin on your WordPress site:
1. Compress the workspace folder `06 Wp multi type blog` into a ZIP archive (e.g. `wp-multi-post-type-blog.zip`).
2. Go to your WordPress Dashboard -> **Plugins** -> **Add New** -> **Upload Plugin**.
3. Upload the ZIP file and click **Install Now**.
4. Activate the plugin.
5. Edit your Home Page in Elementor, find the **Premium Multi-Post Blog** widget under the General category, and drag it onto your page.
6. Configure the query filters and style parameters!
