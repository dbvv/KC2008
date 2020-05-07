<?php
/**
 * Базовый класс для обмена данными с АПИ Measoft
 *
 * Created by Measoft 2019
 */

class MeasoftCourier
{
    /**
     * Типы оплаты
     */
    const PAYMENT_TYPE_CASH = 'CASH';
    const PAYMENT_TYPE_CARD = 'CARD';
    const PAYMENT_TYPE_NONE = 'NO';
    const PAYMENT_TYPE_OTHER = 'OTHER';

    /**
     * Возвратная корреспонденция
     */
    const RETURN_YES = 'YES';
    const RETURN_NO = 'NO';

    /**
     * Получатель оплачивает и доставку
     */
    const RECEIVER_PAYS_YES = 'YES';
    const RECEIVER_PAYS_NO = 'NO';

    /**
     * Забор
     */
    const PICKUP_YES = 'YES';
    const PICKUP_NO = 'NO';

    /**
     * Разрешить частитчную доставку
     */
    const ACCEPT_PARTIALLY_YES = 'YES';
    const ACCEPT_PARTIALLY_NO = 'NO';

    /**
     * Разрешить исключения в случае ошибок
     * @var bool
     */
    public $enableExceptions = true;

    /**
     * Версия класса
     * @var string
     */
    private $version = '2.0.0';

    /**
     * Учетные данные для авторизации в АПИ
     * @var string
     */
    private $login = null, $password = null, $extracode = null;

    /**
     * Ссылка на АПИ
     * @var string
     */
    private $url = 'https://home.courierexe.ru/api/';

    /**
     * Лог ответов от АПИ
     * @var array
     */
    private $responses = array();

    /**
     * Лог ошибок от АПИ
     * @var array
     */
    private $errors = array();

    public function __construct($login, $password, $extracode)
    {
        $this->login = $login;
        $this->password = $password;
        $this->extracode = $extracode;
    }

    /**
     * Расчет стоимости доставки согласно тарифам КС
     *
     * @param array $params - параметры для расчета
     * @param bool $priceOnly - возврат только стоимости
     * @return integer|array|false - возвращает массив или число стоимости
     */
    public function calculate(array $params, $priceOnly = false)
    {
        if (!isset($params['townto']) || !$params['townto']) {
            return $this->error('Не указан город назначения');
        }

        $data = array('calc' => array('attributes' => array(
            'mass' => 0.1,
            'service' => 1,
        )));
        foreach ($params as $param=>$val) {
            //Исключение для получения расчета всех видов срочности
            if ($param == 'service' && $val === null)
                unset($data['calc']['attributes'][$param]);
            else $data['calc']['attributes'][$param] = $val;
        }

        $cost = array();
        $results = $this->sendRequest($this->makeXML('calculator', $data));
        if (is_object($results) || is_array($results)) foreach ($results as $result) {
            $cost[(int)$result->service] = array(
                'price' => (double)$result->price,
                'days' => array(
                    'min' => (int)$result->mindeliverydays,
                    'max' => (int)$result->maxdeliverydays,
                ),
            );
        } else return $results;

        if ($priceOnly) {
            $cost = array_shift($cost);
            return $cost['price'];
        }

        return $cost;
    }

