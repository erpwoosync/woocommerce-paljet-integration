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
			$(document.body).on( 'click', '.paljet_sync_prices', function(e) {
				e.preventDefault();

				Paljet.sync_by_link('prices');
			} );

			// Sync Warehouses
			$(document.body).on( 'click', '.paljet_sync_warehouses', function(e) {
				e.preventDefault();

				Paljet.sync_by_link('warehouses');
			} );
			

			// Sync Button
			$(document.body).on( 'click', '.paljet_sync_button', function(e) {
				e.preventDefault();

				const type = $(this).data('type');
				$('.paljet_sync_button').attr('disabled', 'disabled');

				if( type == 'products' ) {
					Paljet.sync_products();
				} else {
					Paljet.sync_button( type );
				}

			} );


			// Delete Logs button
			$(document.body).on('click', '.paljet_delete_logs', function(e) {
				e.preventDefault();
				
				$('.paljet_delete_logs').attr('disabled', 'disabled');

				Paljet.delete_logs_button();
			} );


			// Add attributes
			$(document.body).on('click', '.attr-add', function(e) {
				e.preventDefault();

				const $old_attr = $(this).prev('.attr-group');
				const $new_attr = $old_attr.clone();
				const new_id = parseInt( $old_attr.data('id'), 10 ) + 1;

				$new_attr.attr('data-id', new_id);
				$new_attr.find('input[type="text"]').attr('name', 'paljet_settings[attr][' + new_id + '][slug]').val('');
				$new_attr.find('select').attr('name', 'paljet_settings[attr][' + new_id + '][wc]').find("option:selected").removeAttr("selected");
				$new_attr.find('.select2-container').remove();
				
				$new_attr.insertAfter($old_attr);

				$new_attr.find('select.wc-enhanced-select-nostd').selectWoo( { allowClear:true } ).addClass( 'enhanced' );
			});


			// Remove attributes
			$(document.body).on('click', '.attr-remove', function(e) {
				e.preventDefault();
				$(this).parent('.attr-group').remove();
			});
		},


		sync_by_link: function( sync_type ) {

			const $box = $( '.box-' + sync_type ),
				$link = $( 'a[data-type="' + sync_type + '"]' ),
				$message = $( '.message_paljet_sync_' + sync_type ),
				$iselect = $( '.select-' + sync_type );

			// Hide the link
			$link.hide();

			// Loading icon
			$message.html( paljet_vars.img_loading + ' ' + paljet_vars.sync_loading );

			// Remove select2 from the select
			$box.find('.select2-container').remove();
			$iselect.removeClass('select2-hidden-accessible enhanced');

			// Disable the select input
			$iselect.attr('disabled', 'disabled');

			// select the firt option
			$iselect.val('');


			$.ajax({
				url : paljet_vars.url_ajax,
				dataType: 'json',
				type: 'POST',
				data: { action: 'paljet_sync_' + sync_type, wpnonce : paljet_vars.nonce },
				success: function (response) {

					// Show the link
					$link.show();

					if( response.success ) {

						// Message successfully
						$message.html(paljet_vars.img_success + ' ' + paljet_vars.sync_success);

						// Empty the select input
						$iselect.find('option:not(:first)').remove();

						// Add new messages
						$.each(response.data, function(val, text) {
							$iselect.append( new Option(text, val) );
						});

					} else
						$message.html(paljet_vars.img_failure + ' ' + paljet_vars.sync_failure);


					// Disable the select input
					$iselect.removeAttr('disabled');

					// Add select2()
					$iselect.selectWoo( { allowClear: true } ).addClass( 'enhanced' );
				}
			});
		},



		sync_button: function( type ) {

			const action = 'paljet_sync_' + type,
				$message = $('#message_paljet_sync_' + type);

			$message.html( paljet_vars.img_loading + ' ' + paljet_vars.sync_loading );

			$.ajax({
				url : paljet_vars.url_ajax,
				dataType: 'json',
				type: 'POST',
				data: { action: action, wpnonce : paljet_vars.nonce },
				success: function (response) {

					$('.paljet_sync_button').removeAttr('disabled');

					if( response.success )
						$message.html(paljet_vars.img_success + ' ' + paljet_vars.sync_success);
					else
						$message.html(paljet_vars.img_failure + ' ' + paljet_vars.sync_failure);
				}
			});
		},

		sync_products: function() {
			const action = 'paljet_total_products',
				$message = $('#message_paljet_sync_products');

			$message.html( '<div class="page_totals">' + paljet_vars.img_loading + ' ' + paljet_vars.sync_loading + '</div>' );

			$.ajax({
				url : paljet_vars.url_ajax,
				dataType: 'json',
				type: 'POST',
				data: { action: action, wpnonce : paljet_vars.nonce },
				success: function (response) {

					if( response.success ) {
						$message.find('.page_totals').html( paljet_vars.img_success + ' ' + paljet_vars.total_products + response.data.pages_total );
						Paljet.load_products( 0, parseInt( response.data.pages_total, 10 ) );
					} else {
						$('.paljet_sync_button').removeAttr('disabled');
						$message.find('.page_totals').html( paljet_vars.img_failure + ' ' + paljet_vars.sync_failure );
					}
				}
			});
		},

		load_products: function( page = 0, max ) {

			if( page >= max ) {
				$('.paljet_sync_button').removeAttr('disabled');
				return false;
			}

			const action = 'paljet_sync_products',
				$message = $('#message_paljet_sync_products');

			$message.append( '<div class="page_' + page + '">' + paljet_vars.img_loading + ' ' + paljet_vars.sync_loading + '</div>' );

			$.ajax({
				url : paljet_vars.url_ajax,
				dataType: 'json',
				type: 'POST',
				data: { action: action, page: page, wpnonce : paljet_vars.nonce },
				success: function (response) {

					if( response.success ) {
						$message.find('.page_' + page).html( paljet_vars.img_success + ' ' + paljet_vars.page_loaded + page );
						Paljet.load_products( ++page, max );
					} else {
						$('.paljet_sync_button').removeAttr('disabled');
						$message.find('.page_' + page).html( paljet_vars.img_failure + ' ' + paljet_vars.sync_failure );
					}
				}
			});
		},

		delete_logs_button: function() {
			const action = 'paljet_delete_logs',
				$message = $('#message_paljet_delete_logs');

			$message.html(paljet_vars.img_loading + ' ' + paljet_vars.logs_loading);

			$.ajax({
				url : paljet_vars.url_ajax,
				dataType: 'json',
				type: 'POST',
				data: { action: action, wpnonce : paljet_vars.nonce },
				success: function (response) {

					$('.paljet_delete_logs').removeAttr('disabled');

					if( response.success == true ) {
						$('textarea[data-input="paljet_log"]').val('');
						$message.html(paljet_vars.img_success + ' ' + paljet_vars.logs_success);
					} else
						$message.html(paljet_vars.img_failure + ' ' + paljet_vars.logs_failure);
				}
			});
		}

	};

	Paljet.init();
	// Add to global scope.
	window.paljet = Paljet;
})(jQuery);
