<?php

namespace app\model;

use MartynBiz\Mongo\Mongo;

class Subscription extends Mongo
{
    protected static $collection = 'subscriptions';

    protected static $whitelist = array(
        'country_id',
        'price_id',
        'date_id',
        
        'chat_id',
    );

    public function getCreatedAt()
    {
        return date('Y-M-d h:i:s', $this->data['created_at']->sec);
    }
}