<?php

namespace app;

use Telegram\Bot\Api;
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

    /**
     * App constructor
     */
    public function __construct()
    {
        $this->loadConfig();

        $this->telegram = new Api($this->config['token']);
        $this->db = $this->connectDB();
    }

    /**
     * Run Application
     *
     * @param $updates
     */
    public function run()
    {
        $this->updates = $this->telegram->getWebhookUpdates();

        return $this->telegram->sendMessage([
            'chat_id' => $this->updates->getMessage()->getChat()->getId(),
            'parse_mode' => 'HTML',
            'reply_markup' => $this->telegram->replyKeyboardMarkup([
                'keyboard' => [
                    ['Москва'],
                    ['Санкт-Петербург'],
                    ['Новосибирск'],
                    ['Казань']
                ]
            ]),
            'text' => 'Выберите ваш город или отправьте местоположение.'
        ]);

        //writeToLog($this->updates);


        //$this->user = $this->loadUser();

        //$this->parseUserText();
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
        $this->checkDepartureCityInUpdates($this->updates->getMessage()->getText());
    }

    /**
     * Show Bot Main Menu
     *
     * @return \Telegram\Bot\Objects\Message
     */
    protected function mainMenu()
    {
        if ($this->user->departure_id)
        {
            return $this->telegram->sendMessage([
                'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                'parse_mode' => 'HTML',
                'reply_markup' => $this->telegram->replyKeyboardMarkup([
                    'keyboard' => [
                        ['Подписка на новые предложения'],
                        ['Актуальные предложения'],
                        ['Изменить свое местоположение'],
                    ]
                ]),
                'text' => 'Отлично. Давай начнем. Можно подписаться на новые предложения или посмотреть актуальные на текущий момент. Что будем делать?'
            ]);
        }
        else
        {
            return $this->telegram->sendMessage([
                'chat_id' => $this->updates->getMessage()->getChat()->getId(),
                'parse_mode' => 'HTML',
                'reply_markup' => $this->telegram->replyKeyboardMarkup([
                    'keyboard' => [
                        ['Москва'],
                        ['Санкт-Петербург'],
                        ['Новосибирск'],
                        ['Казань']
                    ]
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
            if ($city == $message) {
                $current_city = $city;
            }
        }

        if ($current_city) {
            // set user city
            $this->user->departure_city = $current_city->_id;

            // go to main menu
            $this->mainMenu();
        }
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
                'departure_city' => '\\app\\model\\DepartureCity'
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