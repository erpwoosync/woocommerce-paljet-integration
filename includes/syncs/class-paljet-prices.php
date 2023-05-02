<?php
/**
 * Prices Class
 * @since  1.0.0
 * @package Public / Prices
 */
class Paljet_Prices {

	/**
	 * Sync Static Method
	 * @return [type] [description]
	 */
	public static function sync() {
		global $PaljetApi;

		Paljet_Logs::add( 'notice', esc_html__( 'Start prices lists sync', 'paljet' ) );

		Paljet_Logs::add( 'notice', esc_html__( 'Getting prices lists from the Paljet API', 'paljet' ) );

		// Get Brands from ERP Paljet
		try {
			$params = apply_filters( 'paljet/prices/params', [ 'size' => 100 ] );
			$request = $PaljetApi->Prices->get( $params );
		} catch (Exception $e) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}

		
		// Check if there is prices lists
		if( ! isset( $request->_embedded->listaPrecioResources ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'There is not prices lists', 'paljet' ) );
			return false;
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( '%s prices lists ready to import', 'paljet' ), $request->page->totalElements
		) );

		// Initialize bucle
		$prices = [];
		$body = $request->_embedded->listaPrecioResources;
		
		foreach( $body as $item ) {
				
			if( ! $item->activa )
				continue;
			
			$prices[ $item->listaId ] = $item->nombre;
		}

		// Save prices lists
		update_option( 'paljet_prices', $prices );
		
		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Synced prices lists : %s', 'paljet' ),
			count( $prices )
		) );
		

		Paljet_Logs::add( 'notice', esc_html__( 'End prices lists sync', 'paljet' ) );

		return true;
	}
}