    /**
     * @param array $order - информация о заказе
     * @param array $items - товары в заказе
     * @return string|false - номер заказа или false в случае ошибки
     */
    public function orderCreate(array $order = array(), array $items = array())
    {
        if (empty($order) || empty($items)) {
            return $this->error('Пустой массив заказа');
        }

        $order_items = array();
        $inshprice = $weight = 0;
        if (!empty($items)) {
            foreach ($items as $item) {
                if (!in_array($item['name'], array('Доставка', 'Скидка', 'Наценка'))) {
                    //Расчёт стоимости всех товаров
                    $inshprice += $item['retprice'] * $item['quantity'];

                    //Расчёт массы всех товаров
                    $weight += $item['mass'] * $item['quantity'];
                }

                $order_item = array(
                    'attributes' => array(
                        'quantity' => $item['quantity'] ?: $item['quantity'],
                        'mass' => $item['mass'] ?: 0.1,
                        'retprice' => $item['retprice'],
                        'barcode' => strip_tags($item['barcode']),
                        'type' => 1,
                    ),
                    'value' => $item['name'],
                );
                //Если передан артикул
                if (isset($item['article']) && $item['article'])
                    $order_item['attributes']['article'] = strip_tags($item['article']);
                //Если передана ставка НДС
                if (isset($item['VATrate']) && $item['VATrate'])
                    $order_item['attributes']['VATrate'] = strip_tags($item['VATrate']);
                //Если передан тип вложения
                if (isset($item['type']) && (int)$item['type'])
                    $order_item['attributes']['type'] = strip_tags($item['type']);

                $order_items[] = $order_item;
            }
        }

        $data = array();
        foreach ($order as $param=>$value) {
            switch ($param) {
                case 'sender': $data[$param] = array('attributes' => array(
                    'type' => 4,
                    'module' => $value['module'],
                    'module_version' => $value['module_version'],
                    'cms_version' => $value['cms_version'],
                )); break;

                case 'company':
                case 'person':
                case 'phone':
                case 'zipcode':
                case 'town':
                case 'address':
                case 'date':
                case 'time_min':
                case 'time_max':
                    $data['receiver'][$param] = $value;
                break;

                case 'weight': $data[$param] = $value ?: $weight; break;
                case 'inshprice': $data[$param] = $value ?: $inshprice; break;
                case 'quantity':
                case 'service': $data[$param] = $value ?: 1; break;
                case 'discount': $data[$param] = $value ?: 0; break;
                case 'paytype':
                    $data[$param] = $value && in_array($value, $this->getPaymentTypes()) ?: self::PAYMENT_TYPE_CASH;
                break;
                case 'receiverpays':
                    $data[$param] = $value && in_array($value, array(self::RECEIVER_PAYS_YES, self::RECEIVER_PAYS_NO)) ?: self::RECEIVER_PAYS_NO;
                break;
                case 'return':
                    $data[$param] = $value && in_array($value, array(self::RETURN_YES, self::RETURN_NO)) ?: self::RETURN_NO;
                break;

                default: $data[$param] = $value;
            }
        }
        $data['items']['item'] = $order_items;

        // error_log( print_r( $this->makeXML('neworder', ['order' => [
        //     'attributes' => ['orderno' => $order['orderno']],
        //     'value' => $data,
        // ]]), true ) );

        if (isset($order['sender_info'])) {
            $senderInfo = $order['sender_info'];
        } else {
            $senderInfo = false;
        }

        $result = $this->sendRequest($this->makeXML('neworder', ['order' => [
            'attributes' => ['orderno' => $order['orderno']],
            'value' => $data,
        ]], true, $senderInfo));
        if (isset($result->createorder[0]['orderno'])) {
            return (string)$result->createorder[0]['orderno'];
        }

        return false;
    }

    /**
     * Получение статуса заказа по его номеру
     *
     * @param string $number - номер заказа
     * @return string|false - текстовый статус заказа или false в случае ошибки
     */
    public function orderStatus($number)
    {
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

        if (!$number) {
            return $this->error('Не указан номер заказа');
        }

        $result = $this->sendRequest($this->makeXML('statusreq', ['orderno' => $number]));
        $attrs = $result->attributes();
        // return $result;
        if ($result->error) {
            return 'authorization_error';
        } elseif ($attrs['count'] > 0) {
            $status = trim((string) $result->order[0]->status);
            return isset($statuses[$status]) ? $statuses[$status] : $status;
        } else return 'Заказ №'.$number.' не найден';
    }

