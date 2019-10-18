<?php

namespace AceAuth\Store;

class EmptyStore implements StoreInterface
{
    public function set($key, $value)
    {
    }

    public function get($key, $default = null)
    {
        return $default;
    }

    public function delete($key)
    {
    }
}
