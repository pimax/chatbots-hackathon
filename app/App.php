<?php

namespace app;

use Telegram\Bot\Api;
use PicoFeed\Reader\Reader;
use MartynBiz\Mongo\Connection;

/**
 * Class App: Main Class for the Bot
 *
 * @package app
 */
class App
{
    /**
     * Current User Object
     *
     * @var User
     */
    protected $user;

    /**
     * Mongo DB Connection
     *
     * @var Connection
     */
    protected $db;

    /**
     * Updates structure from Telegram
     *
     * @var array
     */
    protected $updates;

    /**
     * Api object
     *
     * @var Api
     */
    protected $telegram;

    /**
     * Config of the Bot
     *
     * @var array
     */
    protected $config = [];

    protected $session;

    protected $googl;

    /**
     * App constructor
     */
    public function __construct()
    {
        $this->loadConfig();

        $this->telegram = new Api($this->config['token']);
        $this->db = $this->connectDB();
        $this->googl = new \GooglShortener($this->config['googl_token']);
    }

    /**
     * Run Application
     *
     * @param $updates
     */
    public function run()
    {
        $this->updates = $this->telegram->getWebhookUpdates();
        $this->user = $this->loadUser();
        $this->parseUserText();
    }

    public function runSendTours()
    {
        $subscriptions = \app\model\Subscription::find();

        if($subscriptions)
        {
            foreach ($subscriptions as $sub)
            {
                $this->sendHot($sub->chat_id);
            }
        }

    }

    protected function sendHot($chat_id, $limit = 1, $direct = false)
    {
        $photos = [
            'http://www.tui.ru/img/0c4c8e1f-73ca-4248-8d8f-57859e185bfa/Europe/Spain/Barcelona/Costa-Brava/Lloret-De-Mar/san-juan-park.jpg',
            'http://www.tui.ru/img/6e7d40fd-a73e-4e24-9852-eaae5e997c5a/Europe/Spain/Barcelona/Costa-Brava/Lloret-De-Mar/Gran-Hotel-Casino-Royal.jpg',
            'http://www.tui.ru/img/ea916676-62a0-4fec-bf40-d900ac9e90f9/Europe/Spain/Barcelona/Costa-Dorada/fortuna-best-hotels-3.jpg'
        ];

        $countries  = \app\model\Countries::find();
        $countries_names = [];
        foreach ($countries as $city)
        {
            $countries_names[] = $city->name;
        }

        try {

            $reader = new Reader;
            $resource = $reader->download($this->config['tours_feed']);

            $parser = $reader->getParser(
                $resource->getUrl(),
                $resource->getContent(),
                $resource->getEncoding()
            );

            $feed = $parser->execute();


            $items = $feed->getItems();

            if (count($items)) {

                for ($i = 1; $i<= $limit; $i++)
                {
                    $itm = $items[array_rand($items)];
                    $tmp_title = str_replace("Горящий тур  ! Вылет ", "", $itm->getTitle());
                    $data_tmp = explode(" - ", $tmp_title);

                    $url = $this->googl->shorten($itm->getUrl());

                    $msg = "";
                    if ($direct) {
                        $msg .= "По вышим параметрам появилось новое предложение:\n\n";
                    }
                    $msg .= "Страна: ".$countries_names[array_rand($countries_names)]."\n".strip_tags($itm->getContent())."\n\nВылет: ".$data_tmp[0]."\nЦена: ".$data_tmp[1]."\n".$url->id;

                    $this->telegram->sendMessage([
                        'chat_id' => $chat_id,
                        'parse_mode' => 'HTML',
                        'text' => $msg
                    ]);

                    $this->telegram->sendphoto([
                        'chat_id' => $chat_id,
                        'parse_mode' => 'HTML',
                        'photo' => $photos[array_rand($photos)],
                    ]);
                }
            }
        }
        catch (Exception $e) {
            // Do something...
        }
    }


    /**
     * Load System Config
     */
    protected function loadConfig()
    {
        $this->config = include 'config.inc.php';
    }

