<?php
if ( ! defined("ABSPATH" ) ) exit; // Exit if accessed directly

/**
 * Create CSV from array
 * @since 0.1
 */
function WooParc_array2csv(array &$array) {
   if (count($array) == 0) {
     return null;
   }
   ob_start();
   $df = fopen("php://output", 'w');
   fputcsv($df, array_keys(reset($array)));
   foreach ($array as $row) {
      fputcsv($df, $row);
   }
   fclose($df);
   return ob_get_clean();
}

/**
 * Get CSV Headers
 * @since 0.1
 */
function WooParc_csv_headers() {
    // disable caching
	$filename='parcelware-export.csv';
    $now = gmdate("D, d M Y H:i:s");
    header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
    header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
    header("Last-Modified: {$now} GMT");

    // force download  
    header("Content-Type: application/force-download");
    header("Content-Type: application/octet-stream");
    header("Content-Type: application/download");
	//header("Content-Type: text/plain");

    // disposition / encoding on response body
    header("Content-Disposition: attachment;filename={$filename}");
    header("Content-Transfer-Encoding: binary");
	echo "\xEF\xBB\xBF"; // UTF-8 BOM
}

/**
 * Download CSV export file
 * @since 0.1
 */
function WooParc_csv_download($csv) {
	WooParc_csv_headers();
	echo $csv;
}

/**
 * CSV columns
 * @since 0.1
 */
function WooParc_columns() {
	$return='';
	$columns=array('RefNr','SendRef','ClientRef','Contract','RecRef','Rec_Companyname','Rec_Firstname','Rec_Lastname','Street','Homenumber','Homenumber_ext','Building','Door','Floor','Department','Postcode','City','District','Region','Country','Phone','Mobile','Email','XML','Sen_IBAN','Sen_Name','ShipmentCount','ShipRemark','CostCentre','Ordernumber','Barcode');
	foreach($columns as $column) {
		$return.=$column.';';
	}
	return $return;
}

/**
 * Split address into street, housenumber and extension
 * @since 0.1
 */
function WooParc_split_address($address) {
	if ( preg_match( '~^[0-9]+,~', $address ) )
	{
        $pattern = '#^([0-9],) ([\p{L}a-z0-9\-/ \']*) ([\p{L}a-z0-9\-/ ]{0,})#i';
        $b = preg_match($pattern,$address, $aMatch);
        $huisnummer = (isset($aMatch[1])) ? $aMatch[1] : '';
        $straatnaam = (isset($aMatch[2])) ? $aMatch[2] : '';
        $huisnrtoe = (isset($aMatch[3])) ? $aMatch[3] : '';
	}
	else
	{
        $pattern = '#^([\p{L}a-z0-9 [:punct:]\']*) ([0-9 ]{1,5})([\p{L}a-z0-9\-/]{0,})$#i';
        $aMatch = array();
        $b = preg_match($pattern,$address, $aMatch);
        $straatnaam = (isset($aMatch[1])) ? $aMatch[1] : '';
        $huisnummer = (isset($aMatch[2])) ? $aMatch[2] : '';
        $huisnrtoe = (isset($aMatch[3])) ? $aMatch[3] : '';
	}
	if($straatnaam!="" && $huisnummer!="") {
		return array ($straatnaam, $huisnummer, $huisnrtoe);
	} else {
		return false;
	}
}
 
/**
 * Return address
 * @since 0.1
 */
function WooParc_get_address($address_1,$address_2) {
	$address=WooParc_split_address($address_1);
	
	// If no street & homenumber available
	if(!is_array($address)) {
	
		// Check if address_2 exists, use as homenumber
		if(isset($address_2) && $address_2!="") {
			
			$new_address=$address_1.' '.$address_2;
			$address=WooParc_split_address($new_address);
			
		} else {
			$address=array($address_1);
		}
	
	} elseif($address[2]=='' && isset($address_2) && $address_2!="") {
		$address[2]=$address_2;
	}
	
	if(isset($address[2]) && $address[2]!='' && isset($address_2) && $address_2!="") {
		$address[3]=$address_2;
	}
	
	return $address;
}

/**
 * Receive address information from plugin created order fields
 * @since 0.1
 */
