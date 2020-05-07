<?php


// вывод PHP ошибок в ответ аякса
// if( WP_DEBUG && WP_DEBUG_DISPLAY && (defined('DOING_AJAX') && DOING_AJAX) ){
// 	@ ini_set( 'display_errors', 1 );
// }
/**
* Check if WooCommerce is active
*/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    function KC2008_shipping_method_init() {
    if ( ! class_exists( 'KC2008_Shipping_Method' ) ) {
        class KC2008_Shipping_Method extends WC_Shipping_Method {
            /**
            * Constructor for your shipping class
            *
            * @access public
            * @return void
            */
            public function __construct($instance_id = 0) {
                $this->instance_id        = absint( $instance_id );
                $this->id                 = 'KC2008_shipping_method'; // Id for your shipping method. Should be uunique.
                // $this->method_title       = $this->get_option( 'title' );  // Title shown in admin
                $this->method_title       = 'KC2008';  // Title shown in admin
                $this->method_description = $this->get_option( 'description' ); // Description shown in admin
                $this->enabled            = $this->get_option( 'enabled' );
                // $this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
                $this->supports           = array(
                    'shipping-zones',
                    'settings',
                    'instance-settings',
                    'instance-settings-modal',
                );

                $this->init();
            }

            /**
            * Init your settings
            *
            * @access public
            * @return void
            */
            function init() {
                // Load the settings API
                $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

                $this->title = $this->get_option( 'title' ); //вывод заголовка при выборе способов доставки !!!!

                // Save settings in admin if you have any defined
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

            }

            /**
            * Define settings field for this shipping
            * @return void
            */
            function init_form_fields() {

                $this->form_fields = array(

                'enabled' => array(
                    'title' => 'Активировать',
                    'type' => 'checkbox',
                    'description' => 'Активировать способ доставки КС2008',
                    'default' => 'yes'
                ),

                'title' => array(
                    'title' => 'Заголовок',
                    'type' => 'text',
                    'description' => 'Заговок способа доствки КС2008',
                    'default' => 'Курьерская служба 2008'
                ),

                'description' => array(
                    'title' => 'Описание',
                    'type' => 'text',
                    'description' => 'Описание способа доставки КС2008',
                    'default' => 'Выберите для доставки через КС2008'
                ),

                );

            }

            /**
            * calculate_shipping function.
            *
            * @access public
            * @param mixed $package
            * @return void
            */
            public function calculate_shipping( $package = array() ) {

                $options = get_option( 'KC2008_delivery_option' );
                $measoft = new MeasoftCourier($options['login'], $options['password'], $options['code']);

                if (isset($options['city'])) {
                    $townfrom = $options['city'];
                } elseif (get_option( 'woocommerce_store_city' )) {
                    $townfrom = get_option( 'woocommerce_store_city' );
                } else {
                    $townfrom = false;
                }

                if (WC()->customer->get_shipping_city()) {
                    $townto = WC()->customer->get_shipping_city();
                } elseif (WC()->customer->get_billing_city()) {
                    $townto = WC()->customer->get_billing_city();
                } else {
                    $townto = false;
                }

                $items = WC()->cart->get_cart();
                $mass = 0;
				
                foreach($items as $item => $values) {
                    $_product =  wc_get_product( $values['data']->get_id());
                    $product_weight  = (float)$_product->get_weight() * (int)$values['quantity'];

                    $mass += $product_weight;
                }
				
				
				if ($mass == 0){
					if (isset($options['KC2008_weight'])){
						$mass = 0.1;
					}else{
						$mass = $options['KC2008_weight'] ;
					}
				}
				

                if ($townfrom && $townto) {
                    try {
    					$res = $measoft->calculate(array(
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
                        $res = $res * (float)$options['coefficient'] + $options['add_cost'];
                        $rate = array(
                            'id' => $this->id,
                            'label' => $this->title,
                            'cost' => $res,
                            'calc_tax' => 'per_item'
                        );

                        // Register the rate
                        $this->add_rate( $rate );
                    }
                    // else {
                    //     $rate = array(
                    //         'id' => $this->id,
                    //         'label' => $this->title. " Стоимость доставки необходимо уточнить у менеджера.",
                    //         'cost' => 0,
                    //         'calc_tax' => 'per_item'
                    //     );
                    //
                    //     // Register the rate
                    //     $this->add_rate( $rate );
                    // }
                }
                // else {
                //     $rate = array(
                //         'id' => $this->id,
                //         'label' => $this->title. " Стоимость доставки необходимо уточнить у менеджера.",
                //         'cost' => 0,
                //         'calc_tax' => 'per_item'
                //     );
                //
                //     // Register the rate
                //     $this->add_rate( $rate );
                // }

            }

            /**
            * Is this method available?
            * @param array $package
            * @return bool
            */
            public function is_available($package) {
                return $this->is_enabled();
            }
        }
        }
    }

    //инициализация класса с новым методом доставки
    add_action( 'woocommerce_shipping_init', 'KC2008_shipping_method_init' );

    //добавление нового метода доставки к существующим
    function add_KC2008_shipping_method( $methods ) {
        $methods['KC2008_shipping_method'] = 'KC2008_Shipping_Method';
        return $methods;
    }
    add_filter( 'woocommerce_shipping_methods', 'add_KC2008_shipping_method' );

}
