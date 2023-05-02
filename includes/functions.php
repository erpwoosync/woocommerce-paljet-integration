<?php
if( !function_exists('paljet_get_term_id_from_meta') ) {
	function paljet_get_term_id_from_meta( $metakey = null, $metavalue = null ) {
		global $wpdb;

		if( is_null( $metakey ) || is_null( $metavalue ) )
			return false;

		$query = 'SELECT
				term_id
			FROM
				'.$wpdb->termmeta.'
			WHERE
				meta_key=%s && meta_value=%d
			LIMIT 1';

		$term_id = $wpdb->get_var( $wpdb->prepare( $query, $metakey, $metavalue ) );

		if( is_null( $term_id ) )
			return false;

		return $term_id;
	}
}


if( !function_exists('paljet_get_post_id_from_meta') ) {
	function paljet_get_post_id_from_meta($metakey = null, $metavalue = null) {

		global $wpdb;

		if( is_null( $metakey ) || is_null( $metavalue ) )
			return false;

		$query = 'SELECT post_id FROM '.$wpdb->postmeta.' WHERE meta_key=%s && meta_value=%s LIMIT 1';

		$post_id = $wpdb->get_var( $wpdb->prepare($query, $metakey, $metavalue) );

		if( is_null($post_id) )
			return false;

		return $post_id;
	}
}


if( ! function_exists('paljet_dropdown_wc_attributes') ) {
	function paljet_dropdown_wc_attributes() {

		$options = [ '' => esc_html__( 'Choose your attribute', 'paljet' ) ];

		$attributes = wp_list_pluck(
			wc_get_attribute_taxonomies(),
			'attribute_label', 'attribute_name'
		);

		$options = $options + $attributes;

		return apply_filters( 'paljet/dropdown/attributes', $options );
	}
}


if( ! function_exists('paljet_dropdown_prices_lists') ) {
	function paljet_dropdown_prices_lists() {
		$prices = get_option( 'paljet_prices', [] );

		if( count( $prices ) == 0 ) {
			$options = [ '' => esc_html__( 'No prices lists', 'paljet' ) ];
		} else {
			$options = [ '' => esc_html__( 'Choose your price list', 'paljet' ) ];
			$options = $options + $prices ;
		}

		return apply_filters( 'paljet/dropdown/prices_lists', $options );
	}
}



if( ! function_exists('paljet_links_get_id') ) {
	function paljet_links_get_id( $url = '' ) {

		if( empty( $url ) || strrpos( $url, '/' ) == FALSE )
			return 0;

		$parts = explode( '/', $url );
		$last_key = count( $parts ) - 1;
		$id = intval( $parts[ $last_key ] );

		return apply_filters( 'paljet/links/get_id', $id, $url );
	}
}


if( ! function_exists( 'paljet_get_content_from_note' ) ) {
	function paljet_get_content_from_note( $note = '' ) {

		if( empty( $note ) ) {
			return false;
		}

		if( strpos( $note, '@@#' ) === FALSE )
			return [ 'desc' => $note ];

		$array_note = explode( '@@#', $note );

		// Description
		$output['desc'] = $array_note[0];

		$content = explode( "\n", $array_note[1] );

		foreach( $content as $row ) {
			$parts = explode( ';', $row );

			switch( $parts[0] ) {
				case 'TAMAÑO' :
					$lwh = explode( '-', $parts[1] );

					if( count( $lwh ) == 3 ) {
						$output['size']['length'] = $lwh[0];
						$output['size']['width'] = $lwh[1];
						$output['size']['height'] = $lwh[2];
					}

					break;
				case 'PESO' : $output['weight'] = $parts[1]; break;
				case 'VARIACIONES' :
					$output['tax'][0] = $parts[1];
					$output['tax'][1] = $parts[2];
					break;
			}
		}

		return apply_filters( 'paljet/note/get_content', $output, $note );
	}
}


if( !function_exists('paljet_upload_media') ) {
	function paljet_upload_media( $image_url, $image_format, $desc = null ){
		
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/media.php');
		
		//$media_id = media_sideload_image( $image_url, 0, $desc, 'id' );
		
		/*$attachments = get_posts(
							array(
								'post_type'		=> 'attachment',
								'post_status'	=> null,
								'post_parent'	=> 0,
								'orderby'		=> 'post_date',
								'order'			=> 'DESC'
							)
						);
		*/
	
		if ( empty( $image_url ) )
			return false;
 
		switch( $image_format ) {
			case 'image/jpeg'	: $extension = '.jpg'; break;
			case 'image/gif'	: $extension = '.gif'; break;
			case 'image/png'	: $extension = '.png'; break;
			case 'image/bmp'	: $extension = '.bmp'; break;
			default: $extension = '.jpg';
		}
 
		$file_array         = [];
		$file_array['name'] = wp_basename( $image_url ) . $extension;
 
		// Download file to temp location.
		$file_array['tmp_name'] = download_url( $image_url );
 
		// If error storing temporarily, return the error.
		if ( is_wp_error( $file_array['tmp_name'] ) ) {

			Paljet_Logs::add( 'notice', sprintf(
				esc_html__('Pending images : download image %s', 'paljet'),
				$file
			) );

			Paljet_Logs::add( 'error', $file_array['tmp_name']->get_error_message() );
			return false;
		}
 
		// Do the validation and storage stuff.
		$id = media_handle_sideload( $file_array, 0, $desc );
 
		// If error storing permanently, unlink.
		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );

			Paljet_Logs::add( 'notice', sprintf(
				esc_html__('Pending images : storing image %s', 'paljet'),
				$image_url
			) );

			Paljet_Logs::add( 'error', $id->get_error_message() );
			return false;
		}
 
		// Store the original attachment source in meta.
		add_post_meta( $id, '_source_url', $image_url );
 
		return $id;
	}
}


if( ! function_exists('paljet_get_images_from_media') ) {
	function paljet_get_images_from_media() {

		$output = [];
		$args = [
			'post_type'			=> 'attachment',
			'post_status'		=> 'inherit',
			'nopaging'			=> true,
			'meta_query'		=> [
				'key'		=> '_source_url',
				'compare'	=> 'EXISTS'
			]
			
		];

		$query = new WP_Query( $args );

		if( $query->found_posts == 0 )
			return $output;

		while ( $query->have_posts() ) {
			$query->the_post();
			$output[ get_the_ID() ] = get_post_meta( get_the_ID(), '_source_url', true );
		}

		wp_reset_postdata();

		return apply_filters( 'paljet/media/get_images', $output );
	}
}