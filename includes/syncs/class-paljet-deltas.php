<?php
/**
 * Delta Class
 * @since  1.0.0
 * @package Public / Deltas
 */
class Paljet_Deltas {

	/**
	 * [process_stock description]
	 * @return [type] [description]
	 */
	public static function process_stock() {
		global $PaljetApi;

		// Start transient
		if( get_transient( 'paljet_ajax_sync' ) )
			return;

		Paljet_Logs::add( 'notice', esc_html__( 'Delta Stock : Start', 'paljet' ) );

		// Get settings
		$settings = get_option( 'paljet_settings', [] );

		if( ! isset( $settings['warehouses'] ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'Warehouses are not defined on the settings', 'paljet' ) );
		}

		// 20 minutes ago
		$delta_time = strtotime( current_time( 'mysql' ) ) - 1200;

		// Get Stock from ERP Paljet
		try {
			$params = apply_filters( 'paljet/delta_stock/params', [
				'desde'		=> date('Y-m-d\TH:i:s.v', $delta_time ),
				'dep_ids'	=> implode( ',', array_map( 'trim', $settings['warehouses'] ) ),
			] );

			$format = apply_filters( 'paljet/delta_stock/format', [
				'desde'		=> '%s',
				'dep_ids'	=> '%s',
			] );

			$request = $PaljetApi->Deltas->get_stocks( $params, $format );
		} catch( Exception $e ) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}

		
		// Check if there is products from the Paljet API
		if( ! $request || ! isset( $request->news_updates ) || empty( $request->news_updates ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'Delta Stock : Finish ( There is not deltas stock )', 'paljet' ) );
			return false;
		}

		$processed = 0;
		
		// Pending Products
		Paljet_Products::$PendingProducts = Paljet_Products::get_pending_products();

		foreach( $request->news_updates as $item ) {

			if( ! isset( $item->articulo->art_id ) )
				continue;

			Paljet_Products::save_pending_products( $item->articulo->art_id, '' );
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Delta Stock : Finish ( processed : %d )', 'paljet' ),
			$processed
		) );
	}


	/**
	 * [process_price description]
	 * @return [type] [description]
	 */
	public static function process_price() {
		global $PaljetApi;

		// Start transient
		if( get_transient( 'paljet_ajax_sync' ) )
			return;

		Paljet_Logs::add( 'notice', esc_html__( 'Delta Price : Start', 'paljet' ) );

		// Get settings
		$settings = get_option( 'paljet_settings', [] );

		if( ! isset( $settings['price_regular'] ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'Regular price are not defined on the settings', 'paljet' ) );
		}

		$prices_lists = [];
		$prices_lists[] = $settings['price_regular'];
		
		if( isset( $settings['price_sale'] ) && $settings['price_sale'] != '' )
			$prices_lists[] = $settings['price_sale'];

		// 20 minutes ago
		$delta_time = strtotime( current_time( 'mysql' ) ) - 1200;

		// Get Prices Lists from ERP Paljet
		try {
			$params = apply_filters( 'paljet/delta_price/params', [
				'desde'		=> date('Y-m-d\TH:i:s.v', $delta_time ),
				'lista_ids'	=> implode( ',', array_map( 'trim', $prices_lists ) ),
			] );

			$format = apply_filters( 'paljet/delta_price/format', [
				'desde'		=> '%s',
				'lista_ids'	=> '%s',
			] );

			$request = $PaljetApi->Deltas->get_prices( $params, $format );
		} catch( Exception $e ) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}


		// Check if there is products from the Paljet API
		if( ! $request || ! isset( $request->news_updates ) || empty( $request->news_updates ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'Delta Prices : Finish ( There is not deltas prices )', 'paljet' ) );
			return false;
		}

		$processed = 0;
		
		// Pending Products
		Paljet_Products::$PendingProducts = Paljet_Products::get_pending_products();

		foreach( $request->news_updates as $item ) {

			if( ! isset( $item->art_id ) )
				continue;

			Paljet_Products::save_pending_products( $item->art_id, '' );

			$processed++;
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Delta Prices : Finish ( processed : %d )', 'paljet' ),
			$processed
		) );
	}


	/**
	 * [process_products description]
	 * @return [type] [description]
	 */
	public static function process_products() {
		global $PaljetApi;

		// Start transient
		if( get_transient( 'paljet_ajax_sync' ) )
			return;

		Paljet_Logs::add( 'notice', esc_html__( 'Delta Products : Start', 'paljet' ) );

		// Get settings
		$settings = get_option( 'paljet_settings', [] );

		if( ! isset( $settings['price_sale'] ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'Sale price are not defined on the settings', 'paljet' ) );
		}

		// 20 minutes ago
		$delta_time = strtotime( current_time( 'mysql' ) ) - 1200;

		// Get Prices Lists from ERP Paljet
		try {
			$params = apply_filters( 'paljet/delta_products/params', [
				'desde'		=> date('Y-m-d\TH:i:s.v', $delta_time ),
			] );

			$format = apply_filters( 'paljet/delta_products/format', [
				'desde'		=> '%s',
			] );

			$request = $PaljetApi->Deltas->get_products( $params, $format );

		} catch( Exception $e ) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}

		// There is not new updates
		if( ! $request || ! isset( $request->news_updates ) || empty( $request->news_updates ) ) {
			Paljet_Logs::add( 'notice', esc_html__( 'Delta Products : Finish ( There is not deltas products )', 'paljet' ) );
			return false;
		}

		$processed = 0;
		
		// Pending Products
		Paljet_Products::$PendingProducts = Paljet_Products::get_pending_products();

		// loop new updates
		foreach( $request->news_updates as $item ) {

			if( ! isset( $item->id ) )
				continue;

			Paljet_Products::save_pending_products( $item->id, '' );
			$processed++;
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Delta Products : Finish ( processed : %d )', 'paljet' ),
			$processed
		) );

		return true;
	}
}
