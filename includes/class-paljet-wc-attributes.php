<?php
/**
 * Integration Class
 * @since  1.0.0
 * @package Public / Integration
 */
class Paljet_WC_Attributes {
	
	/**
	 * Construct
	 */
	public function __construct() {

		// Create Attributes to WC
		add_action( 'init', [ $this, 'create_attributes' ], 9 );
	}

	
	/**
	 * Create Attributes
	 * @return mixed
	 */
	public function create_attributes() {
		$attributes = get_option( 'paljet_attributes', [] );

		if( count( $attributes ) == 0 )
			return;

		foreach( $attributes as $attr ) {

			if( taxonomy_exists( wc_attribute_taxonomy_name( $attr ) ) )
				continue;

			$args = [
				'name'			=> $attr,
				'order_by'		=> 'menu_order',
				'has_archives'	=> '',
			];

			$result = wc_create_attribute( $args );

			if( is_wp_error( $result ) ) {
				Paljet_Logs::add( 'error', sprintf(
					esc_html__( 'Creating Attributes : %s ( attribute : %s )', 'paljet' ),
					$result->get_error_message(), $attr
				) );
			}
		}

		delete_option( 'paljet_attributes' );
	}
}

new Paljet_WC_Attributes();