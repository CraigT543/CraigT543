<?php
/*
 * AMELIA APPOINTMENTS MODIFICATIONS
 */

/*
* After client registration add new role for Amilia.  
* Then add Amilia and Easy Appointment clients to the database.
*/
add_action('wp_login', 'add_users_on_login', 10, 2);
function add_users_on_login($login, $user) {
	global $wpdb;
	$user_id = $user->ID;
	$user_info = get_userdata($user_id);
	$user_email = $user_info->user_email;
	$first_name = get_user_meta( $user_id, 'first_name', true); 
	$last_name = get_user_meta( $user_id, 'last_name', true);
	$phone_number = get_user_meta( $user_id, 'phone_number', true); 

    if (!in_array('administrator', $user->roles) &&
        !in_array('wpamelia-customer', $user->roles)) {

		//Add Amelia user
		$wpdb->insert('wp_amelia_users', array(
			'firstName' => $first_name,
			'lastName' => $last_name,
			'email' => $user_email,
			'phone' => $phone_number,
			'type' => 'customer',
			'externalId' => $user_id,
			'note' => $ea_codes
		), array('%s','%s','%s','%s','%s','%d','%s'));
    }	
}


/*
 * WOOCOMMERCE/AMELIA INTEGRATION TOOLS
 * 
 * TABLE OF CONTENTS
 * 1. Schedule Hook to Run update_my_wc_orders() at least daily
 * 2. My Amelia Orders Webhook Endpoint
 * 3. Function to Update WC Orders to Reflect Amelia Appointments Payment Status -- update_my_wc_orders()
 * 4. Function to Update Amelia Orders to Reflect Customer On-line Payment
 * 
 */


// Schedule Hook to Run update_my_wc_orders() at least daily
/* 
 * Update the WC Orders to reflect the Amelia appointments made on the back end 
 * I am including this to be sure it runs at least daily to update the orders
 * This requires that WP-Cron is activated in wp-config.php.  Also see the function my_cron_schedules above.
 */
if (!wp_next_scheduled('amelia_task_hook')) {
	wp_schedule_event( time(), 'hourly', 'amelia_task_hook' );
}

// My Amelia Orders Webhook Endpoint
/*
 * Built per https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
 * This is very simple.  No paramiters are being passed
 * The API path is the default wp-json, the full URL is http://www.YOURWEBSITEHERE.com/wp-json/myameliaorder/v1/author/(?P\d+)
*/
add_action( 'rest_api_init', function () {
  register_rest_route( 'myameliaorder/v1', '/author/(?P<id>\d+)', array(
    'callback' => 'update_my_wc_orders',
  ) );
} );

