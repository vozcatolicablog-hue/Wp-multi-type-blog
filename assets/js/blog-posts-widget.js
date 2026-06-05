(function($) {
	'use strict';

	/**
	 * Initialize pagination handlers for the widget.
	 *
	 * @param {HTMLElement} widgetEl Widget wrapper element.
	 */
	function initWidget(widgetEl) {
		if (!widgetEl) {
			return;
		}

		if (typeof wpMultipostBlogAjax === 'undefined') {
			return;
		}
		
		var $widget = $(widgetEl);
		if ($widget.data('initialized')) {
			return;
		}
		$widget.data('initialized', true);

		var pagination  = $widget.attr('data-pagination');
		var maxPages    = parseInt($widget.attr('data-max-pages'), 10) || 1;
		var currentPage = parseInt($widget.attr('data-current-page'), 10) || 1;
		var settingsRaw = $widget.attr('data-settings');
		var settingsSignature = $widget.attr('data-settings-signature');
		var settings    = {};
		
		try {
			settings = JSON.parse(settingsRaw);
		} catch (e) {
			console.error('Failed to parse blog widget settings.', e);
			return;
		}

		var $list = $widget.find('.premium-blog-widget__list');
		var isLoading = false;
		var observer;

		// 1. AJAX Load More Button Mode
		if (pagination === 'load_more') {
			var $btn = $widget.find('.wp-multipost-blog-load-more-btn');
			$btn.on('click', function(e) {
				e.preventDefault();
				if (isLoading || currentPage >= maxPages) {
					return;
				}
				loadMorePosts(false);
			});
		}

		// 2. AJAX Infinite Scroll Mode
		if (pagination === 'infinite') {
			var $trigger = $widget.find('.premium-blog-widget__infinite-trigger');
			if ($trigger.length && 'IntersectionObserver' in window) {
				observer = new IntersectionObserver(function(entries) {
					if (entries[0].isIntersecting) {
						if (!isLoading && currentPage < maxPages) {
							loadMorePosts(false);
						}
					}
				}, { 
					rootMargin: '100px 0px 300px 0px',
					threshold: 0.1 
				});
				observer.observe($trigger[0]);
			}
		}

		// 3. Post Type Filter Tabs (for Archive Widget)
		var activeFilterPostType = '';
		var $filters = $widget.find('.premium-blog-archive__filters .filter-tab');
		
		$filters.on('click', function(e) {
			e.preventDefault();
			var $clickedTab = $(this);
			if ($clickedTab.hasClass('active') || isLoading) {
				return;
			}
			
			$filters.removeClass('active');
			$clickedTab.addClass('active');
			activeFilterPostType = $clickedTab.attr('data-post-type') || '';
			
			// Reset pagination state
			currentPage = 0;
			maxPages = 1;
			
			$list.fadeOut(200, function() {
				$list.empty().show();
				loadMorePosts(true); // reset load
			});
		});

		/**
		 * Triggers the AJAX call to load the next page of posts.
		 *
		 * @param {boolean} isReset True if this is resetting page query.
		 */
		function loadMorePosts(isReset) {
			isLoading = true;
			var nextPage = isReset ? 1 : (currentPage + 1);

			if (pagination === 'load_more' && $btn) {
				$btn.addClass('is-loading');
				$widget.find('.premium-blog-widget__pagination-ajax').show();
			} else if (pagination === 'infinite' && $trigger) {
				$widget.find('.premium-blog-widget__infinite-trigger').show();
			}

			$.ajax({
				url: wpMultipostBlogAjax.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wp_multiblog_load_more',
					nonce: wpMultipostBlogAjax.nonce,
					page: nextPage,
					signature: settingsSignature,
					settings: settings,
					filter_post_type: activeFilterPostType
				},
				success: function(response) {
					if (response.success && response.data) {
						var responseMaxPages = parseInt(response.data.max_pages, 10) || 0;
						maxPages = responseMaxPages;
						
						if (isReset) {
							currentPage = 0;
						}

						if (response.data.html) {
							var $newElements = $(response.data.html);
							
							// Setup initial states for smooth fade-in
							$newElements.css({ opacity: 0, transform: 'translateY(15px)' });
							$list.append($newElements);
							
							// Staggered sequential entry animation
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

							currentPage = nextPage;
							$widget.attr('data-current-page', currentPage);
						} else if (isReset) {
							// No posts found
							var noPostsText = settings.no_posts_text || 'No se encontraron publicaciones.';
							$list.html('<div class="premium-blog-no-posts">' + noPostsText + '</div>');
						}

						updateLoaderVisibility();
					} else {
						updateLoaderVisibility();
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX request failed loading posts:', error);
					updateLoaderVisibility();
				},
				complete: function() {
					isLoading = false;
					if (pagination === 'load_more' && $btn) {
						$btn.removeClass('is-loading');
					}
				}
			});
		}

		/**
		 * Toggles visibility of loaders based on pages limit.
		 */
		function updateLoaderVisibility() {
			if (currentPage >= maxPages || maxPages <= 1) {
				if (pagination === 'load_more' && $btn) {
					$widget.find('.premium-blog-widget__pagination-ajax').fadeOut(400);
				} else if (pagination === 'infinite' && $trigger) {
					if (observer) {
						observer.disconnect();
					}
					$widget.find('.premium-blog-widget__infinite-trigger').fadeOut(400);
				}
			} else {
				if (pagination === 'load_more' && $btn) {
					$widget.find('.premium-blog-widget__pagination-ajax').fadeIn(400);
				} else if (pagination === 'infinite' && $trigger) {
					$widget.find('.premium-blog-widget__infinite-trigger').fadeIn(400);
					if (observer && $trigger.length) {
						observer.disconnect();
						observer.observe($trigger[0]);
					}
				}
			}
		}
	}

	// Document Ready hook (for frontend post-render)
	$(document).ready(function() {
		$('.premium-blog-widget').each(function() {
			initWidget(this);
		});
	});

	// Elementor live preview editor hook
	$(window).on('elementor/frontend/init', function() {
		if (typeof elementorFrontend === 'undefined') {
			return;
		}

		elementorFrontend.hooks.addAction('frontend/element_ready/wp_multi_post_type_blog_widget.default', function($scope) {
			var $widgetContainer = $scope.find('.premium-blog-widget');
			if ($widgetContainer.length) {
				initWidget($widgetContainer[0]);
			}
		});

		elementorFrontend.hooks.addAction('frontend/element_ready/wp_multi_post_type_archive_widget.default', function($scope) {
			var $widgetContainer = $scope.find('.premium-blog-widget');
			if ($widgetContainer.length) {
				initWidget($widgetContainer[0]);
			}
		});
	});

})(jQuery);