function WooParc_make_address($order_id) {
	global $woocommerce;
	$order = new WC_Order($order_id);
	$street='';
	if(isset($order->order_custom_fields['_shipping_street'][0])) {
		$street=$order->order_custom_fields['_shipping_street'][0];
	}
	
	// If new checkout field "shipping_street" exists, use new address collect, otherwhise use old method
	if(isset($street) && $street!="" && get_option('wooparc_convert_address')!='') {
		$address=array($street,$order->order_custom_fields['_shipping_number'][0],$order->order_custom_fields['_shipping_number_ext'][0],$order->shipping_address_2);
	} else {
		$address=WooParc_get_address($order->shipping_address_1,$order->shipping_address_2);
	}
	return $address;
}

/** 
 * Check if order is ready for export, based on advanced export settings
 * @since 0.1
 */
function WooParc_checkexport($order,$single=false,$quick=false) {

	$shipping_cost=WooParc_shipping_cost($order->order_shipping);
	$shipping_minimum=WooParc_shipping_cost(get_option('wooparc_shipping_cost',0));
	$shipping_methods=get_option('wooparc_shipping_method',array());
		
	if(($shipping_cost>=$shipping_minimum && in_array($order->shipping_method,$shipping_methods)) || $quick==true || $single==true) {
		return true;
	} else {
		return false;
	}
}

/**
 * Get export data
 * @since 0.1
 */
function WooParc_exportdata($order_id,$single=false,$quick=false) {
	global $woocommerce;
	$order = new WC_Order($order_id);
		
	$set_data=array();
	
	$address=WooParc_make_address($order_id);
	
	if(!isset($address[1])) { $address[1]='';}
	if(!isset($address[2])) { $address[2]='';}
	if(!isset($address[3])) { $address[3]='';}
	
	$set_data['Contract']=__('PostNL Pakketten');
	$set_data['RecRef']=$order_id;
	$set_data['Rec_Companyname']=$order->shipping_company;
	$set_data['Rec_Firstname']=$order->shipping_first_name.' ';
	$set_data['Rec_Lastname']=$order->shipping_last_name;
	$set_data['Street']=$address[0];
	$set_data['Homenumber']=$address[1];
	$set_data['Homenumber_ext']=$address[2];
	$set_data['Building']=$address[3];
	$set_data['Postcode']=$order->shipping_postcode;
	$set_data['City']=$order->shipping_city;
	$set_data['Region']=$order->shipping_state;
	if(!isset($order->shipping_country) || $order->shipping_country=="") $order->shipping_country=$order->billing_country;
	if($order->shipping_country=="") $order->shipping_country=get_option( 'woocommerce_default_country');
	$full_country=__($woocommerce->countries->countries[$order->shipping_country],'woocommerce');
	$set_data['Country']=$full_country;
	$set_data['Phone']=$order->billing_phone;
	$set_data['Email']=$order->billing_email;
	$set_data['ShipmentCount']='1';
	$set_data['ShipRemark']=$order->customer_note;
	$set_data['RefNr']=$set_data['Ordernumber']=$order_id;
	$set_data['CostCentre']=$set_data['SendRef']=$set_data['ClientRef']=$set_data['Door']=$set_data['Floor']=$set_data['Department']=$set_data['District']=$set_data['Mobile']=	$set_data['XML']=$set_data['Sen_IBAN']=	$set_data['Sen_Name']=$set_data['Barcode']='';
	
	// Check if shipping cost are same or above minimum shipping cost
	if(WooParc_checkexport($order,$single,$quick)) {
		return array($set_data['RefNr'],$set_data['SendRef'],$set_data['ClientRef'],$set_data['Contract'],$set_data['RecRef'],$set_data['Rec_Companyname'],$set_data['Rec_Firstname'],$set_data['Rec_Lastname'],$set_data['Street'],$set_data['Homenumber'],$set_data['Homenumber_ext'],$set_data['Building'],$set_data['Door'],$set_data['Floor'],$set_data['Department'],$set_data['Postcode'],$set_data['City'],$set_data['District'],$set_data['Region'],$set_data['Country'],$set_data['Phone'],$set_data['Mobile'],$set_data['Email'],$set_data['XML'],$set_data['Sen_IBAN'],$set_data['Sen_Name'],$set_data['ShipmentCount'],$set_data['ShipRemark'],$set_data['CostCentre'],$set_data['Ordernumber'],$set_data['Barcode']);
	} else {
		return false;
	}
}

