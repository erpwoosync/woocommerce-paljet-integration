<?php
/**
 * Product Class
 * @since  1.0.0
 * @package Public / Product
 */
class Paljet_Products {

	/**
	 * Pending Attribute to create
	 * @var array
	 */
	public static $PendingAttributes = [];

	/**
	 * Pending Products to create
	 * @var array
	 */
	public static $PendingProducts = [];

	/**
	 * Type ( manual/online )
	 * @var text
	 */
	public static $type;

	/**
	 * Mode ( new/update/images )
	 * @var text
	 */
	public static $mode;


	/**
	 * Sync Static Method
	 * @return number $page
	 */
	public static function sync( $page = 0 ) {
		global $PaljetApi;

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__('Sync Product : Start ( current page : %d )', 'paljet'),
			$page
		) );

		// Start transient
		set_transient( 'paljet_ajax_sync', true, PALJET_MAX_TIME );

		// Get settings
		$settings = get_option( 'paljet_settings', [] );

		Paljet_Logs::add( 'notice', esc_html__( 'Getting products from the Paljet API', 'paljet' ) );

		// Get Products from ERP Paljet
		try {
			$params = apply_filters( 'paljet/sync_product/sync/params', [
				'publica_web'	=> true,
				'solo_activos'	=> true,
				'size'			=> $settings['product_limit'],
				'page'			=> $page,
				'include'		=> 'listas,stock',
			] );

			$format = apply_filters( 'paljet/sync_product/sync/format', [
				'publica_web'	=> '%b',
				'solo_activos'	=> '%b',
				'size'			=> '%d',
				'page'			=> '%d',
				'include'		=> '%s',
			] );

			$request = $PaljetApi->Products->get( $params, $format );
		} catch (Exception $e) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}

		// Check if there is products from the Paljet API
		if( ! $request ) {
			Paljet_Logs::add( 'error', esc_html__( 'There is not products', 'paljet' ) );
			return false;
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( '%s products ready to import', 'paljet' ), count( $request->content )
		) );

		$processed = 0;

		// Type
		self::$type = 'manual';
		
		// Pending Attributes
		self::$PendingAttributes = get_option( 'paljet_attributes', [] );

		// Pending Products
		self::$PendingProducts = self::get_pending_products();

		// Pending Resources
		Paljet_Resources::$PendingResources = Paljet_Resources::get_pending_resources();
		
		foreach( $request->content as $item ) {

			if( ! self::upload( $item ) )
				continue;

			$processed++;
		}

		// Remove transient
		delete_transient( 'paljet_ajax_sync' );

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__('Sync Product : End ( Processed : %d )', 'paljet'),
			$processed
		) );

		return true;
	}

	
	/**
	 * Upload Product
	 * @param  ARRAY  $item   Product data
	 * @return mixed
	 */
	public static function upload( $item ) {

		$output = $content = $product_id = $variation_id = false;

		if( ! isset( $item->id ) )
			return false;

		if( isset( $item->nota ) && ! empty( $item->nota ) )
			$content = paljet_get_content_from_note( $item->nota );

		do_action( 'paljet/sync_product/upload/before', $item );

		if( isset( $item->codigo_externo ) && $item->codigo_externo != null ) { // Variable Product

			if( $content === FALSE || ! isset( $content['tax'] ) ) {
				Paljet_Logs::add( 'error', sprintf(
					esc_html__('Note field has an incorrect structure ( Paljet ID: %s )', 'paljet'),
					$item->id
				) );
				return false;
			}


			if( ( empty( $content['tax'][0] ) || $content['tax'][0] == 'VACIO' ) &&
				( empty( $content['tax'][1] ) || $content['tax'][1] == 'VACIO' )
			) {
				Paljet_Logs::add( 'error', sprintf(
					esc_html__('Note field has empty variations ( Paljet ID: %s )', 'paljet'),
					$item->id
				) );
				return false;
			}

			// Check if the attribute not exist
			if( ( $content['tax'][0] != 'VACIO' &&
				! taxonomy_exists( wc_attribute_taxonomy_name( $content['tax'][0] ) ) ) ||
				( $content['tax'][1] != 'VACIO' &&
				! taxonomy_exists( wc_attribute_taxonomy_name( $content['tax'][1] ) ) )
			 ) {

				self::save_attributes( $content['tax'] );
				self::save_pending_products( $item->id, $item );

				return false;
			}

			$product_id = paljet_get_post_id_from_meta(
				'paljet_parent_code',
				$item->codigo_externo
			);

			
			if( $product_id != FALSE ) { // exist the product father

				$Variable = new WC_Product_Variable( $product_id );
				$product_id = self::save_variable_product( $Variable, $item, $content );

				$variation_id = paljet_get_post_id_from_meta(
					'paljet_variation_id',
					$item->id
				);

				if( $variation_id != FALSE ) { //update

					self::$mode = 'update';
					
					$Variation = new WC_Product_Variation( $variation_id );
					$variation_id = self::save_variation_product(
						$Variation, $item, $content, 'update'
					);

					if( $variation_id === FALSE )
						return false;

				} else { //insert (new variation)

					self::$mode = 'new';

					$Variation = new WC_Product_Variation();
					$variation_id = self::save_variation_product(
						$Variation,
						$item,
						$content,
						'insert',
						$product_id
					);

					if( $variation_id === FALSE )
						return false;
					
					update_post_meta( $variation_id, 'paljet_variation_id', $item->id );
				}

			} else { // doesnt exist the product father

				self::$mode = 'new';

				// First, create the variable product
				$Variable = new WC_Product_Variable();
				$product_id = self::save_variable_product( $Variable, $item, $content );


				if( $product_id === FALSE )
					return false;

				update_post_meta( $product_id, 'paljet_parent_code', $item->codigo_externo );

				// Second, create the variation of the variable product
				$Variation = new WC_Product_Variation();
				$variation_id = self::save_variation_product(
					$Variation,
					$item,
					$content,
					'insert',
					$product_id
				);

				if( $variation_id === FALSE )
					return false;

				update_post_meta( $variation_id, 'paljet_variation_id', $item->id );
			}

			// after: update variable product
			$product_id = self::after_variable_product( $Variable, $item );

			$output = $variation_id;
			
		} else { // Simple Product

			$product_id = paljet_get_post_id_from_meta( 'paljet_product_id', $item->id );

			if( $product_id != FALSE ) { //update

				self::$mode = 'update';

				$Product = new WC_Product_Simple( $product_id );
				$product_id = self::save_simple_product( $Product, $item, $content );

				if( $product_id == FALSE )
					return false;

			} else { //insert ( new product )

				self::$mode = 'update';

				$Product = new WC_Product_Simple();
				$product_id = self::save_simple_product( $Product, $item, $content );

				if( $product_id == FALSE )
					return false;
				
				update_post_meta( $product_id, 'paljet_product_id', $item->id );
			}

			$output = $product_id;
		}

		do_action( 'paljet/sync_product/upload/after', $item, $output );

		return $output;
	}


	/**
	 * Get page totals
	 * @return [type] [description]
	 */
	public static function totals() {
		global $PaljetApi;

		Paljet_Logs::add( 'notice', esc_html__( 'Start products total page', 'paljet' ) );

		// Get settings
		$settings = get_option( 'paljet_settings', [] );

		Paljet_Logs::add( 'notice', esc_html__( 'Getting products from the Paljet API', 'paljet' ) );

		// Get Families from ERP Paljet
		try {
			$params = apply_filters( 'paljet/sync_product/totals/params', [
				'publica_web'	=> true,
				'solo_activos'	=> true,
				'page'			=> 0,
				'size'			=> $settings['product_limit'],
			] );

			$format = apply_filters( 'paljet/sync_product/totals/format', [
				'publica_web'	=> '%b',
				'solo_activos'	=> '%b',
				'page'			=> '%d',
				'size'			=> '%d',
			] );

			$request = $PaljetApi->Products->get( $params, $format );
		} catch (Exception $e) {
			Paljet_Logs::add( 'error', $e->getMessage() );
			return false;
		}


		// Check if there is products from the Paljet API
		if( ! $request ) {
			Paljet_Logs::add( 'error', esc_html__( 'There is not products', 'paljet' ) );
			return false;
		}

		$output = [
			'pages_total'		=> $request->totalPages,
			'products_total'	=> $request->totalElements,
		];

		return apply_filters( 'paljet/sync_product/totals', $output );
	}


	/**
	 * After variable product
	 * @param  OBJ $Product
	 * @param  OBJ $paljet
	 * @return bool
	 */
	public static function after_variable_product( $Product, $paljet ) {

		do_action( 'paljet/sync_product/after_variable/before', $paljet, $Product );
		
		$children = $Product->get_visible_children();

		$tags = [];
		foreach( $children as $child ) {
			$alias_ids = get_post_meta( $child, 'paljet_alias_ids', true );
			$tags = array_merge( $tags, empty( $alias_ids ) ? [] : $alias_ids );
		}

		$Product->set_tag_ids( wp_parse_id_list( $tags ) );
		$product_id = $Product->save();

		do_action( 'paljet/sync_product/after_variable/after', $paljet, $Product );

		return $product_id;
	}

	/**
	 * Save variable product
	 * @param  OBJ    $Product
	 * @param  OBJ    $paljet
	 * @param  ARRAY  $note
	 * @return INT
	 */
	public static function save_variable_product( $Product, $paljet, $note = [] ) {

		do_action( 'paljet/sync_product/variable/before', $paljet, $Product );

		// Product title
		$product_title = ucfirst( strtolower( $paljet->desc_etiqueta ) );

		// Product Brand to title
		if( isset( $paljet->marca ) && isset( $paljet->marca->nombre ) )
			$product_title .= ' ' . $paljet->marca->nombre;


		$Product->set_name( $product_title );
		$Product->set_slug( sanitize_title( $product_title ) );
		$Product->set_status( 'publish' );
		$Product->set_catalog_visibility( 'visible' );
		
		// Description
		if( $note !== FALSE && isset( $note['desc'] ) )
			$Product->set_description( $note['desc'] );
		else
			$Product->set_description('');

		// Tags
		//$paljet_tags_ids = self::get_tags( $paljet );
		//$paljet_tags_ids = self::get_tags( $paljet );
		//$current_tags_ids = $Product->get_tag_ids('edit');
		//$tags_ids = array_unique( array_merge( $current_tags_ids, $paljet_tags_ids ) );
		//$Product->set_tag_ids( $tags_ids );

		// Categories
		$cat = self::get_category( $paljet );

		if( ! empty( $cat ) )
			$Product->set_category_ids( [ $cat ] );

		$attr = [];

		// Brand
		$term_id = self::get_brand( $paljet );

		if( $term_id !== FALSE ) {
			$brand = self::set_brand( $term_id, $paljet );

			if( $brand !== FALSE )
				$attr[] = $brand;
		}


		// Attributes
		if( $note['tax'][0] != 'VACIO' && ! empty( $paljet->modelo ) ) {
			$attr1 = self::set_attr( $paljet->modelo, $note['tax'][0], $Product );
			$attr[] = $attr1;
		}

		if( $note['tax'][1] != 'VACIO' && ! empty( $paljet->medida ) ) {
			$attr2 = self::set_attr( $paljet->medida, $note['tax'][1], $Product );
			$attr[] = $attr2;
		}

		if( count( $attr ) > 0 )
			$Product->set_attributes( $attr );


		$product_id = $Product->save();

		// If error
		if( is_wp_error( $product_id ) ) {
			Paljet_Logs::add( 'notice', sprintf(
				esc_html__('Saving the variable product %s ( Paljet ID: %s )', 'paljet'),
				strtoupper( $product_title ), $paljet->id
			) );

			Paljet_Logs::add( 'error', $product_id->get_error_message() );
			return false;
		}

		// Resources
		Paljet_Resources::process_pending_resources( $paljet, $product_id );


		// search if exist other product with this title
		$duplicate_ids = self::duplicate_by_title( $product_id, $Product->get_name() );

		if( count( $duplicate_ids ) > 0 ) {
			Paljet_Logs::add( 'error', sprintf(
				esc_html__('This product has the same title that those ids [ %s ] ( WC ID: %d, Paljet ID: %d )', 'paljet'),
				implode( ',', $duplicate_ids ), $product_id, $paljet->id
			) );
		}

		// Paljet Alias
		$terms = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );
		$tags = empty( $terms ) || is_wp_error( $terms ) ? [] : $terms;
		
		update_post_meta( $product_id, 'paljet_alias', implode( ',', $tags ) );

		do_action( 'paljet/sync_product/variable/after', $paljet, $Product );

		return $product_id;
	}


	/**
	 * Save variation
	 * @param  OBJ     $Variation
	 * @param  OBJ     $paljet
	 * @param  ARRAY   $note
	 * @param  string  $proccess_type]
	 * @param  integer $parent_id
	 * @return INT
	 */
	public static function save_variation_product( $Variation, $paljet, $note = [], $proccess_type = 'insert', $parent_id = 0 ) {
		
		do_action( 'paljet/sync_product/variation/before', $paljet, $Variation );

		if( $proccess_type == 'insert' )
			$Variation->set_parent_id( $parent_id );

		// Enable variation
		$Variation->set_status( 'publish' );

		// Description
		$Variation->set_description('');

		// Prices
		$prices = self::get_prices( $paljet );

		if( $prices === FALSE )
			return false;

		$Variation->set_regular_price( $prices['regular']['tax'] );
		$meta_price_notax = $prices['regular']['no_tax'];

		if( $prices['sale'] > 0 ) {
			$Variation->set_sale_price( $prices['sale'] );
			$meta_price_notax = $prices['sale']['no_tax'];
		} else
			$Variation->set_sale_price( '' );

		// SKU only if the product is new
		if( $Variation->get_id() == 0 ) {
			$sku = wc_product_generate_unique_sku( $Variation->get_id(), $paljet->id );
			$Variation->set_sku( $sku );
		}

		//Stock
		$stock = self::get_stock( $paljet );

		if( $stock === FALSE )
			return false;

		$Variation->set_manage_stock( true );

		if( $stock > 0 ) {
			$Variation->set_stock_quantity( $stock );
			$Variation->set_stock_status( 'instock' );
		} else {
			$Variation->set_stock_quantity( 0 );
			$Variation->set_stock_status(' outstock' );
		}

		// Set weight
		if( isset( $note['weight'] ) && $note['weight'] > 0 )
			$Variation->set_weight( $note['weight'] );

		// Set dimensions
		if( isset( $note['size'] ) && array_sum( $note['size'] ) > 0 ) {
			$Variation->set_length( $note['size']['length'] );
			$Variation->set_width( $note['size']['width'] );
			$Variation->set_height( $note['size']['height'] );
		}

		// Visibility
		/*if( $cianbox->vigente == true )
			$Variation->set_status( 'publish' );
		else
			$Variation->set_status( 'private' );*/

		// Visibility
		//$visbility = $item->vigente == true ? 'visible' : 'hidden';
		//$Product->set_catalog_visibility( $visbility );

		$Variation->set_downloadable( 'no' );
		$Variation->set_virtual( 'no' );

		
		// Attributes
		$attr = [];

		if( $note['tax'][0] != 'VACIO' && ! empty( $paljet->modelo ) ) {
			$key_tax = wc_attribute_taxonomy_name( $note['tax'][0] );
			$attr[ $key_tax ] = sanitize_title( $paljet->modelo );
		}

		if( $note['tax'][1] != 'VACIO' && ! empty( $paljet->medida ) ) {
			$key_tax = wc_attribute_taxonomy_name( $note['tax'][1] );
			$attr[ $key_tax ] = sanitize_title( $paljet->medida );
		}

		if( count( $attr ) > 0 )
			$Variation->set_attributes( $attr );


		$variation_id = $Variation->save();

		// If error
		if( is_wp_error( $variation_id ) ) {
			Paljet_Logs::add( 'notice', sprintf(
				esc_html__('Saving the variation %s ( Paljet ID: %s )', 'paljet'),
				strtoupper( $product_title ), $paljet->id
			) );

			Paljet_Logs::add( 'error', $variation_id->get_error_message() );
			return false;
		}
		
		// Resources
		/*Paljet_Resources::save_pending_resources(
			$paljet,
			$variation_id,
			$Variation->get_parent_id()
		);*/


		// Log history product
		Paljet_Logs::product_history( $Variation->get_parent_id(), self::$type, self::$mode );


		// External Plugin: Quantity
		if( $paljet->empaque > 0 ) {
			update_post_meta(
				$Variation->get_parent_id(),
				'_alg_wc_pq_min_' . $variation_id,
				floatval( $paljet->empaque )
			);

			update_post_meta(
				$Variation->get_parent_id(),
				'_alg_wc_pq_step_' . $variation_id,
				floatval( $paljet->empaque )
			);

			update_post_meta( $Variation->get_parent_id(), '_alg_wc_pq_max', 0 );
		}

		// Paljet Alias
		$tags_ids = self::get_tags( $paljet );

		if( ! empty( $paljet->alias ) && $paljet->alias != 'null' )
			update_post_meta( $variation_id, 'paljet_alias', $paljet->alias );

		update_post_meta( $variation_id, 'paljet_alias_ids', $tags_ids );

		// Paljet Price No Tax
		update_post_meta( $variation_id, 'paljet_price_notax', $meta_price_notax );

		// Paljet Code
		update_post_meta( $variation_id, 'paljet_code', $paljet->codigo );
		
		do_action( 'paljet/sync_product/variation/after', $paljet, $Variation );

		return $variation_id;
	}


	/**
	 * Save simple Product on WooCommerce
	 * @param  OBJ $Product
	 * @param  OBJ $paljet
	 * @return INT
	 */
	public static function save_simple_product( $Product, $paljet, $note = [] ) {

		do_action( 'paljet/sync_product/simple/after', $paljet, $Product );

		// Product title
		$product_title = ucfirst( strtolower( $paljet->desc_etiqueta ) );

		// Product Brand to title
		if( isset( $paljet->marca ) && isset( $paljet->marca->nombre ) )
			$product_title .= ' ' . $paljet->marca->nombre;


		$Product->set_name( $product_title );
		$Product->set_slug( sanitize_title( $product_title ) );
		$Product->set_status( 'publish' );
		$Product->set_catalog_visibility( 'visible' );

		// Description
		if( $note !== FALSE && isset( $note['desc'] ) )
			$Product->set_description( $note['desc'] );
		else
			$Product->set_description('');

		// tags_ids
		$tags_ids = self::get_tags( $paljet );
		$Product->set_tag_ids( $tags_ids );

		// SKU only if the product is new
		if( $Product->get_id() == 0 ) {
			$sku = wc_product_generate_unique_sku( $Product->get_id(), $paljet->id );
			$Product->set_sku( $sku );
		}
		
		$prices = self::get_prices( $paljet );

		if( $prices === FALSE )
			return false;
		
		$Product->set_regular_price( $prices['regular']['tax'] );
		$meta_price_notax = $prices['regular']['no_tax'];

		if( $prices['sale']['tax'] > 0 ) {
			$Product->set_sale_price( $prices['sale']['tax'] );
			$meta_price_notax = $prices['sale']['no_tax'];
		} else
			$Product->set_sale_price( '' );


		$Product->set_backorders( 'no' );
		$Product->set_reviews_allowed( true );
		$Product->set_sold_individually( false );
		
		//Stock
		$stock = self::get_stock( $paljet );

		if( $stock === FALSE  )
			return false;

		$Product->set_manage_stock( true );

		if( $stock > 0 ) {
			$Product->set_stock_quantity( $stock );
			$Product->set_stock_status( 'instock' );
		} else {
			$Product->set_stock_quantity( 0 );
			$Product->set_stock_status( 'outstock' );
		}

		// Set weight
		if( isset( $note['weight'] ) && $note['weight'] > 0 )
			$Product->set_weight( $note['weight'] );

		// Set dimensions
		if( isset( $note['size'] ) && array_sum( $note['size'] ) > 0 ) {
			$Product->set_length( $note['size']['length'] );
			$Product->set_width( $note['size']['width'] );
			$Product->set_height( $note['size']['height'] );
		}

		// Visibility
		//$visibility = $paljet->vigente == true ? 'visible' : 'hidden';
		//$Product->set_catalog_visibility( $visibility );

		// Categories
		$cat = self::get_category( $paljet );

		if( ! empty( $cat ) )
			$Product->set_category_ids( [ $cat ] );
 
		// Brand
		$term_id = self::get_brand( $paljet );

		if( ! empty( $term_id ) )
			$brand = self::set_brand( $term_id, $paljet );

		if( $brand !== FALSE )
			$Product->set_attributes( [ $brand ] );

		$product_id = $Product->save();

		// If error
		if( is_wp_error( $product_id ) ) {
			Paljet_Logs::add( 'notice', sprintf(
				esc_html__('Saving the simple product %s ( Paljet ID: %s )', 'paljet'),
				strtoupper( $product_title ), $paljet->id
			) );

			Paljet_Logs::add( 'error', $product_id->get_error_message() );
			return false;
		}
		
		// Resources
		Paljet_Resources::process_pending_resources( $paljet, $product_id );

		// Log history product
		Paljet_Logs::product_history( $product_id, self::$type, self::$mode );

		// search if exist other product with this title
		$duplicate_ids = self::duplicate_by_title( $product_id, $Product->get_name() );

		if( count( $duplicate_ids ) > 0 ) {
			Paljet_Logs::add( 'error', sprintf(
				esc_html__('This product has the same title that those ids [ %s ] ( WC ID: %d, Paljet ID: %d )', 'paljet'),
				implode( ',', $duplicate_ids ), $product_id, $paljet->id
			) );
		}

		
		// External Plugin: Quantity
		if( $paljet->empaque > 0 ) {
			update_post_meta( $product_id, '_alg_wc_pq_min', floatval( $paljet->empaque ) );
			update_post_meta( $product_id, '_alg_wc_pq_step', floatval( $paljet->empaque ) );
			update_post_meta( $product_id, '_alg_wc_pq_max', 0 );
		}

		// Paljet Alias
		$terms = wp_get_post_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );
		$tags = empty( $terms ) || is_wp_error( $terms ) ? [] : $terms;
		update_post_meta( $product_id, 'paljet_alias', implode( ',', $tags ) );

		// Paljet Price No Tax
		update_post_meta( $product_id, 'paljet_price_notax', $meta_price_notax );

		// Paljet Code
		update_post_meta( $product_id, 'paljet_code', $paljet->codigo );

		do_action( 'paljet/sync_product/simple/before', $paljet, $Product );
 
		return $product_id;
	}


	/**
	 * Hide product
	 * @param  int $product_id
	 * @return bool
	 */
	public static function hide_product( $product_id = 0 ) {
		
		if( empty( $product_id ) )
			return false;

		// Check if the post exists
		if( ! get_post( $product_id ) )
			return false;

		// Instance product
		$Product = wc_get_product( $product_id );

		// Stock to 0
		$Product->set_stock_quantity( 0 );
		$Product->set_stock_status( 'outstock' );

		// Disable variation
		if( $Product->is_type( 'variation' ) )
			$Product->set_status( 'private' );

		// Hidde product if it is simple
		if( $Product->is_type( 'simple' ) )
			$Product->set_catalog_visibility( 'hidden' );
		
		$Product->save();

		// If it is a variation
		if( $Product->is_type( 'variation' ) ) {

			$Parent = wc_get_product( $Product->get_parent_id() );
			$children = $Parent->get_visible_children();

			// If all the children are hidden, hide the parent product too
			if( empty( $children ) ) {
				$Parent->set_catalog_visibility( 'hidden' );
				$Parent->save();
			}
		}

		do_action( 'paljet/sync_product/hide', $Product );
		
		return true;
	}

	/**
	 * Get Prices
	 * @param  OBJECT $paljet
	 * @return ARRAY
	 */
	public static function get_prices( $paljet ) {
		$prices = [
			'regular'	=> [ 'no_tax' => 0, 'tax' => 0 ],
			'sale'		=> [ 'no_tax' => 0, 'tax' => 0 ],
		];

		if( ! isset( $paljet->listas ) ) {
			Paljet_Logs::add( 'error',
				sprintf( esc_html__( 'No exists the prices lists node on the API response ( Paljet ID : %d )', 'paljet' ), $paljet->id )
			);
			return false;
		}

		$settings = get_option( 'paljet_settings', [] );

		foreach( $paljet->listas as $lists ) {

			if( ! isset( $lists->lista->id ) )
				continue;

			$list_id = $lists->lista->id;

			if( $settings['price_regular'] == $list_id ) {
				$prices['regular']['no_tax'] = round( $lists->pr_venta, 2 );
				$prices['regular']['tax'] = round( $lists->pr_final, 2 );
				continue;
			}

			if( isset( $settings['price_sale'] ) && $settings['price_sale'] == $list_id ) {
				$prices['sale']['no_tax'] = round( $lists->pr_venta, 2 );
				$prices['sale']['tax'] = round( $lists->pr_final, 2 );
				continue;
			}
		}

		// Check sale price
		if( $prices['sale']['tax'] > 0 && $prices['sale']['tax'] >= $prices['regular']['tax'] ) {
			$prices['sale']['tax'] = 0;
			$prices['sale']['no_tax'] = 0;
		}

		return apply_filters( 'paljet/sync_product/prices', $prices, $paljet );
	}


	/**
	 * Get Stock
	 * @param  OBJECT $paljet
	 * @return INT
	 */
	public static function get_stock( $paljet ) {

		if( ! isset( $paljet->stock ) ) {
			Paljet_Logs::add( 'error',
				sprintf( esc_html__( 'No exists the stock node on the API response ( Paljet ID : %d )', 'paljet' ), $paljet->id )
			);
			return false;
		}

		$settings = get_option( 'paljet_settings', [] );

		$total_stock = 0;

		foreach( $paljet->stock as $warehouses ) {
			if( ! isset( $warehouses->deposito ) )
				continue;

			if( in_array( $warehouses->deposito->id, $settings['warehouses'] ) )
				$total_stock += floatval( $warehouses->disponible );
		}
		
		return apply_filters( 'paljet/sync_product/stock', $total_stock, $paljet );
	}

	/**
	 * [get_tags description]
	 * @param  [type] $paljet [description]
	 * @return [type]         [description]
	 */
	public static function get_tags( $paljet ) {
		
		//$tags_ids = $Product->get_tag_ids('edit');

		if( empty( $paljet->alias ) || $paljet->alias == 'null' )
			return [];

		$tags_ids = [];
		$alias_commas = array_map( 'trim', explode( ';', $paljet->alias ) );

		foreach( $alias_commas as $alias ) {
			
			$name_term = wc_sanitize_term_text_based( $alias );
			$data_term = term_exists( $name_term, 'product_tag' );

			if( ! empty( $data_term ) ) {
				$tags_ids[] = $data_term['term_id'];
				continue;
			}

			$data_term = wp_insert_term( $name_term, 'product_tag' );

			// If it has an error
			if( is_wp_error( $data_term ) ) {
				Paljet_Logs::add( 'error', sprintf(
					esc_html__('Creating the product tag %s', 'paljet'),
					strtoupper( $name_term )
				) );

				Paljet_Logs::add( 'error', $data_term->get_error_message() );
				continue;
			}

			$tags_ids[] = $data_term['term_id'];
		}

		return apply_filters( 'paljet/sync_product/tags', $tags_ids, $paljet );
	}

	/**
	 * Get Categories from Paljet API
	 * @param  OBJECT $paljet
	 * @return ARRAY
	 */
	public static function get_category( $paljet ) {

		// if no exists the category node on the API response
		if( ! isset( $paljet->categoria ) ) {
			return false;
		}
		
		$term_id = paljet_get_term_id_from_meta( 'paljet_cat_id', $paljet->categoria->id );
	
		if( $term_id === FALSE )
			return '';	

		return apply_filters( 'paljet/sync_product/category', $term_id, $paljet );
	}

	public static function get_brand( $paljet ) {

		if( ! isset( $paljet->marca ) ) {
			Paljet_Logs::add( 'error',
				sprintf( esc_html__( 'No exists the brand node on the API response ( Paljet ID : %d )', 'paljet' ), $paljet->id )
			);
			return false;
		}

		$term_id = paljet_get_term_id_from_meta( 'paljet_brand_id', $paljet->marca->id );

		if( $term_id === FALSE )
			return '';

		return apply_filters( 'paljet/sync_product/brand', $term_id, $paljet );
	}

	/**
	 * Set brand like a WC attribute
	 * @param OBJECT $paljet
	 */
	public static function set_brand( $term_id, $paljet ) {

		$attribute = new stdClass();
		$settings = get_option( 'paljet_settings', [] );
		
		$data['tax_name'] = sanitize_text_field($settings['attribute_brand']);
		$data['tax_slug'] = wc_attribute_taxonomy_name($settings['attribute_brand']);
		$data['tax_id'] = wc_attribute_taxonomy_id_by_name($data['tax_slug']);

		$dataTerm = get_term_by( 'id', $term_id, $data['tax_slug'] );

		if( is_wp_error( $dataTerm ) ) {
			Paljet_Logs::add( 'error', sprintf( esc_html__( 'There was a problem getting the brand term in WooCommerce ( Product ID : %d )', 'paljet' ), $paljet->id ) );
			return false;
		}

		$data['brand_id']	= $term_id;
		$data['brand_name']	= isset( $dataTerm->name ) ? $dataTerm->name : '';
		$data['brand_slug']	= isset( $dataTerm->slug ) ? $dataTerm->slug : '';

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( $data['tax_id'] );
		$attribute->set_name( $data['tax_slug'] );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attribute->set_options( [ $data['brand_slug'] ] );

		return $attribute;
	}


	public static function set_attr($term_name, $attr_name, $Product) {

		$data['tax_name']	= sanitize_text_field( $attr_name );
		$data['tax_slug']	= wc_attribute_taxonomy_name( $attr_name );
		$data['tax_id']		= wc_attribute_taxonomy_id_by_name( $data['tax_slug'] );

		$term_label = sanitize_text_field( $term_name );
		$term_slug = sanitize_title( $term_name );

		$data_term = term_exists( $term_slug, $data['tax_slug'] );

		if( 0 == $data_term || null == $data_term ) {
			$data_term = wp_insert_term( $term_label, $data['tax_slug'] );
		}

		$attribute = new stdClass();

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( $data['tax_id'] );
		$attribute->set_name( $data['tax_slug'] );
		$attribute->set_visible( true );
		$attribute->set_variation( true );
		
		//$attribute->set_options( [ $term_slug ] );

		$list_options = [];

		$all_terms = $Product->get_attribute( $data['tax_name'] );

		if( ! empty( $all_terms ) ) {
			if( strpos( $all_terms, ',' ) !== FALSE )
				$list_options = array_map( 'trim', explode(',', $all_terms ) );
			else
				$list_options[] = trim( $all_terms );
		}

		if( ! in_array( trim( $term_label ), $list_options ) )
			$list_options[] = trim( $term_label );

		$attribute->set_options( $list_options );

		return $attribute;
	}


	/**
	 * save attributes products
	 * @param  array  $taxonomies [description]
	 * @return bool
	 */
	static public function save_attributes( $taxonomies = [] ) {

		foreach( $taxonomies as $tax ) {

			if( $tax != 'VACIO' && ! in_array( $tax, self::$PendingAttributes ) )
				self::$PendingAttributes[] = $tax;
		}

		update_option( 'paljet_attributes', self::$PendingAttributes );

		return true;
	}




	/*
			CRON TASK
	 */
	
	public static function set_pending_products() {

		// It means a Paljet ajax process is executing
		if( get_transient( 'paljet_ajax_sync' ) )
			return false;

		// type
		self::$type = 'online';

		// Pending Attributes
		self::$PendingAttributes = get_option( 'paljet_attributes', [] );

		// Get pending products
		self::$PendingProducts = $AltPendingProduct = self::get_pending_products();

		if( count( self::$PendingProducts ) == 0 )
			return;

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__('Set Pending Products : Start ( total : %d ) [includes blank]', 'paljet'),
			count( $AltPendingProduct )
		) );


		// Take the 60% of time
		$time_max = intval( 0.6 * PALJET_MAX_TIME );
		$time_start = time();
	
		$processed = 0;

		foreach( $AltPendingProduct as $content ) {

			if( ! isset( $content['data'] ) || empty( $content['data'] ) )
				continue;

			$item = maybe_unserialize( $content['data'] );

			if( empty( $item ) ) {
				Paljet_Logs::add( 'error', sprintf(
					esc_html__( 'Data from Paljet Product has no a valid structure ( Paljet ID : %d )', 'paljet' ),
					$content['paljet_id']
				) );
				self::delete_pending_products( $content['id'] );
				continue;
			}

			if( self::upload( $item ) )
				self::delete_pending_products( $content['id'] );

			$processed++;

			// We verify if the time is finished
			$time_current = time();
			$time_total = $time_current - $time_start;

			if( $time_total >= $time_max ) {
				
				$missing = count( $AltPendingProduct ) - $processed;

				Paljet_Logs::add( 'notice', sprintf(
					esc_html__( 'Set Pending Products : End ( Processed : %d, Missing : %d )', 'paljet' ),
					$processed,
					$missing
				) );
				break;
			}
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Set Pending Products : End ( Processed : %d )', 'paljet' ),
			$processed
		) );

		return true;
	}


	public static function complete_pending_products() {

		// It means a Paljet ajax process is executing
		if( get_transient( 'paljet_ajax_sync' ) )
			return false;

		global $PaljetApi;

		// Get pending products
		$PendingProduct = self::get_pending_products();

		if( count( $PendingProduct ) == 0 )
			return;

		Paljet_Logs::add( 'notice',	esc_html__('Complete Pending Products : Start', 'paljet') );

		// Take the 60% of time
		$time_max = intval( 0.6 * PALJET_MAX_TIME );
		$time_start = time();
	
		$processed = 0;

		foreach( $PendingProduct as $content ) {

			// Only empty
			if( ! empty( $content['data'] ) )
				continue;
			
			// Get Products from ERP Paljet
			try {
				$params = apply_filters( 'paljet/sync_product/complete/params', [
					'art_id'		=> intval( $content['paljet_id'] ),
					'publica_web'	=> true,
					'solo_activos'	=> true,
					'include'		=> 'listas,stock',
				] );

				$format = apply_filters( 'paljet/sync_product/complete/format', [
					'publica_web'	=> '%b',
					'solo_activos'	=> '%b',
					'art_id'		=> '%d',
					'include'		=> '%s',
				] );

				$request = $PaljetApi->Products->get( $params, $format );

			} catch (Exception $e) {
				Paljet_Logs::add( 'error', $e->getMessage() );
				continue;
			}

			// Check if there is error
			if( ! $request ) {

				Paljet_Logs::add( 'error', sprintf(
					esc_html__( 'Error in request from API ( Paljet ID : %d )', 'paljet' ),
					intval( $content['paljet_id'] )
				) );
				continue;
			}


			// Check if there is products from the Paljet API
			if( ! isset( $request->content ) || empty( $request->content ) ) {

				$product_found = false;

				// Check if this product exist on the WooCommerce
				$product_id = paljet_get_post_id_from_meta(
					'paljet_product_id',
					intval( $content['paljet_id'] )
				);

				if( $product_id != FALSE ) {
					$product_found = true;
					self::hide_product( $product_id );

				} else {
					$variation_id = paljet_get_post_id_from_meta(
						'paljet_variation_id',
						intval( $content['paljet_id'] )
					);

					if( $variation_id != FALSE ) {
						$product_found = true;
						self::hide_product( $variation_id );
					}
				}

				// Delete from pending product
				self::delete_pending_products( $content['id'] );


				if( $product_found ) {
					Paljet_Logs::add( 'error', sprintf(
						esc_html__( 'Product has no anymore publica_web set to true ( Paljet ID : %d ). Stock to 0 and hided', 'paljet' ),
						intval( $content['paljet_id'] )
					) );

				} else {
					Paljet_Logs::add( 'error', sprintf(
						esc_html__( 'Product not found ( Paljet ID : %d ). Deleted', 'paljet' ),
						intval( $content['paljet_id'] )
					) );
				}
				continue;
			}

			$processed = 0;

			$item = reset( $request->content );

			// Check the category
			$update_category = false;
			$paljet_category = self::get_category( $item );
			
			if( $paljet_category !== FALSE && empty( $paljet_category ) ) {
				Paljet_Categories::sync();
			}
			
			// Check the brand
			$paljet_brand = self::get_brand( $item );

			if( $paljet_brand !== FALSE && empty( $paljet_brand ) ) {
				Paljet_Brands::create( $item );
			}

			self::update_pending_products( $content['id'], $item );

			$processed++;

			// We verify if the time is finished
			$time_current = time();
			$time_total = $time_current - $time_start;

			if( $time_total >= $time_max ) {

				Paljet_Logs::add( 'notice', sprintf(
					esc_html__( 'Complete Pending Products : End ( Processed : %d )', 'paljet' ),
					$processed
				) );
				break;
			}
		}

		Paljet_Logs::add( 'notice', sprintf(
			esc_html__( 'Complete Pending Products : End ( Processed : %d )', 'paljet' ),
			$processed
		) );

		return true;
	}


	/*
		Methods to pending products
	 */

	public static function get_pending_products() {
		global $wpdb;

		$table = $wpdb->prefix . 'paljet_products';
		$sql = 'SELECT * FROM ' . $table;
		
		$results = $wpdb->get_results( $sql, ARRAY_A );

		$output = $wpdb->num_rows > 0 ? $results : [];

		return apply_filters( 'paljet/sync_product/pending_products', $output );
	}


	public static function save_pending_products( $paljet_id, $paljet ) {

		// If paljet ID is empty
		if( empty( $paljet_id ) )
			return false;

		// If there are pending products
		if( count( self::$PendingProducts ) > 0 ) {
			$pending_paljet_ids = wp_list_pluck( self::$PendingProducts, 'paljet_id' );

			if( in_array( $paljet_id, $pending_paljet_ids ) )
				return false;
		}

		global $wpdb;

		$ivalues = [];
		$table = $wpdb->prefix . 'paljet_products';

		$paljet_data = ! empty( $paljet  ) ? maybe_serialize( $paljet ) : '';
		
		$wpdb->insert(
			$table,
			[
				'paljet_id'		=> $paljet_id,
				'data'			=> $paljet_data,
				'date_create'	=> current_time( 'mysql' )
			],
			[ '%d', '%s', '%s' ]
		);

		return $wpdb->insert_id;
	}

	public static function update_pending_products( $id, $paljet ) {

		// If ID is empty
		if( empty( $id ) )
			return false;

		global $wpdb;

		$ivalues = [];
		$table = $wpdb->prefix . 'paljet_products';

		$paljet_data = ! empty( $paljet  ) ? maybe_serialize( $paljet ) : '';
		
		$wpdb->update(
			$table,
			[ 'data' => $paljet_data ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		return true;
	}


	public static function delete_pending_products( $id = null ) {

		if( is_null($id) )
			return;

		global $wpdb;
		$table = $wpdb->prefix.'paljet_products';

		$wpdb->delete( $table, [ 'id' => intval( $id ) ], [ '%d' ] );

		return true;
	}


	/*
		Methods to pending brands or categories
	 */
	
	public static function process_pending_attrs( $paljet_attr_id = '', $wc_attr_id = '', $type = 'categories' ) {

		$pending_sync = get_option( 'paljet_pending_attrs', [] );

		if( ! isset( $pending_sync[ $type ][ $paljet_attr_id ] ) )
			return false;

		$paljet_product_id = $pending_sync[ $type ][ $paljet_attr_id ];

		$product_id = paljet_get_post_id_from_meta( 'paljet_product_id', $paljet_product_id );

		// it is wrong, it must be simple or variable
		$Product = new WC_Product( $product_id );

		switch( $type ) {

			default: $Product->set_category_ids( [ $wc_attr_id ] );
		}

		$product_id = $Product->save();

		unset( $pending_sync[ $type ][ $paljet_attr_id ] );

		update_option( 'paljet_pending_attrs', $pending_sync );
	}
	
	public static function save_attr_pending( $paljet, $type = 'categories' ) {

		switch( $type ) {
			case 'brands'	: $paljet_attr_id = $paljet->marca->id; break;
			default 		: $paljet_attr_id = $paljet->categoria->id;
		}
		
		$pending_sync = get_option( 'paljet_pending_attrs', [] );
		$pending_sync[ $type ][ $paljet_attr_id ] = $paljet->id;

		update_option( 'paljet_pending_attrs', $pending_sync );

		return true;
	}


	/*
		UTILITIES
	 */
	
	/**
	 * [duplicate_by_title description]
	 * @param  integer $product_id    [description]
	 * @param  string  $product_title [description]
	 * @return [type]                 [description]
	 */
	public static function duplicate_by_title( $product_id = 0, $product_title = '' ) {
		if( empty( $product_title ) )
			return [];

		global $wpdb;

		$query = 'SELECT
					ID
				FROM
					'. $wpdb->posts .'
				WHERE
					post_type = "product" AND post_title = %s';

		$args = [ $product_title ];

		if( ! empty( $product_id ) ) {
			$query .= ' AND ID <> %d';
			$args[] = $product_id;
		}

		$results = $wpdb->get_col( $wpdb->prepare( $query, $args ) );

		if( count( $results ) == 0 )
			return [];

		return apply_filters('paljet/sync_product/duplicate_products', $results, $product_title, $product_id );
	}
}