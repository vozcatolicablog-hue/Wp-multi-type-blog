# Walkthrough - Multi-Post Type Blog Block for Elementor

We have successfully created a custom WordPress plugin that registers a modern, premium Elementor widget. This widget allows querying and filtering multiple post types (addressing the current limitation of JNews Module 12 which only supports single post types) and features a fully responsive, premium visual redesign.

---

## What Was Created

We structured the plugin as a standalone directory containing the following files:

1. **[wp-multi-post-type-blog-block.php](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/wp-multi-post-type-blog-block.php)**: Main plugin file that registers AJAX hooks for paginating posts asynchronously.
2. **[includes/class-elementor-addon.php](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/includes/class-elementor-addon.php)**: Addon manager that registers the custom widget and enqueues JS/CSS files.
3. **[widgets/class-blog-posts-widget.php](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/widgets/class-blog-posts-widget.php)**: The Elementor Widget class containing content filters (multi-post types, taxonomies, authors, pagination modes) and design controls.
4. **[widgets/class-blog-archive-widget.php](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/widgets/class-blog-archive-widget.php)**: The new Premium Multi-Post Archive widget class, extending the posts widget, which automatically filters by author in archive pages and outputs dynamic post type filters.
5. **[assets/css/blog-posts-widget.css](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/assets/css/blog-posts-widget.css)**: CSS styles implementing glassmorphism, responsive grids, stacked mobile cards, hover scaling animations, and post type filter tabs.
6. **[assets/js/blog-posts-widget.js](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/assets/js/blog-posts-widget.js)**: AJAX logic for dynamic pagination (Load More and Infinite Scroll) with sequential entry transitions, and dynamic tab switching for archive filtering.
7. **[assets/images/placeholder.png](file:///X:/04%20-%20Developer%20WP/06%20Wp%20multi%20type%20blog/assets/images/placeholder.png)**: An elegant, minimalist placeholder image for posts without featured images.

---

## Key Features & Code Details

### 1. Multi-Post Type, Taxonomy Queries & Post Type Prefix
In `widgets/class-blog-posts-widget.php`, we built a unified query parser that allows selecting multiple post types and custom taxonomies:
- Post types are queried dynamically using `get_post_types()`.
- Taxonomies (categories, tags, custom taxonomies) are gathered and parsed as `taxonomy:slug` keys, converting them dynamically into WordPress `tax_query` arrays.
- Filter by Authors using `get_users()` mapping.
- **Dynamic Post Type Prefix**: When displaying publications that are not standard posts (e.g. Portfolio, Events, Pages, etc.), the widget automatically displays the singular label of that post type (e.g., `Portafolio:`, `Evento:`, `Página:`) before the title, styled using the Elementor theme accent color (`var(--e-global-color-accent)`) by default, and fully customizable via custom color controls under the widget's Style tab for both featured and list layouts.

### 2. Premium Design & Glassmorphism
The design is completely revised for premium aesthetics:
- **Featured Post Overlay**: The floating content card sits on top of the featured image with high-end glassmorphism styling (`backdrop-filter: blur(16px)` and translucent borders).
- **Smooth Image Zoom**: Images scale up smoothly on hover using `scale(1.04)` transitions.
- **List Post Cards**: Hovering on items triggers subtle card translates (`translateY(-3px)`) and shadows.
- **Read More Animation**: Text links with SVG arrows that shift on hover.
- **Mobile Stacked Layouts**: Media queries stack horizontal lists into vertical grids below `768px`.
- **Compact Layout Theme**: An optional layout style select control (`Classic` vs `Compact`). In Compact mode, lists display a small 16:9 thumbnail on the left, smaller blue titles, date and author text with custom icons, and category badges placed in the meta list with tag outline icons. Excerpts and buttons are hidden.
- **Post Separation Control**: A slider control under List Styles to customize the row gap/separation between each post in list or grid views.

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

- **Premium Multi-Post Archive**: A second widget (`wp_multi_post_type_archive_widget`) named **Premium Multi-Post Archive** that auto-detects author archives and filters post queries for the current author page context automatically.
- **Dynamic Post Type Filter Tabs**: Displays dynamic filtering buttons at the top of the archive widget, triggering AJAX resets of query results for each post type when tabs are clicked.

---

## Installation & Usage Guide

To install the plugin on your WordPress site:
1. Compress the workspace folder `06 Wp multi type blog` into a ZIP archive (e.g. `wp-multi-post-type-blog.zip`).
2. Go to your WordPress Dashboard -> **Plugins** -> **Add New** -> **Upload Plugin**.
3. Upload the ZIP file and click **Install Now**.
4. Activate the plugin.
5. **Standard Blog Block**: Edit any standard page in Elementor, find the **Premium Multi-Post Blog** widget, and drag it onto the page.
6. **Author Archive Block**: Edit your Author Archive theme template in Elementor Theme Builder, find the **Premium Multi-Post Archive** widget, drag it onto the template, and choose which post types to include. It will automatically render post type filter tabs at the top!
7. Configure filters, pagination, and style parameters!