    /**
     * Parse User Message
     */
    protected function parseUserText()
    {
        $city = $this->checkDepartureCityInUpdates($this->updates->getMessage()->getText());

        if (!$city)
        {
            switch($this->updates->getMessage()->getText())
            {
                case 'Главное меню':
                    $this->mainMenu();
                break;

                case 'Изменить свое местоположение':

                    $this->user->departure_city = "";
                    $this->user->save();

                    $cities  = \app\model\DepartureCity::find();
                    $keyboard = [];
                    foreach ($cities as $city)
                    {
                        $keyboard[] = [$city->name];
                    }

                    return $this->telegram->sendMessage([
                        'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                        'parse_mode' => 'HTML',
                        'reply_markup' => $this->telegram->replyKeyboardMarkup([
                            'keyboard' => $keyboard
                        ]),
                        'text' => 'Выберите ваш город или отправьте местоположение.'
                    ]);

                break;


                case 'Отменить подписку на новые предложения':

                    $subscriptions = \app\model\Subscription::find(array(
                        'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                    ));

                    if($subscriptions)
                    {
                        foreach ($subscriptions as $sub) {
                            $sub->delete();
                        }
                    }

                    $this->telegram->sendMessage([
                        'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                        'parse_mode' => 'HTML',
                        'text' => 'Подписка отменена. Буду ждать тебя в следующий раз!'
                    ]);

                break;

                case 'Подписка на новые предложения':

                    $session = new \app\model\Session();
                    $session->chat_id = $this->updates->getMessage()->getChat()->getId();
                    $session->form_id = 'subscription';
                    $session->current_stage = 1;
                    $session->save();

                    $countries  = \app\model\Countries::find();
                    $keyboard = [
                        ['Все страны']
                    ];
                    foreach ($countries as $city)
                    {
                        $keyboard[] = [$city->name];
                    }

                    $this->telegram->sendMessage([
                        'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                        'parse_mode' => 'HTML',
                        'reply_markup' => $this->telegram->replyKeyboardMarkup([
                            'keyboard' => $keyboard
                        ]),
                        'text' => 'Куда летим?'
                    ]);

                break;

                case 'Актуальные предложения':

                    $session = new \app\model\Session();
                    $session->chat_id = $this->updates->getMessage()->getChat()->getId();
                    $session->form_id = 'actual';
                    $session->current_stage = 1;
                    $session->save();

                    $countries  = \app\model\Countries::find();
                    $keyboard = [
                        ['Все страны']
                    ];
                    foreach ($countries as $city)
                    {
                        $keyboard[] = [$city->name];
                    }

                    $this->telegram->sendMessage([
                        'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                        'parse_mode' => 'HTML',
                        'reply_markup' => $this->telegram->replyKeyboardMarkup([
                            'keyboard' => $keyboard
                        ]),
                        'text' => 'Куда летим?'
                    ]);

                    break;

                default:

                    $session = \app\model\Session::findOne(array(
                        'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                        'created_at' => ['$gt' => new \MongoDate(time() - 300)]
                    ));


                    if ($session) {

                        switch ($session->form_id)
                        {
                            case 'subscription':

                                switch ($session->current_stage)
                                {
                                    case 1:

                                        if ($this->updates->getMessage()->getText() == 'Все страны')
                                        {
                                            $country_id = 0;
                                        } else {
                                            $country = \app\model\Countries::findOne(array(
                                                'name' => $this->updates->getMessage()->getText(),
                                            ));

                                            $country_id = $country->_id;
                                        }


                                        $session->country_id = $country_id;
                                        $session->current_stage = 2;
                                        $session->save();

                                        $this->telegram->sendMessage([
                                            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                                            'parse_mode' => 'HTML',
                                            'reply_markup' => $this->telegram->replyKeyboardMarkup([
                                                'keyboard' => [
                                                    ['Не важно'],
                                                    ['До 20 тыс'],
                                                    ['20 - 50 тыс'],
                                                    ['Выше 50 тыс']
                                                ]
                                            ]),
                                            'text' => 'Выберите ценовую категорию'
                                        ]);

                                    break;
                                    
                                    case 2:
                                        switch($this->updates->getMessage()->getText())
                                        {
                                            case 'Не важно':
                                                $price = 0;
                                            break;
                                            case 'До 20 тыс':
                                                $price = 20;
                                                break;
                                            case '20 - 50 тыс':
                                                $price = 50;
                                                break;
                                            case 'Выше 50 тыс':
                                                $price = 100;
                                                break;
                                        }


                                        $session->price = $price;
                                        $session->current_stage = 3;
                                        $session->save();

                                        $this->telegram->sendMessage([
                                            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                                            'parse_mode' => 'HTML',
                                            'reply_markup' => $this->telegram->replyKeyboardMarkup([
                                                'keyboard' => [
                                                    ['Не важно'],
                                                    ['В ближайшие три дня'],
                                                    ['Вылет от 3-7 дней'],
                                                    ['Вылет от 7 дней и позже']
                                                ]
                                            ]),
                                            'text' => 'Выберите время вылета'
                                        ]);
                                    break;

                                    case 3:

                                        switch($this->updates->getMessage()->getText())
                                        {
                                            case 'Не важно':
                                                $date = 0;
                                                break;
                                            case 'В ближайшие три дня':
                                                $date = 3;
                                                break;
                                            case 'Вылет от 3-7 дней':
                                                $date = 7;
                                                break;
                                            case 'Вылет от 7 дней и позже':
                                                $date = 8;
                                                break;
                                        }


                                        $session->date = $date;
                                        $session->current_stage = 3;
                                        $session->save();

                                        $subscription = new \app\model\Subscription();
                                        $subscription->chat_id = $session->chat_id;
                                        $subscription->country_id = $session->country_id;
                                        $subscription->price = $session->price;
                                        $subscription->date = $session->date;
                                        $subscription->save();

                                        $this->telegram->sendMessage([
                                            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                                            'parse_mode' => 'HTML',
                                            'text' => 'Готово. Теперь мы будем отправлять тебе новые предложения.'
                                        ]);

                                        $this->mainMenu();

                                        break;
                                }

                            break;

                            case 'actual':

                                switch ($session->current_stage)
                                {
                                    case 1:

                                        if ($this->updates->getMessage()->getText() == 'Все страны')
                                        {
                                            $country_id = 0;
                                        } else {
                                            $country = \app\model\Countries::findOne(array(
                                                'name' => $this->updates->getMessage()->getText(),
                                            ));

                                            $country_id = $country->_id;
                                        }


                                        $session->country_id = $country_id;
                                        $session->current_stage = 2;
                                        $session->save();

                                        $this->telegram->sendMessage([
                                            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                                            'parse_mode' => 'HTML',
                                            'reply_markup' => $this->telegram->replyKeyboardMarkup([
                                                'keyboard' => [
                                                    ['Не важно'],
                                                    ['До 20 тыс'],
                                                    ['20 - 50 тыс'],
                                                    ['Выше 50 тыс']
                                                ]
                                            ]),
                                            'text' => 'Выберите ценовую категорию'
                                        ]);

                                        break;

                                    case 2:
                                        switch($this->updates->getMessage()->getText())
                                        {
                                            case 'Не важно':
                                                $price = 0;
                                                break;
                                            case 'До 20 тыс':
                                                $price = 20;
                                                break;
                                            case '20 - 50 тыс':
                                                $price = 50;
                                                break;
                                            case 'Выше 50 тыс':
                                                $price = 100;
                                                break;
                                        }


                                        $session->price = $price;
                                        $session->current_stage = 3;
                                        $session->save();

                                        $this->telegram->sendMessage([
                                            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                                            'parse_mode' => 'HTML',
                                            'reply_markup' => $this->telegram->replyKeyboardMarkup([
                                                'keyboard' => [
                                                    ['Не важно'],
                                                    ['В ближайшие три дня'],
                                                    ['Вылет от 3-7 дней'],
                                                    ['Вылет от 7 дней и позже']
                                                ]
                                            ]),
                                            'text' => 'Выберите время вылета'
                                        ]);
                                        break;

                                    case 3:

                                        switch($this->updates->getMessage()->getText())
                                        {
                                            case 'Не важно':
                                                $date = 0;
                                                break;
                                            case 'В ближайшие три дня':
                                                $date = 3;
                                                break;
                                            case 'Вылет от 3-7 дней':
                                                $date = 7;
                                                break;
                                            case 'Вылет от 7 дней и позже':
                                                $date = 8;
                                                break;
                                        }


                                        $session->date = $date;
                                        $session->current_stage = 3;
                                        $session->save();

                                        $this->sendHot($session->chat_id, 3, true);

                                        $this->telegram->sendMessage([
                                            'chat_id' => $session->chat_id,
                                            'parse_mode' => 'HTML',
                                            'reply_markup' => $this->telegram->replyKeyboardMarkup([
                                                'keyboard' => [
                                                    ['Еще'],
                                                    ['Главное меню'],
                                                ]
                                            ]),
                                            'text' => 'Нашли то, что искали или продолжаем?'
                                        ]);

                                        break;
                                }

                                break;

                            default:
                                $this->mainMenu();

                        }
                    } else {
                        $this->mainMenu();
                    }

            }
        }
    }

    /**
     * Show Bot Main Menu
     *
     * @return \Telegram\Bot\Objects\Message
     */
    protected function mainMenu()
    {
        $session = \app\model\Session::findOne(array(
            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
            'created_at' => ['$gt' => new \MongoDate(time() - 300)]
        ));

        if($session) {
            $session->delete();
        }

        if ($this->user->departure_city)
        {
            return $this->telegram->sendMessage([
                'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                'parse_mode' => 'HTML',
                'reply_markup' => $this->telegram->replyKeyboardMarkup([
                    'keyboard' => [
                        ['Подписка на новые предложения'],
                        ['Отменить подписку на новые предложения'],
                        ['Актуальные предложения'],
                        ['Изменить свое местоположение'],
                    ]
                ]),
                'text' => 'Отлично. Давай начнем. Можно подписаться на новые предложения или посмотреть актуальные на текущий момент. Что будем делать?'
            ]);
        }
        else
        {
            $cities  = \app\model\DepartureCity::find();
            $keyboard = [];
            foreach ($cities as $city)
            {
                $keyboard[] = [$city->name];
            }

            return $this->telegram->sendMessage([
                'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                'parse_mode' => 'HTML',
                'reply_markup' => $this->telegram->replyKeyboardMarkup([
                    'keyboard' => $keyboard
                ]),
                'text' => 'Выберите ваш город или отправьте местоположение.'
            ]);
        }
    }

    /**
     * Check departure city in the user message
     *
     * @param $message
     */
    protected function checkDepartureCityInUpdates($message)
    {
        $current_city = false;

        $cities  = \app\model\DepartureCity::find();
        foreach ($cities as $city)
        {
            if ($city->name == $message) {
                $current_city = $city;
            }
        }

        if ($current_city) {
            // set user city
            $this->user->departure_city = $current_city->_id;
            $this->user->save();

            // go to main menu
            $this->mainMenu();

            return true;
        }

        return false;
    }

    /**
     * Connect to Mongo DB
     *
     * @return Connection
     */
    protected function connectDB()
    {
        return Connection::getInstance()->init(array(
            'db' => 'hackathon_bot',
            'classmap' => array(
                'tour' => '\\app\\model\\Tour',
                'member' => '\\app\\model\\Member',
                'subscription' => '\\app\\model\\Subscription',
                'departure_city' => '\\app\\model\\DepartureCity',
                'countries' => '\\app\\model\\Countries',
                'session' => '\\app\\model\\Session',
            ),
        ));
    }

    /**
     * Load Current User info
     *
     * @return model\Member|array
     * @throws \MartynBiz\Mongo\Exception\WhitelistEmpty
     */
    protected function loadUser()
    {

        $user = \app\model\Member::findOne(array(
            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
        ));

        if (!$user) {
            $user = new \app\model\Member();
            $user->chat_id = $this->updates->getMessage()->getChat()->getId();
            $user->save();
        }

        return $user;
    }
}