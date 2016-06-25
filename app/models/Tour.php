<?php

namespace app\model;

use MartynBiz\Mongo\Mongo;

class Tour extends Mongo
{
    protected static $collection = 'tours';

    protected static $whitelist = array(
        'country_id',
        'name',
        'price',
        'date',
        'url',
        'description',
        'departure_id'
    );

    public function getCreatedAt()
    {
        return date('Y-M-d h:i:s', $this->data['created_at']->sec);
    }
}