<?php
/**
 * Order Class
 * @since  1.0.0
 * @package Public / Orders
 */
class Paljet_Orders {

	/**
	 * Sync Static Method
	 * @return number $page
	 */
	public static function sync() {

	}

	
	/**
	 * Send Order to Paljet
	 * @param  object $order
	 * @return mixed
	 */
	public static function send( $order, $data = [] ) {
		global $PaljetApi;

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Send Order : Start ( WC Order %d )', 'paljet' ),
			$order->get_id()
		) );

		// Get settings
		$settings = get_option( 'paljet_settings', [] );


		if( ! isset( $settings['enable_orders'] ) || empty( $settings['enable_orders'] ) ) {

			$error = esc_html__( 'Send Order : You have to fill the information in the orders tab on the Paljet settings', 'paljet' );

			Paljet_Logs::add( 'error', $error );
			return [ 'status' => 'error', 'response' => $error ];
		}


		$args_order = [
			'anulado'			=> false,
			'fecha_emision'		=> date( 'Y-m-d\TH:i:s.v' ),
			'fecha_hora_alta'	=> date( 'Y-m-d\TH:i:s.v' ),
			'pto_vta'			=> absint( $settings['sale_id'] ),
			'numero'			=> $order->get_id(),
			'tal_id'			=> absint( $settings['ticket_id'] ),
			'cpr_clasif_id'		=> absint( $settings['classification_id'] ),
			'cli_id'			=> absint( $settings['client_id'] ),
			'cuit'				=> strval( $settings['client_cuit'] ),
			'localidad_id'		=> 0,
			'razon_social'		=> $order->get_formatted_billing_full_name(),
			'email'				=> $order->get_billing_email(),
			'domicilio'			=> $order->get_billing_address_1(),
			'telefono'			=> $order->get_billing_phone(),
			'nota'				=> $order->get_customer_note(),
		];


		// Discounts
		$discount = 0;
		foreach( $order->get_items('coupon') as $item )
			$discount += $item->get_discount();

		// Calculate the discount percent
		$pct_discount = $discount > 0 ? (-1) * round( ( $discount*100 )/$order->get_subtotal(), 2 ) : 0;

		// Products
		$total_notax = 0;
		$args_details = [];
		foreach( $order->get_items() as $item ) {

			$product = $item->get_product();

			// Paljet ID
			if( $product->is_type( 'variation' ) )
				$paljet_id = get_post_meta( $product->get_id(), 'paljet_variation_id', true );
			else
				$paljet_id = get_post_meta( $product->get_id(), 'paljet_product_id', true );
			
			// Paljet Code
			$paljet_code 	= get_post_meta( $product->get_id(), 'paljet_code', true );

			// Paljet Price No Tax
			$paljet_price_notax	= get_post_meta( $product->get_id(), 'paljet_price_notax', true );

			// List Price
			if( $product->is_on_sale() )
				$price_id = $settings['price_sale'];
			else
				$price_id = $settings['price_regular'];

			
			// Price without tax
			$pr_vta = ! empty( $paljet_price_notax ) ? $paljet_price_notax : $product->get_price();

			$args_details[] = apply_filters('paljet/orders/send/detail', [
				'art_id'		=> $paljet_id,
				'cod_articulo'	=> $paljet_code,
				'cant'			=> $item->get_quantity(),
				'pr_vta'		=> $pr_vta,
				'pr_final'		=> $product->get_price(),
				'porc_dto_rgo'	=> $pct_discount,
				'lista_id'		=> $price_id,
			], $item, $order );

			// Total without tax
			$total_notax += round( $item->get_quantity() * $pr_vta, 4 );
		}


		// Shipping
		foreach( $order->get_items('shipping') as $item ) {

			if( $item->get_total() == 0 )
				continue;

			$args_details[] = [
				'art_id'		=> $settings['shipping_id'],
				'cod_articulo'	=> $settings['shipping_code'],
				'cant'			=> 1,
				'pr_vta'		=> round( $item->get_total() / 1.21, 4 ),
				'pr_final'		=> $item->get_total(),
				'lista_id'		=> 0,
			];

			break;
		}

		// Set list products
		$args_order['detalle'] = $args_details;

		// If theres is discount, update the total without tax
		if( $discount > 0 )
			$total_notax -= $discount;

		$args_order['monto'] = $order->get_total();

		// Taxes
		$args_order['impuestos'] = [[
			'imp_id'			=> 1,
			'base_imponible'	=> $total_notax,
			'monto_imp'			=> $order->get_total() - $total_notax,
		]];

		// Hook
		$args_order = apply_filters( 'paljet/orders/send/params', $args_order, $order );

		// Get Products from ERP Paljet
		try {
			$request = $PaljetApi->Orders->post( $args_order );
		} catch (Exception $e) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return [ 'status' => 'error', 'response' => $e->getMessage() ];
		}

		// Check if there was an error
		if( isset( $request->estado ) && $request->estado == 400 ) {
			Paljet_Logs::add( 'error', $request->detalle );
			return [ 'status' => 'error', 'response' => $request->detalle ];
		}

		// Check if there is reply from the Paljet API
		if( ! isset( $request->cprId ) || ! is_numeric( $request->cprId ) ) {
			Paljet_Logs::add( 'error', esc_html__( 'Reply ID is not exist or is not numeric', 'paljet' ) );
			return [ 'status' => 'error', 'response' => esc_html__( 'Reply ID is not exist or is not numeric', 'paljet' ) ];
		}

		// Save meta value
		update_post_meta( $order->get_id(), 'paljet_order_id', absint( $request->cprId ) );
		
		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Send Order : Finish ( WC Order %d )', 'paljet' ),
			$order->get_id()
		) );

		return [ 'status' => 'ok', 'response' => absint( $request->cprId ) ];
	}
}