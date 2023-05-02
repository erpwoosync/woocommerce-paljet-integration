<?php
/**
 * Settings Class
 * @since  1.0.0
 * @package admin / Settings
 */
class Paljet_Ajax {

	public function __construct() {

		// Sync Warehouses
		add_action( 'wp_ajax_paljet_sync_warehouses', [ $this, 'sync_warehouses'] );

		// Sync Prices Lists
		add_action( 'wp_ajax_paljet_sync_prices', [ $this, 'sync_prices'] );
		
		// Sync Categories
		add_action( 'wp_ajax_paljet_sync_categories', [ $this, 'sync_categories'] );

		// Sync Brands
		add_action( 'wp_ajax_paljet_sync_brands', [ $this, 'sync_brands'] );

		// Sync Images
		add_action( 'wp_ajax_paljet_sync_images', [ $this, 'sync_images'] );

		// Sync Products
		add_action( 'wp_ajax_paljet_total_products', [ $this, 'total_products'] );
		add_action( 'wp_ajax_paljet_sync_products', [ $this, 'sync_products'] );

		// Delete Logs
		add_action( 'wp_ajax_paljet_delete_logs', [ $this, 'delete_logs'] );
	}


	/**
	 * Sync Prices
	 * @return mixed
	 */
	public function sync_warehouses() {

		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( esc_html( 'You do not have permission.', 'paljet' ) );
		
		// Sync
		if( Paljet_Warehouses::sync() )
			wp_send_json_success( get_option('paljet_warehouses') );
		else
			wp_send_json_error();
	}

	/**
	 * Sync Prices
	 * @return mixed
	 */
	public function sync_prices() {

		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( esc_html( 'You do not have permission.', 'paljet' ) );
		
		// Sync
		if( Paljet_Prices::sync() )
			wp_send_json_success( get_option('paljet_prices') );
		else
			wp_send_json_error();
	}

	/**
	 * Sync Categories
	 * @return mixed
	 */
	public function sync_categories() {

		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( esc_html( 'You do not have permission.', 'paljet' ) );
		
		// Sync
		if( Paljet_Categories::sync() )
			wp_send_json_success();
		else
			wp_send_json_error();
	}


	/**
	 * Sync Brands
	 * @return mixed
	 */
	public function sync_brands() {

		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( esc_html( 'You do not have permission.', 'paljet' ) );
		
		// Sync
		if( Paljet_Brands::sync() )
			wp_send_json_success();
		else
			wp_send_json_error();
	}


	/**
	 * Sync Images
	 * @return mixed
	 */
	public function sync_images() {
		
		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( esc_html( 'You do not have permission.', 'paljet' ) );

		// Sync
		$product_id = intval( $_REQUEST['product_id'] );

		$output = Paljet_Resources::sync( $product_id );
		
		if( $output )
			wp_send_json_success( $output );
		else
			wp_send_json_error();
	}


	/**
	 * Sync Brands
	 * @return mixed
	 */
	public function sync_products() {

		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( esc_html( 'You do not have permission.', 'paljet' ) );

		// Sync
		$page = intval( $_REQUEST['page'] );
		
		if( Paljet_Products::sync( $page ) )
			wp_send_json_success();
		else
			wp_send_json_error();
	}

	/**
	 * Get Total Products
	 * @return mixed
	 */
	public function total_products() {
		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		// Check the permissions
		if( ! current_user_can( 'manage_options' ) )
			wp_send_json_error( esc_html( 'You do not have permission.', 'paljet' ) );

		// Sync
		$totals = Paljet_Products::totals();
		
		if( $totals != FALSE )
			wp_send_json_success( $totals );
		else
			wp_send_json_error();
	}


	/**
	 * Delete Logs
	 * @return mixed
	 */
	public function delete_logs() {
		
		// Run a security check.
		check_ajax_referer( 'paljet-wpnonce', 'wpnonce' );

		if( Paljet_Logs::delete() )
			wp_send_json_success();
		else
			wp_send_json_error();
	}
}

new Paljet_Ajax();