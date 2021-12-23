<?php
if( !defined('ABSPATH') ) {
	exit;
}

if( !class_exists('order_export_process') ) {

	class order_export_process {

		/**
		 * Delimiter for CSV file
		 */
		static $delimiter;

		/**
		 * Tells which fields to export.
		 * 
		 * Also reset orders of fields according to which they were added
		 * in plugin.
		 * 
		 * Refer this support link: https://wordpress.org/support/topic/change-order-5?replies=7#post-6818741
		 */
		static function export_options() {

			global $wpg_order_columns;

			$settings = wpg_order_export::get_settings_fields();
			$settings = apply_filters( 'wpg_export_options_settings', $settings );

			$fields = array();

			$setting_order = array();

			if( is_array( $settings ) ) {

				foreach( $settings as $setting ) {

					if( !is_array( $setting ) || empty($setting['id']) ){
						continue;
					}

					array_push( $setting_order, $setting['id'] );
				}

				$new_order = array();

				foreach( $setting_order as $_setting ) {

					if( empty( $wpg_order_columns[$_setting] ) ){
						continue;
					}

					$new_order[$_setting] = $wpg_order_columns[$_setting];
				}
				
				$wpg_order_columns = $new_order;
			}

			foreach( $wpg_order_columns as $key=>$val ) {

				$retireve = get_option( $key, 'no' );
				$fields[$key] = ( strtolower($retireve) === 'yes' ) ? true : false;
			}

			return $fields;
		}

		/**
		 * Returns order details
		 */
		static function get_orders() {

			$fields		=	self::export_options();
			$fields		=	array_filter( $fields, 'wsoe_array_filter' );
			if($_POST['rel'] == 'refer_candy')
			{	
				$headings	=	self::csv_heading1($fields);
			}
			else
			{
				$headings	=	self::csv_heading($fields);
			}		

			$delimiter	=	( empty( $_POST['wpg_delimiter'] ) || ( gettype( $_POST['wpg_delimiter'] ) !== 'string' ) ) ? ',' : $_POST['wpg_delimiter'][0];

			/**
			 * Filter : wpg_delimiter
			 * Filters the delimiter for exported csv file. Override user defined
			 * delimiter by using this filter.
			 */
			self::$delimiter = apply_filters( 'wpg_delimiter', $delimiter );

			/* Check which order statuses to export. */
			$order_statuses	=	( !empty( $_POST['order_status'] ) && is_array( $_POST['order_status'] ) ) ? $_POST['order_status'] : array_keys( wc_get_order_statuses() );

			$args = array( 'post_type'=>'shop_order', 'posts_per_page'=>-1, 'post_status'=> apply_filters( 'wpg_order_statuses', $order_statuses ) );
			$args['date_query'] = array( array( 'after'=>  $_POST['start_date'], 'before'=> $_POST['end_date'], 'inclusive' => true ) );

			$args = apply_filters( 'wsoe_query_args', $args );

			$orders = new WP_Query( $args );

			if( $orders->have_posts() ) {

				/**
				 * This will be file pointer
				 */
				$csv_file = self::create_csv_file();

				if( empty($csv_file) ) {
					return new WP_Error( 'not_writable', __( 'Unable to create csv file, upload folder not writable', 'woocommerce-simply-order-export' ) );
				}

				fputcsv( $csv_file, $headings, self::$delimiter );

				/**
				 * Loop over each order
				 */
				while( $orders->have_posts() ) {

					$csv_values = array();

					$orders->the_post();

					$order_details = new WC_Order( get_the_ID() );

					/**
					 * Check if we need to export product name.
					 * If yes, then create new row for each product.
					 */
					 
					 

					if( array_key_exists( 'wc_settings_tab_product_name', $fields ) ) {

						$items = $order_details->get_items();
						
						$name = array();
						$qty2 = array();
						$variation_sku  = array();
						$variation_id  = array();
						
						foreach( $items as $item_id=>$item ) {
							
							$name[] = $item ['name'];
							$qty2[] = $item['qty'];
							$variation_id[] = $item['variation_id'];
							
							
						}
	                    
						$item['name'] = implode(';', $name);
						//$item['qty'] = implode(';',$qty2);
						$sum = 0;
						foreach($qty2 as $qty)
						{
						$sum+= $qty;
						}

						$item['qty']=$sum;
						foreach($variation_id as $var_id)
						{
						 $variation_sku[]= get_post_meta($var_id,'_sku',true);	
							
						}
					   $item['sku'] = implode(';',$variation_sku);
 						$csv_values = array();
							if($_POST['rel'] == 'refer_candy')
							{
								self::add_fields_diff_row1( $fields, $csv_values, $order_details, $item_id, $item );
							}	
							else
							{
								self::add_fields_diff_row( $fields, $csv_values, $order_details, $item_id, $item );
							}	
							fputcsv( $csv_file, $csv_values, self::$delimiter );



						// foreach( $items as $item_id=>$item ) {

							// $csv_values = array();
							// self::add_fields_diff_row( $fields, $csv_values, $order_details, $item_id, $item );
							// fputcsv( $csv_file, $csv_values, self::$delimiter );
						// }

					}else{
						/**
						 * Create a single row for order.
						 */
						 if($_POST['rel'] == 'refer_candy')
						 {
							self::add_fields_diff_row1( $fields, $csv_values, $order_details );
						 }
						 else
						 {
							self::add_fields_diff_row( $fields, $csv_values, $order_details );
						 }		
						fputcsv( $csv_file, $csv_values, self::$delimiter );
					}

				}
				wp_reset_postdata();

			}else {

				return new WP_Error( 'no_orders', __( 'No orders for specified duration.', 'woocommerce-simply-order-export' ) );
			}
		}
   static function get_order_date()
   {
	   $args = array(
  'post_type' => 'shop_order',
  'post_status' => 'publish',
  'meta_key' => '_customer_user',
  'posts_per_page' => '-1',
 /*  'date_query' => array(
		array(
			'year'  => 2016,
			'month' => 4,
			'day'   => 14,
		), */
);
$my_query = new WP_Query($args);

$customer_orders = $my_query->posts;

 $order = new WC_Order(get_the_ID());

 $orderdata = (array) $order;

return $orderdata[order_date];
	   
   }
		/**
		 * 
		 */
		static function add_fields_diff_row( $fields, &$csv_values, $order_details, $item_id = null, $current_item = null ) {

			/**
			 * Loop over fields and add value for corresponding field.
			 */
			foreach( $fields as $key =>$field ) {

				switch ( $key ) {

					/**
					 * Check if we need order ID.
					 */
					case 'wc_settings_tab_order_id':
						array_push( $csv_values, $order_details->get_order_number() );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;

					/**
					 * Check if we need customer name.
					 */
					/**
					 * Check if we need customer name.
					 */
					case 'wc_settings_tab_customer_name':
						array_push( $csv_values, self::customer_name( get_the_ID() ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;

					case 'wc_settings_tab_customer_last_name':
						array_push( $csv_values, self::customer_last_name( get_the_ID() ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;

					/**
					 * Check if we need product name.
					 */
					case 'wc_settings_tab_product_name':
						array_push( $csv_values, $current_item['name'] );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;

					/**
					 * Check if we need product quantity.
					 * Product quantity will only be exported if user has selected product name to be exported.
					 * 
					 * If product name is not selected, this column will be filled with just dashes. ;)
					 */
					case 'wc_settings_tab_product_quantity':
						if( array_key_exists( 'wc_settings_tab_product_name', $fields ) ) {
							array_push( $csv_values, $current_item['qty'] );
							do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
						}else{
							array_push( $csv_values, '-' ); // pad the quantity column with dash if there is no product name
							do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
						}
					break;
					
					/**
					 * Check if we need product variations
					 */
					/*case 'wc_settings_tab_product_variation':
						if( array_key_exists( 'wc_settings_tab_product_name', $fields ) ) {
							array_push( $csv_values, self::get_product_variation( $item_id, $order_details ) );
							do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
						}else{
							array_push( $csv_values, '-' );
							do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
						}
					break;*/

					/**
					 * Check if we need order amount.
					 */
					case 'wc_settings_tab_amount':
						//$amount = wc_price( $order_details->get_total(), array( 'currency'=> $order_details->get_order_currency() ) );
						array_push( $csv_values, wsoe_formatted_price( $order_details->get_total(), $order_details ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
							
					/**
					 * Check if we need customer email.
					 */
					case 'wc_settings_tab_customer_email':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_billing_email' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;

					/**
					 * Check if we need customer phone.
					 */
					case 'wc_settings_tab_customer_phone':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_billing_phone' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;

					/**
					 * Check if we need order status.
					 */
					case 'wc_settings_tab_order_status':
						array_push( $csv_values, ucwords($order_details->get_status()) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
					
					/*** billing or shipping details***/
					
					
					  /**
					    * check if we need customer billing compnay information 
						*/
					
					case 'wc_settings_tab_customer_company':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_shipping_company' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
				
					case 'wc_settings_tab_customer_street_address1':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_shipping_address_1' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
					
					case 'wc_settings_tab_customer_street_address2':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_shipping_address_2' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
					
					case 'wc_settings_tab_customer_city':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_shipping_city' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
				
					case 'wc_settings_tab_customer_state':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_shipping_state' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
					
					case 'wc_settings_tab_customer_zip_code':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_shipping_postcode' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
					
					case 'wc_settings_tab_customer_country':
						array_push( $csv_values, self::customer_meta( get_the_ID(), '_shipping_country' ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
					/*** Item Number or Variation sku ***/
					case 'wc_settings_tab_customer_item_number':
						if( array_key_exists( 'wc_settings_tab_product_name', $fields ) ) {
								
							
							array_push( $csv_values,$current_item['sku'] );
							//array_push( $csv_values, self::get_product_variation( $item_id, $order_details ) );
							do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
						}else{
							array_push( $csv_values, '-' );
							do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
						}
					break;
					/** Get order or payment date ****/
					case 'wc_settings_tab_customer_order_date':
							array_push( $csv_values,self::get_order_date());
							
							//array_push( $csv_values, self::get_product_variation( $item_id, $order_details ) );
							do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					
					break ;
					case 'wc_settings_tab_customer_order_notes':
						//array_push( $csv_values, self::customer_meta( get_the_ID(), '_order_note' ) );
						/*array_push( $csv_values, self::get_order_notes( $item_id,$order_details) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );*/
					break;
					
					default :
						/**
						 * Add values to CSV.
						 * 
						 * @param array $csv_values Array of csv values, callback function should accept this argument by reference.
						 * 
						 * @param Object $order_details WC_Order object
						 * 
						 * @param String $key Current key in loop.
						 * 
						 */
						do_action_ref_array( 'wpg_add_values_to_csv', array( &$csv_values, $order_details, $key, $fields, $item_id, $current_item ) );
						do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
					break;
				}
			}

		}
		
		static function add_fields_diff_row1( $fields, &$csv_values, $order_details, $item_id = null, $current_item = null ) {
				array_push( $csv_values, self::customer_name( get_the_ID() ) );
				do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
				array_push( $csv_values, self::customer_last_name( get_the_ID() ) );
				do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
				array_push( $csv_values, self::customer_meta( get_the_ID(), '_billing_email' ) );
				do_action_ref_array( 'wsoe_after_value_'.$key, array( &$csv_values, $order_details, $item_id, $current_item ) );
		}
		
		/**
		 * Returns customer related meta.
		 * Basically it is just get_post_meta() function wrapper.
		 */
		static function customer_meta( $order_id , $meta = '' ) {
			
			if( empty( $order_id ) || empty( $meta ) )
				return '';
			
			return get_post_meta( $order_id, $meta, true );
		}

		
		public function get_order_notes( $order_id, $fields = null ) {

		// ensure ID is valid order ID
		$order_id = $this->validate_request( $order_id, $this->post_type, 'read' );

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$args = array(
			'post_id' => $order_id,
			'approve' => 'approve',
			'type'    => 'order_note'
		);

		remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );

		$notes = get_comments( $args );

		add_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ), 10, 1 );

		$order_notes = array();

		foreach ( $notes as $note ) {

			$order_notes[] = current( $this->get_order_note( $order_id, $note->comment_ID, $fields ) );
		}

		return array( 'order_notes' => apply_filters( 'woocommerce_api_order_notes_response', $order_notes, $order_id, $fields, $notes, $this->server ) );
	}
		
		
		
		
		
		
		
		static function get_product_variation ( $product_id, $order_details ) {

			$metadata = $order_details->has_meta( $product_id );
			$_product = new WC_Product( $product_id );

			$exclude_meta = apply_filters( 'woocommerce_hidden_order_itemmeta', array(
				'_qty',
				'_tax_class',
				'_product_id',
				//'_variation_id',
				'_line_subtotal',
				'_line_subtotal_tax',
				'_line_total',
				'_line_tax',
			) );

			$variation_details = array();

			foreach( $metadata as $k => $meta ) {

				if( in_array( $meta['meta_key'], $exclude_meta ) ){
					continue;
				}

				// Skip serialised meta
				if ( is_serialized( $meta['meta_value'] ) ) {
					continue;
				}

				// Get attribute data
				if ( taxonomy_exists( wc_sanitize_taxonomy_name( $meta['meta_key'] ) ) ) {

					$term               = get_term_by( 'slug', $meta['meta_value'], wc_sanitize_taxonomy_name( $meta['meta_key'] ) );
					$meta['meta_key']   = wc_attribute_label( wc_sanitize_taxonomy_name( $meta['meta_key'] ) );
					$meta['meta_value'] = isset( $term->name ) ? $term->name : $meta['meta_value'];

				}else {
					$meta['meta_key']   = apply_filters( 'woocommerce_attribute_label', wc_attribute_label( $meta['meta_key'], $_product ), $meta['meta_key'] );
				}

				/* array_push( $variation_details, wp_kses_post( urldecode( $meta['meta_key'] ) ) .': '.wp_kses_post( urldecode( $meta['meta_value'] ) ) ); */
				if($meta['meta_key'] == '_variation_id')
					
					{
						
						$variation_details= wp_kses_post( urldecode( $meta['meta_value'] ) ); 
						$sku= get_post_meta($variation_details,'_sku',true);
					}
			}

			return $sku;
		}

		/**
		 * Returns customer name for particular order
		 * @param type $order_id
		 * @return string
		 */
		 /*** customer first name ****/
		static function customer_name( $order_id ) {

			if( empty( $order_id ) ){
				return '';
			}

			$firstname = get_post_meta( $order_id, '_billing_first_name', true );
			

			return $firstname;			
		}
		/*** customer last name ****/
		static function customer_last_name( $order_id ) {

			if( empty( $order_id ) ){
				return '';
			}

			
			$lastname  = get_post_meta( $order_id, '_billing_last_name', true );

			return $lastname;			
		}

		/**
		 * Makes first row for csv
		 */
		static function csv_heading( $fields ) {

			if( !is_array( $fields ) ){
				return false;
			}

			global $wpg_order_columns;
			$headings = array();

			foreach( $fields as $key=>$val ) {

				if( $val === true && array_key_exists( $key, $wpg_order_columns ) ){
					array_push( $headings, $wpg_order_columns[$key] );
					// By using this we can add heading for keys which are not sanitized.
					do_action_ref_array( 'wsoe_after_heading_'.$key, array( &$headings ) );
				}
			}

			return $headings;

		}
		
		
		/**
		 * Makes first row for csv
		 * For referal candy header function 
		 */
		static function csv_heading1( $fields ) {

			if( !is_array( $fields ) ){
				return false;
			}

			global $wpg_order_columns;
			$headings = array();
			$headings[0]='first_name';
			$headings[1]='last_name';
			$headings[2]='email';
			return $headings;

		}		
		/**
		 * Creates csv file in upload directory.
		 */
		static function create_csv_file() {

			$csv_filename = empty($_POST['woo_soe_csv_name']) ? 'order_export.csv' : sanitize_file_name($_POST['woo_soe_csv_name']) .'.csv';
			$new_filename = wp_unique_filename( trailingslashit( wsoe_upload_dir() ), $csv_filename );

			$csv_file = fopen( trailingslashit( wsoe_upload_dir() ) . $new_filename, 'w+' );

			do_action( 'wsoe_file_created', $_POST['start_date'], $_POST['end_date'], $new_filename, $csv_file );

			/**
			 * Save file name in global for later use.
			 */
			$GLOBALS['wsoe_filename'] = str_replace( '.csv', '', $new_filename );
			return $csv_file;
		}
	}
}