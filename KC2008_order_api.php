<?php

	if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

	if( WP_DEBUG && WP_DEBUG_DISPLAY && (defined('DOING_AJAX') && DOING_AJAX) ){ // catch ajax errors
		@ ini_set( 'display_errors', 1 );
	}

    if ( ! class_exists( 'KC2008_order_api' ) ) {
        class KC2008_order_api {
            public $options;
            public $measoft;

            function __construct() {

                $this->options = get_option( 'KC2008_delivery_option' );
                $this->measoft = new MeasoftCourier($this->options['login'], $this->options['password'], $this->options['code']);

                if( wp_doing_ajax() ){
                    add_action( 'wp_ajax_query_create_order', array($this, 'query_create_order') );

                    add_action( 'wp_ajax_ks2008_city_autocomplete', array($this, 'ks2008_city_autocomplete') );
                    add_action( 'wp_ajax_nopriv_ks2008_city_autocomplete', array($this, 'ks2008_city_autocomplete') );

                    add_action( 'wp_ajax_ks2008_adress_autocomplete', array($this, 'ks2008_adress_autocomplete') );
                    add_action( 'wp_ajax_nopriv_ks2008_adress_autocomplete', array($this, 'ks2008_adress_autocomplete') );

                    add_action( 'wp_ajax_ks2008_deliveryprice_calculate', array($this, 'ks2008_deliveryprice_calculate') );
                    add_action( 'wp_ajax_nopriv_ks2008_deliveryprice_calculate', array($this, 'ks2008_deliveryprice_calculate') );

                }
            }

			public function ks2008_deliveryprice_calculate() {
				$townfrom = $_POST['townfrom'];
				$townto = $_POST['townto'];
				$mass = $_POST['mass'];

				$json_error = '';

				if (empty($townfrom) || empty($townto) || empty($mass)) {
					wp_send_json_error();
				}
				try {
					$res = $this->measoft->calculate(array(
						'townfrom' => $townfrom,
						'townto' => $townto,
						'mass' => $mass,
						'service' => 1,
					),true);
				} catch (\Exception $e) {
					$res = false;
					$json_error = $e->getMessage();
				}

				if ($res) {
					$res = (float)$res * (float)$this->options['coefficient'] + (float)$this->options['add_cost'];
					wp_send_json_success( $res );
				} else {
					wp_send_json_error($json_error);
				}

				wp_die();
			}

			public function object2array($object) { return @json_decode(@json_encode($object),1); }

            public function ks2008_adress_autocomplete() {

				$json_error = '';

				$term = $_POST['search']['term'];
				$town = $_POST['town'];

				$street_search = ['town' => $town, 'namestarts' => $term, ];
				$street_search2 = ['town' => $town, 'namecontains' => $term, ];

				try {
					$response = $this->measoft->streetsList($street_search);
				} catch (\Exception $e) {
					$response = false;
					$json_error = $e->getMessage();
				}

				try {
					$response2 = $this->measoft->streetsList($street_search2);
				} catch (\Exception $e) {
					$response2 = false;
					$json_error = $e->getMessage();
				}

				$streets_arr = array();

				if ($response) {
					foreach ($response->street as $street) {
						$streets_arr[] = $this->object2array($street->name)[0];
					}
				}
				if ($response2) {
					foreach ($response2->street as $street) {
						$streets_arr[] = $this->object2array($street->name)[0];
					}
				}

				if (!$response && !$response2) {
					$streets_arr[] = 'Улица не найдена';
				}

				if (!$town) {
					$streets_arr[] = 'Заполните поле с городом';
				}

				wp_send_json_success( $streets_arr );

		    	wp_die();
			}

            public function ks2008_city_autocomplete() {

				$term = $_POST['search']['term'];

				$json_error = '';

				$city_search = ['namecontains' => $term];
				try {
					$response = $this->measoft->citiesList($city_search);
				} catch (\Exception $e) {
					$response = false;
					$json_error = $e->getMessage();
				}

				$cities_arr = array();

				if ($response) {
					foreach ($response as $city) {
						$cities_arr[] = $city['name'];
					}
				} else {
					$cities_arr[] = 'Город не найден';
				}
				wp_send_json_success( $cities_arr );

		    	wp_die();
			}

            public function query_create_order() {
				global $woocommerce;

                $params = array();
				$measoft_items = array();
				$formData = array();

				$order = wc_get_order( $_POST['post_id'] );
				$order_data = $order->get_data();

				## SHIPPING INFORMATION:

				$order_shipping_first_name = $order_data['shipping']['first_name'];
				$order_shipping_last_name = $order_data['shipping']['last_name'];
				$order_shipping_city = $order_data['shipping']['city'];
				$order_shipping_address_1 = $order_data['shipping']['address_1'];
				$order_shipping_address_2 = $order_data['shipping']['address_2'];
            	$order_shipping_postcode = $order_data['shipping']['postcode'];

				## BILLING INFORMATION:

				$order_billing_first_name = $order_data['billing']['first_name'];
				$order_billing_last_name = $order_data['billing']['last_name'];
				$order_billing_phone = $order_data['billing']['phone'];
				$order_billing_city = $order_data['billing']['city'];
				$order_billing_address_1 = $order_data['billing']['address_1'];
				$order_billing_address_2 = $order_data['billing']['address_2'];
            	$order_billing_postcode = $order_data['billing']['postcode'];


				$zipcode = $order_shipping_postcode ? $order_shipping_postcode : $order_billing_postcode;
				$snf = $order_shipping_first_name ? $order_shipping_first_name . ' ' . $order_shipping_last_name : $order_billing_first_name . ' ' . $order_billing_last_name;
				$phone = $order_billing_phone ? $order_billing_phone : '';
				$city = $order_shipping_city ? $order_shipping_city : $order_billing_city;
				$adress = $order_shipping_address_1 ? $order_shipping_address_1 . ' ' . $order_shipping_address_2 : $order_billing_address_1 . ' ' . $order_billing_address_2;

				$total_weight = 0;
				foreach ($order->get_items() as $item_key => $item ):

					if (isset($this->options['articles'])) {
						$product        = $item->get_product(); // Get the WC_Product object
						$product_sku    = $product->get_sku();
						$quantity       = $item->get_quantity();

						try {
							$product_data = $this->measoft->productGetByArticle($product_sku);
						} catch (\Exception $e) {
							$json_error = $e->getMessage();
							wp_send_json_error($product_data);
							wp_die();
						}

						if ($product_data->attributes()->{'count'} == 0) {
							wp_send_json_error('Один, или несколько товаров не найдены в АПИ.');
							wp_die();
						}

						$product_name = $product_data->item->name;
						$line_total   = $product_data->item->retprice;
						$product_weight  = $product_data->item->weight;

						// dimensions
						$product_length = $product_data->item->length;
						$product_width = $product_data->item->width;
						$product_height = $product_data->item->height;

					} else {
						$item_id = $item->get_id();

						$item_data    = $item->get_data();

						$product_name = $item_data['name'];
						$quantity     = $item_data['quantity'];

						$product        = $item->get_product(); // Get the WC_Product object

						$line_total   = $product->get_price();
						$product_sku    = $product->get_sku();
						$product_weight  = (float)$product->get_weight();

						// dimensions
						$product_length = $product->get_length();
						$product_width = $product->get_width();
						$product_height = $product->get_height();

					}

					if ($product_weight) {
						$total_weight += (float)$product_weight * $quantity;
					} else {
						$product_weight = 0.1;
					}

					if (isset($this->options['articles'])) {
						$measoft_items[] = array(
							'name' => $product_name,
							'quantity' => $quantity,
							'mass' => $product_weight,
							'retprice' => $line_total,
							'article' => $product_sku,
							'barcode' => '',
						);
					} else {
						$measoft_items[] = array(
							'name' => $product_name,
							'quantity' => $quantity,
							'mass' => $product_weight,
							'retprice' => $line_total,
							'barcode' => '',
						);
					}

					if ($product_length) {
						$measoft_items[0]['length'] = $product_length;
					}
					if ($product_width) {
						$measoft_items[0]['width'] = $product_width;
					}
					if ($product_height) {
						$measoft_items[0]['height'] = $product_height;
					}

				endforeach;

				if ($total_weight <= 0) {
					if (isset($this->options['KC2008_weight'])) {
						$total_weight = $this->options['KC2008_weight'];
					} else {
						$total_weight = 0.1;
					}
				}

				// parse_str($_POST['formData'], $formData);

				$measoft_order = array(
					'receiver' => array(
						'person' => $snf,
						'phone' => $phone,
						'zipcode' => $zipcode,
						'town' => $city,
						'address' => $adress,
						'date' => $_POST['delivery_date'],
						'time_min' => $_POST['delivery_time_from'],
						'time_max' => $_POST['delivery_time_to'],
					),
					'paytype' => $_POST['pay_type'],
					'price' => $order->get_total(),
					'deliveryprice' => $_POST['deliveryprice'],
					'orderno' => $_POST['orderno'],
					'weight' => $total_weight,
					'enclosure' => $_POST['enclosure'],
					'instruction' => $_POST['instruction'],
					'service' => 1,
					'return' => false,
				);

				if (isset($_POST['pvz'])) {
					$measoft_order['receiver']['pvz'] = $_POST['pvz'];
				}


				$json_success = array();

				try {
					$res = $this->measoft->orderCreate($measoft_order, $measoft_items);
					$json_success['orderno'] = $res;
				} catch (\Exception $e) {
					$res = false;
					$json_error = $e->getMessage();
				}

				if ($res) {

					try {
						$check_order = $this->measoft->orderStatus($res);
					} catch (\Exception $e) {
						$check_order = false;
						$json_error = $e->getMessage();
					}

					if ($check_order) {

						$json_success['status'] = $check_order;
						$order->update_meta_data('KC2008_order_number', $res);
						$order->update_meta_data('KC2008_order_status', $check_order);
						$note = 'Заказ добавлен в КС2008 со статусом '.$check_order;
						$order->add_order_note( $note );

						add_filter( 'woocommerce_order_get_items', 'custom_order_get_items', 10, 3 );
						function custom_order_get_items( $items, $order, $types ) {
							if ( is_admin() && $types == array('shipping') ) {
								$items = array();
							}
							return $items;
						}

						$order->remove_order_items("shipping");

						$shipitem = new WC_Order_Item_Shipping();

						$shipitem->set_method_title( "KC2008_shipping_title" );
						$shipitem->set_method_id( "KC2008_shipping_method" ); // set an existing Shipping method rate ID
						$shipitem->set_total( $_POST['deliveryprice'] ); // (optional)
						// $shipitem->calculate_taxes($calculate_tax_for);

						$order->add_item( $shipitem );

						$order->calculate_totals();

						$ress = $order->save();

						wp_send_json_success($json_success);

					} else {
						wp_send_json_error($json_error);
					}

				} else {
					wp_send_json_error($json_error);
				}

                wp_die();
            }
        }

        return new KC2008_order_api();
    }
