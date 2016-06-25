<?php

namespace app\model;

use MartynBiz\Mongo\Mongo;

class Session extends Mongo
{
    protected static $collection = 'session';

    protected static $whitelist = array(
        'chat_id',
        'form_id',
        'country',
        'date',
        'price',
        'current_stage',
    );

    public function getCreatedAt()
    {
        return date('Y-M-d h:i:s', $this->data['created_at']->sec);
    }
}