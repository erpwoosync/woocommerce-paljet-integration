<?php
/**
 * Metaboxes Class
 * @since  1.0.0
 * @package Admin / Metaboxes
 */
class Paljet_Metaboxes {
	
	/**
	 * Construct method
	 */
	public function __construct() {

		// Add scripts JS or CSS
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ]);

		// Product edit
		add_action( 'add_meta_boxes', [ $this, 'product_meta_boxes' ], 40 );
		
		// Product column
		add_filter( 'manage_product_posts_columns', [ $this, 'product_name_column' ]);
		add_action( 'manage_product_posts_custom_column', [ $this, 'product_value_column'], 10, 2);
	}


	/**
	 * Register/Include scripts JS or CSS
	 * @return [type] [description]
	 */
	public function enqueue_scripts() {
		global $pagenow;

		// $pagenow
		// edit.php is the products list
		// post.php is the product detail

		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : get_post_type();

		// To Products
		if( $post_type == 'product' && in_array( $pagenow, [ 'edit.php', 'post.php' ] ) ) {
			wp_enqueue_style(
				'paljet_product_css',
				PALJET_PLUGIN_URL . 'resources/assets/css/admin-products.css'
			);

			wp_enqueue_script(
				'paljet_product_js',
				PALJET_PLUGIN_URL . 'resources/assets/js/admin-products.js',
				[ 'jquery' ], false, true
			);

			$img_loading = '<img src=" '.admin_url( 'images/spinner.gif' ).'" alt="loading" />';
			$img_success = '<img src=" '.admin_url( 'images/yes.png' ).'" alt="yes" />';
			$img_failure = '<img src=" '.admin_url( 'images/no.png' ).'" alt="no" />';

			wp_localize_script( 'paljet_product_js', 'paljet_vars', [
				'url_ajax'			=> admin_url( 'admin-ajax.php' ),
				'img_loading'		=> $img_loading,
				'img_success'		=> $img_success,
				'img_failure'		=> $img_failure,
				'screen'			=> $pagenow == 'post.php' ? 'detail' : 'list',
				'nonce'				=> wp_create_nonce( 'paljet-wpnonce' ),
			]);
		}
	}

	/**
	 * Name column on the products list
	 * @param  array  $columns
	 * @return array
	 */
	public function product_name_column( $columns = [] ) {

		$columns['wc_actions'] = esc_html__( 'Actions', 'paljet' );
		
		return apply_filters( 'paljet/metaboxes/product_columns/name', $columns );
	}


	/**
	 * Value column on the products list
	 * @param  string  $column
	 * @param  integer $post_id
	 * @return mixed
	 */
	public function product_value_column( $column = '', $post_id = 0 ) {

		if( $column == 'wc_actions' ) {

			$args = [
				'action'	=> 'paljet_sync_images',
				'title'		=> esc_html__( 'Sync Images from Paljet', 'paljet' ),
				'product'	=> $post_id,
			];

			wc_get_template(
				'resources/layouts/admin/button_sync_images.php',
				$args, false, PALJET_PLUGIN_DIR
			);
		}
	}

	/**
	 * [product_meta_boxes description]
	 * @return [type] [description]
	 */
	public function product_meta_boxes() {
		
		// Button Sync Image
		add_meta_box(
			'paljet-resources',
			esc_html__( 'Actions', 'paljet' ),
			[ $this, 'product_meta_actions' ],
			'product', 'side', 'low'
		);


		add_meta_box(
			'paljet-history',
			esc_html__( 'History ( Maximum 15 messages )', 'paljet' ),
			[ $this, 'product_history' ],
			'product', 'normal', 'low'
		);
	}

	/**
	 * [product_meta_actions description]
	 * @param  [type] $post [description]
	 * @return [type]       [description]
	 */
	public function product_meta_actions( $post ) {

		$args = [
			'action'	=> 'paljet_sync_images',
			'title'		=> esc_html__( 'Sync Images from Paljet', 'paljet' ),
			'product'	=> $post->ID,
		];

		wc_get_template(
			'resources/layouts/admin/product_sync_images.php',
			$args, false, PALJET_PLUGIN_DIR
		);
	}

	/**
	 * Product History
	 * @param  OBJECT $post [description]
	 * @return HTML
	 */
	public function product_history( $post ) {

		$histories = get_post_meta( $post->ID, 'paljet_history', true );

		$args = [
			'histories'	=> ! empty( $histories ) ? $histories : [],
		];

		wc_get_template(
			'resources/layouts/admin/product_history.php',
			$args, false, PALJET_PLUGIN_DIR
		);
	}
}

new Paljet_Metaboxes();