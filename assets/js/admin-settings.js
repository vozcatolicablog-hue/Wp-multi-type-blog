(function($) {
	'use strict';

	var strings = window.wpMultipostBlogAdmin || {};

	/**
	 * Open the media library and store the chosen attachment in the row.
	 */
	$(document).on('click', '.wpmb-select-image', function(e) {
		e.preventDefault();

		if (typeof wp === 'undefined' || !wp.media) {
			return;
		}

		var $field = $(this).closest('.wpmb-image-field');

		// A fresh frame per click keeps each row's selection independent.
		var frame = wp.media({
			title: strings.frameTitle || 'Seleccionar imagen de respaldo',
			button: { text: strings.frameButton || 'Usar esta imagen' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			var previewUrl = attachment.url;

			if (attachment.sizes && attachment.sizes.thumbnail) {
				previewUrl = attachment.sizes.thumbnail.url;
			}

			$field.find('.wpmb-image-id').val(attachment.id);
			$field.find('.wpmb-image-preview').attr('src', previewUrl).show();
			$field.find('.wpmb-remove-image').show();
		});

		frame.open();
	});

	/**
	 * Clear the fallback image for a row.
	 */
	$(document).on('click', '.wpmb-remove-image', function(e) {
		e.preventDefault();

		var $field = $(this).closest('.wpmb-image-field');

		$field.find('.wpmb-image-id').val('');
		$field.find('.wpmb-image-preview').attr('src', '').hide();
		$(this).hide();
	});

})(jQuery);
