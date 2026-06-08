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
		var $btn, $trigger; // 2.2: Declared in outer scope to be safely available in inner scopes

		// 1. AJAX Load More Button Mode
		if (pagination === 'load_more') {
			$btn = $widget.find('.wp-multipost-blog-load-more-btn');
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
			$trigger = $widget.find('.premium-blog-widget__infinite-trigger');
			if ($trigger.length && 'IntersectionObserver' in window) {
				observer = new IntersectionObserver(function(entries) {
					if (entries[0].isIntersecting) {
						if (!isLoading && currentPage < maxPages) {
							loadMorePosts(false);
						}
					}
				}, { 
					rootMargin: '0px 0px 150px 0px', // 7.2: Adjusted to be less aggressive and reduce duplicate requests
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
			
			// 7.3: Fade out numerical pagination when filter tab is clicked
			if (pagination === 'numbers') {
				$widget.find('.numbers-pagination').fadeOut(200);
			}

			// Hide any existing "No more posts" message when changing filters
			$widget.find('.premium-blog-widget__no-more').hide();
			
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

			if (pagination === 'load_more' && $btn && $btn.length) {
				$btn.addClass('is-loading');
				$widget.find('.premium-blog-widget__pagination-ajax').show();
			} else if (pagination === 'infinite' && $trigger && $trigger.length) {
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
							
							// 5.4: Hardware-accelerated CSS Transitions instead of expensive jQuery animation step callbacks
							$newElements.css({ 
								opacity: 0, 
								transform: 'translateY(15px)',
								transition: 'opacity 500ms ease, transform 500ms ease'
							});
							$list.append($newElements);
							
							// Staggered sequential entry animation
							$newElements.each(function(index, el) {
								setTimeout(function() {
									$(el).css({
										opacity: 1,
										transform: 'translateY(0)'
									});
								}, index * 120);
							});

							currentPage = nextPage;
							$widget.attr('data-current-page', currentPage);
						} else if (isReset) {
							// 3.3: Use jQuery .text() to prevent potential XSS injection of dynamic server strings
							var noPostsText = settings.no_posts_text || wpMultipostBlogAjax.no_posts_text || 'No se encontraron publicaciones.';
							var $noPostsDiv = $('<div>').addClass('premium-blog-no-posts').text(noPostsText);
							$list.html($noPostsDiv);
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
					if (pagination === 'load_more' && $btn && $btn.length) {
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
				if (pagination === 'load_more' && $btn && $btn.length) {
					$widget.find('.premium-blog-widget__pagination-ajax').fadeOut(400);
					if (maxPages > 1) {
						$widget.find('.premium-blog-widget__no-more').fadeIn(400); // 7.1: Show end message
					}
				} else if (pagination === 'infinite' && $trigger && $trigger.length) {
					if (observer) {
						observer.disconnect();
					}
					$widget.find('.premium-blog-widget__infinite-trigger').fadeOut(400);
					if (maxPages > 1) {
						$widget.find('.premium-blog-widget__no-more').fadeIn(400); // 7.1: Show end message
					}
				}
			} else {
				$widget.find('.premium-blog-widget__no-more').hide();

				if (pagination === 'load_more' && $btn && $btn.length) {
					$widget.find('.premium-blog-widget__pagination-ajax').fadeIn(400);
				} else if (pagination === 'infinite' && $trigger && $trigger.length) {
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
