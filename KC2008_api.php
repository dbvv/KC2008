<?php

    if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

    if ( ! class_exists( 'KC2008_api' ) ) {
        class KC2008_api {
            // public $options;
            // public $measoft;

            function __construct() {

                // $this->options = get_option( 'KC2008_delivery_option' );


                if( wp_doing_ajax() ){
                    add_action( 'wp_ajax_query_authorization', array($this, 'query_authorization') );
                }
            }

            public function query_authorization() {

                $delivery_option_login =  $_POST['delivery_option_login'];
                $delivery_option_password =  $_POST['delivery_option_password'];
                $delivery_option_code =  $_POST['delivery_option_code'];

                $measoft = new MeasoftCourier($delivery_option_login, $delivery_option_password, $delivery_option_code);
                // $measoft->orderStatus('1235900');
                try {
                    $res = $measoft->orderStatus('1235900');
                    if ($res == 'authorization_error') {
                        wp_send_json_error('Ошибка авторизации');
                    } else {
                        wp_send_json_success($res);
                    }
                } catch (Exception $e) {
                    wp_send_json_success('Ошибка');
                }

                wp_die();
            }
        }

        return new KC2008_api();
    }
