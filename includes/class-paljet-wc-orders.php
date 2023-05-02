<?php
/**
 * WC Orders Class
 * @since  1.0.0
 * @package Public / Orders
 */
class Paljet_WC_Orders {
	
	/**
	 * Construct
	 */
	public function __construct() {

		// The order was made successfully
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'wc_send' ], 99, 2 );

		// MercadoPago Gateway Payment / IPN
		add_action( 'valid_mercadopago_ipn_request', [ $this, 'mp_send' ], 99, 1 );

		// Mobbex Gateway Payment / Webhook
		add_action( 'mobbex_webhook_process', [ $this, 'mobbex_send' ], 99, 2 );
	}

	
	/**
	 * Send Order from WC to Paljet
	 * @param  int      $orderID
	 * @param  array    $posted_data
	 * @param  WC_Order $order
	 * @return mixed
	 */
	public function wc_send( int $orderID , array $posted_data ) {

		if( ! paljet()->license->is_active() ) {
			return;
		}

		$order = wc_get_order( $orderID );

		if( metadata_exists( 'post', $orderID, 'paljet_order_id') ) {
			return;
		}

		if( $order->has_status( 'failed' ) ) {
			return;
		}

		$gateways_allowed = [ 'cod', 'cheque', 'bacs' ];

		if( ! in_array( $order->get_payment_method(), $gateways_allowed ) ) {
			return;
		}

		// Send Order to Paljet
		$request_order = Paljet_Orders::send( $order );
		
		// Don't send ANTICIPO if it is COD CHEQUE or BACS
		//$request_voucher = Paljet_Vouchers::send( $order );

		$this->register( $order, $request_order );

		return true;
	}


	/**
	 * Send Order from IPN/Webhook MercadoPago
	 * @param  array  $data [description]
	 * @return [type]       [description]
	 */
	public function mp_send( $data = [] ) {

		if( ! paljet()->license->is_active() )
			return;
		
		$request_voucher = [];
		$order_key 		= $data['external_reference'];
		$invoice_prefix	= get_option('_mp_store_identificator', 'WC-');
		$order_id 		= (int)str_replace($invoice_prefix, '', $order_key);

		$order = wc_get_order( $order_id );

		if( ! $order )
			return;

		if( metadata_exists( 'post', $order_id, 'paljet_order_id') )
			return;

		if( $order->has_status( 'failed' ) )
			return;

		// Send Order to Paljet
		$request_order = Paljet_Orders::send( $order );

		// Send Voucher to Paljet
		if( ! metadata_exists( 'post', $order_id, 'paljet_voucher_id') )
			$request_voucher = Paljet_Vouchers::send( $order );

		$this->register( $order, $request_order, $request_voucher, [
			'payment_request'	=> $data,
			'type_request'		=> isset( $data['ipn_type'] ) ? 'ipn' : 'webhook',
		] );
	}

	/**
	 * Mobbex Send
	 * @param  integer $order_id
	 * @param  array $data
	 * @return mixed
	 */
	public function mobbex_send( $order_id = 0, $data = [] ) {

		if( ! paljet()->license->is_active() )
			return;

		if( metadata_exists( 'post', $order_id, 'paljet_order_id') )
			return;

		$request_voucher = [];
		$order = wc_get_order( $order_id );

		if( ! $order || $order->has_status( 'failed' ) )
			return;

		// Send Order to Paljet
		$request_order = Paljet_Orders::send( $order, $data );

		// Send Voucher to Paljet
		if( ! metadata_exists( 'post', $order_id, 'paljet_voucher_id') )
			$request_voucher = Paljet_Vouchers::send( $order, $data );

		$this->register( $order, $request_order, $request_voucher );

		return true;
	}


	/**
	 * Register History
	 * @param  class $order
	 * @param  array request_order
	 * @param  array request_voucher
	 * @param  array  $args
	 * @return mixed
	 */
	private function register( $order, $request_order = [], $request_voucher = [], $args = [] ) {

		// Paljet Order
		if( $request_order['status'] == 'ok' ) {
			
			$order->add_order_note( sprintf(
				esc_html__( 'Paljet Order ID %s ( Gateway : %s )', 'paljet' ),
				$request_order['response'],
				$order->get_payment_method()
			) );

		} else {

			$order->add_order_note( sprintf(
				esc_html__( 'Paljet Order Error : %s ( Gateway : %s )', 'paljet' ),
				$request_order['response'],
				$order->get_payment_method()
			) );
		}

		// Paljet Voucher
		if( ! empty( $request_voucher ) ) {
			if( $request_voucher['status'] == 'ok' ) {
				
				$order->add_order_note( sprintf(
					esc_html__( 'Paljet Voucher ID %s ( Gateway : %s )', 'paljet' ),
					$request_voucher['response'],
					$order->get_payment_method()
				) );

			} else {

				$order->add_order_note( sprintf(
					esc_html__( 'Paljet Voucher Error : %s ( Gateway : %s )', 'paljet' ),
					$request_voucher['response'],
					$order->get_payment_method()
				) );
			}
		}

		// Register history
		$history = get_post_meta( $order->get_id(), 'paljet_history', true );
		$history = ! empty( $history ) ? $history : [];

		$history[] = [
			'date'		=> date('Y-m-d H:i:s'),
			'payment'	=> $order->get_payment_method(),
			'type'		=> isset( $args['type_request'] ) ? $args['type_request'] : '',
			'data'		=> isset( $args['payment_request'] ) ? $args['payment_request'] : '',
			'request_order'		=> $request_order,
			'request_voucher'	=> $request_voucher,
		];

		// Register history
		update_post_meta( $order->get_id(), 'paljet_history', $history );
	}
}

new Paljet_WC_Orders();