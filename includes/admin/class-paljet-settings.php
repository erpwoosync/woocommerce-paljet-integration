<?php
/**
 * Settings Class
 * @since  1.0.0
 * @package admin / Settings
 */
class Paljet_Settings {

	private $connection = false;

	/**
	 * Construct function
	 */
	public function __construct() {

		// Js Script
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 11);
		
		// Settings Tab Class
		add_filter( 'woocommerce_get_settings_pages', [ $this, 'add_class' ], 10, 1 );

		// Check if the connection is good
		add_action( 'woocommerce_settings_start', [ $this, 'check_connection' ], 10 );

		// Create the sync button field
		add_action( 'woocommerce_admin_field_input_button', [ $this, 'input_button' ], 10, 1 );

		// Create the select/multiselect field
		add_action( 'woocommerce_admin_field_input_select', [ $this, 'input_select' ], 10, 1 );
		add_action( 'woocommerce_admin_field_input_multiselect', [ $this, 'input_select' ], 10, 1 );

		// Enable Send Orders
		add_action( 'woocommerce_admin_settings_sanitize_option_paljet_settings', [ $this, 'enable_sections' ], 10, 3 );

		// Additional Information ( Log Tab )
		add_action( 'woocommerce_admin_field_table_additional', [ $this, 'table_additional' ], 10, 1 );
	}

	/**
	 * Add JS and CSS to settings page
	 * @return [type] [description]
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		// If it is not the settings Woocommerce
		if( ! isset( $screen->base ) || $screen->base != 'woocommerce_page_wc-settings' )
			return;

		global $current_section, $current_tab;

		// If it is not the advanced section
		if( $current_tab != 'wc_paljet' )
			return;

		wp_enqueue_style(
			'paljet_settings_css',
			PALJET_PLUGIN_URL . 'resources/assets/css/admin-settings.css'
		);

		wp_enqueue_script(
			'paljet_settings_js',
			PALJET_PLUGIN_URL . 'resources/assets/js/admin-settings.js',
			[ 'jquery' ], false, true
		);

		$img_loading = '<img src=" '.admin_url( 'images/spinner.gif' ).'" alt="loading" />';
		$img_success = '<img src=" '.admin_url( 'images/yes.png' ).'" alt="yes" />';
		$img_failure = '<img src=" '.admin_url( 'images/no.png' ).'" alt="no" />';

		wp_localize_script( 'paljet_settings_js', 'paljet_vars', [
			'url_ajax'			=> admin_url( 'admin-ajax.php' ),
			'img_loading'		=> $img_loading,
			'img_success'		=> $img_success,
			'img_failure'		=> $img_failure,
			'sync_loading'		=> esc_html__( 'Synchronizing. It may take several minutes.', 'paljet' ),
			'sync_success'		=> esc_html__( 'Completed synchronization.', 'paljet' ),
			'sync_failure'		=> esc_html__( 'There was an error.', 'paljet' ),
			'total_products'	=> esc_html__( 'Total pages : ', 'paljet' ),
			'page_loaded'		=> esc_html__( 'Page loaded : ', 'paljet' ),

			'logs_loading'		=> esc_html__( 'Deleting logs...', 'paljet' ),
			'logs_success'		=> esc_html__( 'Logs deleted.', 'paljet' ),
			'logs_failure'		=> esc_html__( 'There was an error.', 'paljet' ),

			'nonce'				=> wp_create_nonce( 'paljet-wpnonce' ),
		]);
	}

	/**
	 * Add Tab Class
	 * @param array $settings [description]
	 */
	public function add_class( $settings = [] ) {

		if( ! paljet()->license->is_active() ) {
			return $settings;
		}

		$settings[] = include PALJET_PLUGIN_DIR . 'includes/admin/class-paljet-wc-tabs.php';

		return $settings;
	}


	/**
	 * Show connnection
	 * @return [type] [description]
	 */
	public function check_connection() {
		global $PaljetApi, $current_section, $current_tab;

		if( empty( $PaljetApi ) || $current_tab != 'wc_paljet' || $current_section != 'advanced' )
			return;

		try {
			$request = $PaljetApi->Users->get();

			if( $request ) {
				WC_Admin_Settings::add_message( esc_html__('Paljet ERP connected', 'paljet') );
			}
		} catch (Exception $e) {
			WC_Admin_Settings::add_error( $e->getMessage() );
		}
	}


	/**
	 * Layout Button
	 * @param  ARRAY $opts
	 * @return HTML
	 */
	public function input_button( $opts ) {

		// row class
		if( ! isset( $opts['tr_class'] ) )
			$opts['tr_class'] = '';

		// Disable button
		if( ! isset( $opts['disabled'] ) )
			$opts['disabled'] = false;

		// Custom attribute handling.
		$custom_attributes = [];

		if ( ! empty( $opts['custom_attributes'] ) && is_array( $opts['custom_attributes'] ) ) {
			foreach ( $opts['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		// Description handling.
		$field_description = WC_Admin_Settings::get_field_description( $opts );
		$description       = $field_description['description'];
		$tooltip_html      = $field_description['tooltip_html'];

		$args = [
			'options'		=> $opts,
			'attributes'	=> $custom_attributes,
			'description'	=> $description,
			'tooltip_html'	=> $tooltip_html,
		];

		wc_get_template(
			'resources/layouts/admin/input_button.php',
			$args, false, PALJET_PLUGIN_DIR
		);
	}


	/**
	 * [input_select description]
	 * @param  [type] $opts [description]
	 * @return [type]       [description]
	 */
	public function input_select( $opts ) {

		// row class
		if( ! isset( $opts['tr_class'] ) )
			$opts['tr_class'] = '';

		// Custom attribute handling.
		$custom_attributes = [];

		if ( ! empty( $opts['custom_attributes'] ) && is_array( $opts['custom_attributes'] ) ) {
			foreach ( $opts['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		// Description handling.
		$field_description = WC_Admin_Settings::get_field_description( $opts );
		$description       = $field_description['description'];
		$tooltip_html      = $field_description['tooltip_html'];

		$args = [
			'value'				=> $opts,
			'custom_attributes'	=> $custom_attributes,
			'description'		=> $description,
			'tooltip_html'		=> $tooltip_html,
		];

		wc_get_template(
			'resources/layouts/admin/input_select.php',
			$args, false, PALJET_PLUGIN_DIR
		);
	}

	/**
	 * Enable Orders
	 * @param  string $value
	 * @param  array $option
	 * @param  string $raw_value
	 * @return bool
	 */
	public function enable_sections( $value, $option, $raw_value ) {

		// Enable Orders
		if( strrpos( $option['id'], 'enable_orders' ) != FALSE ) {
			$opts = wc_clean( $_POST['paljet_settings'] );
			
			if( empty( $opts['sale_id'] ) || empty( $opts['classification_id'] ) ||
				empty( $opts['client_id'] ) || empty( $opts['client_cuit'] ) ||
				empty( $opts['ticket_id'] ) || empty( $opts['shipping_id'] ) ||
				empty( $opts['shipping_code'] )
			) return false;

			return true;
		}

		// Enable Vouchers
		if( strrpos( $option['id'], 'enable_vouchers' ) != FALSE ) {
			$opts = wc_clean( $_POST['paljet_settings'] );
			
			if( empty( $opts['voucher_sale_id'] ) ||
				empty( $opts['voucher_classification_id'] ) ||
				empty( $opts['voucher_classification_id_2'] ) ||
				empty( $opts['voucher_voucher_client_id'] ) ||
				empty( $opts['voucher_ticket_id'] ) || empty( $opts['voucher_account_id'] )
			) return false;

			return true;
		}

		return $value;
	}

	/**
	 * Table Log additional
	 * @param  [type] $opts [description]
	 * @return [type]       [description]
	 */
	public function table_additional( $opts ) {
		$args = [
			'opts' => $opts,
		];

		wc_get_template(
			'resources/layouts/admin/table_additional.php',
			$args, false, PALJET_PLUGIN_DIR
		);
	}
}

new Paljet_Settings();