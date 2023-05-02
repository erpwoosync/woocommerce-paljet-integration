<?php
class Paljet_Admin {

	/**
	 * Construct
	 */
	public function __construct() {

		add_filter( 'plugin_action_links_'.PALJET_PLUGIN_BASE, [ $this, 'setting_internal_link' ] );

		add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
		add_filter( 'woocommerce_resize_images', '__return_false' );
		add_action( 'init', [ $this, 'remove_wc_image_get_intermediate_size' ] );
	}

	public function remove_wc_image_get_intermediate_size() {
		remove_action( 'image_get_intermediate_size', [ 'WC_Regenerate_Images', 'filter_image_get_intermediate_size' ], 10, 3 );
	}


	public function setting_internal_link( $links ) {

		if( ! paljet()->license->is_active() )
			return $links;

		$settings = [
			'settings' => sprintf( '<a href="%s">%s</a>',
				admin_url( 'admin.php?page=wc-settings&tab=wc_paljet' ),
				esc_html__( 'Settings', 'paljet' )
			)
		];
	
		return array_merge( $settings, $links );
	}
}

new Paljet_Admin();