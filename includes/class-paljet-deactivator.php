<?php
class Paljet_Deactivator {

	static public function deactivate() {

		self::clear_cron();
	}

	static public function clear_cron() {
		wp_clear_scheduled_hook( 'paljet_pending_images' );
		wp_clear_scheduled_hook( 'paljet_pending_resources' );
		wp_clear_scheduled_hook( 'paljet_pending_products' );
		wp_clear_scheduled_hook( 'paljet_complete_products' );
		wp_clear_scheduled_hook( 'paljet_delta_products' );
		wp_clear_scheduled_hook( 'paljet_delta_stock' );
		wp_clear_scheduled_hook( 'paljet_delta_price' );
	}

}