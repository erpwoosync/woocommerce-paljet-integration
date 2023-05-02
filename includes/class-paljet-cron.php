<?php
/**
 * Cron Class
 * @since  1.0.0
 * @package Public / Integration
 */
class Paljet_Cron {

	public function __construct() {
		add_action( 'init', [ $this, 'initialize' ] );
		add_filter( 'cron_schedules', [ $this, 'add_new_schedule'], 100, 1 );
		add_action( 'paljet_pending_images', [ $this, 'pending_images'] );
		add_action( 'paljet_pending_resources', [ $this, 'pending_resources'] );
		add_action( 'paljet_pending_products', [ $this, 'pending_products'] );
		add_action( 'paljet_complete_products', [ $this, 'complete_products'] );

		// Deltas
		add_action( 'paljet_delta_products', [ $this, 'delta_products'] );
		add_action( 'paljet_delta_stock', [ $this, 'delta_stock'] );
		add_action( 'paljet_delta_price', [ $this, 'delta_price'] );
	}

	/**
	 * Initialize tasks
	 * @return [type] [description]
	 */
	public function initialize() {

		if( ! paljet()->license->is_active() )
			return;

		// Pending Images
		if ( ! wp_next_scheduled( 'paljet_pending_images' ) ) {
			wp_schedule_event(
				current_time( 'timestamp' ) + 220,
				'paljet_cron_time',
				'paljet_pending_images'
			);
		}

		// Pending Resources
		if ( ! wp_next_scheduled( 'paljet_pending_resources' ) ) {
			wp_schedule_event(
				current_time( 'timestamp' ) + 150,
				'paljet_cron_time',
				'paljet_pending_resources'
			);
		}

		// Pending Products
		if ( ! wp_next_scheduled( 'paljet_pending_products' ) ) {
			wp_schedule_event(
				current_time( 'timestamp' ) + 80,
				'paljet_cron_time',
				'paljet_pending_products'
			);
		}

		$settings = get_option( 'paljet_settings', [] );

		if( isset( $settings['enable_deltas'] ) && $settings['enable_deltas'] == 'yes' ) {

			// Complete Pending Products
			if ( ! wp_next_scheduled( 'paljet_complete_products' ) ) {
				wp_schedule_event(
					current_time( 'timestamp' ) + 10,
					'paljet_cron_time',
					'paljet_complete_products'
				);
			}

			// Delta Products
			if ( ! wp_next_scheduled( 'paljet_delta_products' ) ) {
				wp_schedule_event(
					current_time( 'timestamp' ) + 10,
					'paljet_10min',
					'paljet_delta_products'
				);
			}

			// Delta Stock
			if ( ! wp_next_scheduled( 'paljet_delta_stock' ) ) {
				wp_schedule_event(
					current_time( 'timestamp' ) + 10,
					'paljet_10min',
					'paljet_delta_stock'
				);
			}

			// Delta Price
			if ( ! wp_next_scheduled( 'paljet_delta_price' ) ) {
				wp_schedule_event(
					current_time( 'timestamp' ) + 10,
					'paljet_10min',
					'paljet_delta_price'
				);
			}
		
		} else {
			
			if ( wp_next_scheduled( 'paljet_complete_products' ) )
				wp_clear_scheduled_hook( 'paljet_complete_products' );

			if ( wp_next_scheduled( 'paljet_delta_products' ) )
				wp_clear_scheduled_hook( 'paljet_delta_products' );

			if ( wp_next_scheduled( 'paljet_delta_stock' ) )
				wp_clear_scheduled_hook( 'paljet_delta_stock' );

			if ( wp_next_scheduled( 'paljet_delta_price' ) )
				wp_clear_scheduled_hook( 'paljet_delta_price' );
		}
	}

	/**
	 * Add new schedules
	 * @param [type] $schedules [description]
	 */
	public function add_new_schedule( $schedules ) {
		
		if( ! isset( $schedules['paljet_cron_time'] ) ) {
			$schedules['paljet_cron_time'] = [
				'interval'	=> PALJET_MAX_TIME,
				'display'	=> sprintf( esc_html__( 'Every %d secs', 'paljet' ), PALJET_MAX_TIME ),
			];
		}

		//if( ! isset( $schedules['paljet_5min'] ) ) {
		//	$schedules['paljet_5min'] = [
		//		'interval'	=> 5 * MINUTE_IN_SECONDS,
		//		'display'	=> esc_html__( 'Every 5 minutes', 'paljet' ),
		//	];
		//}

		//if( ! isset( $schedules['paljet_3min'] ) ) {
		//	$schedules['paljet_3min'] = [
		//		'interval'	=> 3 * MINUTE_IN_SECONDS,
		//		'display'	=> esc_html__( 'Every 3 minutes', 'paljet' ),
		//	];
		//}

		if( ! isset( $schedules['paljet_10min'] ) ) {
			$schedules['paljet_10min'] = [
				'interval'	=> 10 * MINUTE_IN_SECONDS,
				'display'	=> esc_html__( 'Every 10 minutes', 'paljet' ),
			];
		}
		return $schedules;
	}


	public function pending_images() {
		Paljet_Resources::set_pending_images();
	}

	public function pending_products() {
		Paljet_Products::set_pending_products();
	}

	public function complete_products() {
		Paljet_Products::complete_pending_products();
	}

	public function pending_resources() {
		Paljet_Resources::set_pending_resources();
	}


	/*
		DELTAS
	 */
	public function delta_products() {
		if( ! paljet()->license->is_active() )
			return;
		
		Paljet_Deltas::process_products();
	}

	public function delta_stock() {
		if( ! paljet()->license->is_active() )
			return;

		Paljet_Deltas::process_stock();
	}

	public function delta_price() {
		if( ! paljet()->license->is_active() )
			return;
		
		Paljet_Deltas::process_price();
	}
}

new Paljet_Cron();