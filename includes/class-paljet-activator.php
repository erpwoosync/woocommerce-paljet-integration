<?php
class Paljet_Activator {

	static public function activate() {

		self::create_tables();
		self::update_tables();
		
		/*
			TODO: this method was moved to class-paljet-cron.php
		 */
		//self::create_cron();
	}

	/**
	 * [create_tables description]
	 * @return [type] [description]
	 */
	static public function create_tables() {
		global $wpdb;

		// Pending Products
		$table_products = $wpdb->prefix . 'paljet_products';
		$sql_products = sanitize_text_field( str_replace( '\\', '', '
CREATE TABLE IF NOT EXISTS ' . $table_products . ' (
	id int(11) NOT NULL AUTO_INCREMENT,
	paljet_id INT(11) unsigned NOT NULL DEFAULT "0",
	data text NOT NULL,
	date_create DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
	PRIMARY KEY (id)
);') );
		$wpdb->query( $sql_products );


		// Pending resources
		$table_resources = $wpdb->prefix . 'paljet_resources';
		$sql_resource = sanitize_text_field( str_replace( '\\', '', '
CREATE TABLE IF NOT EXISTS ' . $table_resources . ' (
	id INT(11) NOT NULL AUTO_INCREMENT,
	paljet_id INT(11) NOT NULL COMMENT "paljet id",
	bucket_id VARCHAR(50) NOT NULL COMMENT "resources id",
	product_id INT(11) NOT NULL DEFAULT "0" COMMENT "woocommerce product id",
	date_create DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
	KEY product (product_id),
	PRIMARY KEY(id)
);') );
		$wpdb->query( $sql_resource );


		// Pending Images
		$table_images = $wpdb->prefix . 'paljet_images';
		$sql_images = sanitize_text_field( str_replace( '\\', '', '
CREATE TABLE IF NOT EXISTS ' . $table_images . ' (
	id INT(11) NOT NULL AUTO_INCREMENT,
	product_id INT(11) NOT NULL DEFAULT "0" COMMENT "Woocommerce Product ID",
	image_url VARCHAR(150) NOT NULL COMMENT "origin image url",
	image_desc VARCHAR(50) NOT NULL COMMENT "origin image description",
	image_format VARCHAR(50) NOT NULL COMMENT "origin image format",
	date_create DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
	KEY product (product_id),
	KEY images (image_url),
	PRIMARY KEY(id)
);') );
		$wpdb->query( $sql_images );

	}

	/**
	 * [update_tables description]
	 * @return [type] [description]
	 */
	static public function update_tables() {
		global $wpdb;

		$table_products 	= $wpdb->prefix . 'paljet_products';
		$table_resources 	= $wpdb->prefix . 'paljet_resources';
		$table_images 		= $wpdb->prefix . 'paljet_images';
		
		$sql_alter_products = sprintf(
			'ALTER TABLE %s ADD COLUMN IF NOT EXISTS paljet_id INT(11) unsigned NOT NULL DEFAULT "0" AFTER id, ADD COLUMN IF NOT EXISTS date_create DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00" COMMENT "creation date";',
			$table_products
		);

		$sql_alter_resources = sprintf(
			'ALTER TABLE %s DROP COLUMN IF EXISTS product_type, ADD COLUMN IF NOT EXISTS date_create DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00" COMMENT "creation date"',
			$table_resources
		);

		$sql_alter_images = sprintf(
			'ALTER TABLE %s DROP COLUMN IF EXISTS product_type, ADD COLUMN IF NOT EXISTS date_create DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00" COMMENT "creation date"',
			$table_images
		);

		$wpdb->query( $sql_alter_products );
		$wpdb->query( $sql_alter_resources );
		$wpdb->query( $sql_alter_images );
	}
}