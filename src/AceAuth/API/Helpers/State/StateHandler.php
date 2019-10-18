<?php

namespace AceAuth\API\Helpers\State;

interface StateHandler
{
    public function issue();
    public function store($state);
    public function validate($state);
}