    /**
     * Получение списка городов по заданному критерию
     *
     * @param array $conditions - условия для поиска
     * @param array $limit - ограничения результатов
     * @param array $search - поиск по кодам, conditions и limit игнорируются
     * @return array|false - найденные города или false в случае ошибки
     */
    public function citiesList(array $conditions = array(), array $limit = array(), array $search = array())
    {
        if (empty($conditions) && empty($limit) && empty($search)) {
            return $this->error('Не указаны параметры для поиска');
        }

        //Ограничиваем кол-во результатов, если не указано другое
        $request = array('limit' => array('limitcount' => 10));

        if (!empty($conditions)) {
            foreach ($conditions as $condition=>$value) {
                $request['conditions'][$condition] = $value;
            }
        }

        if (!empty($limit)) {
            foreach ($limit as $option=>$value) {
                switch ($option) {
                    case 'countall':
                        $request['limit'][$option] = $value && strtoupper($value)!='NO' ? 'YES' : 'NO';
                        break;
                    default: $request['limit'][$option] = $value;
                }
            }
        }

        if (!empty($search)) {
            foreach ($search as $field=>$value) {
                $request['codesearch'][$field] = $value;
            }
        }

        $results = $this->sendRequest($this->makeXML('townlist', $request, false));

        if (!empty($results)) {
            $cities = array();
            foreach ($results as $result) {
                $cities[(int)$result->code] = array(
                    'code' => (int)$result->code,
                    'name' => (string)$result->name,
                    'fiascode' => (string)$result->fiascode,
                    'kladrcode' => (string)$result->kladrcode,
                    'shortname' => (string)$result->shortname,
                    'typename' => (string)$result->typename,
                    'region' => array(
                        'code' => (int)$result->city->code,
                        'name' => (string)$result->city->name,
                    ),
                );
            }

            return $cities;
        } else {
            return $this->error('Ничего не найдено');
        }
    }

    /**
     * Получение списка улиц по заданному критерию
     *
     * @param array $conditions - условия для поиска
     * @param array $limit - ограничения результатов
     * @param array $search - поиск по кодам, conditions и limit игнорируются
     * @return array|false - найденные города или false в случае ошибки
     */
    public function streetsList(array $conditions = array(), array $limit = array())
    {
        if (empty($conditions) && empty($limit) && empty($search)) {
            return $this->error('Не указаны параметры для поиска');
        }

        //Ограничиваем кол-во результатов, если не указано другое
        $request = array('limit' => array('limitcount' => 10));

        if (!empty($conditions)) {
            foreach ($conditions as $condition=>$value) {
                $request['conditions'][$condition] = $value;
            }
        }

        if (!empty($limit)) {
            foreach ($limit as $option=>$value) {
                switch ($option) {
                    case 'countall':
                        $request['limit'][$option] = $value && strtoupper($value)!='NO' ? 'YES' : 'NO';
                        break;
                    default: $request['limit'][$option] = $value;
                }
            }
        }
        // echo "<pre>";
        // print_r($this->makeXML('streetlist', $request, false));
        // echo "</pre>";
        $results = $this->sendRequest($this->makeXML('streetlist', $request, false));

        if (!empty($results)) {
            $streets = array();
            foreach ($results as $result) {
                $streets[(int)$result->code] = array(
                    'shortname' => (int)$result->shortname,
                    'name' => (string)$result->name,
                    'typename' => (string)$result->typename
                );
            }

            return $results;
        } else {
            return $this->error('Ничего не найдено');
        }
    }

    /**
     * Получение списка улиц по заданному критерию
     *
     * @param array $conditions - условия для поиска
     * @param array $limit - ограничения результатов
     * @param array $search - поиск по кодам, conditions и limit игнорируются
     * @return array|false - найденные города или false в случае ошибки
     */
    public function pvzList($town)
    {
        if (empty($town)) {
            return $this->error('Не указаны параметры для поиска');
        }

        //Ограничиваем кол-во результатов, если не указано другое
        $request = array('json' => 'NO');

        if (!empty($town)) {
            $request['town'] = $town;
        }

        $results = $this->sendRequest($this->makeXML('pvzlist', $request, true));

        if (!empty($results)) {
            // $streets = array();
            // foreach ($results as $result) {
            //     $streets[(int)$result->code] = array(
            //         'shortname' => (int)$result->shortname,
            //         'name' => (string)$result->name,
            //         'typename' => (string)$result->typename
            //     );
            // }

            return $results;
        } else {
            return $this->error('Ничего не найдено');
        }
    }

