<?php
/**
 * Categories Class
 * @since  1.0.0
 * @package Public / Categories
 */
class Paljet_Categories {

	public static $slug_taxonomy = 'product_cat';
	public static $counter = 0;

	/**
	 * Sync Static Method
	 * @return [type] [description]
	 */
	public static function sync() {
		global $PaljetApi;

		Paljet_Logs::add( 'notice', esc_html__( 'Start categories sync', 'paljet' ) );

		// Check if the Woocommerce Category Taxonomy exists
		if ( ! taxonomy_exists( self::$slug_taxonomy ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'Woocommerce Category Taxonomy doesnt exists', 'paljet' ) );

			return false;
		}

		Paljet_Logs::add( 'notice', esc_html__( 'Getting categories from the Paljet API', 'paljet' ) );

		// Get Categories from ERP Paljet
		try {
			$request = $PaljetApi->Categories->get();
		} catch (Exception $e) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}


		// Check if there is categories from the Paljet API
		if( ! isset( $request[0] ) || $request[0]->cantHijos == 0 ) {
			Paljet_Logs::add( 'error', esc_html__( 'There is not categories', 'paljet' ) );
			return false;
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Categories ready to import', 'paljet' ), count( $request )
		) );

		$body = $request[0]->hijos;

		// Upload Categories
		if( ! self::upload( $body ) )
			return false;

		
		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Synced categories : %s', 'paljet' ),
			self::$counter
		) );
		

		Paljet_Logs::add( 'notice', esc_html__( 'End categories sync', 'paljet' ) );

		return true;
	}

	
	/**
	 * Upload Category
	 * @param  ARRAY  $body      Category data
	 * @param  iNT $parent_id [description]
	 * @return bool
	 */
	public static function upload( $body = [], $parent_id = 0 ) {

		foreach( $body as $item ) {

			$slug_term = sanitize_title( $item->nombre );
			$name_term = wc_sanitize_term_text_based( $item->nombre );

			// Search if the category exists
			$term_id = paljet_get_term_id_from_meta( 'paljet_cat_id', $item->art_cat_id );

			// If it is not a new category on the WooCommerce
			if( $term_id != FALSE ) {

				$data_term = wp_update_term(
					$term_id,
					self::$slug_taxonomy,
					[ 'name' => $name_term ]
				);


				// If it has an error
				if( is_wp_error( $data_term ) ) {
					Paljet_Logs::add( 'notice', sprintf(
						esc_html__('Updating the %s category ( ID: %s )', 'paljet'),
						strtoupper( $name_term ), $term_id
					) );

					Paljet_Logs::add( 'error', $data_term->get_error_message() );
					return false;
				}
			
			} else {

				$data_term = term_exists( $name_term, self::$slug_taxonomy, $parent_id );

				if( 0 == $data_term || null == $data_term ) {
					
					// If the category has parent
					$extra = [ 'parent' => $parent_id ];

					$data_term = wp_insert_term(
						$name_term,
						self::$slug_taxonomy,
						$extra
					);


					// If it has an error
					if( is_wp_error( $data_term ) ) {
						Paljet_Logs::add( 'notice', sprintf(
							esc_html__('Inserting the %s category', 'paljet'),
							strtoupper( $name_term )
						) );

						Paljet_Logs::add( 'error', $data_term->get_error_message() );
						return false;
					}
				}

				//Paljet_Products::process_pending_attrs(
				//	$item->art_cat_id, $data_term['term_id'], 'categories'
				//);
			}

			self::$counter++;

			// save meta
			update_term_meta( $data_term['term_id'], 'paljet_cat_id', $item->art_cat_id );

			// If it has children
			if( ! empty( $item->cantHijos ) && $item->cantHijos > 0 ) {
				if( ! self::upload( $item->hijos, $data_term['term_id'] ) )
					return false;
			}
		}

		return true;
	}
}