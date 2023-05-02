<?php
/**
 * Log Class
 * @since  1.0.0
 * @package Public / Log
 */
class Paljet_Logs {

	static public $path_log = 'includes/admin/logs.txt';
	static public $messages = [];

	/**
	 * add messages to log
	 * @param string $type    notice|error
	 * @param string $message
	 */
	public static function add( $type = 'notice', $message = '' ) {


		$settings = get_option( 'paljet_settings', [] );

		if( ! isset( $settings['enable_logs'] ) || $settings['enable_logs'] != 'yes' )
			return;

		$logs = fopen( PALJET_PLUGIN_DIR . self::$path_log, 'a' );

		$content = '[' . current_time( 'mysql' ) . '] ' . strtoupper( $type ) . ' : ' . $message . "\n";

		fwrite( $logs, $content );

		fclose( $logs );

		return true;
	}


	/**
	 * Print Logs
	 * @param  string $format html|text
	 * @param  bool   $echo
	 * @return mixed
	 */
	public static function print( $format = 'html', $echo = true ) {

		$output = '';
		$settings = get_option( 'paljet_settings', [] );

		if( isset( $settings['enable_logs'] ) && $settings['enable_logs'] == 'yes' ) {

			$path = PALJET_PLUGIN_DIR . 'includes/admin/logs.txt';
			$logs = fopen( $path, 'r' );

			$size = filesize( $path );

			if( $size != 0 )
				$output = fread( $logs, $size );

			fclose( $logs );

			if( $format == 'html' )
				$output = str_replace( "\n", '<br />', $output );
		}

		if( ! $echo )
			return $output;

		echo $output;
	}


	/**
	 * Delete Logs
	 * @return [type] [description]
	 */
	public static function delete() {
		$path = PALJET_PLUGIN_DIR . self::$path_log;
		$logs = fopen( $path, 'w' );

		return true;
	}


	/**
	 * Save history product
	 * @param  integer $product_id
	 * @param  string  $type      
	 * @param  string  $mode      
	 * @return bool
	 */
	public static function product_history( $product_id = 0, $type = 'online', $mode = 'new' ) {
		
		$histories = get_post_meta( $product_id, 'paljet_history', true );
		$histories = ! empty( $histories ) ? $histories : [];

		$date = current_time( 'mysql' );

		// Info
		switch( $mode ) {
			case 'new' : $mode_message = esc_html__( 'new product', 'paljet'); break;
			case 'update' : $mode_message = esc_html__( 'update product', 'paljet'); break;
			case 'images' : $mode_message = esc_html__( 'sync images', 'paljet'); break;
			default: $mode_message = esc_html__( 'update product', 'paljet');
		}

		// type
		switch( $type ) {
			case 'online' : $type_message = esc_html__( 'online mode', 'paljet'); break;
			case 'manual' : $type_message = esc_html__( 'manual mode', 'paljet'); break;
			default: $type_message = esc_html__( 'online mode', 'paljet');
		}


		$message = '[ ' . $date . ' ] ' . $mode_message . ' ( ' . $type_message . ' )';

		// If the array is higher than 15
		if( count( $histories ) > 15 ) {
			for( $i=15; $i < count( $histories ); $i++ )
				unset( $histories[ $i ] );
		}

		// Add the message to the beginning
		array_unshift( $histories, $message );

		// Update
		update_post_meta( $product_id, 'paljet_history', $histories );

		return true;
	}
}