    /**
     * Получение товара с АПИ
     *
     * @param array $article - артикул товара
     * @return array|false - найденный товар или false в случае ошибки
     */
    public function productGetByArticle($article)
    {
        if (empty($article)) {
            return $this->error('Не указаны параметры для поиска');
        }

        //Ограничиваем кол-во результатов, если не указано другое
        $request = array('limit' => array('limitcount' => 1));
        $request['codesearch']['article'] = $article;

        $results = $this->sendRequest($this->makeXML('itemlist', $request, true));

        if (!empty($results)) {
            // Возможно будет удобней разобрать здесь обьект $results в массив
            return $results;
        } else {
            return $this->error('Ничего не найдено');
        }
    }

    /**
     * отметить полученные статусы успешно полученными
     *
     * @return array|false - true или false в случае ошибки
     */
    public function commitLastStatusRequest()
    {

        $request = array('client' => 'CLIENT');
        // print_r($this->makeXML('commitlaststatus', $request, true));
        $results = $this->sendRequest($this->makeXML('commitlaststatus', $request, true));

        if (!empty($results)) {
            return $results;
        } else {
            return false;
        }
    }

    /**
     * получение только изменившихся заказов
     *
     * @return array|false - массив заказов или false в случае ошибки
     */
    public function changedOrdersRequest()
    {

        $request = array('changes' => 'ONLY_LAST');

        $results = $this->sendRequest($this->makeXML('statusreq', $request, true));

        if (!empty($results)) {
            return $results;
        } else {
            return false;
        }
    }

    /**
     * Возвращает массив доступных типов оплаты
     *
     * @return array
     */
    public function getPaymentTypes()
    {
        return array(
            self::PAYMENT_TYPE_CASH,
            self::PAYMENT_TYPE_CARD,
            self::PAYMENT_TYPE_NONE,
            self::PAYMENT_TYPE_OTHER,
        );
    }

    public function __get($name)
    {
        switch ($name) {
            case 'error':
                $value = !empty($this->errors) ? $this->errors[count($this->errors)-1] : null;
            break;
            case 'response':
                $value = !empty($this->responses) ? $this->responses[count($this->responses)-1] : null;
            break;
            default: $value = null;
        }

        return $value;
    }

    /**
     * Генерирует XML объект из массива
     *
     * @param $action - метод АПИ
     * @param array $data - данные для запроса
     * @param bool $withAuth - использовать авторизацию или нет
     * @return string - XML строка
     */
    private function makeXML($action, $data = array(), $withAuth = true, $senderInfo = false) {
        $xml = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?><'.$action.'/>');
        if ($withAuth) {
            $auth = $xml->addChild('auth');
            $auth->addAttribute('login', $this->login);
            $auth->addAttribute('pass', $this->password);
            $auth->addAttribute('extra', $this->extracode);
        }
        if ($senderInfo) {
            $sender = $xml->addChild('sender');
            $sender->addAttribute('type', $senderInfo['type']);
            $sender->addAttribute('module', $senderInfo['module']);
            $sender->addAttribute('module_version', $senderInfo['module_version']);
            $sender->addAttribute('cms_version', $senderInfo['cms_version']);
        }
//print_r($data);
        if (!empty($data)) {
            foreach ($data as $node=>$value) {
                $this->addXMLnode($xml, $node, $value);
            }
        }
//print_r($xml);
        return $xml->asXML();
    }