/**
 * Get order data for Quick Export
 * @since 0.1
 */
function WooParc_quickdata() {
	global $woocommerce;
	$data=array();	

	function WooParc_filter( $where = '' ) {
		$date_to=date('Y-m-d 23:59:59', strtotime(get_option( 'wooparc_dateto' )));
		$where .= " AND post_date >= '" . date('Y-m-d', strtotime(get_option( 'wooparc_datefrom' ))) . "'" . " AND post_date <= '" . $date_to . "'";
		return $where;
	}
	
	$status=get_option( 'wooparc_orderstatus' );
	$status_args='';
	if($status!="" && isset($status)) {
		$status_args=array(
				'taxonomy' => 'shop_order_status',
				'field' => 'slug',
				'terms' => array($status)
			);
	}
	$args = array(
		'post_type'	=> 'shop_order',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'tax_query' => array($status_args)
	);
	
	add_filter('posts_where','WooParc_filter');
	$loop = new WP_Query( $args );
	remove_filter('posts_where','WooParc_filter');

	while ( $loop->have_posts() ) : $loop->the_post();
		$order_id = $loop->post->ID;
		$data[$order_id]=WooParc_exportdata($order_id,false,true);
		
	endwhile; 
	return $data;
}

/**
 * Get order data for all other export options
 * @since 0.1
 */
function WooParc_getdata($order_id,$single=false) {
	$data=array();	
	$data[$order_id]=WooParc_exportdata($order_id,$single);
	return $data;
}

/**
 * Get order status from order_id
 * @since 0.1
 */
function WooParc_orderstatus($order_id) {
	$order = new WC_Order($order_id);
	return $order->status;
}

/**
 * Init function for file export
 * @since 0.1
 */
function WooParc_exporter() {
	// Export files when submitted
	if(isset($_REQUEST['_wpnonce'])) {
		$nonce=$_REQUEST['_wpnonce'];
	}
	if ( isset( $_GET['wooparc_submitted'] ) && $_GET['wooparc_submitted'] == 'exported' && wp_verify_nonce($nonce,'wooparc_nonce')) {
		$type=$_GET['wooparc_type'];
		
		if($type=='quick') {
			
			// Save export data
			foreach ( $_GET as $key => $value ) {
				if ( get_option( $key ) != $value ) {
					update_option( $key, $value );
				} else {
					add_option( $key, $value, '', 'no' );
				}
			}
			
			$from=sanitize_text_field($_GET['wooparc_datefrom']);
			$to=sanitize_text_field($_GET['wooparc_dateto']);
			$status=sanitize_text_field($_GET['wooparc_orderstatus']);
			// Get export file
			$csv=WooParc_columns()."\r\n";
			$order_ids=array();
			
			foreach (WooParc_quickdata() as $key=>$data) {
				
				if(is_array($data) && !empty($data)) {
					
					$order_ids[]=$key;
				
					foreach ($data as $value) {
						$csv.=$value.";";
					}
					$csv.="\r\n";
				}
			}
			WooParc_csv_download($csv);
		} elseif ($type=='single') {
			
			// Get export file
			$csv=WooParc_columns()."\r\n";
			$order_ids=array();
			
			foreach (WooParc_getdata((int)$_GET['order_id'],true) as $key=>$data) {
				
				if(is_array($data) && !empty($data)) {
					
					$order_ids[]=$key;
				
					foreach ($data as $value) {
						$csv.=$value.";";
					}
					$csv.="\r\n";
				}
			}
			WooParc_csv_download($csv);
	
		} else {
		
			$get_ids=explode(',', $_GET['order_ids']);
			
			$csv=WooParc_columns()."\r\n";
			$order_ids=array();
			foreach($get_ids as $order_id) {
				foreach (WooParc_getdata((int)$order_id) as $key=>$data) {
				
					if(is_array($data) && !empty($data)) {
					
						$order_ids[]=$key;
						
						foreach ($data as $value) {
							$csv.=$value.";";
						}
						$csv.="\r\n";
					}
				}

			}
			WooParc_csv_download($csv);
		}
		
		foreach($order_ids as $order_id) {
			update_post_meta( $order_id, 'export-date', date('Ymd H:i:s'));
		}
	
		die();
	}
}