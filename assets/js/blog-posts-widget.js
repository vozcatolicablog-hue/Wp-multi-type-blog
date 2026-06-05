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
				loadMorePosts();
			});
		}

		// 2. AJAX Infinite Scroll Mode
		if (pagination === 'infinite') {
			var $trigger = $widget.find('.premium-blog-widget__infinite-trigger');
			if ($trigger.length && 'IntersectionObserver' in window) {
				observer = new IntersectionObserver(function(entries) {
					if (entries[0].isIntersecting) {
						if (!isLoading && currentPage < maxPages) {
							loadMorePosts();
						}
					}
				}, { 
					rootMargin: '100px 0px 300px 0px',
					threshold: 0.1 
				});
				observer.observe($trigger[0]);
			}
		}

		/**
		 * Triggers the AJAX call to load the next page of posts.
		 */
		function loadMorePosts() {
			isLoading = true;
			if (pagination === 'load_more' && $btn) {
				$btn.addClass('is-loading');
			}

			$.ajax({
				url: wpMultipostBlogAjax.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'wp_multiblog_load_more',
					nonce: wpMultipostBlogAjax.nonce,
					page: currentPage + 1,
					signature: settingsSignature,
					settings: settings
				},
				success: function(response) {
					if (response.success && response.data && response.data.html) {
						var $newElements = $(response.data.html);
						var responseMaxPages = parseInt(response.data.max_pages, 10);

						if (responseMaxPages) {
							maxPages = responseMaxPages;
						}
						
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
										// Map opacity [0-1] to Y translate [15px-0px]
										$(el).css('transform', 'translateY(' + (15 - now * 15) + 'px)');
									}
								}
							});
						});

						currentPage++;
						$widget.attr('data-current-page', currentPage);

						// Handle page limit checks
						if (currentPage >= maxPages) {
							removeLoader();
						}
					} else {
						removeLoader();
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX request failed loading posts:', error);
					removeLoader();
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
		 * Safely tear down and remove loaders/triggers.
		 */
		function removeLoader() {
			if (pagination === 'load_more' && $btn) {
				$btn.fadeOut(400, function() {
					$btn.parent().remove();
				});
			} else if (pagination === 'infinite' && $trigger) {
				if (observer) {
					observer.disconnect();
				}
				$trigger.fadeOut(400, function() {
					$trigger.remove();
				});
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
	});

})(jQuery);