    private function addXMLnode(SimpleXMLElement &$xml, $name, $data)
    {
        $node = $xml->addChild($name, is_array($data) ? (isset($data['value']) && is_scalar($data['value']) ? $data['value'] : null) : $data);
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $name=>$value) {
                $node->addAttribute($name, $value);
            }
        } elseif (is_array($data)) {
            foreach ($data as $name=>$value) {
                if (!is_array($value) || $value !== array_values($value))
                    $this->addXMLnode($node, $name, $value);
                else foreach ($value as $item) {
                    $this->addXMLnode($node, $name, $item);
                }
            }
        }

        if (isset($data['value']) && is_array($data['value'])) {
            foreach ($data['value'] as $name=>$value) {
                $this->addXMLnode($node, $name, $value);
            }
        }

        return true;
    }

    /**
     * Отправка запроса к АПИ
     *
     * @param $data - XML с запросом
     * @return SimpleXMLElement|false - XML ответ от сервера или false в случае ошибки
     */
    private function sendRequest($data)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/xml; charset=utf-8'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $contents = curl_exec($ch);
        $headers = curl_getinfo($ch);
        curl_close($ch);

        if ($headers['http_code'] != 200 || !$contents) {
            return $this->error('Ошибка сервиса');
        }

        $this->responses[] = $contents;
//print_r($contents);
        $xml = simplexml_load_string($contents);

        return $this->checkResponse($xml) ? $xml : false;
    }

    /**
     * Проверка ответа АПИ на ошибки
     *
     * @param $xml - ответ от сервера
     * @return bool - результат проверки
     */
    private function checkResponse($xml)
    {
        if (!($xml instanceof SimpleXMLElement))
            $this->error('Ошибка сервиса');

        $attr = $xml->attributes();
        if (isset($attr['error'])) {
            // print_r($xml);
            return $this->error($this->getErrorMessage((int)$attr['error']), (int)$attr['error']);
        }

        return true;
    }

    /**
     * Получение текстового сообщение об ошибке по ее коду от АПИ
     *
     * @param $code - код ошибки
     * @return string - текст ошибки
     */
    private function getErrorMessage($code)
    {
        $errors = array(
            0=>'OK',
            1=>'Неверный xml',
            2=>'Широта не указана',
            3=>'Долгота не указана',
            4=>'Дата и время запроса не указаны',
            5=>'Точность не указана',
            6=>'Идентификатор телефона не указан',
            7=>'Идентификатор телефона не найден',
            8=>'Неверная широта',
            9=>'Неверная долгота',
            10=>'Неверная точность',
            11=>'Заказы не найдены',
            12=>'Неверные дата и время запроса',
            13=>'Ошибка mysql',
            14=>'Неизвестная функция',

            15=>'Тариф не найден',
            18=>'Город отправления не указан',
            19=>'Город назначения не указан',
            20=>'Неверная масса',
            21=>'Город отправления не найден',
            22=>'Город назначения не найден',
            23=>'Масса не указана',
            24=>'Логин не указан',
            25=>'Ошибка авторизации',
            26=>'Логин уже существует',
            27=>'Клиент уже существует',
            28=>'Адрес не указан',
            29=>'Более не поддерживается',
            30=>'Настройка sip не выполнена',
            31=>'Телефон не указан',
            32=>'Телефон курьера не указан',
            33=>'Ошибка соединения',
            34=>'Неверный номер',
            35=>'Неверный номер',
            36=>'Ошибка определения тарифа',
            37=>'Ошибка определения тарифа',
            38=>'Тариф не найден',
            39=>'Тариф не найден',
        );

        return isset($errors[$code]) ? $errors[$code] : 'Неизвестная ошибка';
    }

    /**
     * Генерация ошибки и запись ее в историю
     *
     * @param $message - сообщение об ошибке
     * @param int $code - код ошибки
     * @return bool
     * @throws MeasoftCourier_Exception
     */
    private function error($message, $code = 0)
    {
        $this->errors[] = $message;
        if ($this->enableExceptions)
            throw new MeasoftCourier_Exception($message, $code);

        return false;
    }
}

class MeasoftCourier_Exception extends Exception {}
