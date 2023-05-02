(function ($) {

	const Paljet = {

		/**
		 * Start the engine.
		 *
		 * @since 2.0.0
		 */
		init: function () {

			// Document ready
			$(document).ready(Paljet.ready);

			// Page load
			$(window).on('load', Paljet.load);
		},

		/**
		 * Page load.
		 *
		 * @since 2.0.0
		 */
		load: function () {
			Paljet.executeUIActions();
		},

		/**
		 * Document ready.
		 *
		 * @since 2.0.0
		 */
		ready: function () {
			// Bind all actions.
			Paljet.bindUIActions();
		},

		/**
		 * Execute when the page is loaded
		 * @return mixed
		 */
		executeUIActions: function() {
			
		},

		/**
		 * Element bindings.
		 *
		 * @since 2.0.0
		 */
		bindUIActions: function () {

			// Sync Prices
			$(document.body).on( 'click', '.paljet_sync_images', function(e) {
				e.preventDefault();

				const product_id = $(this).data('product');

				if( paljet_vars.screen == 'detail' )
					Paljet.sync_images_from_detail( product_id );
				else
					Paljet.sync_images_from_list( product_id );
			} );
		},
		/**
		 * [sync_images_from_list description]
		 * @param  {[type]} product_id [description]
		 * @return {[type]}            [description]
		 */
		sync_images_from_list: function( product_id = 0 ) {

			const $sync_images = $( '.paljet_sync_images[data-product="' + product_id + '"]' );

			// Replace sync image icon by loading icon
			$sync_images.replaceWith( '<span class="loading_paljet_sync_images">' + paljet_vars.img_loading + '</span>' );

			// Ajax
			$.ajax({
				url : paljet_vars.url_ajax,
				dataType: 'json',
				type: 'POST',
				data: {
					action: 'paljet_sync_images',
					product_id: product_id,
					wpnonce : paljet_vars.nonce
				},
				success: function (response) {

					// if success
					if( response.success ) {

						// Message successfully
						$( '.loading_paljet_sync_images' ).replaceWith( '<span class="success_paljet_sync_images">' + paljet_vars.img_success + '</span>' );
						$( 'tr#post-' + product_id + ' td.column-thumb' ).html(response.data);

					} else {

						// Message error
						$( '.loading_paljet_sync_images' ).replaceWith( '<span class="failure_paljet_sync_images">' + paljet_vars.img_failure + '</span>' );

					}
				}
			});
		},

		sync_images_from_detail: function( product_id = 0 ) {
			// Add loading icon
			$('.paljet_sync_images').after( '<span class="loading_paljet_sync_images">' + paljet_vars.img_loading + '</span>' );

			// Ajax
			$.ajax({
				url : paljet_vars.url_ajax,
				dataType: 'json',
				type: 'POST',
				data: {
					action: 'paljet_sync_images',
					product_id: product_id,
					wpnonce : paljet_vars.nonce
				},
				success: function (response) {

					$( '.loading_paljet_sync_images' ).remove();

					// if success
					if( response.success ) {

						// Message successfully
						$( '.paljet_sync_images' ).after( '<span class="success_paljet_sync_images">' + paljet_vars.img_success + '</span>' );

						setTimeout(function(){ location.reload(); }, 1000);

					} else {

						// Message error
						$( '.paljet_sync_images' ).after( '<span class="failure_paljet_sync_images">' + paljet_vars.img_failure + '</span>' );

					}
				}
			});
		}
	};

	Paljet.init();
	// Add to global scope.
	window.paljet = Paljet;
})(jQuery);
