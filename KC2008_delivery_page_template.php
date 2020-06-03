<?php
/* Admin options page
----------------------------------------------------------------- */
if ( ! defined( 'ABSPATH' ) ) exit; // close from direct request

if ( ! class_exists( 'KC2008_delivery_page_template' ) ) {

    class KC2008_delivery_page_template {

        protected static $_instance = null;

        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        function __construct() {
            add_action( 'wp_enqueue_scripts', array($this, 'ks2008_scripts') );
        }

        public function ks2008_scripts( $hook ){ // js file and set myajax.url
            wp_localize_script('jquery', 'myajax',
                array(
                    'url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('myajax-nonce')
                )
            );

            global $woocommerce;
            if ($woocommerce->cart->cart_contents_weight) {
                $ksweight = $woocommerce->cart->cart_contents_weight;
            } elseif (get_option( 'KC2008_delivery_option' )['KC2008_weight']) {
                $ksweight = get_option( 'KC2008_delivery_option' )['KC2008_weight'];
            } else {
                $ksweight = 0.1;
            }

            wp_localize_script('jquery', 'ks2008client',
                array(
                    'id' => get_option( 'KC2008_delivery_option' )['code'],
                    'map_width' => get_option( 'KC2008_delivery_option' )['map_width'],
                    'map_height' => get_option( 'KC2008_delivery_option' )['map_height'],
                    'weight' => $ksweight
                )
            );

            wp_enqueue_script('KC2008-measoft-map-script', 'https://home.courierexe.ru/js/measoft_map.js', array('jquery'), '1.0.0', 1 );
            //wp_enqueue_script('KC2008-delivery-script', plugins_url('js/scripts.js', __FILE__), array('jquery'), '1.0.2', 1 );


            if( ! wp_script_is( 'jquery-ui-autocomplete', 'enqueued' ) ) {
                wp_enqueue_script('jquery-ui-autocomplete');
                wp_enqueue_style('jquery-ui-autocomplete-css', plugins_url('css/jquery.auto-complete.css', __FILE__));
            }

        }

        static public function KC2008_delivery_set_template() { // delivery settings in admin panel
            $options = get_option( 'KC2008_delivery_option' );
            ?>

            <div>
                <h1>KC2008 - Настройки</h1>
                <form class="deliveryForm" method="post" action="options.php">
                    <?php settings_fields( 'KC2008_delivery_options_group' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="delivery_option_login">Логин <span>*</span></label>
                            </th>
                            <td>
                                <input autocomplete="off" class="delivery_option_login required" name="KC2008_delivery_option[login]" id="delivery_option_login" type="text" value="<?php echo (isset($options['login'])) ? $options['login'] : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="delivery_option_password">Пароль <span>*</span></label>
                            </th>
                            <td>
                                <input autocomplete="off" class="delivery_option_password required" name="KC2008_delivery_option[password]" id="delivery_option_password" type="password" value="<?php echo (isset($options['password'])) ? $options['password'] : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="delivery_option_code">Сервисный код доставки <span>*</span></label>
                            </th>
                            <td>
                                <input autocomplete="off"  class="delivery_option_code required" name="KC2008_delivery_option[code]" id="delivery_option_code" type="text" value="<?php echo (isset($options['code'])) ? $options['code'] : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <span class="alertBlock"></span>
                            </th>
                            <td>
                                <div class="button delivery_option_test_call">Проверка авторизации</div>
                            </td>
                        </tr>
                    </table>
                    <hr>
                    <table class="form-table">
                        <tr>
                            <th>
                                <label for="delivery_option_prefix">Префикс номера заказа</label>
                            </th>
                            <td>
                                <input id="delivery_option_prefix" type="text" name="KC2008_delivery_option[prefix]" value="<?php echo (isset($options['prefix'])) ? $options['prefix'] : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="delivery_option_size_num">Длина номера заказа</label>
                            </th>
                            <td>
                                <input id="delivery_option_size_num" type="number" name="KC2008_delivery_option[size_num]" value="<?php echo (isset($options['size_num'])) ? $options['size_num'] : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="delivery_option_articles">Использовать артикулы</label>
                            </th>
                            <td>
                                <input id="delivery_option_articles" type="checkbox" name="KC2008_delivery_option[articles]" <?php echo (isset($options['articles'])) ?  'checked' :  ''; ?>>
                                <p class="description">Если здесь установлена галочка, то все данные товаров будут взяты из АПИ склада КС2008</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="delivery_option_coefficient">Коэффициент стоимости доставки</label>
                            </th>
                            <td>
                                <input id="delivery_option_coefficient" type="number" min="1" step="0.01" name="KC2008_delivery_option[coefficient]" value="<?php echo (isset($options['coefficient'])) ? $options['coefficient'] : '1'; ?>">
                                <p class="description">Минимальное значение "1"</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="delivery_option_add_cost">Добавочная стоимость доставки</label>
                            </th>
                            <td>
                                <input id="delivery_option_add_cost" class="required" type="number" min="0" step="0.01" name="KC2008_delivery_option[add_cost]" value="<?php echo (isset($options['add_cost'])) ? $options['add_cost'] : '0'; ?>">
                                <p class="description">Не может быть ниже нуля.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="delivery_option_default_weight">Вес по умолчанию</label>
                            </th>
                            <td>
                                <input id="delivery_option_default_weight" type="text" name="KC2008_delivery_option[KC2008_weight]" value="<?php echo (isset($options['KC2008_weight'])) ? $options['KC2008_weight'] : '0.1'; ?>">
                                <p class="description">Если вес у товаров не был задан, то вес заказа будет взят из этого поля</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="delivery_option_default_city">Город отправки по умолчанию</label>
                            </th>
                            <td>
                                <input id="delivery_option_default_city" class="required" type="text" name="KC2008_delivery_option[city]" value="<?php echo (isset($options['city'])) ? $options['city'] : ''; ?>">
                                <p class="description">Начните вводить название города, и выберите его из выпадающего списка.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="delivery_option_map_size">Размер карты для выбора ПВЗ</label>
                            </th>
                            <td>
                                Ширина: <input id="delivery_option_map_size" class="required" type="number" name="KC2008_delivery_option[map_width]" value="<?php echo (isset($options['map_width'])) ? $options['map_width'] : '650'; ?>">
                                &nbsp;&nbsp;&nbsp;Высота: <input id="delivery_option_map_size_h" class="required" type="number" name="KC2008_delivery_option[map_height]" value="<?php echo (isset($options['map_height'])) ? $options['map_height'] : '755'; ?>">
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>


                <?php
                // $cron_jobs = get_option( 'cron' );
// var_dump($cron_jobs);
// echo wp_schedule_event( time(), 'half_an_hour', 'ks2008_hourly_event');
                // echo '<pre>';
                // print_r( $cron_jobs );
                // print_r( _get_cron_array() );
                // echo '</pre>';
                ?>
                <!-- <table class="form-table">
                    <tr>
                        <th>
                            <label for="cron_jobs">Работа КРОН</label>
                        </th>
                        <td>
                            <input id="cron_jobs" type="text" name="KC2008_delivery_option[cron_jobs]" value="<?php //echo (isset($options['prefix'])) ? $options['prefix'] : ''; ?>">
                        </td>
                    </tr>
                </table> -->
            </div>

            <?php
        }
    }
}


if ( ! function_exists( 'callTheTemplateClass' ) ) {

    function callTheTemplateClass() {
        return KC2008_delivery_page_template::instance();
    }
}
callTheTemplateClass();
