<?php
/*
Plugin Name: Woocommerce Paljet Integration 
Plugin URI: https://www.letsgodev.com/
Description: This plugin allows synchronizer Paljet ERP with WooCommerce, you will get the categories, brands, attributes, products, or more.
Version: 1.5.7
Author: Lets Go Dev
Author URI: https://www.letsgodev.com/
Developer: Alexander Gonzales
Developer URI: https://vcard.gonzalesc.org/
Text Domain: paljet, woocommerce, integration, orders, products, erp
Requires at least: 5.4
Tested up to: 6.2
Stable tag: 5.8
WC requires at least: 7.6
WC tested up to: 7.6.1
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PALJET_API_URL', 'https://www.erpwoosync.com/index.php' );
define( 'PALJET_PRODUCT_ID', 'PALJET-101' );
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
define( 'PALJET_DOMAIN', str_replace($protocol, "", get_bloginfo('wpurl')));
define( 'PALJET_EMAIL_SUPPORT', 'soporte@erpwoosync.com' );
define( 'PALJET_VERSION', '1.5.7' );

define( 'PALJET_PLUGIN_DIR' , plugin_dir_path( __FILE__ ) );
define( 'PALJET_PLUGIN_URL' , plugin_dir_url( __FILE__ ) );
define( 'PALJET_PLUGIN_BASE' , plugin_basename( __FILE__ ) );
define( 'PALJET_MAX_TIME', intval( ini_get( 'max_execution_time' ) ) );
define( 'PALJET_EMPID' , 1 );

/**
 * The core plugin class that is used to define internationalization,
 * dashboard-specific hooks, and public-facing site hooks.
 */
require_once PALJET_PLUGIN_DIR . 'includes/class-paljet.php';


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-paljet-activator.php
 */
function paljet_activate() {
	require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-activator.php';
	Paljet_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-paljet-deactivator.php
 */
function paljet_deactivate() {
	require_once PALJET_PLUGIN_DIR . 'includes/class-paljet-deactivator.php';
	Paljet_Deactivator::deactivate();
}


register_activation_hook( __FILE__, 'paljet_activate' );
register_deactivation_hook( __FILE__, 'paljet_deactivate' );

/**
 * Store the plugin global
 */
global $paljet;

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 */

function paljet() {
	return Paljet::instance();
}

$paljet = paljet();
?>