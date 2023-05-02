<?php
/**
 * WooCommerce Paljet Settings
 *
 * @package WooCommerce/Admin
 * @version 2.4.0
 */

defined( 'ABSPATH' ) || exit;


if ( class_exists( 'WC_Settings_Paljet', false ) ) {
	return new WC_Settings_Paljet();
}

/**
* WC_Settings_Timersys
*/
class WC_Settings_Paljet extends WC_Settings_Page {

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->id    = 'wc_paljet';
		$this->label = esc_html__( 'Paljet ERP', 'paljet' );

		parent::__construct();
	}


	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		global $PaljetApi;

		// General
		$sections = [ '' => esc_html__( 'General', 'paljet' ) ];

		// Adavanced
		if( ! empty( $PaljetApi ) ) {
			$sections['syncs'] = esc_html__( 'Syncs', 'paljet' );
			$sections['orders'] = esc_html__( 'Orders', 'paljet' );
			$sections['vouchers'] = esc_html__( 'Vouchers', 'paljet' );
		}

		// Logs
		$sections['logs'] = esc_html__( 'Logs', 'paljet' );

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Output the settings.
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );

		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings.
	 */
	public function save() {
		global $current_section;

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );

		if ( $current_section ) {
			do_action( 'woocommerce_update_options_' . $this->id . '_' . $current_section );
		}
	}


	/**
	 * Get settings array.
	 *
	 * @param string $current_section Current section name.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		global $PaljetApi;
		
		if ( 'syncs' === $current_section ) {

			$msg_brand = $msg_product = [];
			$disable_product = $disable_brand = empty( $PaljetApi );

			$settings = get_option( 'paljet_settings', [] );

			// Product limit
			if( ! isset( $settings['product_limit'] ) || empty( $settings['product_limit'] ) ) {
				$msg_product[] = esc_html__( 'You must set a product limit to sync', 'paljet' );
			}

			// Price List
			if( ! isset( $settings['price_regular'] ) || $settings['price_regular'] == '' )
				$msg_product[] = esc_html__('You must set a price list to regular price', 'paljet');

			// Warehouses
			if( ! isset( $settings['warehouses'] ) )
				$msg_product[] = esc_html__( 'You must set the warehouses', 'paljet' );

			// Brand attribute
			if( ! isset( $settings['attribute_brand'] ) || empty( $settings['attribute_brand'] ) ) {
				$msg_brand[] = esc_html__( 'You must set an attribute to the brand', 'paljet' );
				$msg_product[] = esc_html__( 'You must set an attribute to the brand', 'paljet' );
			}

			// Resources URL
			if( ! isset( $settings['resources_url'] ) || empty( $settings['resources_url'] ) ) {
				$msg_product[] = esc_html__( 'You must set your URL to the resources', 'paljet' );
			}


			$disable_brand = count( $msg_brand ) > 0 ? true : $disable_brand;
			$disable_product = count( $msg_product ) > 0 ? true : $disable_product;


			$settings = apply_filters(
				'woocommerce_syncs_paljet_settings',
				[
					[
						'title'	=> esc_html__( 'Sync', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_syncs_sync',
					],[
						'title'			=> esc_html__( 'Categories', 'paljet' ),
						'label'			=> esc_html__( 'Sync Categories', 'paljet' ),
						'id'			=> 'paljet_sync_categories',
						'type'			=> 'input_button',
						'tr_class'		=> 'row_sync_button',
						'disabled'		=> empty( $PaljetApi ),
						'class'			=> 'paljet_sync_button button button-secondary button-hero',
						'css'			=> '',
						'custom_attributes' => [ 'data-type' => 'categories' ],
					],[
						'title'			=> esc_html__( 'Brands', 'paljet' ),
						'label'			=> esc_html__( 'Sync Brands', 'paljet' ),
						'id'			=> 'paljet_sync_brands',
						'type'			=> 'input_button',
						'tr_class'		=> 'row_sync_button',
						'disabled'		=> $disable_brand,
						'class'			=> 'paljet_sync_button button button-secondary button-hero',
						'css'			=> '',
						'alert'			=> $msg_brand,
						'custom_attributes' => [ 'data-type' => 'brands' ],
					],[
						'title'			=> esc_html__( 'Products', 'paljet' ),
						'label'			=> esc_html__( 'Sync Products', 'paljet' ),
						'id'			=> 'paljet_sync_products',
						'type'			=> 'input_button',
						'tr_class'		=> 'row_sync_button',
						'disabled'		=> $disable_product,
						'class'			=> 'paljet_sync_button button button-secondary button-hero',
						'css'			=> '',
						'alert'			=> $msg_product,
						'custom_attributes' => [ 'data-type' => 'products' ],
					],[
						'type' => 'sectionend',
						'id'   => 'paljet_syncs_sync',
					],[
						'title'	=> esc_html__( 'Options', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_syncs_options',
					],[
						'title'		=> esc_html__( 'Regular Price', 'paljet' ),
						'id'		=> 'paljet_settings[price_regular]',
						'type'		=> 'input_select',
						'default'	=> '',
						'options'	=> paljet_dropdown_prices_lists(),
						'class'		=> 'wc-enhanced-select-nostd',
						'css'		=> 'min-width:300px;',
						'text_link'	=> esc_html__( 'Need Sync? Click here', 'paljet' ),
						'type_sync'	=> 'prices',
					],[
						'title'		=> esc_html__( 'Sale Price', 'paljet' ),
						'id'		=> 'paljet_settings[price_sale]',
						'type'		=> 'input_select',
						'default'	=> '',
						'options'	=> paljet_dropdown_prices_lists(),
						'class'		=> 'wc-enhanced-select-nostd',
						'css'		=> 'min-width:300px;',
						'type_sync'	=> 'prices',
					],[
						'title'		=> esc_html__( 'Warehouses', 'paljet' ),
						'id'		=> 'paljet_settings[warehouses]',
						'type'		=> 'input_multiselect',
						'default'	=> '',
						'options'	=> get_option( 'paljet_warehouses', [] ),
						'class'		=> 'wc-enhanced-select-nostd',
						'css'		=> 'min-width:300px;',
						'placeholder'	=> esc_html__('Choose a warehouse', 'paljet'),
						'text_link'	=> esc_html__( 'Need Sync? Click here', 'paljet' ),
						'type_sync'	=> 'warehouses',
					],[
						'type' => 'sectionend',
						'id'   => 'paljet_syncs_options',
					]
				]
			);

		} elseif( 'orders' === $current_section ) {

			$settings = apply_filters(
				'woocommerce_orders_paljet_settings',
				[
					[
						'title'	=> esc_html__( 'Orders', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_orders_basic',
					],[
						'title'			=> esc_html__( 'Point of Sale ID', 'paljet' ),
						'id'			=> 'paljet_settings[sale_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your point of sale ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Classification ID', 'paljet' ),
						'id'			=> 'paljet_settings[classification_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your classification ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Client ID', 'paljet' ),
						'id'			=> 'paljet_settings[client_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your client ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Client CUIT', 'paljet' ),
						'id'			=> 'paljet_settings[client_cuit]',
						'type'			=> 'text',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your client CUIT', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Ticket ID', 'paljet' ),
						'id'			=> 'paljet_settings[ticket_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your ticket ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Shipping ID', 'paljet' ),
						'id'			=> 'paljet_settings[shipping_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your shipping ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Shipping Code', 'paljet' ),
						'id'			=> 'paljet_settings[shipping_code]',
						'type'			=> 'text',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your shipping Code', 'paljet' ),
					],[
						'id'			=> 'paljet_settings[enable_orders]',
						'type'			=> 'hidden',
					],[
						'type' 			=> 'sectionend',
						'id'   			=> 'paljet_orders_basic',
						'default'		=> '',
					]
				]
			);

		} elseif( 'vouchers' === $current_section ) {

			$settings = apply_filters(
				'woocommerce_vouchers_paljet_settings',
				[
					[
						'title'	=> esc_html__( 'Vouchers', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_vouchers_basic',
					],[
						'title'			=> esc_html__( 'Point of Sale ID', 'paljet' ),
						'id'			=> 'paljet_settings[voucher_sale_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your point of sale ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Classification ID', 'paljet' ),
						'id'			=> 'paljet_settings[voucher_classification_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your classification ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Voucher Classification ID', 'paljet' ),
						'id'			=> 'paljet_settings[voucher_classification_id_2]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your voucher classification ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Client ID', 'paljet' ),
						'id'			=> 'paljet_settings[voucher_client_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your client ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Ticket ID', 'paljet' ),
						'id'			=> 'paljet_settings[voucher_ticket_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your ticket ID', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Account ID', 'paljet' ),
						'id'			=> 'paljet_settings[voucher_account_id]',
						'type'			=> 'number',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your account ID', 'paljet' ),
					],[
						'id'			=> 'paljet_settings[enable_vouchers]',
						'type'			=> 'hidden',
					],[
						'type' 			=> 'sectionend',
						'id'   			=> 'paljet_vouchers_basic',
						'default'		=> '',
					]
				]
			);

		} elseif( 'logs' === $current_section ) {

			$settings = apply_filters(
				'woocommerce_logs_paljet_settings',
				[
					[
						'title'	=> esc_html__( 'Logs', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_logs_options',
					],[
						'title'		=> esc_html__( 'Enable Logs', 'paljet' ),
						'id'		=> 'paljet_settings[enable_logs]',
						'type'		=> 'checkbox',
						'default'	=> '',
						'class'		=> '',
						'css'		=> '',
					],[
						'title'		=> esc_html__( 'Logs content', 'paljet' ),
						'id'		=> '',
						'type'		=> 'textarea',
						'default'	=> '',
						'class'		=> 'input-text wide-input ',
						'css'		=> '',
						'value'		=> Paljet_Logs::print('text', false),
						'custom_attributes' => [
							'rows'			=> 25,
							'readonly'		=> 'readonly',
							'data-input'	=> 'paljet_log',
						],
					],[
						'title'			=> esc_html__( 'Delete Logs', 'paljet' ),
						'label'			=> esc_html__( 'Delete Now', 'paljet' ),
						'id'			=> 'paljet_delete_logs',
						'type'			=> 'input_button',
						'tr_class'		=> 'row_delete_rows',
						'class'			=> 'paljet_delete_logs button button-secondary button-hero',
						'css'			=> '',
					],[
						'type'		=> 'sectionend',
						'id'		=> 'paljet_logs_options',
					],[
						'title'	=> esc_html__( 'Additional Information', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_additional_options',
					],[
						'title'			=> esc_html__( 'Variables', 'paljet' ),
						'label'			=> esc_html__( 'Variables', 'paljet' ),
						'id'			=> 'paljet_additional_info',
						'type'			=> 'table_additional',
						'css'			=> '',
					],[
						'type'		=> 'sectionend',
						'id'		=> 'paljet_additional_options',
					],
				]
			);

		} else {

			$settings = apply_filters(
				'woocommerce_general_paljet_settings',
				[
					[
						'title'	=> esc_html__( 'General', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_general_options',
					],[
						'title'			=> esc_html__( 'Resources URL', 'paljet' ),
						'id'			=> 'paljet_settings[resources_url]',
						'type'			=> 'url',
						'desc'			=> esc_html__( 'To download product images', 'paljet' ),
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your URL', 'paljet' ),
					],[
						'title'		=> esc_html__( 'Brand Attribute', 'paljet' ),
						'id'		=> 'paljet_settings[attribute_brand]',
						'type'		=> 'select',
						'default'	=> '',
						'options'	=> paljet_dropdown_wc_attributes(),
						'class'		=> 'wc-enhanced-select-nostd',
						'css'		=> 'min-width:300px;',
					],[
						'title'		=> esc_html__( 'Products Limit to Sync', 'paljet' ),
						'id'		=> 'paljet_settings[product_limit]',
						'type'		=> 'number',
						'default'	=> '0',
						'class'		=> '',
						'css'		=> 'min-width:300px;',
						'custom_attributes' => [ 'step' => 1 ],
					],[
						'title'		=> esc_html__( 'Enable Deltas', 'paljet' ),
						'id'		=> 'paljet_settings[enable_deltas]',
						'type'		=> 'checkbox',
						'default'	=> '',
						'class'		=> '',
						'css'		=> '',
						'desc'		=> sprintf( esc_html__( 'Check for newly updated products every %d secs.', 'paljet' ), PALJET_MAX_TIME ),
					],[
						'type'		=> 'sectionend',
						'id'		=> 'paljet_general_options',
					],[
						'title'	=> esc_html__( 'Login', 'paljet' ),
						'type'	=> 'title',
						'desc'	=> '',
						'id'	=> 'paljet_general_login',
					],[
						'title'			=> esc_html__( 'URL', 'paljet' ),
						'id'			=> 'paljet_settings[access_url]',
						'type'			=> 'url',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your URL', 'paljet' ),
					],[
						'title'			=> esc_html__( 'User', 'paljet' ),
						'id'			=> 'paljet_settings[access_user]',
						'type'			=> 'text',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your user', 'paljet' ),
					],[
						'title'			=> esc_html__( 'Password', 'paljet' ),
						'id'			=> 'paljet_settings[access_pass]',
						'type'			=> 'password',
						'default'		=> '',
						'class'			=> '',
						'css'			=> '',
						'placeholder'	=> esc_html__( 'Enter your password', 'paljet' ),
					],[
						'type' => 'sectionend',
						'id'   => 'paljet_general_login',
					],
				]
			);
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}
}

return new WC_Settings_Paljet();