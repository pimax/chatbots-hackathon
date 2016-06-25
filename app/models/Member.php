<?php

namespace app\model;

use MartynBiz\Mongo\Mongo;

class Member extends Mongo
{
    protected static $collection = 'members';

    protected static $whitelist = array(
        'chat_id',
        'name',
        'departure_id'
    );

    public function getCreatedAt()
    {
        return date('Y-M-d h:i:s', $this->data['created_at']->sec);
    }
}