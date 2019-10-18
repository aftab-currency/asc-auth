<?php

namespace AceAuth\API\Helpers\State;

use AceAuth\Store\StoreInterface;

class SessionStateHandler implements StateHandler
{
    const STATE_NAME = 'web_auth_state';
    private $store;
    public function __construct(StoreInterface $store)
    {
        $this->store = $store;
    }
    public function issue()
    {
        $state = uniqid('', true);
        $this->store($state);
        return $state;
    }

    public function store($state)
    {
        $this->store->set(self::STATE_NAME, $state);
    }
    public function validate($state)
    {
        $valid = $this->store->get(self::STATE_NAME) == $state;
        $this->store->delete(self::STATE_NAME);
        return $valid;
    }
}
