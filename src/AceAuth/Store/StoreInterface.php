<?php
namespace AceAuth\Store;

interface StoreInterface
{
    public function set($key, $value);
    public function get($key, $default = null);
    public function delete($key);
}
