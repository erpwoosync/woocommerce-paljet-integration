<?php
/**
 * Define the locale for this plugin for internationalization.
 *
 * Uses the Paljet_i18n class in order to set the domain and to register the hook
 * with WordPress.
 *
 */
class Paljet_i18n {

	/**
	 * The domain specified for this plugin.
	 */
	private $domain = 'paljet';

	public function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			$this->domain,
			false,
			dirname( PALJET_PLUGIN_BASE ) . '/languages/'
		);
	}
}

new Paljet_i18n();