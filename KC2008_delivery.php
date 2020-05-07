<?php
/**
 * Plugin Name: KC2008 Delivery Plugin
 * Description: Плагин интеграции курьерской доставки КС2008
 * Plugin URI:  http://courierexe.ru/integration.htm
 * Version:     1.0.1
 */

if ( ! defined( 'ABSPATH' ) ) exit; // close from direct request

define('KC2008_PLUGIN_VERSION', '1.0.1');

if ( ! class_exists( 'KC2008_delivery_plugin' ) ) {
    final class KC2008_delivery_plugin {
        protected static $_instance = null;

        public $options = null;

        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        function includes() {

            require_once('class/MeasoftCourier.php'); // SDK

            require_once('KC2008_delivery_page_template.php'); // main template in admin
            require_once('KC2008_api.php');
            require_once('KC2008_order_form_template.php');
            require_once('KC2008_order_api.php');
            require_once('KC2008_delivery_method.php');
        }

        public function __construct() {
            global $wp;
            $this->includes();

            add_action( 'admin_menu',                               array($this, 'KC2008_delivery_setup_menu') ); // set page and tabname for plugin
            add_action( 'admin_init',                               array($this, 'KC2008_register_settings') );
            add_action( 'woocommerce_checkout_update_order_meta',   array($this, 'ks2008_save_pvz_field') );
            add_action( 'woocommerce_after_order_notes',            array($this, 'ks2008_pvz_checkout_field') );

            add_action( 'add_meta_boxes_shop_order', function(){
                add_meta_box(
                    'kc2008_api_box',
                    'Отправить в курьерскую службу',
                    array('KC2008_order_form_template', 'KC2008_set_order_form_template'),
                    'shop_order',
                    'side',
                    'default'
                );
            }, 20 );

            add_filter( 'cron_schedules', function( $list ) {

                $list['every_30min'] = array(
                    'schedule' => 'half_an_hour',
                    'interval' => 1800,
                    'display' => 'Half an hour',
                );
                return $list;
            });

            register_activation_hook(__FILE__, function() {
            	// удалим на всякий случай все такие же задачи cron, чтобы добавить новые с "чистого листа"
            	// это может понадобиться, если до этого подключалась такая же задача неправильно (без проверки что она уже есть)
            	wp_clear_scheduled_hook( 'ks2008_hourly_event' );

            	// добавим новую cron задачу
                wp_schedule_event( time(), 'every_30min', 'ks2008_hourly_event');
            });

            add_action( 'ks2008_hourly_event', function () {
                global $woocommerce, $wpdb;

                $statuses = array(
                    'NEW' => 'Новый',
                    'PICKUP' => 'Забран у отправителя',
                    'ACCEPTED' => 'Получен складом',
                    'INVENTORY' => 'Инвентаризация',
                    'DEPARTURING' => 'Планируется отправка',
                    'DEPARTURE' => 'Отправлено со склада',
                    'DELIVERY' => 'Выдан курьеру на доставку',
                    'COURIERDELIVERED' => 'Доставлен (предварительно)',
                    'COMPLETE' => 'Доставлен',
                    'PARTIALLY' => 'Доставлен частично',
                    'COURIERRETURN' => 'Курьер вернул на склад',
                    'CANCELED' => 'Не доставлен (Возврат/Отмена)',
                    'RETURNING' => 'Планируется возврат',
                    'RETURNED' => 'Возвращен',
                    'CONFIRM' => 'Согласована доставка',
                    'DATECHANGE' => 'Перенос',
                    'NEWPICKUP' => 'Создан забор',
                    'UNCONFIRM' => 'Не удалось согласовать доставку',
                    'PICKUPREADY' => 'Готов к выдаче',
                    'AWAITING_SYNC'=>'Ожидание синхронизации',
                );

                $options = get_option( 'KC2008_delivery_option' );
                $measoft = new MeasoftCourier($options['login'], $options['password'], $options['code']);

                $neworders = $measoft->changedOrdersRequest();

                if ($neworders && $neworders->attributes()->{'count'} != 0) {

                    if ($neworders->attributes()->{'count'} < 2) {

                        $sql = "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'KC2008_order_number' AND  meta_value = '".$neworders->order['orderno']."' LIMIT 1";
                        $posts = $wpdb->get_results($sql,ARRAY_A);

                        $order = wc_get_order( $posts[0]['post_id'] );

                        if ($order) {

                            if (isset($statuses[(string)$neworders->order->status])) {
                                $status = $statuses[(string)$neworders->order->status];

                            } else {
                                $status = (string)$neworders->order->status;
                            }

                            $order->update_meta_data('KC2008_order_status', (string)$status);
                            $note = 'Изменен статус заказа на '.$status;
    						$order->add_order_note( $note );
                            if ($neworders->order->status == 'COMPLETE') {
                                $order->update_status('completed');
                            }
                            $order->save();
                        }


                    } else {
                        foreach ($neworders->order as $orders) {

                            $sql = "SELECT * FROM $wpdb->postmeta WHERE meta_key = 'KC2008_order_number' AND  meta_value = '".$orders->attributes()->{'orderno'}."' LIMIT 1";
                            $posts = $wpdb->get_results($sql,ARRAY_A);

                            $order = wc_get_order( $posts[0]['post_id'] );

                            if ($order) {
                                if (isset($statuses[(string)$orders->status])) {
                                    $status = $statuses[(string)$orders->status];
                                } else {
                                    $status = (string)$orders->status;
                                }
                                $order->update_meta_data('KC2008_order_status', $status);
                                $note = 'Изменен статус заказа на '.$status;
        						$order->add_order_note( $note );
                                if ((string)$orders->status == 'COMPLETE') {
                                    $order->update_status('completed');
                                }
                                $order->save();
                            }


                        }
                    }
                }

                try {
                    $measoft->commitLastStatusRequest();
                } catch (\Exception $e) {

                }


            } );

            // При деактивации плагина, обязательно нужно удалить задачу:
            register_deactivation_hook( __FILE__, function(){
            	wp_clear_scheduled_hook( 'ks2008_hourly_event' );
            } );



        }

        public function KC2008_delivery_setup_menu(){
            add_menu_page( 'Delivery plugin', 'KC2008 настройки', 'manage_options', 'delivery-plugin', array('KC2008_delivery_page_template', 'KC2008_delivery_set_template'), 'dashicons-admin-site-alt2', 57 );
        }

        public function KC2008_register_settings() {
            register_setting( 'KC2008_delivery_options_group', 'KC2008_delivery_option', 'KC2008_callback' );
        }

        public function ks2008_save_pvz_field( $order_id ) {

            if( !empty($_POST['pvzcode']) && !empty($_POST['pvzname']) ) {
                update_post_meta( $order_id, 'pvzcode', $_POST['pvzcode'] );
                update_post_meta( $order_id, 'pvzname', $_POST['pvzname'] );
            }

        }

        public function ks2008_pvz_checkout_field( $checkout ) {

            echo '<div id="ks2008_pvz_checkout" style="display:none;">';

            woocommerce_form_field( 'pvzcode', array(
                'type'          => 'text',
                'class'         => array('ks2008_pvzcode form-row-wide'),
                'label'         => __('Выбор ПВЗ на карте'),
                'placeholder'   => __(''),
            ), '');
            woocommerce_form_field( 'pvzname', array(
                'type'          => 'text',
                'class'         => array('ks2008_pvzname form-row-wide'),
                'label'         => __('Название ПВЗ'),
                'placeholder'   => __(''),
            ), '');


            echo "<div id='ks2008_pvz_map_block'></div>";
            echo '</div>';

        }

    }
}


if ( ! function_exists( 'callTheClass' ) ) {

    function callTheClass() {
        return KC2008_delivery_plugin::instance();
    }
}
callTheClass();
