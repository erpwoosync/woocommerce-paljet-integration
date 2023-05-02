<?php
/**
 * Warehouse Class
 * @since  1.0.0
 * @package Public / Categories
 */
class Paljet_Warehouses {

	public static $success_counter = 0;

	/**
	 * Sync Static Method
	 * @return [type] [description]
	 */
	public static function sync() {
		global $PaljetApi;

		Paljet_Logs::add( 'notice', esc_html__( 'Start warehouses sync', 'paljet' ) );


		Paljet_Logs::add( 'notice', esc_html__( 'Getting warehouses from the Paljet API', 'paljet' ) );

		// Get Families from ERP Paljet
		try {
			$request = $PaljetApi->Warehouses->get();
		} catch (Exception $e) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}


		// Check if there is categories from the Paljet API
		if( ! $request ) {
			Paljet_Logs::add( 'error', esc_html__( 'There is not warehouses', 'paljet' ) );
			return false;
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( '%s warehouses ready to import', 'paljet' ), count( $request )
		) );

		$warehouses = [];
		
		foreach( $request as $item ) {

			if( ! isset( $item->idDeposito ) || $item->activo == 'N' )
				continue;

			$warehouses[ $item->idDeposito ] = $item->nombre;
		}

		update_option( 'paljet_warehouses', $warehouses );

		
		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Synced warehouses : %s', 'paljet' ),
			count( $warehouses )
		) );
		

		Paljet_Logs::add( 'notice', esc_html__( 'End warehouses sync', 'paljet' ) );

		return true;
	}
}