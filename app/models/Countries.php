<?php

namespace app\model;

use MartynBiz\Mongo\Mongo;

class Countries extends Mongo
{
    protected static $collection = 'countries';

    protected static $whitelist = array(
        'name',
    );

    public function getCreatedAt()
    {
        return date('Y-M-d h:i:s', $this->data['created_at']->sec);
    }
}