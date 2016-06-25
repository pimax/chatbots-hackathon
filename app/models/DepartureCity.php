<?php

namespace app\model;

use MartynBiz\Mongo\Mongo;

class DepartureCity extends Mongo
{
    protected static $collection = 'departure_city';

    protected static $whitelist = array(
        'name',
    );

    public function getCreatedAt()
    {
        return date('Y-M-d h:i:s', $this->data['created_at']->sec);
    }
}