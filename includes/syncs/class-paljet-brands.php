<?php
/**
 * Brands Class
 * @since  1.0.0
 * @package Public / Brands
 */
class Paljet_Brands {

	public static $success_counter = 0;

	/**
	 * Sync Static Method
	 * @return [type] [description]
	 */
	public static function sync() {
		global $PaljetApi;

		Paljet_Logs::add( 'notice', esc_html__( 'Start brands sync', 'paljet' ) );

		// Get settings
		$settings = get_option( 'paljet_settings', [] );

		// add pa_{$attr}
		$slug_brands = wc_attribute_taxonomy_name( $settings['attribute_brand'] );

		// Check if the Woocommerce Brand Taxonomy exists
		if ( ! taxonomy_exists( $slug_brands ) ) {
			Paljet_Logs::add( 'error', sprintf(
				esc_html__( 'The "%s" attribute doesnt exists', 'paljet' ), $slug_brands
			) );
			return false;
		}

		Paljet_Logs::add( 'notice', esc_html__( 'Getting brands from the Paljet API', 'paljet' ) );

		// Get Brands from ERP Paljet
		try {
			$request = $PaljetApi->Brands->get();
		} catch (Exception $e) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}

		
		// Check if there is brands
		if( ! isset( $request ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'There is not brands', 'paljet' ) );
			return false;
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( '%s brands ready to import', 'paljet' ), count( $request )
		) );

		$uploaded = [];

		foreach( $request as $item ) {

			if( ! isset( $item->links[0]->href ) )
				continue;

			// Same name
			if( in_array( sanitize_title( $item->nombre ), $uploaded ) )
				continue;
			
			if( ! self::upload( $item, $slug_brands ) )
				return false;

			$uploaded[] = sanitize_title( $item->nombre );
		}

		
		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Synced brands : %s', 'paljet' ),
			self::$success_counter
		) );
		

		Paljet_Logs::add( 'notice', esc_html__( 'End brands sync', 'paljet' ) );

		return true;
	}


	public static function upload( $item, $slug_brands ) {
		$slug_term = sanitize_title( $item->nombre );
		$name_term = wc_sanitize_term_text_based( $item->nombre );

		// Paljet ID Brand
		$paljet_brand_id = paljet_links_get_id( $item->links[0]->href );

		// Search if the category exists
		$term_id = paljet_get_term_id_from_meta( 'paljet_brand_id', $paljet_brand_id );

		// Check if brand exists
		if( $term_id != FALSE ) {
			$data_term = wp_update_term( $term_id, $slug_brands, [ 'name' => $name_term ] );

			// If it has an error
			if( is_wp_error( $data_term ) ) {
				Paljet_Logs::add( 'notice', sprintf(
					esc_html__('Updating the brand %s ( ID: %s )', 'paljet'),
					strtoupper( $name_term ), $term_id
				) );

				Paljet_Logs::add( 'error', $data_term->get_error_message() );
				return false;
			}
	
		} else {

			$data_term = term_exists( $name_term, $slug_brands );

			if( 0 == $data_term || null == $data_term ) {
				
				$data_term = wp_insert_term( $name_term, $slug_brands );

				// If it has an error
				if( is_wp_error( $data_term ) ) {
					Paljet_Logs::add( 'notice', sprintf(
						esc_html__('Inserting the brand %s', 'paljet'),
						strtoupper( $name_term )
					) );

					Paljet_Logs::add( 'error', $data_term->get_error_message() );
					return false;
				}
			}

			
			//Palejt_Product::process_pending_attrs(
			//	$paljet_brand_id, $data_term['term_id'], 'brands'
			//);

		}

		self::$success_counter++;

		// save meta
		update_term_meta( $data_term['term_id'], 'paljet_brand_id', $paljet_brand_id );

		return true;
	}



	/**
	 * [create description]
	 * @param  [type] $item [description]
	 * @return [type]       [description]
	 */
	public static function create( $item ) {

		// Get settings
		$settings = get_option( 'paljet_settings', [] );

		// add pa_{$attr}
		$slug_brands = wc_attribute_taxonomy_name( $settings['attribute_brand'] );

		// brand name
		$slug_term = sanitize_title( $item->marca->nombre );
		$name_term = wc_sanitize_term_text_based( $item->marca->nombre );
		$paljet_brand_id = intval( $item->marca->id );


		$data_term = term_exists( $name_term, $slug_brands );

		if( 0 == $data_term || null == $data_term ) {
			
			$data_term = wp_insert_term( $name_term, $slug_brands );

			// If it has an error
			if( is_wp_error( $data_term ) ) {
				Paljet_Logs::add( 'notice', sprintf(
					esc_html__('Creating the brand %s', 'paljet'),
					strtoupper( $name_term )
				) );

				Paljet_Logs::add( 'error', $data_term->get_error_message() );
				return false;
			}
		}

		update_term_meta( $data_term['term_id'], 'paljet_brand_id', $paljet_brand_id );

		return true;
	}
}