// Function to Update WC Orders to Reflect Amelia Appointments Payment Status -- update_my_wc_orders()
add_action ( 'amelia_task_hook', 'update_my_wc_orders' );
function update_my_wc_orders() {
	global $wpdb;	
	global $woocommerce;
	global $product;
	
	//Basic Amelia information for the queries
	$query = "SELECT u.firstName, u.lastName, u.email, u.phone, u.externalId, a.id, a.bookingStart, s.name, " .
		"e.firstName EfirstName, e.lastName ElastName, a.internalNotes, b.price, p.status " .
		"FROM wp_amelia_payments p " .
		"LEFT JOIN wp_amelia_customer_bookings b " .
		"ON b.id = p.customerBookingId " .
		"LEFT JOIN wp_amelia_appointments a " .
		"ON a.id = b.appointmentId " .
		"LEFT JOIN wp_amelia_services s " .
		"ON s.id = a.serviceId " .
		"LEFT JOIN wp_amelia_users u " .
		"ON u.id = b.customerId " .
		"LEFT JOIN wp_amelia_users e " .
		"ON e.id = a.providerId ";
		
	//Create a new Woocomerce order for pending backend Amelia payments	
	$query1 = $query . "WHERE p.status = %s AND b.price <> %d AND CAST(SUBSTRING_INDEX(a.internalNotes, '|', 1) AS UNSIGNED) = %d";
	$query1 = $wpdb->prepare($query1,array('pending', 0, 0));
	
	$rows = $wpdb->get_results($query1, ARRAY_A);

	if(!empty($query1)){
		foreach ($rows as $row) {

			$date= date('F d, Y g:i a', strtotime($row['bookingStart']) - 60 * 60 * 7);
			$datepost =date('Y-m-d H:i:s', strtotime($row['bookingStart']) - 60 * 60 * 7);
			
			// Now we create the order
			$order = wc_create_order();
			$order->set_customer_id( $row['externalId']);
			
			// The add_product() function below is located in /plugins/woocommerce/includes/abstracts/abstract_wc_order.php
			$args = array(
				'subtotal'		=> $row['price'],
				'total'			=> $row['price'],
			);
			$order->add_product( wc_get_product( 10961 ), 1, $args); // Use the product IDs to add

			//Add meta_data for line items
			$items = $order->get_items();
			foreach($items as $item){
				$item->update_meta_data( 'Appointment Time', $date, true );
				$item->update_meta_data( 'Service', $row['name'], true );
				$item->update_meta_data( 'Psychotherapist', $row['EfirstName'] . ' ' . $row['ElastName'] , true );
				$item->update_meta_data( 'Total Number of Persons', 1, true );
			}
		
			// Set addresses
			$address = array(
				'first_name' => $row['firstName'],
				'last_name'  => $row['lastName'],
				'email'      => $row['email'],
				'phone'      => $row['phone'],
			);
			$order->set_address( $address );
			
			// Calculate totals
			$order->calculate_totals();
			$order->update_status( $row['status'], 'Order created dynamically - ', TRUE);

			$order_id = $order->get_id();
			$wpdb->update( 'wp_amelia_appointments', array( 'internalNotes' => $order_id . '|' . $row['internalNotes'] ), array( 'id' => $row['id'] ), array( '%s','%d' ) );
			
			wp_update_post(array ('ID' => $order_id,
			'post_date'=> $datepost,'post_date_gmt' => get_gmt_from_date( $datepost )));
		}	
	}	

	//Update current pending orders with new information 
	$query2 = $query . "WHERE p.status = %s AND b.price <> %d AND CAST(SUBSTRING_INDEX(a.internalNotes, '|', 1) AS UNSIGNED) > %d";
	$query2 = $wpdb->prepare($query2,array('pending', 0, 0));

	if(!empty($query2)){
		$rows = $wpdb->get_results($query2, ARRAY_A);

		foreach ($rows as $row) {

			$date= date('F d, Y g:i a', strtotime($row['bookingStart']) - 60 * 60 * 7);
			$datepost =date('Y-m-d H:i:s', strtotime($row['bookingStart']) - 60 * 60 * 7);
			
			// Now we find the existing order
			$note = explode("|", $row['internalNotes'], 2);
			$order_id = $note[0];
			$order = new WC_Order($order_id);
			
			//Add meta_data for line items
			$items = $order->get_items();
			foreach($items as $item){
				$item->set_subtotal( $row['price'] );
				$item->set_total( $row['price'] );
				$item->update_meta_data( 'Appointment Time', $date, true );
				$item->update_meta_data( 'Service', $row['name'], true );
				$item->update_meta_data( 'Psychotherapist', $row['EfirstName'] . ' ' . $row['ElastName'] , true );
				$item->update_meta_data( 'Total Number of Persons', 1, true );
				$item->save(); // Save line item data
			}
			
			// Set addresses
			$address = array(
				'first_name' => $row['firstName'],
				'last_name'  => $row['lastName'],
				'email'      => $row['email'],
				'phone'      => $row['phone'],
			);
			$order->set_address( $address );
			
			// Calculate totals
			$order->calculate_totals();
			$order->save();
			
			wp_update_post(array ('ID' => $order_id,
			'post_date'=> $datepost,'post_date_gmt' => get_gmt_from_date( $datepost )));
		}
	}
	
	//Update paid orders from Amelia to WooCommerce 
	$query3 = $query . "WHERE p.status = %s AND b.price <> %d AND CAST(SUBSTRING_INDEX(a.internalNotes, '|', 1) AS UNSIGNED) > %d";
	$query3 = $wpdb->prepare($query3,array('paid', 0, 0));

	$rows = $wpdb->get_results($query3, ARRAY_A);

	if(!empty($query3)){
		foreach ($rows as $row) {
			$note = explode("|", $row['internalNotes'], 2);
			$order_id = $note[0];
			$order = new WC_Order($order_id);

			//Add meta_data for line items
			$items = $order->get_items();
			foreach($items as $item){
				$item->set_subtotal( $row['price'] );
				$item->set_total( $row['price'] );
				$item->update_meta_data( 'Appointment Time', $date, true );
				$item->update_meta_data( 'Service', $row['name'], true );
				$item->update_meta_data( 'Psychotherapist', $row['EfirstName'] . ' ' . $row['ElastName'] , true );
				$item->update_meta_data( 'Total Number of Persons', 1, true );
				$item->save(); // Save line item data
			}
			
			// Set addresses
			$address = array(
				'first_name' => $row['firstName'],
				'last_name'  => $row['lastName'],
				'email'      => $row['email'],
				'phone'      => $row['phone'],
			);
			$order->set_address( $address );
			
			// Calculate totals
			$order->calculate_totals();
			
			$order->update_status( 'completed' );
			$order->save();
			
			$str = $row['internalNotes'];
			$internalNotes = substr($str, ($pos = strpos($str, '|')) !== false ? $pos + 1 : 0);
			
			$wpdb->update( 'wp_amelia_appointments', array( 'internalNotes' => $internalNotes ), array( 'id' => $row['id'] ), array( '%s','%d' ) );
		}
	}
}

