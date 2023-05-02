<?php

class Paljet {

	/**
	 * Plugin Instance
	 */
	protected static $_instance = null;

	/**
	 * Integrator Instance
	 */
	public $integrator;

	/**
	 * License Instance
	 */
	public $license;


	/**
	 * Ensures only one instance is loaded or can be loaded.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'paljet' ), '2.1' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'paljet' ), '2.1' );
	}


	/**
	 * Construct
	 */
	public function __construct() {

		$this->load_license();
		$this->load_dependencies();

		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], 0 );
		add_action( 'wp_loaded', [ $this, 'connection' ], 99 );
	}

	/**
	 * Load License
	 * @return mixed
	 */
	private function load_license() {

		require_once PALJET_PLUGIN_DIR . 'includes/wp-license/class-erpwoosync-license.php';

		// Update
		$update = [
			'plugin_name'	=> esc_html__( 'Paljet ERP', 'paljet' ),
			'plugin_base'	=> PALJET_PLUGIN_BASE,

			'library_base'	=> trailingslashit( dirname( PALJET_PLUGIN_BASE ) ) . 'includes/wp-license/' ,
			'library_dir'	=> PALJET_PLUGIN_DIR . 'includes/wp-license/',
			'library_url'	=> PALJET_PLUGIN_URL . 'includes/wp-license/',

			'api_url'	=> PALJET_API_URL,
			'product'	=> PALJET_PRODUCT_ID,
			'domain'	=> PALJET_DOMAIN,
			'version'	=> PALJET_VERSION,
			'email'		=> PALJET_EMAIL_SUPPORT,
		];

		$this->license	= new ERPWooSync_License( $update );
	}


	private function load_dependencies() {

		require_once PALJET_PLUGIN_DIR . 'vendor/autoload.php';
		require_once PALJET_PLUGIN_DIR . 'includes/functions.php';
		require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-i18n.php';
		require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-logs.php';
		require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-cron.php';
		require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-ajax.php';

		// Syncs
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-orders.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-vouchers.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-prices.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-brands.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-warehouses.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-categories.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-resources.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-products.php';
		require_once PALJET_PLUGIN_DIR . 'includes/syncs/class-paljet-deltas.php';

		require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-wc-attributes.php';
		require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-wc-orders.php';

		if( is_admin() ) {

			//if ( ! class_exists( 'WC_Session' ) )
			//	require_once WP_PLUGIN_DIR  . '/woocommerce/includes/abstracts/abstract-wc-session.php';

			require_once PALJET_PLUGIN_DIR . 'includes/admin/class-paljet-settings.php';
			require_once PALJET_PLUGIN_DIR . 'includes/admin/class-paljet-admin.php';
			require_once PALJET_PLUGIN_DIR . 'includes/admin/class-paljet-metaboxes.php';
		}
	}


	public function notice_woo() {
		echo '<div class="error"><p>' . sprintf( esc_html__( 'Woocommerce Paljet Integration plugin depends on the last version of %s to work!', 'paljet' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
	}

	public function notice_currency() {
		echo '<div class="error"><p>' . esc_html__( 'Woocommerce Paljet Integration plugin needs the currency in the commerce be ARS to work!', 'paljet' ) . '</p></div>';
	}

	/**
	 * Notices
	 * @return [type] [description]
	 */
	public function plugins_loaded() {
			
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action( 'admin_notices', [ $this, 'notice_woo'] );
			return;
		}

		if ( get_woocommerce_currency() != 'ARS' ) {
			add_action( 'admin_notices', [ $this, 'notice_currency'] );
			return;
		}	
	}

	/**
	 * Connection with Paljet
	 * @return [type] [description]
	 */
	public function connection() {
		global $PaljetApi;

		$settings = get_option( 'paljet_settings', [] );

		if( isset( $settings ) && ! empty( $settings['access_url'] ) &&
			! empty( $settings['access_user'] ) && ! empty( $settings['access_pass'] )
		) {
			try {
	 			$PaljetApi = new Paljet\Paljet( [
	 				'url'	=> $settings['access_url'],
	 				'user'	=> $settings['access_user'],
	 				'pass'	=> $settings['access_pass'],
	 				'emp'	=> PALJET_EMPID,
	 			] );
			} catch (Exception $e) {

			}
		}
	}
}

?>