<?php
/*
    Admin order form template
___________________________________
*/

if ( ! defined( 'ABSPATH' ) ) exit; // close from direct request

if ( ! class_exists( 'KC2008_order_form_template' ) ) {
    class KC2008_order_form_template {
        protected static $_instance = null;

        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        function __construct() {
            add_action( 'admin_enqueue_scripts', array($this, 'add_admin_iris_scripts') );
        }

        public function add_admin_iris_scripts( $hook ){ // js file and set myajax.url
            wp_localize_script('jquery', 'myajax',
                array(
                    'url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('myajax-nonce')
                )
            );
            wp_localize_script('jquery', 'ks2008client',
                array(
                    'id' => get_option( 'KC2008_delivery_option' )['code'],
                    'map_width' => get_option( 'KC2008_delivery_option' )['map_width'],
                    'map_height' => get_option( 'KC2008_delivery_option' )['map_height'],
                )
            );

            wp_enqueue_script('KC2008-measoft-map-script', 'https://home.courierexe.ru/js/measoft_map.js', array('jquery'), '1.0.0', 1 );
            wp_enqueue_script('KC2008-order-form-script', plugins_url('js/order-form-script.js', __FILE__), array('jquery'), '1.0.2', 1 );
            wp_enqueue_style('KC2008-order-form-style', plugins_url('css/order-form-style.css', __FILE__), array(), '1.0.1');

            if( ! wp_script_is( 'jquery-ui-autocomplete', 'enqueued' ) ) {
                wp_enqueue_script('jquery-ui-autocomplete');

            }
        }

        static public function KC2008_set_order_form_template() {
            global $post;
            $order = wc_get_order( $post->ID );
            $order_data = $order->get_data();
            $options = get_option( 'KC2008_delivery_option' );
            $measoft = new MeasoftCourier($options['login'], $options['password'], $options['code']); // test query with php

            $order_shipping_city = $order_data['shipping']['city'];
            $order_billing_city = $order_data['billing']['city'];

            $city = $order_shipping_city ? $order_shipping_city : $order_billing_city;

            if (get_post_meta($post->ID,'pvzname',true)) {
                $pvzname = get_post_meta($post->ID,'pvzname',true);
            } else {
                $pvzname = '';
            }

            if (get_post_meta($post->ID,'pvzcode',true)) {
                $pvzcode = get_post_meta($post->ID,'pvzcode',true);
            } else {
                $pvzcode = '';
            }

            // get weight

            $total_weight = 0;
            foreach ($order->get_items() as $item_key => $item ):

                if (isset($options['articles'])) {
                    $product        = $item->get_product(); // Get the WC_Product object
                    $quantity       = $item->get_quantity();
					$product_sku    = $product->get_sku();
                    $product_data = $measoft->productGetByArticle($product_sku);

                    $product_weight  = $product_data->item->weight * $quantity;

                } else {
                    $product        = $item->get_product(); // Get the WC_Product object
                    $quantity       = $item->get_quantity();
                    $product_weight  = (float)$product->get_weight() * (int)$quantity;
                }

                if ($product_weight) {
                    $total_weight += (float)$product_weight;
                } else {
                    $product_weight = 0.1;
                }

            endforeach;

            if ($total_weight <= 0) {
                if (isset($options['KC2008_weight'])) {
                    $total_weight = $options['KC2008_weight'];
                } else {
                    $total_weight = 0.1;
                }
            }

            // <-- get weight




            if (isset($options['prefix'])) {
                $orderno = $options['prefix'] . $order->get_id();
            } else {
                $orderno = $order->get_id();
            }

            if (isset($options['city'])) {
                $townfrom = $options['city'];
            } elseif (get_option( 'woocommerce_store_city' )) {
                $townfrom = get_option( 'woocommerce_store_city' );
            } else {
                $townfrom = '';
            }

                        if ($order->get_meta('KC2008_order_number')) {
                            echo "<p>Заказ № ".$order->get_meta('KC2008_order_number')." отправлен в КС2008</p>";
                            echo "<p>Статус: ".$order->get_meta('KC2008_order_status')."</p>";
                        } else {
                        ?>

                        <div class="form-table ks2008_order_delivery_table">

                            <p>
                                <label for="delivery_date">Дата доставки <span>*</span></label>
                                <input required class="delivery_date" name="delivery_date" id="delivery_date" type="date" value="<?php echo date('Y-m-d'); ?>" min="<?php echo date('Y-m-d'); ?>">
                            </p>
                            <p>
                                <hr>
                                <label for="delivery_time_from"><strong>Время доставки</strong></label>
                                <br>
                                <span>От:</span>
                                <input class="delivery_time" name="delivery_time_from" id="delivery_time_from" type="time" value="10:00">
                                <br>
                                <br>
                                <span>До:</span>
                                <input class="delivery_time" name="delivery_time_to" id="delivery_time_to" type="time" value="18:00">
                            </p>

                            <!-- <p> -->
                                <hr>
                                <label><strong>Выбор ПВЗ:</strong></label><br>

                                <br>
                                <div id="measoftMapBlock">

                                </div>
                                <br>
                                <input type="text" autocomplete="off" id="pvzname" name="pvz_name" value="<?php echo $pvzname; ?>" placeholder="Название ПВЗ" readonly />
                                <br>
                                <input type="hidden" autocomplete="off" class="pvzcode" name="pvz_id" value="<?php echo $pvzcode; ?>" placeholder="Код ПВЗ" readonly />
                                <br>
                                <button type="button" name="ks2008_clean_pvz" id="ks2008_clean_pvz" class="button button-secondary">Очистить выбор ПВЗ</button>

                            <!-- </p> -->

                            <p>
                                <hr>
                                <label><strong>Тип оплаты:</strong></label>
                                <br>
                                <input class="pay_type" name="pay_type" id="pay_type1" type="radio" value="CARD">
                                <label for="pay_type1">Карта</label><br>
                                <input class="pay_type" name="pay_type" id="pay_type2" type="radio" value="CASH" checked>
                                <label for="pay_type2">Наличные</label><br>
                                <input class="pay_type" name="pay_type" id="pay_type3" type="radio" value="NO">
                                <label for="pay_type3">Без оплаты</label>
                                <hr>
                            </p>

                            <p>
                                <label for="orderno">Номер заказа</label>
                                <input class="orderno" name="orderno" id="orderno" type="text" value="<?php echo $orderno; ?>" required />
                            </p>

                            <p>
                                <label for="deliveryprice">Стоимость доставки</label>
                                <input class="deliveryprice" name="deliveryprice" step="any" id="deliveryprice" type="number" min="0" value="<?php echo $order->get_total_shipping(); ?>"><br>
                                <button type="button" name="deliveryprice_calculate" id="ks2008_deliveryprice_calculate" class="button button-secondary">Рассчитать стоимость доставки</button>
                                <span style="display:none;" class="ks2008_deliveryprice_responce"></span>
                            </p>

                            <p>
                                <label for="enclosure">Вложение</label>
                                <textarea id="enclosure" name="enclosure" rows="3" placeholder="Текст вложения"></textarea>
                            </p>

                            <p>
                                <label for="instruction">Поручение</label>
                                <textarea id="instruction" name="instruction" rows="3" placeholder="Текст поручения"></textarea>
                            </p>

                            <p style="display:none;">
                                <input type="hidden" name="order_id" value="<?php echo $post->ID; ?>">
                                <input type="hidden" name="townfrom" id="townfrom" value="<?php echo $townfrom; ?>">
                                <input type="hidden" name="townto" id="city" value="<?php echo $city; ?>">
                                <input type="hidden" name="weight" id="weight" value="<?php echo $total_weight; ?>">
                            </p>

                            <p>
                                <div class="createOrderBtn button button-primary">Отправить заказ в KC2008</div>
                            </p>

                            <p>
                                <div class="alertBlock"></div>
                            </p>
                        </div>
                    <?php }
        }
    }
}

if ( ! function_exists( 'callTheOrderTemplateClass' ) ) {

    function callTheOrderTemplateClass() {
        return KC2008_order_form_template::instance();
    }
}
callTheOrderTemplateClass();