// Function to Update Amelia Orders to Reflect Customer On-line Payment
function mysite_woocommerce_order_status_completed( $order_id ) {
    error_log( "Order complete for order $order_id", 0 );
	global $wpdb;	
	global $woocommerce;
	global $product;
	//Basic Amelia information for the queries
	$appdata = $wpdb->get_row($wpdb->prepare(
		"SELECT a.id aid, a.internalNotes, b.price, p.id pid " .
		"FROM wp_amelia_payments p " .
		"LEFT JOIN wp_amelia_customer_bookings b " .
		"ON b.id = p.customerBookingId " .
		"LEFT JOIN wp_amelia_appointments a " .
		"ON a.id = b.appointmentId " .
		"WHERE SUBSTRING_INDEX(a.internalNotes, '|', 1) = '%d'", $order_id));
	if(!empty($appdata)){
		$str = $appdata->internalNotes;
		$aid = $appdata->aid;
		$pid = $appdata->pid;
		$internalNotes = substr($str, ($pos = strpos($str, '|')) !== false ? $pos + 1 : 0);
		$dateTime = date('n/j/Y h:m', $unixTimestamp);

		$wpdb->update('wp_amelia_appointments', array( 
			'internalNotes' => $internalNotes 
		), array('id' => $aid), array('%s','%d'));
		
		$wpdb->update( 'wp_amelia_payments', array( 
			'dateTime' 		=> $dateTime,
			'status'		=> 'paid',
			'amount'		=> $appdata->price,
			'gateway'		=> 'wc',
			'gatewayTitle'	=> 'PayPal Checkout'
		), array('id' => $pid), array('%d', '%d', '%d'));	
	}		
}
add_action( 'woocommerce_order_status_completed', 'mysite_woocommerce_order_status_completed', 10, 1 );

//clear the order code in Amelia if order is deleted in WooCommerce
function action_woocommerce_before_delete_order_item( $order_id ) {
    error_log( "Order complete for order $order_id", 0 );
	global $wpdb;	
	global $woocommerce;
	global $product;
	//Basic Amelia information for the queries
	$appdata = $wpdb->get_row($wpdb->prepare("SELECT id aid, internalNotes FROM wp_amelia_payments " .
		"WHERE SUBSTRING_INDEX(internalNotes, '|', 1) = '%d'", $order_id));
	if(!empty($appdata)){
		$str = $appdata->internalNotes;
		$aid = $appdata->id;
		$internalNotes = substr($str, ($pos = strpos($str, '|')) !== false ? $pos + 1 : 0);
		$wpdb->update('wp_amelia_appointments', array( 
			'internalNotes' => $internalNotes 
		), array('id' => $aid), array('%s','%d'));
	}	
}; 
add_action( 'woocommerce_before_delete_order_item', 'action_woocommerce_before_delete_order_item', 10, 1 );
